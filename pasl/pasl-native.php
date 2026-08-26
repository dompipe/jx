<?php declare(strict_types=1);
namespace pasl;

use RuntimeException;

/**
 * PASM -> native-equivalent text backends.
 *
 * The order is deliberately readable:
 *   compile($source, $target)
 *   lower($pasm, $target)
 *
 * Source first, destination second: say what you have before where it goes.
 */
final class NativeEquivalence
{
    public static function toPasm(string $source): string
    {
        return (new Compiler(true))->toPasmAsm($source);
    }

    public static function toNasm(string $source): string
    {
        return PasmNativeTranslator::toNasm(self::toPasm($source));
    }

    public static function toCAsm(string $source): string
    {
        return PasmNativeTranslator::toCAsm(self::toPasm($source));
    }
}

/**
 * Translate the numeric PASM ISA into x86-64 NASM or GCC/Clang inline ASM.
 * The PASM program is the common meaning; native syntax is just another coat.
 */
final class PasmNativeTranslator
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

    /** @return list<array{label:?string,op:?string,args:list<string>,raw:string}> */
    public static function parse(string $pasm): array
    {
        $program = [];
        foreach (preg_split('/\R/', $pasm) ?: [] as $raw) {
            $line = trim(preg_replace('/[;#].*$/', '', $raw) ?? '');
            if ($line === '') continue;
            if (str_ends_with($line, ':')) {
                $program[] = ['label'=>rtrim($line, ':'), 'op'=>null, 'args'=>[], 'raw'=>$raw];
                continue;
            }
            $parts = preg_split('/[\s,]+/', $line) ?: [];
            $op = strtoupper((string)array_shift($parts));
            $program[] = ['label'=>null, 'op'=>$op, 'args'=>array_values($parts), 'raw'=>$raw];
        }
        return $program;
    }

    public static function toNasm(string $pasm): string
    {
        $body = self::translate($pasm, false);
        return "; PASL -> PASM -> NASM x86-64\n"
            . "; Same program meaning, native spelling.\n"
            . "bits 64\ndefault rel\nglobal _start\nsection .text\n_start:\n"
            . self::zeroRegisters('    ')
            . $body
            . "    xor rax, rax\n"
            . "jx_return:\n"
            . "    mov rdi, rax\n"
            . "    mov rax, 60\n"
            . "    syscall\n";
    }

    public static function toCAsm(string $pasm): string
    {
        $asm = self::translate($pasm, true);
        $asm = self::zeroRegisters('') . $asm;
        $lines = [];
        foreach (preg_split('/\R/', rtrim($asm)) ?: [] as $line) {
            $lines[] = '        "' . addcslashes($line . "\n", "\\\"") . '"';
        }
        $literal = implode("\n", $lines);

        return "/* PASL -> PASM -> portable C shell + real x86-64 inline assembly. */\n"
            . "/* The C shell is the host; the PASM-equivalent work is assembly. */\n"
            . "#include <stdint.h>\n\n"
            . "static int64_t pasl_entry(void) {\n"
            . "    int64_t result;\n"
            . "    __asm__ volatile(\n"
            . "        \".intel_syntax noprefix\\n\"\n"
            . $literal . "\n"
            . "        \".att_syntax prefix\\n\"\n"
            . "        : \"=a\"(result)\n"
            . "        :\n"
            . "        : \"rcx\", \"rdx\", \"r8\", \"r9\", \"r10\", \"r11\", \"r12\", \"r13\", \"r14\", \"r15\", \"cc\", \"memory\"\n"
            . "    );\n"
            . "    return result;\n"
            . "}\n\n"
            . "int main(void) { return (int)(pasl_entry() & 255); }\n";
    }

    private static function zeroRegisters(string $indent): string
    {
        $out = '';
        foreach (array_values(self::REG) as $reg) $out .= $indent . "xor {$reg}, {$reg}\n";
        return $out;
    }

    private static function reg(string $name): string
    {
        $key = strtolower($name);
        if (!isset(self::REG[$key])) throw new RuntimeException("PASM register {$name} has no x86-64 mapping");
        return self::REG[$key];
    }

    private static function translate(string $pasm, bool $inline): string
    {
        $out = '';
        foreach (self::parse($pasm) as $item) {
            if ($item['label'] !== null) {
                $out .= $item['label'] . ":\n";
                continue;
            }
            $op = $item['op'];
            $a = $item['args'];
            if ($op === null) continue;
            $emit = static fn(string $line): string => "    {$line}\n";

            $out .= match ($op) {
                'MOVI' => $emit('mov ' . self::reg($a[0]) . ', ' . (int)$a[1]),
                'MOVR' => $emit('mov ' . self::reg($a[0]) . ', ' . self::reg($a[1])),
                'ADD'  => self::binary($a, 'add'),
                'SUB'  => self::binary($a, 'sub'),
                'AND'  => self::binary($a, 'and'),
                'OR'   => self::binary($a, 'or'),
                'XOR'  => self::binary($a, 'xor'),
                'MUL'  => self::multiply($a),
                'DIV'  => self::divide($a, false),
                'MOD'  => self::divide($a, true),
                'SHL'  => self::shift($a, 'shl'),
                'SHR'  => self::shift($a, 'sar'),
                'INC'  => $emit('inc ' . self::reg($a[0])),
                'DEC'  => $emit('dec ' . self::reg($a[0])),
                'NEG'  => $emit('neg ' . self::reg($a[0])),
                'CMP'  => $emit('cmp ' . self::reg($a[0]) . ', ' . self::reg($a[1])),
                'JMP'  => $emit('jmp ' . $a[0]),
                'JZ'   => $emit('je ' . $a[0]),
                'JNZ'  => $emit('jne ' . $a[0]),
                'PUSH' => $emit('push ' . self::reg($a[0])),
                'POP'  => $emit('pop ' . self::reg($a[0])),
                'LOAD32' => self::load32($a),
                'STORE32' => self::store32($a),
                'RET'  => $emit('mov rax, ' . self::reg($a[0])) . ($inline ? $emit('jmp jx_return') : $emit('jmp jx_return')),
                'HALT' => $emit('xor rax, rax') . $emit('jmp jx_return'),
                default => self::resistantLongForm($op, $a),
            };
        }
        return $out;
    }

    /** dest = left OP right; destination order always reads dest, left, right. */
    private static function binary(array $a, string $op): string
    {
        $dest = self::reg($a[0]);
        $left = self::reg($a[1]);
        $right = self::reg($a[2]);
        $out = '';
        if ($dest !== $left) $out .= "    mov {$dest}, {$left}\n";
        $out .= "    {$op} {$dest}, {$right}\n";
        return $out;
    }

    private static function multiply(array $a): string
    {
        $dest = self::reg($a[0]);
        $left = self::reg($a[1]);
        $right = self::reg($a[2]);
        $out = $dest === $left ? '' : "    mov {$dest}, {$left}\n";
        return $out . "    imul {$dest}, {$right}\n";
    }

    private static function divide(array $a, bool $remainder): string
    {
        $dest = self::reg($a[0]);
        $left = self::reg($a[1]);
        $right = self::reg($a[2]);
        return "    mov rax, {$left}\n"
            . "    cqo\n"
            . "    idiv {$right}\n"
            . "    mov {$dest}, " . ($remainder ? 'rdx' : 'rax') . "\n";
    }

    private static function shift(array $a, string $op): string
    {
        $dest = self::reg($a[0]);
        $left = self::reg($a[1]);
        $count = self::reg($a[2]);
        $out = $dest === $left ? '' : "    mov {$dest}, {$left}\n";
        return $out . "    mov rcx, {$count}\n    {$op} {$dest}, cl\n";
    }

    private static function load32(array $a): string
    {
        $dest = self::reg($a[0]);
        $base = self::reg($a[1]);
        $offset = (int)($a[2] ?? 0);
        return "    mov {$dest}d, dword [{$base}+{$offset}]\n";
    }

    private static function store32(array $a): string
    {
        $base = self::reg($a[0]);
        $src = self::reg($a[1]);
        $offset = (int)($a[2] ?? 0);
        return "    mov dword [{$base}+{$offset}], {$src}d\n";
    }

    /**
     * Resistant does not mean "do not compile". Unknown PASM is preserved as
     * a long-form marker and execution continues. The Book compiler may route
     * the same artifact to portable C when full semantics need a host runtime.
     */
    private static function resistantLongForm(string $op, array $args): string
    {
        $text = $op . ($args === [] ? '' : ' ' . implode(', ', $args));
        return "    ; RESISTANT long-form retained: {$text}\n    nop\n";
    }
}
