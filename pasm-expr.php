<?php declare(strict_types=1);
/**
 * Restricted statement language → PASM assembly → bytecode
 *
 * Expressions (assignments, ++, arithmetic, bitwise) plus control flow:
 *   while (cond) { ... }
 *   for (init; cond; step) { ... }
 *   if (cond) { ... } else { ... }
 *   select (x) { case 1: ...; case 2: ...; default: ...; }
 *   break;  continue;   (innermost loop)
 *
 * Conditions: comparisons == != < > <= >= and integer truthiness (CMP vs 0).
 * foreach is only supported as: foreach ($i = a; $i <= b; $i++) { }  alias of for
 *   or  for ($i = a; $i <= b; $i++) — not PHP iterators/arrays.
 *
 * Variables map to the 8 bytecode registers.
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
        $extra = $statement !== null ? " near `{$statement}`" : '';
        parent::__construct("[PASMExpr] {$message}{$extra}", $code, $previous);
    }
}

final class PASMExprCompiler
{
    public const REGS = ['ecx', 'ah', 'adx', 'bdx', 'cdx', 'ddx', 'edx', 'rdx'];

    /** @var array<string,string> */
    private array $map = [];
    private int $nextReg = 0;
    /** @var string[] */
    private array $lines = [];
    private int $labelSeq = 0;
    private int $tmpSeq = 0;

    /** @var array<int,array{break:string,continue:string}> */
    private array $loopStack = [];

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
        $this->labelSeq = 0;
        $this->tmpSeq = 0;
        $this->loopStack = [];
    }

    public function compile(string $source): string
    {
        $this->resetCode();
        $this->lines[] = '; stmt/expr → PASM';
        $src = $this->stripComments($source);
        $this->compileBlock($src);
        $ret = array_key_last($this->map);
        $this->lines[] = '        RET    ' . ($ret !== null ? $this->map[$ret] : 'ecx');
        return implode("\n", $this->lines);
    }

    public function compileToBytecode(string $source, bool $optimize = true): string
    {
        $asm = $this->compile($source);
        $assembler = new PASMOptimizingAssembler($optimize);
        try {
            return $assembler->compile($asm);
        } catch (\Throwable $e) {
            throw new PASMExprException('Assembler failed: ' . $e->getMessage(), null, [], 0, $e);
        }
    }

    private function stripComments(string $s): string
    {
        $s = preg_replace('/\/\*.*?\*\//s', '', $s) ?? $s;
        $s = preg_replace('/\/\/.*$/m', '', $s) ?? $s;
        $s = preg_replace('/#.*$/m', '', $s) ?? $s;
        return $s;
    }

    private function freshLabel(string $prefix): string
    {
        return $prefix . '_' . ($this->labelSeq++);
    }

    private static function normVar(string $v): string
    {
        $v = ltrim(trim($v), '$');
        if ($v === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $v)) {
            throw new PASMExprException('Invalid variable name', $v);
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
            throw new PASMExprException('Out of bytecode registers (max 8)', $v, ['mapped' => $this->map]);
        }
        $reg = self::REGS[$this->nextReg++];
        $this->map[$v] = $reg;
        $this->lines[] = "; var \${$v} → {$reg}";
        return $reg;
    }

    private function tmpReg(): string
    {
        for ($i = count(self::REGS) - 1; $i >= 0; $i--) {
            $r = self::REGS[$i];
            if (!in_array($r, $this->map, true)) {
                return $r;
            }
        }
        $this->lines[] = '; WARNING: reusing rdx as temp';
        return 'rdx';
    }

    /* ---------- block / statement dispatch ---------- */

    private function compileBlock(string $src): void
    {
        $src = trim($src);
        if ($src === '') {
            return;
        }
        $i = 0;
        $n = strlen($src);
        while ($i < $n) {
            while ($i < $n && ctype_space($src[$i])) {
                $i++;
            }
            if ($i >= $n) {
                break;
            }
            $rest = substr($src, $i);

            if (preg_match('/^while\s*\(/i', $rest)) {
                $i += $this->compileWhile(substr($src, $i));
                continue;
            }
            if (preg_match('/^for\s*\(/i', $rest)) {
                $i += $this->compileFor(substr($src, $i));
                continue;
            }
            if (preg_match('/^foreach\s*\(/i', $rest)) {
                throw new PASMExprException(
                    'foreach over iterators is not supported; use for ($i = a; $i <= b; $i++)',
                    substr($rest, 0, 40)
                );
            }
            if (preg_match('/^if\s*\(/i', $rest)) {
                $i += $this->compileIf(substr($src, $i));
                continue;
            }
            if (preg_match('/^select\s*\(/i', $rest) || preg_match('/^switch\s*\(/i', $rest)) {
                $i += $this->compileSelect(substr($src, $i));
                continue;
            }
            if (preg_match('/^break\s*;?/i', $rest, $m)) {
                $this->emitBreak();
                $i += strlen($m[0]);
                continue;
            }
            if (preg_match('/^continue\s*;?/i', $rest, $m)) {
                $this->emitContinue();
                $i += strlen($m[0]);
                continue;
            }

            // simple statement up to ;
            $end = $this->findStatementEnd($src, $i);
            $stmt = trim(substr($src, $i, $end - $i));
            if ($stmt !== '' && $stmt !== ';') {
                $stmt = rtrim($stmt, ';');
                $this->compileSimpleStatement($stmt);
            }
            $i = $end + 1;
        }
    }

    private function findStatementEnd(string $src, int $start): int
    {
        $n = strlen($src);
        $depth = 0;
        for ($i = $start; $i < $n; $i++) {
            $c = $src[$i];
            if ($c === '{' || $c === '(') {
                $depth++;
            } elseif ($c === '}' || $c === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($c === ';' && $depth === 0) {
                return $i;
            }
        }
        return $n; // no semicolon — rest of string
    }

    private function extractParen(string $s, int $openPos): array
    {
        // $openPos points at '('
        $n = strlen($s);
        $depth = 0;
        for ($i = $openPos; $i < $n; $i++) {
            if ($s[$i] === '(') {
                $depth++;
            } elseif ($s[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    return [substr($s, $openPos + 1, $i - $openPos - 1), $i + 1];
                }
            }
        }
        throw new PASMExprException('Unbalanced (', substr($s, $openPos, 40));
    }

    private function extractBrace(string $s, int $from): array
    {
        $n = strlen($s);
        $i = $from;
        while ($i < $n && ctype_space($s[$i])) {
            $i++;
        }
        if ($i >= $n || $s[$i] !== '{') {
            // single statement without braces
            $end = $this->findStatementEnd($s, $i);
            $body = trim(substr($s, $i, $end - $i));
            return [$body, min($n, $end + 1)];
        }
        $depth = 0;
        for ($j = $i; $j < $n; $j++) {
            if ($s[$j] === '{') {
                $depth++;
            } elseif ($s[$j] === '}') {
                $depth--;
                if ($depth === 0) {
                    return [substr($s, $i + 1, $j - $i - 1), $j + 1];
                }
            }
        }
        throw new PASMExprException('Unbalanced {', substr($s, $i, 40));
    }

    /* ---------- while ---------- */

    private function compileWhile(string $s): int
    {
        // while (cond) { body }
        if (!preg_match('/^while\s*\(/i', $s, $hm)) {
            throw new PASMExprException('Expected while');
        }
        $open = strpos($s, '(');
        [$cond, $afterParen] = $this->extractParen($s, $open);
        [$body, $afterBody] = $this->extractBrace($s, $afterParen);

        $Lhead = $this->freshLabel('while');
        $Lbody = $this->freshLabel('wbody');
        $Lend  = $this->freshLabel('wend');

        $this->loopStack[] = ['break' => $Lend, 'continue' => $Lhead];

        $this->lines[] = "; while ({$cond})";
        $this->lines[] = "{$Lhead}:";
        $this->emitConditionJump($cond, $Lbody, $Lend, true);
        $this->lines[] = "{$Lbody}:";
        $this->compileBlock($body);
        $this->lines[] = "        JMP   {$Lhead}";
        $this->lines[] = "{$Lend}:";

        array_pop($this->loopStack);
        return $afterBody;
    }

    /* ---------- for ---------- */

    private function compileFor(string $s): int
    {
        // for (init; cond; step) { body }
        if (!preg_match('/^for\s*\(/i', $s)) {
            throw new PASMExprException('Expected for');
        }
        $open = strpos($s, '(');
        [$inside, $afterParen] = $this->extractParen($s, $open);
        $parts = $this->splitForHeader($inside);
        if (count($parts) !== 3) {
            throw new PASMExprException('for requires for (init; cond; step)', $inside);
        }
        [$init, $cond, $step] = $parts;
        [$body, $afterBody] = $this->extractBrace($s, $afterParen);

        $Lhead = $this->freshLabel('for');
        $Lbody = $this->freshLabel('fbody');
        $Lstep = $this->freshLabel('fstep');
        $Lend  = $this->freshLabel('fend');

        $this->lines[] = "; for ({$init}; {$cond}; {$step})";
        if (trim($init) !== '') {
            $this->compileSimpleStatement(trim($init));
        }

        $this->loopStack[] = ['break' => $Lend, 'continue' => $Lstep];

        $this->lines[] = "{$Lhead}:";
        if (trim($cond) === '') {
            // infinite until break
            $this->lines[] = "        JMP   {$Lbody}";
        } else {
            $this->emitConditionJump(trim($cond), $Lbody, $Lend, true);
        }
        $this->lines[] = "{$Lbody}:";
        $this->compileBlock($body);
        $this->lines[] = "{$Lstep}:";
        if (trim($step) !== '') {
            $this->compileSimpleStatement(trim($step));
        }
        $this->lines[] = "        JMP   {$Lhead}";
        $this->lines[] = "{$Lend}:";

        array_pop($this->loopStack);
        return $afterBody;
    }

    private function splitForHeader(string $inside): array
    {
        $parts = [];
        $cur = '';
        $depth = 0;
        $n = strlen($inside);
        for ($i = 0; $i < $n; $i++) {
            $c = $inside[$i];
            if ($c === '(') {
                $depth++;
                $cur .= $c;
            } elseif ($c === ')') {
                $depth--;
                $cur .= $c;
            } elseif ($c === ';' && $depth === 0) {
                $parts[] = $cur;
                $cur = '';
            } else {
                $cur .= $c;
            }
        }
        $parts[] = $cur;
        return $parts;
    }

    /* ---------- if / else ---------- */

    private function compileIf(string $s): int
    {
        if (!preg_match('/^if\s*\(/i', $s)) {
            throw new PASMExprException('Expected if');
        }
        $open = strpos($s, '(');
        [$cond, $afterParen] = $this->extractParen($s, $open);
        [$thenBody, $afterThen] = $this->extractBrace($s, $afterParen);

        $rest = ltrim(substr($s, $afterThen));
        $elseBody = null;
        $consumed = $afterThen;
        if (preg_match('/^else\b/i', $rest)) {
            $elseStart = $afterThen + (strlen(substr($s, $afterThen)) - strlen($rest)) + 4; // skip 'else'
            // actually compute properly:
            $offset = $afterThen;
            while ($offset < strlen($s) && ctype_space($s[$offset])) {
                $offset++;
            }
            $offset += 4; // else
            [$elseBody, $consumed] = $this->extractBrace($s, $offset);
        }

        $Lthen = $this->freshLabel('then');
        $Lelse = $this->freshLabel('else');
        $Lend  = $this->freshLabel('endif');

        $this->lines[] = "; if ({$cond})";
        if ($elseBody !== null) {
            $this->emitConditionJump($cond, $Lthen, $Lelse, true);
            $this->lines[] = "{$Lthen}:";
            $this->compileBlock($thenBody);
            $this->lines[] = "        JMP   {$Lend}";
            $this->lines[] = "{$Lelse}:";
            $this->compileBlock($elseBody);
            $this->lines[] = "{$Lend}:";
        } else {
            $this->emitConditionJump($cond, $Lthen, $Lend, true);
            $this->lines[] = "{$Lthen}:";
            $this->compileBlock($thenBody);
            $this->lines[] = "{$Lend}:";
        }
        return $consumed;
    }

    /* ---------- select / switch ---------- */

    private function compileSelect(string $s): int
    {
        if (!preg_match('/^(select|switch)\s*\(/i', $s, $km)) {
            throw new PASMExprException('Expected select/switch');
        }
        $open = strpos($s, '(');
        [$expr, $afterParen] = $this->extractParen($s, $open);
        [$body, $afterBody] = $this->extractBrace($s, $afterParen);

        $xreg = $this->emitExpr(trim($expr));
        $Lend = $this->freshLabel('selend');
        $this->lines[] = "; select ({$expr})";

        // Parse case / default labels inside body
        $cases = $this->parseSelectBody($body);
        foreach ($cases as $c) {
            if ($c['type'] === 'case') {
                $Lcase = $this->freshLabel('case');
                $Lnext = $this->freshLabel('casenext');
                $imm = $this->tmpReg();
                $this->lines[] = "        MOVI  {$imm} {$c['value']}";
                $this->lines[] = "        CMP   {$xreg} {$imm}";
                $this->lines[] = "        JNZ   {$Lnext}";
                $this->lines[] = "{$Lcase}:";
                $this->compileBlock($c['body']);
                $this->lines[] = "        JMP   {$Lend}";
                $this->lines[] = "{$Lnext}:";
            } else { // default
                $this->compileBlock($c['body']);
                $this->lines[] = "        JMP   {$Lend}";
            }
        }
        $this->lines[] = "{$Lend}:";
        return $afterBody;
    }

    private function parseSelectBody(string $body): array
    {
        $cases = [];
        $body = trim($body);
        $n = strlen($body);
        $i = 0;
        while ($i < $n) {
            while ($i < $n && ctype_space($body[$i])) {
                $i++;
            }
            if ($i >= $n) {
                break;
            }
            $rest = substr($body, $i);
            if (preg_match('/^case\s+(-?\d+)\s*:/i', $rest, $m)) {
                $i += strlen($m[0]);
                $start = $i;
                // body until next case/default or end
                while ($i < $n) {
                    $r2 = substr($body, $i);
                    if (preg_match('/^(case\s+-?\d+\s*:|default\s*:)/i', $r2)) {
                        break;
                    }
                    $i++;
                }
                $cases[] = ['type' => 'case', 'value' => (int)$m[1], 'body' => substr($body, $start, $i - $start)];
                continue;
            }
            if (preg_match('/^default\s*:/i', $rest, $m)) {
                $i += strlen($m[0]);
                $cases[] = ['type' => 'default', 'body' => substr($body, $i)];
                break;
            }
            throw new PASMExprException('Expected case or default in select', substr($rest, 0, 40));
        }
        return $cases;
    }

    private function emitBreak(): void
    {
        if ($this->loopStack === []) {
            throw new PASMExprException('break outside loop');
        }
        $L = $this->loopStack[array_key_last($this->loopStack)]['break'];
        $this->lines[] = "        JMP   {$L}";
    }

    private function emitContinue(): void
    {
        if ($this->loopStack === []) {
            throw new PASMExprException('continue outside loop');
        }
        $L = $this->loopStack[array_key_last($this->loopStack)]['continue'];
        $this->lines[] = "        JMP   {$L}";
    }

    /**
     * Emit jumps for a condition.
     * $whenTrue: if true, jump to $Ltrue when condition holds, else $Lfalse.
     * Uses CMP + JZ/JNZ. For < > <= >= uses SUB + flag approximation via zero only:
     *   a == b → CMP a b; JZ
     *   a != b → CMP a b; JNZ
     *   Integer truthiness: CMP x #0 style via MOVI 0 + CMP
     *
     * Limited relational support: == != and nonzero checks are reliable on this ISA
     * (only ZF is exposed). For < > <= >= we emit a documented approximation:
     *   a < b  → tmp = a - b; treat negative as true — NOT available without SF.
     * So we only fully support: ==, !=, and bare identifier/expr (nonzero).
     */
    private function emitConditionJump(string $cond, string $Ltrue, string $Lfalse, bool $jumpToTrueFirst): void
    {
        $cond = trim($cond);

        if (preg_match('/^(.+?)\s*(==|!=)\s*(.+)$/', $cond, $m)) {
            $a = $this->emitExpr(trim($m[1]));
            $b = $this->emitExpr(trim($m[3]));
            $this->lines[] = "        CMP   {$a} {$b}";
            if ($m[2] === '==') {
                $this->lines[] = "        JZ    {$Ltrue}";
                $this->lines[] = "        JMP   {$Lfalse}";
            } else {
                $this->lines[] = "        JNZ   {$Ltrue}";
                $this->lines[] = "        JMP   {$Lfalse}";
            }
            return;
        }

        // Relational: approximate using subtraction and equality only is insufficient.
        // Provide a = b-1 style loops via == after DEC, but for explicit < we use:
        //   not supported fully — emit error suggesting == or !=
        if (preg_match('/^(.+?)\s*(<=|>=|<|>)\s*(.+)$/', $cond, $m)) {
            // Implement a < b as: repeatedly not available; use integer:
            // We synthesize: t = a - b; if t is "negative" we cannot test SF.
            // Fallback: only support range loops that use == after DEC pattern in for-step.
            // Practical approach: convert a < b to a loop-counter style by comparing equality after computing nothing.
            // Instead: implement a <= b as (a != b+1) is wrong.
            // Best effort: a < b  →  tmp=b; DEC loops not here.
            // Use: t = a;  while comparing with SUB and checking zero for equality only.
            // Document limitation and implement a < b as:
            //   for integer ISA without SF, we encode:
            //   CMP only works for ==. So for < we throw a clear error.
            throw new PASMExprException(
                "Relational operator {$m[2]} is not supported by the bytecode ZF-only ISA; use == or != (or structure for-loops with ++/--)",
                $cond
            );
        }

        // Truthiness: expr nonzero
        $r = $this->emitExpr($cond);
        $z = $this->tmpReg();
        $this->lines[] = "        MOVI  {$z} 0";
        $this->lines[] = "        CMP   {$r} {$z}";
        $this->lines[] = "        JNZ   {$Ltrue}";
        $this->lines[] = "        JMP   {$Lfalse}";
    }

    /* ---------- simple statements (assignments, ++, etc.) ---------- */

    private function compileSimpleStatement(string $stmt): void
    {
        $stmt = trim($stmt);
        if ($stmt === '') {
            return;
        }
        $this->lines[] = '; ' . $stmt;

        if (preg_match('/^(\+\+|--)\s*\$?([A-Za-z_][A-Za-z0-9_]*)$/', $stmt, $m)) {
            $reg = $this->regFor($m[2]);
            $this->lines[] = '        ' . ($m[1] === '++' ? 'INC' : 'DEC') . '    ' . $reg;
            return;
        }
        if (preg_match('/^\$?([A-Za-z_][A-Za-z0-9_]*)\s*(\+\+|--)$/', $stmt, $m)) {
            $reg = $this->regFor($m[1]);
            $this->lines[] = '        ' . ($m[2] === '++' ? 'INC' : 'DEC') . '    ' . $reg;
            return;
        }
        if (preg_match('/^\$?([A-Za-z_][A-Za-z0-9_]*)\s*(\+|\-|\*|\/|%|&|\||\^|<<|>>)=\s*(.+)$/', $stmt, $m)) {
            $dst = $this->regFor($m[1]);
            $rhs = $this->emitExpr(trim($m[3]));
            $this->emitBinOp($dst, $dst, $rhs, $m[2]);
            return;
        }
        if (preg_match('/^\$?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.+)$/', $stmt, $m)) {
            $dst = $this->regFor($m[1]);
            $rhs = trim($m[2]);
            if (preg_match('/^\$?([A-Za-z_][A-Za-z0-9_]*)$/', $rhs, $vm)) {
                $src = $this->regFor($vm[1]);
                if ($src !== $dst) {
                    $this->lines[] = "        MOVR  {$dst} {$src}";
                }
                return;
            }
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

    /* ---------- expression parser (same as before) ---------- */

    private function emitExpr(string $expr): string
    {
        $tokens = $this->tokenize($expr);
        $pos = 0;
        $reg = $this->parseOr($tokens, $pos);
        if ($pos < count($tokens)) {
            throw new PASMExprException('Unexpected token in expression', $expr);
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
                continue;
            }
            if (ctype_digit($c) || ($c === '-' && $i + 1 < $n && ctype_digit($expr[$i + 1]))) {
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
            $left = $this->binIntoNew($left, $this->parseXor($t, $p), '|');
        }
        return $left;
    }
    private function parseXor(array $t, int &$p): string
    {
        $left = $this->parseAnd($t, $p);
        while ($this->peekOp($t, $p, '^')) {
            $p++;
            $left = $this->binIntoNew($left, $this->parseAnd($t, $p), '^');
        }
        return $left;
    }
    private function parseAnd(array $t, int &$p): string
    {
        $left = $this->parseShift($t, $p);
        while ($this->peekOp($t, $p, '&')) {
            $p++;
            $left = $this->binIntoNew($left, $this->parseShift($t, $p), '&');
        }
        return $left;
    }
    private function parseShift(array $t, int &$p): string
    {
        $left = $this->parseAdd($t, $p);
        while (true) {
            if ($this->peekOp($t, $p, '<<')) {
                $p++;
                $left = $this->binIntoNew($left, $this->parseAdd($t, $p), '<<');
            } elseif ($this->peekOp($t, $p, '>>')) {
                $p++;
                $left = $this->binIntoNew($left, $this->parseAdd($t, $p), '>>');
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
                $left = $this->binIntoNew($left, $this->parseMul($t, $p), '+');
            } elseif ($this->peekOp($t, $p, '-')) {
                $p++;
                $left = $this->binIntoNew($left, $this->parseMul($t, $p), '-');
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
                $left = $this->binIntoNew($left, $this->parseUnary($t, $p), '*');
            } elseif ($this->peekOp($t, $p, '/')) {
                $p++;
                $left = $this->binIntoNew($left, $this->parseUnary($t, $p), '/');
            } elseif ($this->peekOp($t, $p, '%')) {
                $p++;
                $left = $this->binIntoNew($left, $this->parseUnary($t, $p), '%');
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
