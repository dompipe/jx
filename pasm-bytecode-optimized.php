<?php declare(strict_types=1);
namespace pasm;

require_once __DIR__ . '/pasm-bytecode.php';

/**
 * Reserved superinstruction opcode IDs.
 *
 * These identities remain stable for manifests/provenance.  The active
 * optimizing layer deliberately emits the canonical packed base ABI until a
 * superinstruction is repacked and independently benchmarked.  Correctness
 * and one executable ABI take precedence over preserving an older unpacked
 * shadow format.
 */
final class PASMSuperBC
{
    public const MOVI2_ADD = 0x80;
    public const MOVI2_MUL = 0x81;
    public const CMP_JZ = 0x82;
    public const CMP_JNZ = 0x83;
    public const DEC_CMP_JNZ = 0x84;
    public const LOAD32_ADD = 0x85;

    public static function name(int $op): string
    {
        return match ($op) {
            self::MOVI2_ADD => 'MOVI2_ADD',
            self::MOVI2_MUL => 'MOVI2_MUL',
            self::CMP_JZ => 'CMP_JZ',
            self::CMP_JNZ => 'CMP_JNZ',
            self::DEC_CMP_JNZ => 'DEC_CMP_JNZ',
            self::LOAD32_ADD => 'LOAD32_ADD',
            default => 'UNKNOWN',
        };
    }
}

/**
 * Optimization compatibility layer.
 *
 * PASL loop/body fusion happens before this assembly stage.  This class now
 * uses PASMAssembler as the single source of truth for byte sizes, label
 * offsets, and packed 3-bit register tuples.  Future superinstructions must
 * be added on top of this ABI rather than creating a second encoding.
 */
final class PASMOptimizingAssembler
{
    public function __construct(private bool $enabled = true) {}

    public function compile(string|array $source): string
    {
        return (new PASMAssembler())->compile($source);
    }
}

/**
 * Optimized execution facade over the one canonical packed PASM VM.
 * Keeping this type preserves the Engine API while eliminating the former
 * incompatible unpacked-register execution path.
 */
final class PASMOptimizedBytecodeVM
{
    public function __construct(
        private PASMRuntime $runtime,
        private int $maxInstructions = 1_000_000,
    ) {}

    public function run(string $code): mixed
    {
        return (new PASMBytecodeVM($this->runtime, $this->maxInstructions))->run($code);
    }
}
