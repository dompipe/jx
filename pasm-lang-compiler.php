<?php declare(strict_types=1);
namespace pasm\lang;
/** Full Compiler implementation — see PASL_Language_Manual.md */
final class Compiler
{
    public const REGS = ['ecx', 'ah', 'adx', 'bdx', 'cdx', 'ddx', 'edx', 'rdx'];
    private bool $optimize;
    private bool $verbose;
    private array $intMap = [];
    private array $cxMap = [];
    private int $nextReg = 0;
    private array $lines = [];
    private int $labelSeq = 0;
    private array $loopStack = [];
    private array $complexVars = [];

    public function __construct(bool $optimize = true, bool $verbose = false)
    {
        $this->optimize = $optimize;
        $this->verbose = $verbose;
    }

    public function compile(string $source): string
    {
        $this->reset();
        $src = $this->stripComments($source);
        $src = $this->optimizeSource($src);
        $this->emit('; PASL compiler output');
        $this->compileBlock($src);
        $ret = $this->pickReturnReg();
        $this->emit("        RET    {$ret}");
        $asm = implode("\n", $this->lines);
        if ($this->verbose) fwrite(STDERR, $asm . "\n");
        return $asm;
    }

    public function compileToBytecode(string $source): string
    {
        $asm = $this->compile($source);
        $assembler = $this->optimize
            ? new \pasm\PASMOptimizingAssembler(true)
            : new \pasm\PASMAssembler();
        try {
            return $assembler->compile($asm);
        } catch (\Throwable $e) {
            throw new LangException('Assemble failed: ' . $e->getMessage(), 'assemble', null, $e);
        }
    }

    public function compileToFile(string $source, string $path): string
    {
        $code = $this->compileToBytecode($source);
        $flags = $this->optimize ? PbcFile::FLAG_OPTIMIZED : 0;
        PbcFile::write($path, $code, $flags);
        return $code;
    }

    public function varMap(): array
    {
        return ['int' => $this->intMap, 'complex' => $this->cxMap];
    }

    private function reset(): void
    {
        $this->intMap = []; $this->cxMap = []; $this->nextReg = 0;
        $this->lines = []; $this->labelSeq = 0; $this->loopStack = []; $this->complexVars = [];
    }

    private function emit(string $line): void { $this->lines[] = $line; }
    private function freshLabel(string $p): string { return $p . '_' . ($this->labelSeq++); }

    private function stripComments(string $s): string
    {
        $s = preg_replace('/\/\*.*?\*\//s', '', $s) ?? $s;
        $s = preg_replace('/\/\/.*$/m', '', $s) ?? $s;
        $s = preg_replace('/#.*$/m', '', $s) ?? $s;
        return $s;
    }

    private function optimizeSource(string $src): string
    {
        if (!$this->optimize) return $src;
        $src = preg_replace('/\$?([A-Za-z_]\w*)\s*=\s*\$?\1\s*;/', ';', $src) ?? $src;
        $src = preg_replace('/\$?[A-Za-z_]\w*\s*\+=\s*0\s*;/', ';', $src) ?? $src;
        $src = preg_replace('/\$?[A-Za-z_]\w*\s*-=\s*0\s*;/', ';', $src) ?? $src;
        $src = preg_replace('/\$?[A-Za-z_]\w*\s*\*=\s*1\s*;/', ';', $src) ?? $src;
        return $src;
    }

    private function pickReturnReg(): string
    {
        if ($this->cxMap !== []) {
            $last = array_key_last($this->cxMap);
            return $this->cxMap[$last]['re'];
        }
        if ($this->intMap !== []) return $this->intMap[array_key_last($this->intMap)];
        return 'ecx';
    }

    private function allocReg(string $hint = ''): string
    {
        if ($this->nextReg >= count(self::REGS)) {
            throw new LangException('Out of registers (max 8; complex uses 2 each)', 'regalloc');
        }
        return self::REGS[$this->nextReg++];
    }

    private function intReg(string $var): string
    {
        $v = $this->norm($var);
        if (isset($this->cxMap[$v])) throw new LangException("{$v} is complex", 'type');
        if (!isset($this->intMap[$v])) {
            $this->intMap[$v] = $this->allocReg($v);
            $this->emit("; int \${$v} → {$this->intMap[$v]}");
        }
        return $this->intMap[$v];
    }

    private function cxPair(string $var): array
    {
        $v = $this->norm($var);
        if (isset($this->intMap[$v])) throw new LangException("{$v} is int", 'type');
        if (!isset($this->cxMap[$v])) {
            $re = $this->allocReg($v . '_re');
            $im = $this->allocReg($v . '_im');
            $this->cxMap[$v] = ['re' => $re, 'im' => $im];
            $this->complexVars[$v] = true;
            $this->emit("; complex \${$v} → {$re}+{$im}i");
        }
        return $this->cxMap[$v];
    }

    private function norm(string $v): string
    {
        $v = ltrim(trim($v), '$');
        if (!preg_match('/^[A-Za-z_]\w*$/', $v)) throw new LangException("Bad name {$v}", 'parse');
        return strtolower($v);
    }

    private function tmp(): string
    {
        for ($i = count(self::REGS) - 1; $i >= 0; $i--) {
            $r = self::REGS[$i];
            if (!in_array($r, $this->intMap, true)) {
                $usedCx = false;
                foreach ($this->cxMap as $p) {
                    if ($p['re'] === $r || $p['im'] === $r) { $usedCx = true; break; }
                }
                if (!$usedCx) return $r;
            }
        }
        return 'rdx';
    }

    private function compileBlock(string $src): void
    {
        $src = trim($src); $i = 0; $n = strlen($src);
        while ($i < $n) {
            while ($i < $n && ctype_space($src[$i])) $i++;
            if ($i >= $n) break;
            $rest = substr($src, $i);
            if (preg_match('/^while\s*\(/i', $rest)) { $i += $this->compileWhile(substr($src, $i)); continue; }
            if (preg_match('/^for\s*\(/i', $rest)) { $i += $this->compileFor(substr($src, $i)); continue; }
            if (preg_match('/^foreach\s*\(/i', $rest)) throw new LangException('foreach not supported; use for', 'parse');
            if (preg_match('/^if\s*\(/i', $rest)) { $i += $this->compileIf(substr($src, $i)); continue; }
            if (preg_match('/^(select|switch)\s*\(/i', $rest)) { $i += $this->compileSelect(substr($src, $i)); continue; }
            if (preg_match('/^break\s*;?/i', $rest, $m)) { $this->emitBreak(); $i += strlen($m[0]); continue; }
            if (preg_match('/^continue\s*;?/i', $rest, $m)) { $this->emitContinue(); $i += strlen($m[0]); continue; }
            if (preg_match('/^complex\s+/i', $rest)) {
                $end = $this->findSemi($src, $i);
                $this->compileComplexDecl(trim(substr($src, $i, $end - $i)));
                $i = $end + 1; continue;
            }
            $end = $this->findSemi($src, $i);
            $stmt = rtrim(trim(substr($src, $i, $end - $i)), ';');
            if ($stmt !== '') $this->compileStmt($stmt);
            $i = min($n, $end + 1);
        }
    }

    private function findSemi(string $s, int $start): int
    {
        $n = strlen($s); $d = 0;
        for ($i = $start; $i < $n; $i++) {
            $c = $s[$i];
            if ($c === '(' || $c === '{') $d++;
            elseif ($c === ')' || $c === '}') $d = max(0, $d - 1);
            elseif ($c === ';' && $d === 0) return $i;
        }
        return $n;
    }

    private function extractParen(string $s, int $open): array
    {
        $n = strlen($s); $d = 0;
        for ($i = $open; $i < $n; $i++) {
            if ($s[$i] === '(') $d++;
            elseif ($s[$i] === ')') {
                $d--;
                if ($d === 0) return [substr($s, $open + 1, $i - $open - 1), $i + 1];
            }
        }
        throw new LangException('Unbalanced (', 'parse');
    }

    private function extractBrace(string $s, int $from): array
    {
        $n = strlen($s); $i = $from;
        while ($i < $n && ctype_space($s[$i])) $i++;
        if ($i >= $n || $s[$i] !== '{') {
            $end = $this->findSemi($s, $i);
            return [trim(substr($s, $i, $end - $i)), min($n, $end + 1)];
        }
        $d = 0;
        for ($j = $i; $j < $n; $j++) {
            if ($s[$j] === '{') $d++;
            elseif ($s[$j] === '}') {
                $d--;
                if ($d === 0) return [substr($s, $i + 1, $j - $i - 1), $j + 1];
            }
        }
        throw new LangException('Unbalanced {', 'parse');
    }

    private function compileComplexDecl(string $stmt): void
    {
        if (!preg_match('/^complex\s+\$?([A-Za-z_]\w*)\s*=\s*(.+)$/i', $stmt, $m)) {
            if (preg_match('/^complex\s+\$?([A-Za-z_]\w*)\s*$/i', $stmt, $m2)) { $this->cxPair($m2[1]); return; }
            throw new LangException('Bad complex decl', 'parse');
        }
        $pair = $this->cxPair($m[1]);
        $lit = Complex::parse(trim($m[2]));
        $this->emit("; complex {$m[1]} = {$lit}");
        $this->emit("        MOVI  {$pair['re']} {$lit->re}");
        $this->emit("        MOVI  {$pair['im']} {$lit->im}");
    }

    private function compileWhile(string $s): int
    {
        $open = strpos($s, '(');
        [$cond, $ap] = $this->extractParen($s, $open);
        [$body, $ab] = $this->extractBrace($s, $ap);
        $Lh = $this->freshLabel('while'); $Lb = $this->freshLabel('wbody'); $Le = $this->freshLabel('wend');
        $this->loopStack[] = ['break' => $Le, 'continue' => $Lh];
        $this->emit("; while ({$cond})");
        $this->emit("{$Lh}:");
        $this->condJump($cond, $Lb, $Le);
        $this->emit("{$Lb}:");
        $this->compileBlock($body);
        $this->emit("        JMP   {$Lh}");
        $this->emit("{$Le}:");
        array_pop($this->loopStack);
        return $ab;
    }

    private function compileFor(string $s): int
    {
        $open = strpos($s, '(');
        [$inside, $ap] = $this->extractParen($s, $open);
        $parts = $this->splitFor($inside);
        if (count($parts) !== 3) throw new LangException('for (init;cond;step)', 'parse');
        [$init, $cond, $step] = $parts;
        [$body, $ab] = $this->extractBrace($s, $ap);
        $Lh = $this->freshLabel('for'); $Lb = $this->freshLabel('fbody');
        $Ls = $this->freshLabel('fstep'); $Le = $this->freshLabel('fend');
        if (trim($init) !== '') $this->compileStmt(trim($init));
        $this->loopStack[] = ['break' => $Le, 'continue' => $Ls];
        $this->emit("{$Lh}:");
        if (trim($cond) === '') $this->emit("        JMP   {$Lb}");
        else $this->condJump(trim($cond), $Lb, $Le);
        $this->emit("{$Lb}:");
        $this->compileBlock($body);
        $this->emit("{$Ls}:");
        if (trim($step) !== '') $this->compileStmt(trim($step));
        $this->emit("        JMP   {$Lh}");
        $this->emit("{$Le}:");
        array_pop($this->loopStack);
        return $ab;
    }

    private function splitFor(string $in): array
    {
        $parts = []; $cur = ''; $d = 0; $n = strlen($in);
        for ($i = 0; $i < $n; $i++) {
            $c = $in[$i];
            if ($c === '(') { $d++; $cur .= $c; }
            elseif ($c === ')') { $d--; $cur .= $c; }
            elseif ($c === ';' && $d === 0) { $parts[] = $cur; $cur = ''; }
            else $cur .= $c;
        }
        $parts[] = $cur;
        return $parts;
    }

    private function compileIf(string $s): int
    {
        $open = strpos($s, '(');
        [$cond, $ap] = $this->extractParen($s, $open);
        [$then, $at] = $this->extractBrace($s, $ap);
        $consumed = $at; $else = null; $off = $at;
        while ($off < strlen($s) && ctype_space($s[$off])) $off++;
        if (preg_match('/^else\b/i', substr($s, $off))) {
            $off += 4;
            [$else, $consumed] = $this->extractBrace($s, $off);
        }
        $Lt = $this->freshLabel('then'); $Lf = $this->freshLabel('else'); $Le = $this->freshLabel('endif');
        if ($else !== null) {
            $this->condJump($cond, $Lt, $Lf);
            $this->emit("{$Lt}:"); $this->compileBlock($then);
            $this->emit("        JMP   {$Le}");
            $this->emit("{$Lf}:"); $this->compileBlock($else);
            $this->emit("{$Le}:");
        } else {
            $this->condJump($cond, $Lt, $Le);
            $this->emit("{$Lt}:"); $this->compileBlock($then);
            $this->emit("{$Le}:");
        }
        return $consumed;
    }

    private function compileSelect(string $s): int
    {
        $open = strpos($s, '(');
        [$expr, $ap] = $this->extractParen($s, $open);
        [$body, $ab] = $this->extractBrace($s, $ap);
        $x = $this->emitIntExpr(trim($expr));
        $Le = $this->freshLabel('selend');
        foreach ($this->parseCases($body) as $c) {
            if ($c['type'] === 'case') {
                $Ln = $this->freshLabel('casenext');
                $imm = $this->tmp();
                $this->emit("        MOVI  {$imm} {$c['value']}");
                $this->emit("        CMP   {$x} {$imm}");
                $this->emit("        JNZ   {$Ln}");
                $this->compileBlock($c['body']);
                $this->emit("        JMP   {$Le}");
                $this->emit("{$Ln}:");
            } else {
                $this->compileBlock($c['body']);
                $this->emit("        JMP   {$Le}");
            }
        }
        $this->emit("{$Le}:");
        return $ab;
    }

    private function parseCases(string $body): array
    {
        $cases = []; $body = trim($body); $n = strlen($body); $i = 0;
        while ($i < $n) {
            while ($i < $n && ctype_space($body[$i])) $i++;
            if ($i >= $n) break;
            $rest = substr($body, $i);
            if (preg_match('/^case\s+(-?\d+)\s*:/i', $rest, $m)) {
                $i += strlen($m[0]); $start = $i;
                while ($i < $n && !preg_match('/^(case\s+-?\d+\s*:|default\s*:)/i', substr($body, $i))) $i++;
                $cases[] = ['type' => 'case', 'value' => (int)$m[1], 'body' => substr($body, $start, $i - $start)];
                continue;
            }
            if (preg_match('/^default\s*:/i', $rest, $m)) {
                $i += strlen($m[0]);
                $cases[] = ['type' => 'default', 'body' => substr($body, $i)];
                break;
            }
            throw new LangException('Expected case/default', 'parse');
        }
        return $cases;
    }

    private function emitBreak(): void
    {
        if ($this->loopStack === []) throw new LangException('break outside loop', 'parse');
        $this->emit('        JMP   ' . $this->loopStack[array_key_last($this->loopStack)]['break']);
    }

    private function emitContinue(): void
    {
        if ($this->loopStack === []) throw new LangException('continue outside loop', 'parse');
        $this->emit('        JMP   ' . $this->loopStack[array_key_last($this->loopStack)]['continue']);
    }

    private function condJump(string $cond, string $Lt, string $Lf): void
    {
        $cond = trim($cond);
        if (preg_match('/^(.+?)\s*(==|!=)\s*(.+)$/', $cond, $m)) {
            $a = $this->emitIntExpr(trim($m[1]));
            $b = $this->emitIntExpr(trim($m[3]));
            $this->emit("        CMP   {$a} {$b}");
            $this->emit($m[2] === '==' ? "        JZ    {$Lt}" : "        JNZ   {$Lt}");
            $this->emit("        JMP   {$Lf}");
            return;
        }
        $r = $this->emitIntExpr($cond);
        $z = $this->tmp();
        $this->emit("        MOVI  {$z} 0");
        $this->emit("        CMP   {$r} {$z}");
        $this->emit("        JNZ   {$Lt}");
        $this->emit("        JMP   {$Lf}");
    }

    private function compileStmt(string $stmt): void
    {
        $stmt = trim($stmt);
        $this->emit('; ' . $stmt);
        if (preg_match('/^\$?([A-Za-z_]\w*)\s*=\s*(.+)$/', $stmt, $m)) {
            $name = $this->norm($m[1]); $rhs = trim($m[2]);
            if ($this->looksComplex($rhs) || isset($this->complexVars[$name]) || isset($this->cxMap[$name])) {
                $this->compileCxAssign($name, $rhs); return;
            }
        }
        if (preg_match('/^(\+\+|--)\$?([A-Za-z_]\w*)$/', $stmt, $m)) {
            $r = $this->intReg($m[2]);
            $this->emit('        ' . ($m[1] === '++' ? 'INC' : 'DEC') . "    {$r}"); return;
        }
        if (preg_match('/^\$?([A-Za-z_]\w*)(\+\+|--)$/', $stmt, $m)) {
            $r = $this->intReg($m[1]);
            $this->emit('        ' . ($m[2] === '++' ? 'INC' : 'DEC') . "    {$r}"); return;
        }
        if (preg_match('/^\$?([A-Za-z_]\w*)\s*(\+|\-|\*|\/|%|&|\||\^|<<|>>)=\s*(.+)$/', $stmt, $m)) {
            $dst = $this->intReg($m[1]);
            $rhs = $this->emitIntExpr(trim($m[3]));
            $this->binop($dst, $dst, $rhs, $m[2]); return;
        }
        if (preg_match('/^\$?([A-Za-z_]\w*)\s*=\s*(.+)$/', $stmt, $m)) {
            $dst = $this->intReg($m[1]); $rhs = trim($m[2]);
            if (preg_match('/^\$?([A-Za-z_]\w*)$/', $rhs, $vm) && !isset($this->cxMap[$this->norm($vm[1])])) {
                $src = $this->intReg($vm[1]);
                if ($src !== $dst) $this->emit("        MOVR  {$dst} {$src}");
                return;
            }
            if (preg_match('/^-?\d+$/', $rhs)) {
                $this->emit("        MOVI  {$dst} " . (int)$rhs); return;
            }
            if ($this->optimize && preg_match('/^\$?([A-Za-z_]\w*)\s*\*\s*2$/', $rhs, $sm)) {
                $s = $this->intReg($sm[1]);
                $this->emit("        ADD   {$dst} {$s} {$s}"); return;
            }
            if ($this->optimize && preg_match('/^\$?([A-Za-z_]\w*)\s*\+\s*0$/', $rhs, $sm)) {
                $s = $this->intReg($sm[1]);
                if ($s !== $dst) $this->emit("        MOVR  {$dst} {$s}");
                return;
            }
            $src = $this->emitIntExpr($rhs);
            if ($src !== $dst) $this->emit("        MOVR  {$dst} {$src}");
            return;
        }
        throw new LangException("Unsupported statement: {$stmt}", 'parse');
    }

    private function looksComplex(string $rhs): bool
    {
        if (preg_match('/\di\b/i', $rhs) || preg_match('/\bi\b/', $rhs)) return true;
        if (preg_match('/^\$?([A-Za-z_]\w*)$/', $rhs, $m) && isset($this->cxMap[$this->norm($m[1])])) return true;
        return false;
    }

    private function compileCxAssign(string $name, string $rhs): void
    {
        $pair = $this->cxPair($name);
        $rhs = trim($rhs);
        try {
            $lit = Complex::parse($rhs);
            $this->emit("        MOVI  {$pair['re']} {$lit->re}");
            $this->emit("        MOVI  {$pair['im']} {$lit->im}");
            return;
        } catch (LangException $e) {}
        if (preg_match('/^\$?([A-Za-z_]\w*)$/', $rhs, $m) && isset($this->cxMap[$this->norm($m[1])])) {
            $src = $this->cxMap[$this->norm($m[1])];
            $this->emit("        MOVR  {$pair['re']} {$src['re']}");
            $this->emit("        MOVR  {$pair['im']} {$src['im']}");
            return;
        }
        if (preg_match('/^\$?([A-Za-z_]\w*)\s*([+\-*])\s*\$?([A-Za-z_]\w*)$/', $rhs, $m)) {
            $a = $this->cxPair($m[1]); $b = $this->cxPair($m[3]);
            if ($m[2] === '+') {
                $this->emit("        ADD   {$pair['re']} {$a['re']} {$b['re']}");
                $this->emit("        ADD   {$pair['im']} {$a['im']} {$b['im']}");
            } elseif ($m[2] === '-') {
                $this->emit("        SUB   {$pair['re']} {$a['re']} {$b['re']}");
                $this->emit("        SUB   {$pair['im']} {$a['im']} {$b['im']}");
            } else {
                $t1 = $this->tmp(); $t2 = $this->tmp();
                $this->emit("        MUL   {$t1} {$a['re']} {$b['re']}");
                $this->emit("        MUL   {$t2} {$a['im']} {$b['im']}");
                $this->emit("        SUB   {$pair['re']} {$t1} {$t2}");
                $this->emit("        MUL   {$t1} {$a['re']} {$b['im']}");
                $this->emit("        MUL   {$t2} {$a['im']} {$b['re']}");
                $this->emit("        ADD   {$pair['im']} {$t1} {$t2}");
            }
            return;
        }
        throw new LangException("Unsupported complex RHS: {$rhs}", 'parse');
    }

    private function emitIntExpr(string $expr): string
    {
        $expr = trim($expr);
        if ($this->optimize && preg_match('/^-?\d+(\s*[+\-*\/]\s*-?\d+)+$/', $expr)) {
            try {
                $v = $this->evalConst($expr);
                $r = $this->tmp();
                $this->emit("        MOVI  {$r} {$v}");
                return $r;
            } catch (\Throwable $e) {}
        }
        $tokens = $this->tokenize($expr);
        $pos = 0;
        return $this->parseOr($tokens, $pos);
    }

    private function evalConst(string $expr): int
    {
        if (!preg_match('/^[\d\s+\-*\/()]+$/', $expr)) throw new LangException('not const', 'opt');
        $r = @eval('return (int)(' . $expr . ');');
        if (!is_int($r)) throw new LangException('const fold failed', 'opt');
        return $r;
    }

    private function tokenize(string $expr): array
    {
        $tokens = []; $i = 0; $n = strlen($expr);
        while ($i < $n) {
            $c = $expr[$i];
            if (ctype_space($c)) { $i++; continue; }
            if ($c === '$') { $i++; continue; }
            if (ctype_digit($c) || ($c === '-' && $i + 1 < $n && ctype_digit($expr[$i + 1]))) {
                $j = $i + ($c === '-' ? 1 : 0);
                while ($j < $n && ctype_digit($expr[$j])) $j++;
                $tokens[] = ['num', (int)substr($expr, $i, $j - $i)];
                $i = $j; continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                $j = $i + 1;
                while ($j < $n && (ctype_alnum($expr[$j]) || $expr[$j] === '_')) $j++;
                $tokens[] = ['id', substr($expr, $i, $j - $i)];
                $i = $j; continue;
            }
            if ($i + 1 < $n && in_array($expr[$i] . $expr[$i + 1], ['<<', '>>'], true)) {
                $tokens[] = ['op', $expr[$i] . $expr[$i + 1]]; $i += 2; continue;
            }
            if (str_contains('+-*/%&|^()', $c)) { $tokens[] = ['op', $c]; $i++; continue; }
            throw new LangException("Bad char '{$c}' in expr", 'parse');
        }
        return $tokens;
    }

    private function parseOr(array $t, int &$p): string
    {
        $l = $this->parseXor($t, $p);
        while ($this->peek($t, $p, '|')) { $p++; $l = $this->binNew($l, $this->parseXor($t, $p), '|'); }
        return $l;
    }
    private function parseXor(array $t, int &$p): string
    {
        $l = $this->parseAnd($t, $p);
        while ($this->peek($t, $p, '^')) { $p++; $l = $this->binNew($l, $this->parseAnd($t, $p), '^'); }
        return $l;
    }
    private function parseAnd(array $t, int &$p): string
    {
        $l = $this->parseShift($t, $p);
        while ($this->peek($t, $p, '&')) { $p++; $l = $this->binNew($l, $this->parseShift($t, $p), '&'); }
        return $l;
    }
    private function parseShift(array $t, int &$p): string
    {
        $l = $this->parseAdd($t, $p);
        while (true) {
            if ($this->peek($t, $p, '<<')) { $p++; $l = $this->binNew($l, $this->parseAdd($t, $p), '<<'); }
            elseif ($this->peek($t, $p, '>>')) { $p++; $l = $this->binNew($l, $this->parseAdd($t, $p), '>>'); }
            else break;
        }
        return $l;
    }
    private function parseAdd(array $t, int &$p): string
    {
        $l = $this->parseMul($t, $p);
        while (true) {
            if ($this->peek($t, $p, '+')) { $p++; $l = $this->binNew($l, $this->parseMul($t, $p), '+'); }
            elseif ($this->peek($t, $p, '-')) { $p++; $l = $this->binNew($l, $this->parseMul($t, $p), '-'); }
            else break;
        }
        return $l;
    }
    private function parseMul(array $t, int &$p): string
    {
        $l = $this->parseUnary($t, $p);
        while (true) {
            if ($this->peek($t, $p, '*')) { $p++; $l = $this->binNew($l, $this->parseUnary($t, $p), '*'); }
            elseif ($this->peek($t, $p, '/')) { $p++; $l = $this->binNew($l, $this->parseUnary($t, $p), '/'); }
            elseif ($this->peek($t, $p, '%')) { $p++; $l = $this->binNew($l, $this->parseUnary($t, $p), '%'); }
            else break;
        }
        return $l;
    }
    private function parseUnary(array $t, int &$p): string
    {
        if ($this->peek($t, $p, '-')) {
            $p++; $r = $this->parseUnary($t, $p); $d = $this->tmp();
            if ($d !== $r) $this->emit("        MOVR  {$d} {$r}");
            $this->emit("        NEG   {$d}"); return $d;
        }
        if ($this->peek($t, $p, '+')) { $p++; return $this->parseUnary($t, $p); }
        return $this->parsePrimary($t, $p);
    }
    private function parsePrimary(array $t, int &$p): string
    {
        if ($p >= count($t)) throw new LangException('Unexpected end of expr', 'parse');
        $tok = $t[$p];
        if ($tok[0] === 'num') { $p++; $d = $this->tmp(); $this->emit("        MOVI  {$d} {$tok[1]}"); return $d; }
        if ($tok[0] === 'id') { $p++; return $this->intReg($tok[1]); }
        if ($tok[0] === 'op' && $tok[1] === '(') {
            $p++; $r = $this->parseOr($t, $p);
            if (!$this->peek($t, $p, ')')) throw new LangException('Expected )', 'parse');
            $p++; return $r;
        }
        throw new LangException('Bad primary', 'parse');
    }

    private function peek(array $t, int $p, string $op): bool
    {
        return isset($t[$p]) && $t[$p][0] === 'op' && $t[$p][1] === $op;
    }

    private function binNew(string $a, string $b, string $op): string
    {
        $d = $this->tmp(); $this->binop($d, $a, $b, $op); return $d;
    }

    private function binop(string $d, string $a, string $b, string $op): void
    {
        $opc = match ($op) {
            '+', '+=' => 'ADD', '-', '-=' => 'SUB', '*', '*=' => 'MUL',
            '/', '/=' => 'DIV', '%', '%=' => 'MOD', '&', '&=' => 'AND',
            '|', '|=' => 'OR', '^', '^=' => 'XOR', '<<', '<<=' => 'SHL', '>>', '>>=' => 'SHR',
            default => throw new LangException("op {$op}", 'parse'),
        };
        $this->emit("        {$opc}   {$d} {$a} {$b}");
    }
}
