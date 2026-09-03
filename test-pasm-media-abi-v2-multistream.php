<?php declare(strict_types=1);

namespace tests;

use pasm\PASMMediaOpV2;
use pasm\PASMStreamKind;
use pasm\PASMStreamClockPolicy;
use pasm\PASMMediaSlotTable;
use pasm\PASMStreamSlotTable;
use pasm\PASMMediaInstruction;
use pasm\PASMStreamInstruction;
use pasm\PASMMediaGraphCompiler;
use pasm\PASMMediaGraph;

require __DIR__ . '/pasm-media-abi-v2-multistream.php';

class TestPasmMediaAbiV2Multistream
{
    public function run(): void
    {
        $this->testOpcodeNames();
        $this->testStreamKindIds();
        $this->testClockPolicies();
        $this->testMediaSlotTable();
        $this->testStreamSlotTable();
        $this->testMediaInstruction();
        $this->testStreamInstruction();
        $this->testGraphCompilerV1Compat();
        $this->testGraphCompilerWithStreams();
        $this->testBytecodeGeneration();
        echo "✓ All PASM Media ABI v2 tests passed\n";
    }

    private function testOpcodeNames(): void
    {
        assert(PASMMediaOpV2::NAMES[PASMMediaOpV2::MOPEN] === 'MOPEN');
        assert(PASMMediaOpV2::NAMES[PASMMediaOpV2::MSTREAM_BIND] === 'MSTREAM_BIND');
        assert(PASMMediaOpV2::NAMES[PASMMediaOpV2::MSTREAM_DECODE] === 'MSTREAM_DECODE');
        assert(PASMMediaOpV2::NAMES[PASMMediaOpV2::MSTREAM_CLOSE] === 'MSTREAM_CLOSE');

        assert(PASMMediaOpV2::isV1Opcode(PASMMediaOpV2::MOPEN));
        assert(PASMMediaOpV2::isV1Opcode(PASMMediaOpV2::MCLOSE));
        assert(!PASMMediaOpV2::isV1Opcode(PASMMediaOpV2::MSTREAM_BIND));

        assert(PASMMediaOpV2::isV2StreamOpcode(PASMMediaOpV2::MSTREAM_BIND));
        assert(PASMMediaOpV2::isV2StreamOpcode(PASMMediaOpV2::MSTREAM_ACQUIRE));
        assert(!PASMMediaOpV2::isV2StreamOpcode(PASMMediaOpV2::MOPEN));
    }

    private function testStreamKindIds(): void
    {
        assert(PASMStreamKind::id('video') === PASMStreamKind::VIDEO);
        assert(PASMStreamKind::id('audio') === PASMStreamKind::AUDIO);
        assert(PASMStreamKind::id('subtitle') === PASMStreamKind::SUBTITLE);
        assert(PASMStreamKind::id('data') === PASMStreamKind::DATA);

        assert(PASMStreamKind::name(PASMStreamKind::VIDEO) === 'video');
        assert(PASMStreamKind::name(PASMStreamKind::AUDIO) === 'audio');

        // Case insensitive
        assert(PASMStreamKind::id('VIDEO') === PASMStreamKind::VIDEO);
        assert(PASMStreamKind::id('Audio') === PASMStreamKind::AUDIO);
    }

    private function testClockPolicies(): void
    {
        assert(PASMStreamClockPolicy::WALL_TIME === 0);
        assert(PASMStreamClockPolicy::FRAME_TICK === 1);
        assert(PASMStreamClockPolicy::SAMPLE_TICK === 2);
        assert(PASMStreamClockPolicy::CUSTOM === 3);

        assert(PASMStreamClockPolicy::NAMES[PASMStreamClockPolicy::WALL_TIME] === 'wall_time');
        assert(PASMStreamClockPolicy::NAMES[PASMStreamClockPolicy::FRAME_TICK] === 'frame_tick');
    }

    private function testMediaSlotTable(): void
    {
        $table = new PASMMediaSlotTable();

        $id1 = $table->intern('media', 'source1', ['mime' => 'video/mp4']);
        $id2 = $table->intern('media', 'source2', ['mime' => 'audio/aac']);
        assert($id1 === 0);
        assert($id2 === 1);

        // Duplicate returns same id
        $id1_again = $table->intern('media', 'source1');
        assert($id1_again === $id1);

        $entry1 = $table->entry(0);
        assert($entry1['kind'] === 'media');
        assert($entry1['name'] === 'source1');
        assert($entry1['meta']['mime'] === 'video/mp4');

        assert(count($table->all()) === 2);
    }

    private function testStreamSlotTable(): void
    {
        $table = new PASMStreamSlotTable();

        $vid = $table->intern('video', 'h264_main', [
            'codec' => 'h264',
            'width' => 1920,
            'height' => 1080,
            'jxl_section' => 1,
        ]);
        $aud = $table->intern('audio', 'aac_stereo', [
            'codec' => 'aac',
            'sample_rate' => 48000,
            'channels' => 2,
            'jxl_section' => 2,
        ]);

        assert($vid === 0);
        assert($aud === 1);

        $vidEntry = $table->entry($vid);
        assert($vidEntry['kind'] === 'video');
        assert($vidEntry['kind_id'] === PASMStreamKind::VIDEO);
        assert($vidEntry['meta']['codec'] === 'h264');
        assert($vidEntry['jxl_section'] === 1);

        $audEntry = $table->entry($aud);
        assert($audEntry['kind'] === 'audio');
        assert($audEntry['kind_id'] === PASMStreamKind::AUDIO);
        assert($audEntry['meta']['sample_rate'] === 48000);
        assert($audEntry['jxl_section'] === 2);
    }

    private function testMediaInstruction(): void
    {
        // v1 instruction
        $instr = new PASMMediaInstruction(PASMMediaOpV2::MOPEN, [5]);
        assert($instr->opcode === PASMMediaOpV2::MOPEN);
        assert($instr->operands === [5]);
        assert($instr->text() === 'MOPEN 5');
        assert($instr->bytes() === chr(0x40) . chr(5));
    }

    private function testStreamInstruction(): void
    {
        // v2 stream instruction
        $instr = new PASMStreamInstruction(PASMMediaOpV2::MSTREAM_BIND, 3);
        assert($instr->opcode === PASMMediaOpV2::MSTREAM_BIND);
        assert($instr->streamSlot === 3);
        assert($instr->operands === []);
        assert(str_contains($instr->text(), 'stream=3'));
        assert($instr->bytes() === chr(0x46) . chr(3));

        // With operands
        $instr2 = new PASMStreamInstruction(PASMMediaOpV2::MSTREAM_ACQUIRE, 3, [1]);
        assert($instr2->bytes() === chr(0x47) . chr(3) . chr(1));
    }

    private function testGraphCompilerV1Compat(): void
    {
        $compiler = new PASMMediaGraphCompiler();

        // Backward compat: compile v1-only graph (no streams)
        $graph = $compiler->compile(
            media: [
                ['id' => 'src', 'control' => 'media', 'type' => 'file', 'mime' => 'video/mp4'],
            ],
            bindings: [],
            charts: [],
            streams: [],
            clock: []
        );

        assert(count($graph->mediaInstructions) > 0);
        assert(count($graph->streamInstructions) === 0);
        assert($graph->mediaSlots->entry(0)['kind'] === 'media');
    }

    private function testGraphCompilerWithStreams(): void
    {
        $compiler = new PASMMediaGraphCompiler();

        $graph = $compiler->compile(
            media: [],
            bindings: [],
            charts: [],
            streams: [
                [
                    'id' => 'video_h264',
                    'kind' => 'video',
                    'jxl_section' => 1,
                    'meta' => ['codec' => 'h264', 'width' => 1920, 'height' => 1080],
                ],
                [
                    'id' => 'audio_aac',
                    'kind' => 'audio',
                    'jxl_section' => 2,
                    'meta' => ['codec' => 'aac', 'sample_rate' => 48000, 'channels' => 2],
                ],
            ],
            clock: ['policy' => PASMStreamClockPolicy::WALL_TIME]
        );

        // Should generate BIND, ACQUIRE, and CLOCK for each stream
        assert(count($graph->streamInstructions) >= 6);  // 3 per stream × 2 streams

        $streams = $graph->streams();
        assert(count($streams) === 2);
        assert($streams[0]['kind'] === 'video');
        assert($streams[0]['kind_id'] === PASMStreamKind::VIDEO);
        assert($streams[0]['jxl_section'] === 1);
        assert($streams[1]['kind'] === 'audio');
        assert($streams[1]['kind_id'] === PASMStreamKind::AUDIO);
        assert($streams[1]['jxl_section'] === 2);
    }

    private function testBytecodeGeneration(): void
    {
        $compiler = new PASMMediaGraphCompiler();

        $graph = $compiler->compile(
            media: [
                ['id' => 'src', 'control' => 'media', 'type' => 'file', 'mime' => 'video/mp4'],
            ],
            bindings: [],
            charts: [],
            streams: [
                [
                    'id' => 'stream0',
                    'kind' => 'video',
                    'jxl_section' => 1,
                    'meta' => ['codec' => 'h264'],
                ],
            ],
            clock: []
        );

        $bc = $graph->bytecode();
        assert(is_string($bc));
        assert(strlen($bc) > 0);

        $listing = $graph->listing();
        assert(is_string($listing));
        assert(strlen($listing) > 0);
        assert(str_contains($listing, 'MOPEN'));
        assert(str_contains($listing, 'MSTREAM_BIND'));
    }
}

$test = new TestPasmMediaAbiV2Multistream();
$test->run();
