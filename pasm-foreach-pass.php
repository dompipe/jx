<?php declare(strict_types=1);

namespace pasm\lang;

use InvalidArgumentException;

/**
 * Collection-loop lowering pass.
 *
 * Surface:
 *   foreach ($items as $value) { ... }
 *   foreach ($items as $key => $value) { ... }
 *   reveach ($items as $value) { ... }
 *   reveach ($items as $key => $value) { ... }
 *
 * The pass deliberately reuses the already-tested bounded while compiler for
 * body/block semantics. After register allocation it replaces the synthetic
 * while condition with ITERF/ITERR <slot>. The iterator descriptor owns the
 * destination register(s), therefore the repeated bytecode remains exactly
 * two bytes: opcode + one u8 slot.
 */
final class PASMForeachPass
{
    /** @var array<string,true> */
    private array $collections = [];
    /** @var list<array<string,mixed>> */
    private array $plans = [];
    /** @var list<array{slot:int,collection:string,value_reg:int,key_reg:?int,reverse:bool}> */
    private array $bindings = [];
    private int $seq = 0;

    /** @param list<string> $collectionNames */
    public function __construct(array $collectionNames)
    {
        foreach ($collectionNames as $name) {
            $this->collections[$this->norm((string)$name)] = true;
        }
    }

    public function lower(string $source): string
    {
        $this->plans = [];
        $this->bindings = [];
        $this->seq = 0;
        return $this->lowerBlock($source);
    }

    /** @return list<array{slot:int,collection:string,value_reg:int,key_reg:?int,reverse:bool}> */
    public function bindings(): array { return $this->bindings; }

    /** Replace synthetic while checks with the compact iterator controller. */
    public function rewriteAsm(string $asm, array $varMap): string
    {
        $lines = preg_split('/\R/', $asm) ?: [];
        $ints = $varMap['int'] ?? [];
        $this->bindings = [];

        foreach ($this->plans as $plan) {
            $gateReg = $ints[$plan['gate']] ?? null;
            $valueReg = $ints[$plan['value']] ?? null;
            $keyReg = $plan['key'] === null ? null : ($ints[$plan['key']] ?? null);
            if (!is_string($gateReg) || !is_string($valueReg) || ($plan['key'] !== null && !is_string($keyReg))) {
                throw new LangException('Collection loop register allocation is incomplete', 'foreach-regalloc');
            }

            $found = false;
            for ($i = 0, $n = count($lines) - 3; $i < $n; $i++) {
                if (!preg_match('/^\s*MOVI\s+(\w+)\s+0\s*$/i', $lines[$i], $m0)) continue;
                $zeroReg = $m0[1];
                if (!preg_match('/^\s*CMP\s+' . preg_quote($gateReg, '/') . '\s+' . preg_quote($zeroReg, '/') . '\s*$/i', $lines[$i + 1])) continue;
                if (!preg_match('/^\s*JNZ\s+(\S+)\s*$/i', $lines[$i + 2], $mb)) continue;
                if (!preg_match('/^\s*JMP\s+(\S+)\s*$/i', $lines[$i + 3], $me)) continue;

                $op = $plan['reverse'] ? 'ITERR' : 'ITERF';
                array_splice($lines, $i, 4, [
                    '        ' . $op . '  ' . $plan['slot'],
                    '        JZ    ' . $me[1],
                    '        JMP   ' . $mb[1],
                ]);
                $found = true;
                break;
            }
            if (!$found) {
                throw new LangException('Could not locate collection-loop controller for ' . $plan['collection'], 'foreach-lower');
            }

            $this->bindings[] = [
                'slot' => $plan['slot'],
                'collection' => $plan['collection'],
                'value_reg' => \pasm\PASMBC::regId($valueReg),
                'key_reg' => $keyReg === null ? null : \pasm\PASMBC::regId($keyReg),
                'reverse' => $plan['reverse'],
            ];
        }

        return implode("\n", $lines);
    }

    private function lowerBlock(string $src): string
    {
        $out = '';
        $i = 0;
        $n = strlen($src);
        while ($i < $n) {
            $keyword = null;
            if ($this->wordAt($src, $i, PASMForeachSurface::FOREACH)) $keyword = PASMForeachSurface::FOREACH;
            elseif ($this->wordAt($src, $i, PASMForeachSurface::REVEACH)) $keyword = PASMForeachSurface::REVEACH;

            if ($keyword !== null) {
                $j = $this->skipWs($src, $i + strlen($keyword));
                if ($j >= $n || $src[$j] !== '(') throw new LangException("{$keyword} requires (...)", 'parse');
                [$header, $afterHeader] = $this->extractDelimited($src, $j, '(', ')');
                [$body, $afterBody] = $this->extractBody($src, $afterHeader);
                [$collection, $key, $value] = $this->parseHeader($header);

                if (!isset($this->collections[$collection])) {
                    throw new LangException("Unbound collection {$collection}; bind it on Engine before compiling {$keyword}", 'foreach-bind');
                }
                $slot = count($this->plans);
                if ($slot > 255) throw new LangException('More than 256 collection-loop sites require a wider iterator ABI', 'foreach-slots');
                $gate = '__jx_iter_gate_' . $this->seq++;
                $plan = [
                    'slot'=>$slot,
                    'collection'=>$collection,
                    'key'=>$key,
                    'value'=>$value,
                    'gate'=>$gate,
                    'reverse'=>PASMForeachSurface::reverse($keyword),
                ];
                $this->plans[] = $plan;

                // Allocate destinations once. The gate exists only so the normal
                // bounded while compiler owns the body, break/continue and depth.
                $out .= '$' . $value . " = 0;\n";
                if ($key !== null) $out .= '$' . $key . " = 0;\n";
                $out .= '$' . $gate . " = 1;\n";
                $out .= 'while ($' . $gate . ") {\n" . $this->lowerBlock($body) . "\n}\n";
                $i = $afterBody;
                continue;
            }

            if ($src[$i] === '"' || $src[$i] === "'") {
                $q = $src[$i]; $start = $i++;
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

    /** @return array{0:string,1:?string,2:string} */
    private function parseHeader(string $header): array
    {
        $h = trim($header);
        if (!preg_match('/^\$?([A-Za-z_]\w*)\s+as\s+(?:(?:\$?([A-Za-z_]\w*))\s*=>\s*)?\$?([A-Za-z_]\w*)$/i', $h, $m)) {
            throw new LangException('Expected collection loop header: $collection as [$key =>] $value', 'parse');
        }
        $collection = $this->norm($m[1]);
        $key = isset($m[2]) && $m[2] !== '' ? $this->norm($m[2]) : null;
        $value = $this->norm($m[3]);
        if ($key === $value) throw new LangException('Collection key and value variables must be distinct', 'parse');
        return [$collection, $key, $value];
    }

    /** @return array{0:string,1:int} */
    private function extractBody(string $src, int $from): array
    {
        $i = $this->skipWs($src, $from);
        if ($i >= strlen($src)) throw new LangException('Missing collection-loop body', 'parse');
        if ($src[$i] === '{') return $this->extractDelimited($src, $i, '{', '}');
        $semi = strpos($src, ';', $i);
        if ($semi === false) return [substr($src, $i), strlen($src)];
        return [substr($src, $i, $semi - $i), $semi + 1];
    }

    /** @return array{0:string,1:int} */
    private function extractDelimited(string $src, int $openAt, string $open, string $close): array
    {
        $depth = 0; $quote = null; $n = strlen($src);
        for ($i = $openAt; $i < $n; $i++) {
            $c = $src[$i];
            if ($quote !== null) {
                if ($c === '\\') { $i++; continue; }
                if ($c === $quote) $quote = null;
                continue;
            }
            if ($c === '"' || $c === "'") { $quote = $c; continue; }
            if ($c === $open) $depth++;
            elseif ($c === $close && --$depth === 0) return [substr($src, $openAt + 1, $i - $openAt - 1), $i + 1];
        }
        throw new LangException("Unbalanced {$open}", 'parse');
    }

    private function skipWs(string $src, int $i): int
    {
        $n = strlen($src); while ($i < $n && ctype_space($src[$i])) $i++; return $i;
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

    private function norm(string $name): string
    {
        $name = ltrim(trim($name), '$');
        if (!preg_match('/^[A-Za-z_]\w*$/', $name)) throw new InvalidArgumentException("Bad collection name {$name}");
        return strtolower($name);
    }
}
