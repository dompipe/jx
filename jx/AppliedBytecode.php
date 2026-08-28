<?php declare(strict_types=1);

namespace jx;

require_once dirname(__DIR__) . '/jx-bytecode-page-report.php';

/**
 * Compiler-owned applied bytecodes shared by jx.exe and native hosts.
 *
 * Canonical JX never names these numeric encodings. The compiler lowers stable
 * semantic operations into them after canonical analysis/prelinking.
 *
 * System envelope (always 3 bytes):
 *   7f 00 01  idle bus tick / wake permission
 *   7f 00 02  idle bus collect
 *
 * Prepared calls are already-lowered generation execution codes and stay
 * exactly one or two bytes. Their meaning belongs to the generation/prelink
 * table; this class deliberately does not assign canonical meaning to them.
 */
final class AppliedBytecode
{
    public const VERSION = 'jx.applied-bytecode/1';
    public const TARGET = 'JX-APPLIED';

    public const SYSTEM_ESCAPE = 0x7f;
    public const SYSTEM_BUS = 0x00;
    public const BUS_TICK = 0x01;
    public const BUS_COLLECT = 0x02;

    public const SYSTEM_BYTES = 3;
    public const PREPARED_MIN_BYTES = 1;
    public const PREPARED_MAX_BYTES = 2;

    /** Runtime table entry offsets when baked into jx.exe/.64B. */
    public const RUNTIME_TICK_OFFSET = 0;
    public const RUNTIME_COLLECT_OFFSET = 3;
    public const RUNTIME_PAGE_BYTES = 6;

    public static function idleTick(): string
    {
        return pack('CCC', self::SYSTEM_ESCAPE, self::SYSTEM_BUS, self::BUS_TICK);
    }

    public static function idleCollect(): string
    {
        return pack('CCC', self::SYSTEM_ESCAPE, self::SYSTEM_BUS, self::BUS_COLLECT);
    }

    /** Preserve an existing promoted/micro/prelinked code byte-for-byte. */
    public static function prepared(string $bytes): string
    {
        $n = strlen($bytes);
        if ($n < self::PREPARED_MIN_BYTES || $n > self::PREPARED_MAX_BYTES) {
            throw new JxException('Prepared applied bytecode must be exactly 1 or 2 bytes', 'applied-bytecode', true,
                ['bytes'=>$n]);
        }
        return $bytes;
    }

    /**
     * Stable runtime entry table baked by jx.exe: host jumps to offset 0 for
     * tick or offset 3 for collect. These are entrypoints, not a sequential
     * request to execute tick then collect.
     */
    public static function runtimeBusPage(): string
    {
        return self::idleTick() . self::idleCollect();
    }
}

/** Backend emitter used after canonical JX/PASL lowering. */
final class AppliedBytecodeCompiler
{
    /**
     * @param list<string|array{prepared:string}> $operations
     */
    public function compile(array $operations): string
    {
        $out = '';
        foreach ($operations as $operation) {
            if ($operation === 'idle.tick') {
                $out .= AppliedBytecode::idleTick();
                continue;
            }
            if ($operation === 'idle.collect') {
                $out .= AppliedBytecode::idleCollect();
                continue;
            }
            if (is_array($operation) && array_key_exists('prepared', $operation) && is_string($operation['prepared'])) {
                $out .= AppliedBytecode::prepared($operation['prepared']);
                continue;
            }
            throw new JxException('Unknown applied bytecode semantic operation', 'applied-bytecode', true,
                ['operation'=>is_scalar($operation) ? (string)$operation : get_debug_type($operation)]);
        }
        return $out;
    }

    /** Compiler page/report used by the jx.exe build frontend. */
    public function page(int $page, array $operations, ?string $source = null, ?string $output = null): JxBytecodePageReport
    {
        $bytes = $this->compile($operations);
        return new JxBytecodePageReport(
            page: $page,
            bytecode: $bytes,
            optimized: true,
            fused: true,
            reactive: true,
            target: AppliedBytecode::TARGET,
            source: $source,
            shadow: 'runtime.bus',
            dependencies: ['host:idle-bus', 'host:prelink'],
            registers: [],
            iteratorSlots: 0,
            output: $output,
        );
    }

    /** Section inserted beside native code when jx.exe builds a native Book. */
    public function nativeRuntimeSection(): array
    {
        return ['CODE/applied-bus.bin' => AppliedBytecode::runtimeBusPage()];
    }
}
