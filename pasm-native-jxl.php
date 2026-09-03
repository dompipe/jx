<?php declare(strict_types=1);

namespace pasm;

require_once __DIR__ . '/pasm-bytecode.php';

use InvalidArgumentException;
use RuntimeException;

/**
 * Direct PASM -> x86-64 machine-code encoder.
 *
 * This is intentionally not a general x86 assembler. PASM is already the
 * resolved instruction language, so each supported PASM operation maps to a
 * deterministic native byte template plus register/immediate data.
 *
 * The returned bytes are native JXL for the x86-64 SysV profile. There is no
 * NASM/GAS/LLVM stage between PASM and these bytes.
 */
final class PASMNativeJxlException extends RuntimeException {}

final class PASMNativeJxlEncoder
{
    public const ARCH = 'x86_64-sysv';

    /**
     * PASM logical register -> x86-64 register id.
     *
     * All mapped registers are SysV caller-saved. R0/ecx maps to RAX so the
     * common RET ecx path needs no move at all.
     *
     * x86 ids: rax=0 rcx=1 rdx=2 rsi=6 rdi=7 r8=8 r9=9 r10=10 r11=11.
     * r11 is reserved as an encoder scratch and is never source-visible.
     */
    private const XREG = [
        'ecx' => 0,  // R0 -> RAX / native return register
        'ah'  => 1,  // R1 -> RCX
        'adx' => 2,  // R2 -> RDX
        'bdx' => 6,  // R3 -> RSI
        'cdx' => 7,  // R4 -> RDI
        'ddx' => 8,  // R5 -> R8
        'edx' => 9,  // R6 -> R9
        'rdx' => 10, // R7 -> R10
    ];

    private const SCRATCH = 11; // R11

    /** @var array<string,int> */
    private const JCC = [
        'JZ'  => 0x84,
        'JNZ' => 0x85,
        'JL'  => 0x8c,
        'JLE' => 0x8e,
        'JG'  => 0x8f,
        'JGE' => 0x8d,
    ];

    /** @return array<string,int> PASM register name -> native x86 register id */
    public static function registerMap(): array
    {
        return self::XREG;
    }

    /**
     * @param string|array<int,string> $source canonical PASM assembly
     */
    public function compile(string|array $source): string
    {
        $lines = is_array($source) ? $source : (preg_split('/\R/', $source) ?: []);
        $rows = [];
        $labels = [];
        $pc = 0;

        // Pass 1: resolve exact native byte offsets. Instruction size depends
        // only on operands, never on branch distance because branches are rel32.
        foreach ($lines as $raw) {
            $line = trim(preg_replace('/[;#].*$/', '', (string)$raw) ?? '');
            if ($line === '') continue;

            if (str_ends_with($line, ':')) {
                $label = strtolower(rtrim($line, ':'));
                if (!preg_match('/^[a-z_][a-z0-9_]*$/', $label)) {
                    throw new PASMNativeJxlException("Bad PASM label {$label}");
                }
                if (array_key_exists($label, $labels)) {
                    throw new PASMNativeJxlException("Duplicate PASM label {$label}");
                }
                $labels[$label] = $pc;
                continue;
            }

            $tokens = preg_split('/[\s,]+/', $line) ?: [];
            $op = strtoupper((string)array_shift($tokens));
            $preview = $this->emit($op, $tokens, $pc, null);
            $rows[] = ['pc' => $pc, 'op' => $op, 'args' => $tokens];
            $pc += strlen($preview);
        }

        // Pass 2: branches become direct x86-64 relative displacements.
        $out = '';
        foreach ($rows as $row) {
            $bytes = $this->emit($row['op'], $row['args'], $row['pc'], $labels);
            $out .= $bytes;
        }
        return $out;
    }

    /**
     * @param string|array<int,string> $source
     */
    public function compileFile(string|array $source, string $path): string
    {
        $bytes = $this->compile($source);
        $dir = dirname($path);
        if ($dir !== '.' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new PASMNativeJxlException("Cannot create {$dir}");
        }
        if (file_put_contents($path, $bytes) !== strlen($bytes)) {
            throw new PASMNativeJxlException("Cannot write {$path}");
        }
        return $bytes;
    }

    /** @param array<int,string> $a @param array<string,int>|null $labels */
    private function emit(string $op, array $a, int $at, ?array $labels): string
    {
        return match ($op) {
            'HALT' => $this->emitHalt($a),
            'MOVI' => $this->emitMovI($a),
            'MOVR' => $this->emitMovR($a),
            'ADD', 'SUB', 'MUL', 'AND', 'OR', 'XOR' => $this->emitBinary($op, $a),
            'CMP' => $this->emitCmp($a),
            'JMP' => $this->emitJmp($a, $at, $labels),
            'JZ', 'JNZ', 'JL', 'JLE', 'JG', 'JGE' => $this->emitJcc($op, $a, $at, $labels),
            'PUSH' => $this->emitPushPop($a, true),
            'POP' => $this->emitPushPop($a, false),
            'INC', 'DEC', 'NEG' => $this->emitUnary($op, $a),
            'RET' => $this->emitRet($a),
            'DIV', 'MOD', 'SHL', 'SHR',
            'LOAD32', 'STORE32', 'ITERF', 'ITERR', 'IRESET',
            'NLOAD', 'NSTORE', 'MCALL0', 'MCALL1', 'MCALL2', 'MCALL3'
                => throw new PASMNativeJxlException(
                    "{$op} is not yet admitted by the direct native JXL encoder; refusing hidden ABI clobbers"
                ),
            default => throw new PASMNativeJxlException("Unknown PASM opcode {$op}"),
        };
    }

    /** @param array<int,string> $a */
    private function emitHalt(array $a): string
    {
        self::argc('HALT', $a, 0);
        return "\xC3"; // return current RAX/R0
    }

    /** @param array<int,string> $a */
    private function emitMovI(array $a): string
    {
        self::argc('MOVI', $a, 2);
        $dst = $this->reg($a[0]);
        $value = self::parseInt($a[1]);
        return $this->movImm64($dst, $value);
    }

    /** @param array<int,string> $a */
    private function emitMovR(array $a): string
    {
        self::argc('MOVR', $a, 2);
        return $this->movReg($this->reg($a[0]), $this->reg($a[1]));
    }

    /** @param array<int,string> $a */
    private function emitBinary(string $op, array $a): string
    {
        self::argc($op, $a, 3);
        $d = $this->reg($a[0]);
        $left = $this->reg($a[1]);
        $right = $this->reg($a[2]);
        $commutative = in_array($op, ['ADD', 'MUL', 'AND', 'OR', 'XOR'], true);

        // Preserve true PASM three-address semantics even when dst aliases the
        // right operand. For commutative ops we can simply reverse operands.
        if ($d === $left) {
            return $this->binaryReg($op, $d, $right);
        }
        if ($commutative && $d === $right) {
            return $this->binaryReg($op, $d, $left);
        }
        if ($d !== $right) {
            return $this->movReg($d, $left) . $this->binaryReg($op, $d, $right);
        }

        // Non-commutative d == right: save right before overwriting d with left.
        return $this->movReg(self::SCRATCH, $right)
            . $this->movReg($d, $left)
            . $this->binaryReg($op, $d, self::SCRATCH);
    }

    /** @param array<int,string> $a */
    private function emitCmp(array $a): string
    {
        self::argc('CMP', $a, 2);
        $left = $this->reg($a[0]);
        $right = $this->reg($a[1]);
        // CMP r/m64, r64 => flags for left-right.
        return $this->rex(true, $right, $left)
            . "\x39"
            . $this->modrm($right, $left);
    }

    /** @param array<int,string> $a @param array<string,int>|null $labels */
    private function emitJmp(array $a, int $at, ?array $labels): string
    {
        self::argc('JMP', $a, 1);
        $len = 5;
        $disp = $labels === null ? 0 : $this->relativeTarget($a[0], $at, $len, $labels);
        return "\xE9" . self::i32($disp);
    }

    /** @param array<int,string> $a @param array<string,int>|null $labels */
    private function emitJcc(string $op, array $a, int $at, ?array $labels): string
    {
        self::argc($op, $a, 1);
        $len = 6;
        $disp = $labels === null ? 0 : $this->relativeTarget($a[0], $at, $len, $labels);
        return "\x0F" . chr(self::JCC[$op]) . self::i32($disp);
    }

    /** @param array<int,string> $a */
    private function emitPushPop(array $a, bool $push): string
    {
        $op = $push ? 'PUSH' : 'POP';
        self::argc($op, $a, 1);
        $r = $this->reg($a[0]);
        $base = $push ? 0x50 : 0x58;
        return ($r >= 8 ? "\x41" : '') . chr($base + ($r & 7));
    }

    /** @param array<int,string> $a */
    private function emitUnary(string $op, array $a): string
    {
        self::argc($op, $a, 1);
        $r = $this->reg($a[0]);
        [$opcode, $ext] = match ($op) {
            'INC' => ["\xFF", 0],
            'DEC' => ["\xFF", 1],
            'NEG' => ["\xF7", 3],
        };
        return $this->rex(true, 0, $r) . $opcode . $this->modrm($ext, $r);
    }

    /** @param array<int,string> $a */
    private function emitRet(array $a): string
    {
        self::argc('RET', $a, 1);
        $src = $this->reg($a[0]);
        return ($src === 0 ? '' : $this->movReg(0, $src)) . "\xC3";
    }

    private function binaryReg(string $op, int $dst, int $src): string
    {
        if ($op === 'MUL') {
            // IMUL r64, r/m64
            return $this->rex(true, $dst, $src)
                . "\x0F\xAF"
                . $this->modrm($dst, $src);
        }

        $opcode = match ($op) {
            'ADD' => 0x01,
            'SUB' => 0x29,
            'AND' => 0x21,
            'OR'  => 0x09,
            'XOR' => 0x31,
            default => throw new PASMNativeJxlException("No native binary encoding for {$op}"),
        };
        // op r/m64, r64
        return $this->rex(true, $src, $dst)
            . chr($opcode)
            . $this->modrm($src, $dst);
    }

    private function movReg(int $dst, int $src): string
    {
        // MOV r/m64, r64
        return $this->rex(true, $src, $dst)
            . "\x89"
            . $this->modrm($src, $dst);
    }

    private function movImm64(int $dst, int $value): string
    {
        // REX.W + B8+rd + imm64.
        $rex = 0x48 | (($dst >> 3) & 1);
        return chr($rex)
            . chr(0xB8 + ($dst & 7))
            . self::i64($value);
    }

    private function rex(bool $w, int $regField, int $rm): string
    {
        $v = 0x40
            | ($w ? 0x08 : 0)
            | ((($regField >> 3) & 1) << 2)
            | (($rm >> 3) & 1);
        return chr($v);
    }

    private function modrm(int $regField, int $rm): string
    {
        return chr(0xC0 | (($regField & 7) << 3) | ($rm & 7));
    }

    private function reg(string $name): int
    {
        $name = strtolower(trim($name));
        if (!array_key_exists($name, self::XREG)) {
            throw new PASMNativeJxlException("Unsupported PASM register {$name}");
        }
        return self::XREG[$name];
    }

    /** @param array<string,int> $labels */
    private function relativeTarget(string $target, int $at, int $instructionBytes, array $labels): int
    {
        $key = strtolower(trim($target));
        if (array_key_exists($key, $labels)) {
            $absolute = $labels[$key];
        } elseif (preg_match('/^-?(?:0x[0-9a-f]+|\d+)$/i', $key)) {
            $absolute = self::parseInt($key);
        } else {
            throw new PASMNativeJxlException("Unknown PASM branch target {$target}");
        }
        $disp = $absolute - ($at + $instructionBytes);
        if ($disp < -2147483648 || $disp > 2147483647) {
            throw new PASMNativeJxlException("Branch displacement out of rel32 range: {$disp}");
        }
        return $disp;
    }

    private static function parseInt(string $text): int
    {
        $text = trim($text);
        if (!preg_match('/^-?(?:0x[0-9a-f]+|\d+)$/i', $text)) {
            throw new PASMNativeJxlException("Expected integer, got {$text}");
        }
        $negative = str_starts_with($text, '-');
        $body = $negative ? substr($text, 1) : $text;
        $value = str_starts_with(strtolower($body), '0x')
            ? (int)hexdec(substr($body, 2))
            : (int)$body;
        return $negative ? -$value : $value;
    }

    private static function i32(int $value): string
    {
        return pack('V', $value & 0xffffffff);
    }

    private static function i64(int $value): string
    {
        $lo = $value & 0xffffffff;
        $hi = ($value >> 32) & 0xffffffff;
        return pack('V2', $lo, $hi);
    }

    /** @param array<int,mixed> $args */
    private static function argc(string $op, array $args, int $expected): void
    {
        if (count($args) !== $expected) {
            throw new InvalidArgumentException("{$op} expects {$expected} operands, got " . count($args));
        }
    }
}
