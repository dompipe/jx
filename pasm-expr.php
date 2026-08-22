<?php declare(strict_types=1);
/**
 * Restricted expression → PASM assembly → bytecode
 *
 * This is NOT a full PHP compiler. It accepts a small statement language
 * that looks like PHP integer ops and lowers them to the binary ISA.
 *
 * Supported:
 *   x = y + 1
 *   x += 1 / x -= 1 / x *= 2 / x /= 2 / x %= 3
 *   x++ / ++x / x-- / --x
 *   x = a + b * c  (left-to-right within +-, then */%  — simple precedence)
 *   bitwise: & | ^ << >>
 *   unary -
 *
 * Variables map onto the 8 bytecode registers (ecx, ah, adx, bdx, cdx, ddx, edx, rdx).
 * First use of an unknown name allocates the next free register.
 */

namespace pasm;

require_once __DIR__ . '/pasm-bytecode.php';
require_once __DIR__ . '/pasm-bytecode-optimized.php';

use InvalidArgumentException;
use RuntimeException;

final class PASMExprException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $statement = null,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $extra = $statement !== null ? " in `{$statement}`" : '';
        parent::__construct("[PASMExpr] {$message}{$extra}", $code, $previous);
    }
}

final class PASMExprCompiler
{
    /** Fixed bytecode register pool (order = allocation order). */
    public const REGS = ['ecx', 'ah', 'adx', 'bdx', 'cdx', 'ddx', 'edx', 'rdx'];

    /** @var array<string,string> userVar → regName */
    private array $map = [];
    private int $nextReg = 0;
    /** @var string[] emitted ASM lines */
    private array $lines = [];
    private int $tmpSeq = 0;

    public function __construct(?array $presetMap = null)
    {
        if ($presetMap !== null) {
            foreach ($presetMap as $var => $reg) {
                $reg = strtolower((string)$reg);
                if (!in_array($reg, self::REGS, true)) {
                    throw new InvalidArgumentException("Invalid register {$reg}");
                }
                $this->map[self::normVar((string)$var)] = $reg;
                $this->nextReg = max($this->nextReg, array_search($reg, self::REGS, true) + 1);
            }
        }
    }

    public function vars(): array
    {
        return $this->map;
    }

    public function resetCode(): void
    {
        $this->lines = [];
        $this->tmpSeq = 0;
    }

    /** Compile one or more statements separated by ; or newlines. */
    public function compile(string $source): string
    {
        $this->resetCode();
        $this->lines[] = '; expr → PASM';
        $stmts = $this->splitStatements($source);
        if ($stmts === []) {
            throw new PASMExprException('No statements to compile');
        }
        foreach ($stmts as $stmt) {
            $this->compileStatement($stmt);
        }
        // Return the last assigned variable if any, else first mapped var, else ecx
        $ret = array_key_last($this->map);
        if ($ret !== null) {
            $this->lines[] = '        RET    ' . $this->map[$ret];
        } else {
            $this->lines[] = '        RET    ecx';
        }
        return implode("\n", $this->lines);
    }

    public function compileToBytecode(string $source, bool $optimize = true): string
    {
        $asm = $this->compile($source);
        $assembler = new PASMOptimizingAssembler($optimize);
        try {
            return $assembler->compile($asm);
        } catch (\Throwable $e) {
            throw new PASMExprException('Assembler failed: ' . $e->getMessage(), $source, [], 0, $e);
        }
    }

    private static function normVar(string $v): string
    {
        $v = ltrim(trim($v), '$');
        if ($v === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $v)) {
            throw new PASMExprException("Invalid variable name", $v);
        }
        return strtolower($v);
    }

    private function regFor(string $var): string
    {
        $v = self::normVar($var);
        if (isset($this->map[$v])) {
            return $this->map[$v];
        }
        if ($this->nextReg >= count(self::REGS)) {
            throw new PASMExprException(
                'Out of bytecode registers (max ' . count(self::REGS) . ')',
                $v,
                ['mapped' => $this->map]
            );
        }
        $reg = self::REGS[$this->nextReg++];
        $this->map[$v] = $reg;
        $this->lines[] = "; var \${$v} → {$reg}";
        return $reg;
    }

    private function tmpReg(): string
    {
        // Prefer unused high registers; if none, reuse rdx as scratch carefully
        for ($i = count(self::REGS) - 1; $i >= 0; $i--) {
            $r = self::REGS[$i];
            if (!in_array($r, $this->map, true)) {
                return $r;
            }
        }
        // All mapped — use rdx as last-resort scratch (document it)
        $this->lines[] = '; WARNING: reusing rdx as temp';
        return 'rdx';
    }

    private function splitStatements(string $source): array
    {
        $source = preg_replace('/\/\*.*?\*\//s', '', $source) ?? $source;
        $source = preg_replace('/\/\/.*$/m', '', $source) ?? $source;
        $source = preg_replace('/#.*$/m', '', $source) ?? $source;
        $parts = preg_split('/[;\r\n]+/', $source) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    private function compileStatement(string $stmt): void
    {
        $this->lines[] = '; ' . $stmt;

        // ++x / --x
        if (preg_match('/^(\+\+|--)\s*\$?([A-Za-z_][A-Za-z0-9_]*)$/', $stmt, $m)) {
            $reg = $this->regFor($m[2]);
            $this->lines[] = '        ' . ($m[1] === '++' ? 'INC' : 'DEC') . '    ' . $reg;
            return;
        }
        // x++ / x--
        if (preg_match('/^\$?([A-Za-z_][A-Za-z0-9_]*)\s*(\+\+|--)$/', $stmt, $m)) {
            $reg = $this->regFor($m[1]);
            $this->lines[] = '        ' . ($m[2] === '++' ? 'INC' : 'DEC') . '    ' . $reg;
            return;
        }

        // compound assign: x += expr, x -=, *=, /=, %=, &=, |=, ^=, <<=, >>=
        if (preg_match('/^\$?([A-Za-z_][A-Za-z0-9_]*)\s*(\+|\-|\*|\/|%|&|\||\^|<<|>>)=\s*(.+)$/', $stmt, $m)) {
            $dst = $this->regFor($m[1]);
            $op = $m[2];
            $rhsReg = $this->emitExpr(trim($m[3]));
            $this->emitBinOp($dst, $dst, $rhsReg, $op);
            return;
        }

        // simple assign: x = expr
        if (preg_match('/^\$?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)$/', $stmt, $m)) {
            $dst = $this->regFor($m[1]);
            $rhs = trim($m[2]);
            // x = y  (register move)
            if (preg_match('/^\$?([A-Za-z_][A-Za-z0-9_]*)$/', $rhs, $vm)) {
                $src = $this->regFor($vm[1]);
                if ($src !== $dst) {
                    $this->lines[] = "        MOVR  {$dst} {$src}";
                }
                return;
            }
            // x = 42
            if (preg_match('/^-?\d+$/', $rhs)) {
                $this->lines[] = "        MOVI  {$dst} " . (int)$rhs;
                return;
            }
            $src = $this->emitExpr($rhs);
            if ($src !== $dst) {
                $this->lines[] = "        MOVR  {$dst} {$src}";
            }
            return;
        }

        throw new PASMExprException('Unsupported statement', $stmt);
    }

    /**
     * Emit expression into a register; return that register name.
     * Precedence: unary -  >  * / %  >  + -  >  << >>  >  &  >  ^  >  |
     * Implementation: recursive descent on token stream.
     */
    private function emitExpr(string $expr): string
    {
        $tokens = $this->tokenize($expr);
        $pos = 0;
        $reg = $this->parseOr($tokens, $pos);
        if ($pos < count($tokens)) {
            throw new PASMExprException('Unexpected token ' . $tokens[$pos], $expr);
        }
        return $reg;
    }

    private function tokenize(string $expr): array
    {
        $tokens = [];
        $i = 0;
        $n = strlen($expr);
        while ($i < $n) {
            $c = $expr[$i];
            if (ctype_space($c)) {
                $i++;
                continue;
            }
            if ($c === '$') {
                $i++;
                continue; // strip PHP $ prefix
            }
            if (ctype_digit($c) || ($c === '-' && $i + 1 < $n && ctype_digit($expr[$i + 1]) && ($i === 0 || in_array($expr[$i - 1] ?? '', ['(', '+', '-', '*', '/', '%', '&', '|', '^', '<', '>', '=', ','], true)))) {
                $j = $i;
                if ($expr[$j] === '-') {
                    $j++;
                }
                while ($j < $n && ctype_digit($expr[$j])) {
                    $j++;
                }
                $tokens[] = ['num', (int)substr($expr, $i, $j - $i)];
                $i = $j;
                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                $j = $i + 1;
                while ($j < $n && (ctype_alnum($expr[$j]) || $expr[$j] === '_')) {
                    $j++;
                }
                $tokens[] = ['id', substr($expr, $i, $j - $i)];
                $i = $j;
                continue;
            }
            // two-char ops
            if ($i + 1 < $n) {
                $two = $expr[$i] . $expr[$i + 1];
                if (in_array($two, ['<<', '>>'], true)) {
                    $tokens[] = ['op', $two];
                    $i += 2;
                    continue;
                }
            }
            if (str_contains('+-*/%&|^()', $c)) {
                $tokens[] = ['op', $c];
                $i++;
                continue;
            }
            throw new PASMExprException("Unexpected character '{$c}'", $expr);
        }
        return $tokens;
    }

    private function parseOr(array $t, int &$p): string
    {
        $left = $this->parseXor($t, $p);
        while ($this->peekOp($t, $p, '|')) {
            $p++;
            $right = $this->parseXor($t, $p);
            $left = $this->binIntoNew($left, $right, '|');
        }
        return $left;
    }

    private function parseXor(array $t, int &$p): string
    {
        $left = $this->parseAnd($t, $p);
        while ($this->peekOp($t, $p, '^')) {
            $p++;
            $right = $this->parseAnd($t, $p);
            $left = $this->binIntoNew($left, $right, '^');
        }
        return $left;
    }

    private function parseAnd(array $t, int &$p): string
    {
        $left = $this->parseShift($t, $p);
        while ($this->peekOp($t, $p, '&')) {
            $p++;
            $right = $this->parseShift($t, $p);
            $left = $this->binIntoNew($left, $right, '&');
        }
        return $left;
    }

    private function parseShift(array $t, int &$p): string
    {
        $left = $this->parseAdd($t, $p);
        while (true) {
            if ($this->peekOp($t, $p, '<<')) {
                $p++;
                $right = $this->parseAdd($t, $p);
                $left = $this->binIntoNew($left, $right, '<<');
            } elseif ($this->peekOp($t, $p, '>>')) {
                $p++;
                $right = $this->parseAdd($t, $p);
                $left = $this->binIntoNew($left, $right, '>>');
            } else {
                break;
            }
        }
        return $left;
    }

    private function parseAdd(array $t, int &$p): string
    {
        $left = $this->parseMul($t, $p);
        while (true) {
            if ($this->peekOp($t, $p, '+')) {
                $p++;
                $right = $this->parseMul($t, $p);
                $left = $this->binIntoNew($left, $right, '+');
            } elseif ($this->peekOp($t, $p, '-')) {
                $p++;
                $right = $this->parseMul($t, $p);
                $left = $this->binIntoNew($left, $right, '-');
            } else {
                break;
            }
        }
        return $left;
    }

    private function parseMul(array $t, int &$p): string
    {
        $left = $this->parseUnary($t, $p);
        while (true) {
            if ($this->peekOp($t, $p, '*')) {
                $p++;
                $right = $this->parseUnary($t, $p);
                $left = $this->binIntoNew($left, $right, '*');
            } elseif ($this->peekOp($t, $p, '/')) {
                $p++;
                $right = $this->parseUnary($t, $p);
                $left = $this->binIntoNew($left, $right, '/');
            } elseif ($this->peekOp($t, $p, '%')) {
                $p++;
                $right = $this->parseUnary($t, $p);
                $left = $this->binIntoNew($left, $right, '%');
            } else {
                break;
            }
        }
        return $left;
    }

    private function parseUnary(array $t, int &$p): string
    {
        if ($this->peekOp($t, $p, '-')) {
            $p++;
            $r = $this->parseUnary($t, $p);
            // NEG in place if temp, else copy then NEG
            $dst = $this->tmpReg();
            if ($dst !== $r) {
                $this->lines[] = "        MOVR  {$dst} {$r}";
            }
            $this->lines[] = "        NEG   {$dst}";
            return $dst;
        }
        if ($this->peekOp($t, $p, '+')) {
            $p++;
            return $this->parseUnary($t, $p);
        }
        return $this->parsePrimary($t, $p);
    }

    private function parsePrimary(array $t, int &$p): string
    {
        if ($p >= count($t)) {
            throw new PASMExprException('Unexpected end of expression');
        }
        $tok = $t[$p];
        if ($tok[0] === 'num') {
            $p++;
            $dst = $this->tmpReg();
            $this->lines[] = "        MOVI  {$dst} {$tok[1]}";
            return $dst;
        }
        if ($tok[0] === 'id') {
            $p++;
            return $this->regFor($tok[1]);
        }
        if ($tok[0] === 'op' && $tok[1] === '(') {
            $p++;
            $r = $this->parseOr($t, $p);
            if (!$this->peekOp($t, $p, ')')) {
                throw new PASMExprException('Expected )');
            }
            $p++;
            return $r;
        }
        throw new PASMExprException('Unexpected token in primary');
    }

    private function peekOp(array $t, int $p, string $op): bool
    {
        return isset($t[$p]) && $t[$p][0] === 'op' && $t[$p][1] === $op;
    }

    private function binIntoNew(string $left, string $right, string $op): string
    {
        $dst = $this->tmpReg();
        $this->emitBinOp($dst, $left, $right, $op);
        return $dst;
    }

    private function emitBinOp(string $dst, string $a, string $b, string $op): void
    {
        $opc = match ($op) {
            '+', '+=' => 'ADD',
            '-', '-=' => 'SUB',
            '*', '*=' => 'MUL',
            '/', '/=' => 'DIV',
            '%', '%=' => 'MOD',
            '&', '&=' => 'AND',
            '|', '|=' => 'OR',
            '^', '^=' => 'XOR',
            '<<', '<<=' => 'SHL',
            '>>', '>>=' => 'SHR',
            default => throw new PASMExprException("Unknown operator {$op}"),
        };
        $this->lines[] = "        {$opc}   {$dst} {$a} {$b}";
    }
}
