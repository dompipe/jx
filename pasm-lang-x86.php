<?php declare(strict_types=1);

namespace pasm\lang;

require_once __DIR__ . '/pasm-lang.php';

use InvalidArgumentException;

/**
 * PASL -> canonical PASM -> GNU x86-64 (Intel syntax).
 *
 * The PASM compiler remains the semantic source of truth.  This backend only
 * lowers the already-resolved PASM register program.  PASM's eight hot
 * registers map to eight callee-saved/scratch x86-64 registers so names,
 * aliases, and expression parsing do not survive into native emission.
 */
final class X86Compiler
{
    private const REG = [
        'ecx' => 'r8',
        'ah'  => 'r9',
        'adx' => 'r10',
        'bdx' => 'r11',
        'cdx' => 'r12',
        'ddx' => 'r13',
        'edx' => 'r14',
        'rdx' => 'r15',
    ];

    public function __construct(private bool $optimize = true) {}

    public function compile(string $source): string
    {
        $pasm = (new Compiler($this->optimize, false))->compile($source);
        return $this->lowerPasm($pasm);
    }

    public function lowerPasm(string $pasm): string
    {
        $out = [
            '.intel_syntax noprefix',
            '.text',
            '.globl pasl_main',
            '.type pasl_main, @function',
            'pasl_main:',
            '    push r12',
            '    push r13',
            '    push r14',
            '    push r15',
        ];

        foreach (preg_split('/\R/', $pasm) ?: [] as $raw) {
            $line = trim((string)preg_replace('/[;#].*$/', '', $raw));
            if ($line === '') continue;
            if (str_ends_with($line, ':')) {
                $out[] = $line;
                continue;
            }

            $t = preg_split('/[\s,]+/', $line) ?: [];
            $op = strtoupper((string)array_shift($t));
            $out = array_merge($out, $this->lowerInstruction($op, $t));
        }

        $out[] = '.size pasl_main, .-pasl_main';
        return implode("\n", $out) . "\n";
    }

    /** @param list<string> $a @return list<string> */
    private function lowerInstruction(string $op, array $a): array
    {
        $r = fn(int $i): string => $this->reg($a[$i] ?? throw new InvalidArgumentException("{$op} missing register {$i}"));

        return match ($op) {
            'HALT' => $this->epilogue('xor rax, rax'),
            'MOVI' => ['    mov ' . $r(0) . ', ' . $this->imm($a[1] ?? '0')],
            'MOVR' => $r(0) === $r(1) ? [] : ['    mov ' . $r(0) . ', ' . $r(1)],
            'ADD'  => $this->binary('add', $r(0), $r(1), $r(2)),
            'SUB'  => $this->binary('sub', $r(0), $r(1), $r(2)),
            'MUL'  => $this->binary('imul', $r(0), $r(1), $r(2)),
            'AND'  => $this->binary('and', $r(0), $r(1), $r(2)),
            'OR'   => $this->binary('or', $r(0), $r(1), $r(2)),
            'XOR'  => $this->binary('xor', $r(0), $r(1), $r(2)),
            'DIV'  => $this->divide($r(0), $r(1), $r(2), false),
            'MOD'  => $this->divide($r(0), $r(1), $r(2), true),
            'SHL'  => $this->shift('shl', $r(0), $r(1), $r(2)),
            'SHR'  => $this->shift('sar', $r(0), $r(1), $r(2)),
            'CMP'  => ['    cmp ' . $r(0) . ', ' . $r(1)],
            'JMP'  => ['    jmp ' . $this->label($a[0] ?? '')],
            'JZ'   => ['    je ' . $this->label($a[0] ?? '')],
            'JNZ'  => ['    jne ' . $this->label($a[0] ?? '')],
            'INC'  => ['    inc ' . $r(0)],
            'DEC'  => ['    dec ' . $r(0)],
            'NEG'  => ['    neg ' . $r(0)],
            'PUSH' => ['    push ' . $r(0)],
            'POP'  => ['    pop ' . $r(0)],
            'RET'  => $this->epilogue('mov rax, ' . $r(0)),
            // Native memory lowering: base register + unsigned byte offset.
            'LOAD32' => ['    mov ' . $r(0) . 'd, DWORD PTR [' . $r(1) . ' + ' . ((int)($a[2] ?? 0) & 255) . ']'],
            'STORE32' => ['    mov DWORD PTR [' . $r(1) . ' + ' . ((int)($a[2] ?? 0) & 255) . '], ' . $r(0) . 'd'],
            default => throw new InvalidArgumentException("Unsupported PASM opcode for x86: {$op}"),
        };
    }

    /** @return list<string> */
    private function binary(string $op, string $dst, string $left, string $right): array
    {
        if ($dst === $left) return ["    {$op} {$dst}, {$right}"];
        if ($op === 'add' && $dst === $right) return ["    add {$dst}, {$left}"];
        return ["    mov {$dst}, {$left}", "    {$op} {$dst}, {$right}"];
    }

    /** @return list<string> */
    private function divide(string $dst, string $left, string $right, bool $mod): array
    {
        return [
            "    mov rax, {$left}",
            '    cqo',
            "    idiv {$right}",
            '    mov ' . $dst . ', ' . ($mod ? 'rdx' : 'rax'),
        ];
    }

    /** Variable shifts use CL on x86; rcx is deliberately outside PASM's mapped register file. @return list<string> */
    private function shift(string $op, string $dst, string $left, string $count): array
    {
        $lines = [];
        if ($dst !== $left) $lines[] = "    mov {$dst}, {$left}";
        $lines[] = "    mov rcx, {$count}";
        $lines[] = "    {$op} {$dst}, cl";
        return $lines;
    }

    /** @return list<string> */
    private function epilogue(string $result): array
    {
        return [
            '    ' . $result,
            '    pop r15',
            '    pop r14',
            '    pop r13',
            '    pop r12',
            '    ret',
        ];
    }

    private function reg(string $name): string
    {
        $k = strtolower(trim($name));
        return self::REG[$k] ?? throw new InvalidArgumentException("Unknown PASM register {$name}");
    }

    private function imm(string $v): string
    {
        if (!preg_match('/^-?\d+$/', trim($v))) throw new InvalidArgumentException("Bad x86 immediate {$v}");
        return (string)(int)$v;
    }

    private function label(string $s): string
    {
        $s = trim($s);
        if (!preg_match('/^[A-Za-z_.][A-Za-z0-9_.]*$/', $s)) throw new InvalidArgumentException("Bad x86 label {$s}");
        return $s;
    }
}
