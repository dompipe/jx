<?php declare(strict_types=1);

namespace pasm\lang;

/**
 * Surface-loop canonicalizer.
 *
 * It lowers richer loop rhetoric into the already-tested bounded `for`/`while`
 * compiler before register allocation. The generated variables are ordinary
 * compiler-visible state, so optimized/unoptimized execution remains identical.
 */
final class PASLSurfaceLoops
{
    private int $seq = 0;

    public static function lower(string $source): string
    {
        return (new self())->lowerBlock($source);
    }

    private function lowerBlock(string $src): string
    {
        $out = '';
        $i = 0;
        $n = strlen($src);

        while ($i < $n) {
            if ($this->wordAt($src, $i, 'do')) {
                [$body, $afterBody] = $this->extractBody($src, $i + 2);
                $j = $this->skipWs($src, $afterBody);
                if (!$this->wordAt($src, $j, 'while')) {
                    throw new LangException('do requires trailing while(condition)', 'parse');
                }
                $j += 5;
                $j = $this->skipWs($src, $j);
                if ($j >= $n || $src[$j] !== '(') throw new LangException('do while requires condition', 'parse');
                [$cond, $afterCond] = $this->extractDelimited($src, $j, '(', ')');
                $afterCond = $this->skipWs($src, $afterCond);
                if ($afterCond < $n && $src[$afterCond] === ';') $afterCond++;

                $id = $this->seq++;
                $gate = '__jx_do_' . $id;
                $loweredBody = $this->lowerBlock($body);
                $out .= "for (\${$gate}=1; \${$gate}!=0; \${$gate}=({$cond})) {\n{$loweredBody}\n}\n";
                $i = $afterCond;
                continue;
            }

            if ($this->wordAt($src, $i, 'repeat')) {
                $j = $this->skipWs($src, $i + 6);
                if ($j >= $n || $src[$j] !== '(') throw new LangException('repeat requires repeat(count)', 'parse');
                [$count, $afterCount] = $this->extractDelimited($src, $j, '(', ')');
                [$body, $afterBody] = $this->extractBody($src, $afterCount);
                $id = $this->seq++;
                $counter = '__jx_repeat_' . $id;
                $loweredBody = $this->lowerBlock($body);
                $out .= "for (\${$counter}=0; \${$counter}!=({$count}); \${$counter}++) {\n{$loweredBody}\n}\n";
                $i = $afterBody;
                continue;
            }

            // Preserve strings verbatim so keywords inside them are never lowered.
            if ($src[$i] === '"' || $src[$i] === "'") {
                $q = $src[$i];
                $start = $i++;
                while ($i < $n) {
                    if ($src[$i] === '\\') { $i += 2; continue; }
                    if ($src[$i] === $q) { $i++; break; }
                    $i++;
                }
                $out .= substr($src, $start, $i - $start);
                continue;
            }

            $out .= $src[$i++];
        }

        return $out;
    }

    /** @return array{0:string,1:int} body, offset-after-body */
    private function extractBody(string $src, int $from): array
    {
        $i = $this->skipWs($src, $from);
        if ($i >= strlen($src)) throw new LangException('Missing loop body', 'parse');
        if ($src[$i] === '{') return $this->extractDelimited($src, $i, '{', '}');

        $semi = $this->findStatementEnd($src, $i);
        return [substr($src, $i, $semi - $i), min(strlen($src), $semi + 1)];
    }

    /** @return array{0:string,1:int} inner, offset-after-close */
    private function extractDelimited(string $src, int $openAt, string $open, string $close): array
    {
        $depth = 0;
        $n = strlen($src);
        $quote = null;
        for ($i = $openAt; $i < $n; $i++) {
            $c = $src[$i];
            if ($quote !== null) {
                if ($c === '\\') { $i++; continue; }
                if ($c === $quote) $quote = null;
                continue;
            }
            if ($c === '"' || $c === "'") { $quote = $c; continue; }
            if ($c === $open) $depth++;
            elseif ($c === $close) {
                $depth--;
                if ($depth === 0) return [substr($src, $openAt + 1, $i - $openAt - 1), $i + 1];
            }
        }
        throw new LangException("Unbalanced {$open}", 'parse');
    }

    private function findStatementEnd(string $src, int $start): int
    {
        $paren = 0;
        $brace = 0;
        $quote = null;
        $n = strlen($src);
        for ($i = $start; $i < $n; $i++) {
            $c = $src[$i];
            if ($quote !== null) {
                if ($c === '\\') { $i++; continue; }
                if ($c === $quote) $quote = null;
                continue;
            }
            if ($c === '"' || $c === "'") { $quote = $c; continue; }
            if ($c === '(') $paren++;
            elseif ($c === ')') $paren--;
            elseif ($c === '{') $brace++;
            elseif ($c === '}') $brace--;
            elseif ($c === ';' && $paren === 0 && $brace === 0) return $i;
        }
        return $n;
    }

    private function skipWs(string $src, int $i): int
    {
        $n = strlen($src);
        while ($i < $n && ctype_space($src[$i])) $i++;
        return $i;
    }

    private function wordAt(string $src, int $i, string $word): bool
    {
        $len = strlen($word);
        if (strncasecmp(substr($src, $i, $len), $word, $len) !== 0) return false;
        $before = $i > 0 ? $src[$i - 1] : '';
        $after = $i + $len < strlen($src) ? $src[$i + $len] : '';
        if ($before !== '' && (ctype_alnum($before) || $before === '_' || $before === '$')) return false;
        if ($after !== '' && (ctype_alnum($after) || $after === '_')) return false;
        return true;
    }
}
