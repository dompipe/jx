<?php declare(strict_types=1);
namespace pasm;

require_once __DIR__ . '/pasm-bytecode.php';

/** Reserved superinstruction opcode IDs. */
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

/** One optimizing assembler, one canonical packed PASM ABI. */
final class PASMOptimizingAssembler
{
    public function __construct(private bool $enabled = true) {}
    public function compile(string|array $source): string { return (new PASMAssembler())->compile($source); }
}

/** Optimized facade over the same packed/address-aware PASM VM. */
final class PASMOptimizedBytecodeVM
{
    public function __construct(
        private PASMRuntime $runtime,
        private int $maxInstructions = 1_000_000,
        private ?PASMNamedMemory $namedMemory = null,
        private ?PASMMethodABI $methods = null,
        private ?PASMIteratorTable $iterators = null,
    ) {}

    public function run(string $code): mixed
    {
        return (new PASMBytecodeVM(
            $this->runtime,
            $this->maxInstructions,
            $this->namedMemory,
            $this->methods,
            $this->iterators,
        ))->run($code);
    }
}
