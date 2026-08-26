<?php declare(strict_types=1);
/**
 * jx language front-end: interpret jx surface; lower pure arithmetic to PASL bytecode via Engine.
 */
namespace jx;

require_once __DIR__ . '/jx.php';
require_once __DIR__ . '/pasm-lang.php';

use pasm\lang\Engine;
use pasm\lang\LangException;

final class JxEngine
{
    private Engine $pasl;
    private SmartTable $table;
    /** @var array<string,mixed> */
    private array $vars = [];
    private ?Book $book = null;

    public function __construct(
        private bool $optimize = true,
        private bool $verbose = false,
    ) {
        $this->pasl = new Engine($optimize, $verbose);
        $this->table = Jx::table();
    }

    /** Compile+run jx source. Returns last expression result. */
    public function runSource(string $source): mixed
    {
        $source = $this->stripComments($source);
        return $this->execBlock($source);
    }

    public function runFile(string $path): mixed
    {
        $src = file_get_contents($path);
        if ($src === false) {
            throw new JxException("Cannot read {$path}", 'io');
        }
        return $this->runSource($src);
    }

    /** Emit PASL bytecode for a pure arithmetic fragment (no bags). */
    public function compilePaslFragment(string $fragment): string
    {
        return $this->pasl->compile($fragment);
    }

    public function compilePaslToFile(string $fragment, string $outPath): void
    {
        $this->pasl->compileFile($fragment, $outPath);
    }

    private function stripComments(string $s): string
    {
        $s = preg_replace('/\/\*.*?\*\//s', '', $s) ?? $s;
        $s = preg_replace('/\/\/.*$/m', '', $s) ?? $s;
        $s = preg_replace('/#.*$/m', '', $s) ?? $s;
        return $s;
    }

    private function execBlock(string $src): mixed
    {
        $src = trim($src);
        $i = 0;
        $n = strlen($src);
        $last = null;
        while ($i < $n) {
            while ($i < $n && ctype_space($src[$i])) {
                $i++;
            }
            if ($i >= $n) {
                break;
            }
            $end = $this->findStmtEnd($src, $i);
            $stmt = trim(substr($src, $i, $end - $i));
            $stmt = rtrim($stmt, ';');
            if ($stmt !== '') {
                $last = $this->execStmt($stmt);
            }
            $i = min($n, $end + 1);
        }
        return $last;
    }

    private function findStmtEnd(string $s, int $start): int
    {
        $n = strlen($s);
        $dParen = 0;
        $dBrace = 0;
        for ($i = $start; $i < $n; $i++) {
            $c = $s[$i];
            if ($c === '(') {
                $dParen++;
            } elseif ($c === ')') {
                $dParen = max(0, $dParen - 1);
            } elseif ($c === '{') {
                $dBrace++;
            } elseif ($c === '}') {
                $dBrace = max(0, $dBrace - 1);
            } elseif ($c === ';' && $dParen === 0 && $dBrace === 0) {
                return $i;
            }
        }
        return $n;
    }

    private function execStmt(string $stmt): mixed
    {
        $stmt = trim($stmt);
        if ($this->verbose) {
            fwrite(STDERR, "; jx: {$stmt}\n");
        }

        // book = Book.open("name") | Jx::book(...)
        if (preg_match('/^\$?(\w+)\s*=\s*Book\.(open|load)\s*\(\s*["\']([^"\']+)["\']\s*(?:,\s*(\d+))?\s*\)$/i', $stmt, $m)) {
            $this->book = Book::open($m[3], isset($m[4]) ? (int)$m[4] : 8_388_608);
            $this->vars[$m[1]] = $this->book;
            return $this->book;
        }

        // bag = Bag.underwrite(N) | Jx.bag(N)
        if (preg_match('/^\$?(\w+)\s*=\s*(?:Bag\.underwrite|Jx\.bag)\s*\(\s*(\d+)\s*\)$/i', $stmt, $m)) {
            $bag = Bag::underwrite((int)$m[2]);
            $this->vars[$m[1]] = $bag;
            if ($this->book) {
                try {
                    $this->book->registerBag($m[1], $bag);
                } catch (JxException $e) {
                    /* optional auto-register */
                }
            }
            $this->table->extrude('bag.underwrite');
            return $bag;
        }

        // task = Task.underwrite(N, "name")
        if (preg_match('/^\$?(\w+)\s*=\s*(?:Task\.underwrite|Jx\.task)\s*\(\s*(\d+)\s*(?:,\s*["\']([^"\']*)["\'])?\s*\)$/i', $stmt, $m)) {
            $task = Task::underwrite((int)$m[2], $m[3] !== '' && $m[3] !== null ? $m[3] : 'task');
            $this->vars[$m[1]] = $task;
            return $task;
        }

        // ref = bag.sign("node")
        if (preg_match('/^\$?(\w+)\s*=\s*\$?(\w+)\.sign\s*\(\s*["\']([^"\']*)["\']\s*\)$/i', $stmt, $m)) {
            $bag = $this->needBag($m[2]);
            $ref = $bag->sign($m[3]);
            $this->vars[$m[1]] = $ref;
            return $ref;
        }

        // bag.set(expr).commit(ref)  or  bag.set("str").commit(ref)
        if (preg_match('/^\$?(\w+)\.set\s*\(\s*(.+)\s*\)\s*\.commit\s*\(\s*\$?(\w+)\s*\)$/i', $stmt, $m)) {
            $bag = $this->needBag($m[1]);
            $ref = $this->needRef($m[3]);
            $data = $this->evalValue(trim($m[2]));
            $bag->set($data)->commit($ref);
            return $data;
        }

        // bag.tell(set, data).pass(ref)
        if (preg_match('/^\$?(\w+)\.tell\s*\(\s*set\s*,\s*(.+)\s*\)\s*\.pass\s*\(\s*\$?(\w+)\s*\)$/i', $stmt, $m)) {
            $bag = $this->needBag($m[1]);
            $ref = $this->needRef($m[3]);
            $data = $this->evalValue(trim($m[2]));
            $bag->tell('set', $data)->pass($ref);
            return $data;
        }

        // task.push("k", v)
        if (preg_match('/^\$?(\w+)\.push\s*\(\s*["\']([^"\']+)["\']\s*,\s*(.+)\s*\)$/i', $stmt, $m)) {
            $bag = $this->needBag($m[1]);
            $val = $this->evalValue(trim($m[3]));
            $bag->push($m[2], $val);
            return $val;
        }

        // x = bag.quotient() | capacity | used | id
        if (preg_match('/^\$?(\w+)\s*=\s*\$?(\w+)\.(quotient|capacity|used|id)\s*\(\s*\)$/i', $stmt, $m)) {
            $bag = $this->needBag($m[2]);
            $op = strtolower($m[3]);
            $v = match ($op) {
                'quotient' => $bag->quotient(),
                'capacity' => $bag->capacity(),
                'used' => $bag->used(),
                'id' => $bag->id(),
            };
            $this->vars[$m[1]] = $v;
            return $v;
        }

        // x = delivery(root, "a.b.c") or x = Jx.delivery(...)
        if (preg_match('/^\$?(\w+)\s*=\s*(?:delivery|Jx\.delivery)\s*\(\s*\$?(\w+)\s*,\s*["\']([^"\']+)["\']\s*(?:,\s*(.+))?\s*\)$/i', $stmt, $m)) {
            $root = $this->vars[$m[2]] ?? null;
            $default = isset($m[4]) ? $this->evalValue(trim($m[4])) : null;
            $v = Delivery::extract($root, $m[3], $default);
            $this->vars[$m[1]] = $v;
            return $v;
        }

        // complex x = 3+4i
        if (preg_match('/^complex\s+\$?(\w+)\s*=\s*(.+)$/i', $stmt, $m)) {
            $c = Complex::parse(trim($m[2]));
            $this->vars[$m[1]] = $c;
            return $c;
        }

        // const x = N
        if (preg_match('/^const\s+\$?(\w+)\s*=\s*(.+)$/i', $stmt, $m)) {
            $v = $this->evalValue(trim($m[2]));
            $this->vars[$m[1]] = jx_const($v);
            return $v;
        }

        // Pure arithmetic / PASL subset → executable compiler (bytecode VM)
        if ($this->isPaslLowerable($stmt)) {
            return $this->runPasl($stmt);
        }

        // Generic assignment of evaluated value
        if (preg_match('/^\$?(\w+)\s*=\s*(.+)$/s', $stmt, $m)) {
            $v = $this->evalValue(trim($m[2]));
            $this->vars[$m[1]] = $v;
            return $v;
        }

        // Bare expression
        return $this->evalValue($stmt);
    }

    private function isPaslLowerable(string $stmt): bool
    {
        // No bag/task/book/delivery/tell in pure path
        if (preg_match('/\b(Bag|Task|Book|Jx|delivery|tell|sign|commit|underwrite|push)\b/i', $stmt)) {
            return false;
        }
        // Looks like PASL assignment / loop / complex
        if (preg_match('/^(complex\s+|while\s*\(|for\s*\(|if\s*\(|\$?\w+\s*=)/i', $stmt)) {
            return true;
        }
        return false;
    }

    private function runPasl(string $stmt): mixed
    {
        // Ensure statement terminator for compiler
        $src = str_ends_with(trim($stmt), ';') ? $stmt : $stmt . ';';
        try {
            $result = $this->pasl->runSource($src);
            // Reflect simple $var = from PASL is not shared; return VM result only
            return $result;
        } catch (LangException $e) {
            throw new JxException('PASL lower failed: ' . $e->getMessage(), 'compile', true);
        }
    }

    private function evalValue(string $expr): mixed
    {
        $expr = trim($expr);
        if ($expr === '') {
            return null;
        }
        // string literal
        if (preg_match('/^["\']([^"\']*)["\']$/', $expr, $m)) {
            return $m[1];
        }
        // int
        if (preg_match('/^-?\d+$/', $expr)) {
            return (int)$expr;
        }
        // float
        if (preg_match('/^-?\d+\.\d+$/', $expr)) {
            return (float)$expr;
        }
        // complex literal
        if (preg_match('/i$/i', $expr) || preg_match('/^[+-]?\d+[+-]\d*i$/i', str_replace(' ', '', $expr))) {
            try {
                return Complex::parse($expr);
            } catch (JxException $e) {
                /* fall through */
            }
        }
        // variable
        if (preg_match('/^\$?(\w+)$/', $expr, $m)) {
            $name = $m[1];
            if (!array_key_exists($name, $this->vars)) {
                throw new JxException("Undefined variable {$name}", 'runtime', true);
            }
            $v = $this->vars[$name];
            return $v instanceof ConstBox ? $v->value : $v;
        }
        // bag.quotient() form as expression
        if (preg_match('/^\$?(\w+)\.(quotient|capacity|used|id)\s*\(\s*\)$/i', $expr, $m)) {
            $bag = $this->needBag($m[1]);
            return match (strtolower($m[2])) {
                'quotient' => $bag->quotient(),
                'capacity' => $bag->capacity(),
                'used' => $bag->used(),
                'id' => $bag->id(),
            };
        }
        // PASL arithmetic expression as resistant/native via compiler
        if (preg_match('/^[\w$\s+\-*\/%()]+$/', $expr) && !preg_match('/Bag|Task|Book/i', $expr)) {
            // Bind known int vars into a small PASL prelude
            $prelude = '';
            foreach ($this->vars as $k => $v) {
                if (is_int($v)) {
                    $prelude .= "\${$k} = {$v}; ";
                }
            }
            $assign = "\$__jx_r = ({$expr});";
            try {
                return $this->pasl->runSource($prelude . $assign);
            } catch (\Throwable $e) {
                throw new JxException('Expr lower failed: ' . $e->getMessage(), 'compile', true);
            }
        }
        throw new JxException("Cannot evaluate: {$expr}", 'runtime', true);
    }

    private function needBag(string $name): Bag
    {
        $b = $this->vars[$name] ?? null;
        if (!$b instanceof Bag) {
            throw new JxException("{$name} is not a Bag", 'type', true);
        }
        return $b;
    }

    private function needRef(string $name): RefSign
    {
        $r = $this->vars[$name] ?? null;
        if (!$r instanceof RefSign) {
            throw new JxException("{$name} is not a RefSign", 'type', true);
        }
        return $r;
    }
}
