<?php declare(strict_types=1);

namespace pasm;

use InvalidArgumentException;
use RuntimeException;

/**
 * PASM Media ABI v2: Multi-stream video/audio with independent JXL decoders.
 *
 * ACTIVE: The v2 extension adds synchronized multi-stream media support while
 * preserving v1 backward compatibility. Video and audio can now load from
 * separate prepared JXL sections and maintain frame/sample alignment via
 * a shared clock index.
 *
 * Design principle:
 *   - Canonical JX source names media streams once
 *   - Compiler resolves streams to prepared JXL decoder identities
 *   - Hot path synchronizes via clock/checkpoint, not name lookup
 *   - Bags collect video frames and audio samples independently
 *   - Bit 7 set on extended opcodes (0x80+) marks new v2 stream operations
 */
final class PASMMediaOpV2
{
    // v1 opcodes (unchanged for compatibility)
    public const MOPEN    = 0x40;
    public const MANALYZE = 0x41;
    public const MPUBLISH = 0x42;
    public const MCHART   = 0x43;
    public const MSYNC    = 0x44;
    public const MCLOSE   = 0x45;

    // v2 stream-specific opcodes (new extended family)
    public const MSTREAM_BIND    = 0x46;  // bind video/audio stream identity
    public const MSTREAM_ACQUIRE = 0x47;  // load/start a prepared JXL section
    public const MSTREAM_CLOCK   = 0x48;  // establish shared presentation clock
    public const MSTREAM_ALIGN   = 0x49;  // synchronize video frame to audio sample clock
    public const MSTREAM_DECODE  = 0x4A;  // active decoding pass (frame or audio chunk)
    public const MSTREAM_FRAME   = 0x4B;  // emit video frame to Bag/display
    public const MSTREAM_SAMPLE  = 0x4C;  // emit audio sample/chunk to Bag
    public const MSTREAM_SEEK    = 0x4D;  // seek to position (clock index)
    public const MSTREAM_CLOSE   = 0x4E;  // close stream, free decoder context

    public const NAMES = [
        self::MOPEN           => 'MOPEN',
        self::MANALYZE        => 'MANALYZE',
        self::MPUBLISH        => 'MPUBLISH',
        self::MCHART          => 'MCHART',
        self::MSYNC           => 'MSYNC',
        self::MCLOSE          => 'MCLOSE',
        self::MSTREAM_BIND    => 'MSTREAM_BIND',
        self::MSTREAM_ACQUIRE => 'MSTREAM_ACQUIRE',
        self::MSTREAM_CLOCK   => 'MSTREAM_CLOCK',
        self::MSTREAM_ALIGN   => 'MSTREAM_ALIGN',
        self::MSTREAM_DECODE  => 'MSTREAM_DECODE',
        self::MSTREAM_FRAME   => 'MSTREAM_FRAME',
        self::MSTREAM_SAMPLE  => 'MSTREAM_SAMPLE',
        self::MSTREAM_SEEK    => 'MSTREAM_SEEK',
        self::MSTREAM_CLOSE   => 'MSTREAM_CLOSE',
    ];

    public static function isV1Opcode(int $op): bool
    {
        return isset(self::NAMES[$op]) && $op >= 0x40 && $op <= 0x45;
    }

    public static function isV2StreamOpcode(int $op): bool
    {
        return isset(self::NAMES[$op]) && $op >= 0x46 && $op <= 0x4E;
    }
}

/**
 * Stream kind enumeration: canonical at compile time, prepared as compact id at runtime.
 */
final class PASMStreamKind
{
    public const VIDEO   = 1;
    public const AUDIO   = 2;
    public const SUBTITLE = 3;
    public const DATA    = 4;

    public const NAMES = [
        self::VIDEO    => 'video',
        self::AUDIO    => 'audio',
        self::SUBTITLE => 'subtitle',
        self::DATA     => 'data',
    ];

    public static function id(string $kind): int
    {
        $kind = strtolower(trim($kind));
        foreach (self::NAMES as $id => $name) {
            if ($name === $kind) return $id;
        }
        throw new InvalidArgumentException("Unknown stream kind: {$kind}");
    }

    public static function name(int $id): string
    {
        return self::NAMES[$id] ?? throw new InvalidArgumentException("Unknown stream kind id: {$id}");
    }
}

/**
 * Clock policy: how streams stay synchronized during playback.
 */
final class PASMStreamClockPolicy
{
    public const WALL_TIME  = 0;  // realtime clock (seconds)
    public const FRAME_TICK = 1;  // frame count (for video-primary)
    public const SAMPLE_TICK = 2; // sample count (for audio-primary)
    public const CUSTOM     = 3;  // application-provided

    public const NAMES = [
        self::WALL_TIME   => 'wall_time',
        self::FRAME_TICK  => 'frame_tick',
        self::SAMPLE_TICK => 'sample_tick',
        self::CUSTOM      => 'custom',
    ];
}

/**
 * Prepared media slot (v1 compatible).
 */
final class PASMMediaSlotTable
{
    private array $ids = [];
    private array $entries = [];

    public function intern(string $kind, string $name, array $meta = []): int
    {
        $kind = strtolower(trim($kind));
        $name = trim($name);
        if ($kind === '' || $name === '') {
            throw new InvalidArgumentException('Media slot kind/name cannot be empty');
        }
        $key = $kind . "\0" . $name;
        if (isset($this->ids[$key])) {
            return $this->ids[$key];
        }
        $id = count($this->entries);
        if ($id > 255) {
            throw new RuntimeException('PASM media ABI supports at most 256 slots per compiled graph');
        }
        $this->ids[$key] = $id;
        $this->entries[$id] = ['id' => $id, 'kind' => $kind, 'name' => $name, 'meta' => $meta];
        return $id;
    }

    public function entry(int $id): array
    {
        if (!isset($this->entries[$id])) {
            throw new RuntimeException("Unknown PASM media slot {$id}");
        }
        return $this->entries[$id];
    }

    public function all(): array
    {
        return $this->entries;
    }
}

/**
 * Stream table: tracks video/audio decoders and their JXL sections.
 */
final class PASMStreamSlotTable
{
    private array $ids = [];
    private array $entries = [];

    /**
     * Register a stream decoder context.
     *
     * @param string $kind   Stream kind (video|audio|subtitle|data)
     * @param string $name   Canonical stream identifier
     * @param array  $meta   Stream metadata: codec, bitrate, sample_rate, width, height, etc.
     * @return int Stream slot id (0..255)
     */
    public function intern(string $kind, string $name, array $meta = []): int
    {
        $kind = strtolower(trim($kind));
        $name = trim($name);
        if ($kind === '' || $name === '') {
            throw new InvalidArgumentException('Stream slot kind/name cannot be empty');
        }

        try {
            $kindId = PASMStreamKind::id($kind);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException("Invalid stream kind '{$kind}': " . $e->getMessage());
        }

        $key = $kind . "\0" . $name;
        if (isset($this->ids[$key])) {
            return $this->ids[$key];
        }

        $id = count($this->entries);
        if ($id > 255) {
            throw new RuntimeException('PASM stream table supports at most 256 stream slots');
        }

        $this->ids[$key] = $id;
        $this->entries[$id] = [
            'id' => $id,
            'kind' => $kind,
            'kind_id' => $kindId,
            'name' => $name,
            'meta' => $meta,
            'jxl_section' => $meta['jxl_section'] ?? null,  // reference to prepared JXL section
            'clock_policy' => $meta['clock_policy'] ?? PASMStreamClockPolicy::WALL_TIME,
        ];
        return $id;
    }

    public function entry(int $id): array
    {
        if (!isset($this->entries[$id])) {
            throw new RuntimeException("Unknown stream slot {$id}");
        }
        return $this->entries[$id];
    }

    public function all(): array
    {
        return $this->entries;
    }
}

/**
 * Media instruction (v1 compatible).
 */
final class PASMMediaInstruction
{
    public function __construct(
        public readonly int $opcode,
        public readonly array $operands = []
    ) {
        if (!isset(PASMMediaOpV2::NAMES[$opcode])) {
            throw new InvalidArgumentException('Unknown PASM media opcode 0x' . dechex($opcode));
        }
        foreach ($operands as $v) {
            if (!is_int($v) || $v < 0 || $v > 255) {
                throw new InvalidArgumentException('PASM media operands must fit one byte');
            }
        }
    }

    public function bytes(): string
    {
        return chr($this->opcode) . implode('', array_map('chr', $this->operands));
    }

    public function text(): string
    {
        $name = PASMMediaOpV2::NAMES[$this->opcode] ?? 'UNKNOWN';
        return $name . ($this->operands ? ' ' . implode(',', $this->operands) : '');
    }
}

/**
 * Stream instruction: v2-specific operations.
 *
 * Format (2-4 bytes):
 *   byte 0: opcode (0x46..0x4E)
 *   byte 1: stream slot id
 *   byte 2: (optional) first operand
 *   byte 3: (optional) second operand
 */
final class PASMStreamInstruction
{
    public function __construct(
        public readonly int $opcode,
        public readonly int $streamSlot,
        public readonly array $operands = []
    ) {
        if (!PASMMediaOpV2::isV2StreamOpcode($opcode)) {
            throw new InvalidArgumentException('Not a v2 stream opcode: 0x' . dechex($opcode));
        }
        if ($streamSlot < 0 || $streamSlot > 255) {
            throw new InvalidArgumentException('Stream slot must be 0..255');
        }
        foreach ($operands as $v) {
            if (!is_int($v) || $v < 0 || $v > 255) {
                throw new InvalidArgumentException('Stream operands must fit one byte');
            }
        }
    }

    public function bytes(): string
    {
        return chr($this->opcode) . chr($this->streamSlot) . implode('', array_map('chr', $this->operands));
    }

    public function text(): string
    {
        $name = PASMMediaOpV2::NAMES[$this->opcode] ?? 'UNKNOWN';
        $str = "{$name} stream={$this->streamSlot}";
        if ($this->operands) {
            $str .= ' ' . implode(',', $this->operands);
        }
        return $str;
    }
}

/**
 * Media graph v2: unified codec/analysis/Bag/chart graph with stream decoders.
 */
final class PASMMediaGraph
{
    /**
     * @param PASMMediaSlotTable $mediaSlots     v1 media sources/analyzers/bags/charts
     * @param PASMStreamSlotTable $streamSlots   v2 video/audio decoders
     * @param list<PASMMediaInstruction> $mediaInstructions    v1 graph operations
     * @param list<PASMStreamInstruction> $streamInstructions  v2 stream operations
     * @param array $provenance    Canonical name/ownership trace
     */
    public function __construct(
        public readonly PASMMediaSlotTable $mediaSlots,
        public readonly PASMStreamSlotTable $streamSlots,
        public readonly array $mediaInstructions,
        public readonly array $streamInstructions = [],
        public readonly array $provenance = []
    ) {
    }

    public function bytecode(): string
    {
        $out = '';
        foreach ($this->mediaInstructions as $i) {
            $out .= $i->bytes();
        }
        foreach ($this->streamInstructions as $i) {
            $out .= $i->bytes();
        }
        return $out;
    }

    public function listing(): string
    {
        $lines = array_map(static fn(PASMMediaInstruction $i) => $i->text(), $this->mediaInstructions);
        $lines = array_merge($lines, array_map(static fn(PASMStreamInstruction $i) => $i->text(), $this->streamInstructions));
        return implode("\n", $lines);
    }

    /**
     * Introspection: list all video/audio streams in this graph.
     * @return array<int,array>
     */
    public function streams(): array
    {
        $out = [];
        foreach ($this->streamSlots->all() as $slot) {
            $out[$slot['id']] = $slot;
        }
        return $out;
    }
}

/**
 * Compiler: Canonical media config → prepared graph with v1 + v2 instructions.
 *
 * Expected canonical shape:
 *   [
 *     'media' => [...],
 *     'bindings' => [...],
 *     'charts' => [...],
 *     'streams' => [
 *       [
 *         'id' => 'video_h264',
 *         'kind' => 'video',
 *         'jxl_section' => 'video.jxl.64b.0',
 *         'meta' => ['codec'=>'h264', 'width'=>1920, 'height'=>1080],
 *       ],
 *       [
 *         'id' => 'audio_aac',
 *         'kind' => 'audio',
 *         'jxl_section' => 'audio.jxl.64b.0',
 *         'meta' => ['codec'=>'aac', 'sample_rate'=>48000, 'channels'=>2],
 *       ],
 *     ],
 *     'clock' => ['policy' => 'wall_time'],
 *   ]
 */
final class PASMMediaGraphCompiler
{
    public function compile(array $media, array $bindings, array $charts = [], array $streams = [], array $clock = []): PASMMediaGraph
    {
        $mediaSlots = new PASMMediaSlotTable();
        $streamSlots = new PASMStreamSlotTable();
        $mediaOps = [];
        $streamOps = [];
        $prov = [];

        // Compile v1 media/analysis/chart graph
        foreach ($media as $m) {
            if (!is_array($m) || ($m['control'] ?? null) !== 'media') {
                throw new InvalidArgumentException('Media graph expects serialized Media controls');
            }
            $id = (string) ($m['id'] ?? '');
            $slot = $mediaSlots->intern('media', $id, ['type' => $m['type'] ?? null, 'mime' => $m['mime'] ?? null]);
            $mediaOps[] = new PASMMediaInstruction(PASMMediaOpV2::MOPEN, [$slot]);
            $prov[] = ['canonical' => 'Media', 'name' => $id, 'slot' => $slot];
        }

        foreach ($bindings as $b) {
            if (!is_array($b) || ($b['kind'] ?? null) !== 'binding') {
                throw new InvalidArgumentException('Media graph expects serialized analysis bindings');
            }
            $mediaName = (string) ($b['source']['media'] ?? '');
            $mediaSlot = $mediaSlots->intern('media', $mediaName);
            $bindingId = (string) ($b['id'] ?? '');
            $analysisSlot = $mediaSlots->intern('analysis', $bindingId, ['binding' => $b['binding'] ?? null]);
            $targetBag = (string) ($b['target']['bag'] ?? '');
            $targetAt = (string) ($b['target']['at'] ?? '_default');
            $bagSlot = $mediaSlots->intern('bag', $targetBag . '.' . $targetAt, ['bag' => $targetBag, 'at' => $targetAt]);

            $mediaOps[] = new PASMMediaInstruction(PASMMediaOpV2::MANALYZE, [$mediaSlot, $analysisSlot]);
            $mediaOps[] = new PASMMediaInstruction(PASMMediaOpV2::MPUBLISH, [$analysisSlot, $bagSlot]);
            $prov[] = ['canonical' => $b['binding'] ?? 'analysis', 'name' => $bindingId, 'slot' => $analysisSlot, 'bag_slot' => $bagSlot];
        }

        foreach ($charts as $c) {
            if (!is_array($c) || ($c['control'] ?? null) !== 'chart') {
                throw new InvalidArgumentException('Media graph expects serialized Chart controls');
            }
            $bag = (string) ($c['source']['bag'] ?? '');
            $at = (string) ($c['source']['at'] ?? '_default');
            $bagSlot = $mediaSlots->intern('bag', $bag . '.' . $at, ['bag' => $bag, 'at' => $at]);
            $chartId = (string) ($c['id'] ?? '');
            $chartSlot = $mediaSlots->intern('chart', $chartId, ['type' => $c['type'] ?? null]);

            $mediaOps[] = new PASMMediaInstruction(PASMMediaOpV2::MCHART, [$bagSlot, $chartSlot]);
            $prov[] = ['canonical' => 'Chart', 'name' => $chartId, 'slot' => $chartSlot, 'bag_slot' => $bagSlot];
        }

        foreach ($mediaSlots->all() as $entry) {
            if ($entry['kind'] === 'bag') {
                $mediaOps[] = new PASMMediaInstruction(PASMMediaOpV2::MSYNC, [$entry['id']]);
            }
        }

        // Compile v2 stream graph
        foreach ($streams as $s) {
            if (!is_array($s)) {
                throw new InvalidArgumentException('Stream entry must be an array');
            }

            $streamId = (string) ($s['id'] ?? '');
            $streamKind = (string) ($s['kind'] ?? 'data');

            $streamSlot = $streamSlots->intern($streamKind, $streamId, $s['meta'] ?? []);
            $prov[] = ['canonical' => 'Stream', 'kind' => $streamKind, 'name' => $streamId, 'slot' => $streamSlot];

            // MSTREAM_BIND: bind stream identity
            $streamOps[] = new PASMStreamInstruction(PASMMediaOpV2::MSTREAM_BIND, $streamSlot);

            // MSTREAM_ACQUIRE: load prepared JXL section
            if (isset($s['jxl_section'])) {
                $jxlId = (int) ($s['jxl_section'] ?? 0);
                $streamOps[] = new PASMStreamInstruction(PASMMediaOpV2::MSTREAM_ACQUIRE, $streamSlot, [$jxlId]);
            }

            // MSTREAM_CLOCK: establish clock policy
            $clockPolicy = $clock['policy'] ?? PASMStreamClockPolicy::WALL_TIME;
            $streamOps[] = new PASMStreamInstruction(PASMMediaOpV2::MSTREAM_CLOCK, $streamSlot, [$clockPolicy]);
        }

        return new PASMMediaGraph($mediaSlots, $streamSlots, $mediaOps, $streamOps, $prov);
    }
}

/**
 * Host adapter interface (extended for v2 streams).
 */
interface PASMMediaHost
{
    // v1 operations
    public function open(array $slot): void;
    public function analyze(array $media, array $analysis): void;
    public function publish(array $analysis, array $bag): void;
    public function chart(array $bag, array $chart): void;
    public function sync(array $slot): void;
    public function close(array $media): void;

    // v2 stream operations
    public function streamBind(array $slot): void;
    public function streamAcquire(array $slot, int $jxlSectionId): void;
    public function streamClock(array $slot, int $clockPolicy): void;
    public function streamAlign(array $videoSlot, array $audioSlot): void;
    public function streamDecode(array $slot, array $context): void;
    public function streamFrame(array $slot, array $frame): void;
    public function streamSample(array $slot, array $sample): void;
    public function streamSeek(array $slot, int $clockIndex): void;
    public function streamClose(array $slot): void;
}

/**
 * Graph executor: runs prepared bytecode on a host.
 */
final class PASMMediaGraphExecutor
{
    public function __construct(private PASMMediaHost $host)
    {
    }

    public function run(PASMMediaGraph $graph): void
    {
        // Execute v1 media instructions
        foreach ($graph->mediaInstructions as $i) {
            $s = $graph->mediaSlots;
            switch ($i->opcode) {
                case PASMMediaOpV2::MOPEN:
                    $this->host->open($s->entry($i->operands[0]));
                    break;
                case PASMMediaOpV2::MANALYZE:
                    $this->host->analyze($s->entry($i->operands[0]), $s->entry($i->operands[1]));
                    break;
                case PASMMediaOpV2::MPUBLISH:
                    $this->host->publish($s->entry($i->operands[0]), $s->entry($i->operands[1]));
                    break;
                case PASMMediaOpV2::MCHART:
                    $this->host->chart($s->entry($i->operands[0]), $s->entry($i->operands[1]));
                    break;
                case PASMMediaOpV2::MSYNC:
                    $this->host->sync($s->entry($i->operands[0]));
                    break;
                case PASMMediaOpV2::MCLOSE:
                    $this->host->close($s->entry($i->operands[0]));
                    break;
            }
        }

        // Execute v2 stream instructions
        foreach ($graph->streamInstructions as $i) {
            $s = $graph->streamSlots;
            $slot = $s->entry($i->streamSlot);
            switch ($i->opcode) {
                case PASMMediaOpV2::MSTREAM_BIND:
                    $this->host->streamBind($slot);
                    break;
                case PASMMediaOpV2::MSTREAM_ACQUIRE:
                    $jxlId = $i->operands[0] ?? 0;
                    $this->host->streamAcquire($slot, $jxlId);
                    break;
                case PASMMediaOpV2::MSTREAM_CLOCK:
                    $policy = $i->operands[0] ?? PASMStreamClockPolicy::WALL_TIME;
                    $this->host->streamClock($slot, $policy);
                    break;
                case PASMMediaOpV2::MSTREAM_DECODE:
                    $this->host->streamDecode($slot, ['context' => $i->operands]);
                    break;
                case PASMMediaOpV2::MSTREAM_SEEK:
                    $clockIdx = ($i->operands[0] ?? 0) | (($i->operands[1] ?? 0) << 8);
                    $this->host->streamSeek($slot, $clockIdx);
                    break;
                case PASMMediaOpV2::MSTREAM_CLOSE:
                    $this->host->streamClose($slot);
                    break;
            }
        }
    }
}
