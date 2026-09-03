<?php declare(strict_types=1);

namespace jx\semantic;

use InvalidArgumentException;

/** Compile-time declaration extracted from canonical `bag Name { ... }` syntax. */
final readonly class PreparedBagDeclaration
{
    /** @param array<string,array{slot:int,type:string}> $fields */
    public function __construct(
        public string $name,
        public int $handle,
        public string $discipline,
        public ?string $elementType,
        public int $capacity,
        public int $width,
        public int $mask,
        public int $flags,
        public array $fields = [],
    ) {}

    public function metadata(): array
    {
        return [
            'name'=>$this->name,
            'handle'=>$this->handle,
            'discipline'=>$this->discipline,
            'of'=>$this->elementType,
            'capacity'=>$this->capacity,
            'width'=>$this->width,
            'mask'=>$this->mask,
            'flags'=>$this->flags,
            'fields'=>$this->fields,
        ];
    }
}

final readonly class PreparedBagSourceUnit
{
    /** @param array<string,PreparedBagDeclaration> $bags */
    public function __construct(
        public string $rewrittenSource,
        public array $bags,
    ) {}
}

/**
 * Recognizes canonical Bag discipline blocks without teaching the base parser a
 * second container grammar. The block is compile-time metadata; the rewritten
 * semantic source uses the parser's existing `bag Name;` declaration.
 */
final class PreparedBagSource
{
    private const DISCIPLINES = ['record','vector','stack','queue','deque','map','set'];
    private const RESERVED = ['type'=>true,'of'=>true,'capacity'=>true,'width'=>true,'handle'=>true,'mask'=>true,'flags'=>true];

    public static function prepare(string $source): PreparedBagSourceUnit
    {
        $blocks = self::findBlocks($source);
        if ($blocks === []) return new PreparedBagSourceUnit($source, []);

        $bags = [];
        $usedHandles = [];
        $nextHandle = 1;
        $out = '';
        $cursor = 0;

        foreach ($blocks as $block) {
            $name = strtolower($block['name']);
            if (isset($bags[$name])) {
                throw new SemanticException("Duplicate Bag declaration {$block['name']}", 'bag-source', $block['line']);
            }

            $spec = self::parseBody($block['body'], $block['line']);
            $handle = $spec['handle'];
            if ($handle === null) {
                while (isset($usedHandles[$nextHandle])) $nextHandle++;
                $handle = $nextHandle++;
            }
            if ($handle < 0) throw new SemanticException('Bag handle must be non-negative', 'bag-source', $block['line']);
            if (isset($usedHandles[$handle])) throw new SemanticException("Duplicate Bag handle {$handle}", 'bag-source', $block['line']);
            $usedHandles[$handle] = true;

            $capacity = $spec['capacity'];
            if ($spec['discipline'] === 'record' && $capacity === 0) $capacity = count($spec['fields']);

            $bag = new PreparedBagDeclaration(
                $name,
                $handle,
                $spec['discipline'],
                $spec['of'],
                $capacity,
                $spec['width'],
                $spec['mask'],
                $spec['flags'],
                $spec['fields'],
            );
            $bags[$name] = $bag;

            $out .= substr($source, $cursor, $block['start'] - $cursor);
            $original = substr($source, $block['start'], $block['end'] - $block['start']);
            // Keep source line numbers stable for all following semantic nodes.
            $out .= 'bag ' . $block['name'] . ';' . str_repeat("\n", substr_count($original, "\n"));
            $cursor = $block['end'];
        }
        $out .= substr($source, $cursor);

        return new PreparedBagSourceUnit($out, $bags);
    }

    /** @return array{discipline:string,of:?string,capacity:int,width:int,handle:?int,mask:int,flags:int,fields:array<string,array{slot:int,type:string}>} */
    private static function parseBody(string $body, int $line): array
    {
        $body = preg_replace('~/\*.*?\*/~s', '', $body) ?? $body;
        $parts = preg_split('/(?:\r?\n|;)+/', $body) ?: [];
        $raw = [];
        foreach ($parts as $part) {
            $part = preg_replace('~//.*$~', '', $part) ?? $part;
            $part = preg_replace('/#.*$/', '', $part) ?? $part;
            $part = trim($part, " \t\r\n,");
            if ($part === '') continue;
            if (!preg_match('/^([A-Za-z_]\w*)\s*:\s*([A-Za-z_]\w*|0[xX][0-9A-Fa-f]+|[0-9]+)\s*$/', $part, $m)) {
                throw new SemanticException("Invalid Bag property '{$part}'", 'bag-source', $line);
            }
            $key = strtolower($m[1]);
            if (array_key_exists($key, $raw)) throw new SemanticException("Duplicate Bag property {$key}", 'bag-source', $line);
            $raw[$key] = $m[2];
        }

        $discipline = strtolower((string)($raw['type'] ?? ''));
        if (!in_array($discipline, self::DISCIPLINES, true)) {
            throw new SemanticException('Bag type must be record, vector, stack, queue, deque, map, or set', 'bag-source', $line);
        }

        $fields = [];
        foreach ($raw as $key => $value) {
            if (isset(self::RESERVED[$key])) continue;
            if ($discipline !== 'record') {
                throw new SemanticException("Only record Bags may declare named field {$key}", 'bag-source', $line);
            }
            $fields[$key] = ['slot'=>count($fields), 'type'=>Type::canonical($value)];
        }

        $capacity = self::number($raw['capacity'] ?? null, 0, 'capacity', $line);
        $width = self::number($raw['width'] ?? null, 8, 'width', $line);
        $handle = array_key_exists('handle', $raw) ? self::number($raw['handle'], 0, 'handle', $line) : null;
        $mask = self::number($raw['mask'] ?? null, 0, 'mask', $line);
        $flags = self::number($raw['flags'] ?? null, 0, 'flags', $line);
        if ($width <= 0) throw new SemanticException('Bag width must be positive', 'bag-source', $line);

        return [
            'discipline'=>$discipline,
            'of'=>isset($raw['of']) ? Type::canonical($raw['of']) : null,
            'capacity'=>$capacity,
            'width'=>$width,
            'handle'=>$handle,
            'mask'=>$mask,
            'flags'=>$flags,
            'fields'=>$fields,
        ];
    }

    private static function number(?string $value, int $default, string $name, int $line): int
    {
        if ($value === null) return $default;
        $n = str_starts_with(strtolower($value), '0x') ? intval(substr($value, 2), 16) : intval($value, 10);
        if ($n < 0) throw new SemanticException("Bag {$name} must be non-negative", 'bag-source', $line);
        return $n;
    }

    /** @return list<array{name:string,start:int,end:int,body:string,line:int}> */
    private static function findBlocks(string $source): array
    {
        $blocks = [];
        $n = strlen($source);
        $i = 0;
        $line = 1;
        while ($i < $n) {
            $c = $source[$i];
            if ($c === "\n") { $line++; $i++; continue; }
            if ($c === '/' && $i + 1 < $n && $source[$i+1] === '/') {
                $i += 2; while ($i < $n && $source[$i] !== "\n") $i++; continue;
            }
            if ($c === '#') { while ($i < $n && $source[$i] !== "\n") $i++; continue; }
            if ($c === '/' && $i + 1 < $n && $source[$i+1] === '*') {
                $i += 2;
                while ($i + 1 < $n && !($source[$i] === '*' && $source[$i+1] === '/')) { if ($source[$i] === "\n") $line++; $i++; }
                $i = min($n, $i + 2); continue;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c; $i++;
                while ($i < $n) {
                    if ($source[$i] === "\n") $line++;
                    if ($source[$i] === '\\') { $i += 2; continue; }
                    if ($source[$i] === $quote) { $i++; break; }
                    $i++;
                }
                continue;
            }
            if (!(ctype_alpha($c) || $c === '_')) { $i++; continue; }

            $wordStart = $i++;
            while ($i < $n && (ctype_alnum($source[$i]) || $source[$i] === '_')) $i++;
            if (strtolower(substr($source, $wordStart, $i - $wordStart)) !== 'bag') continue;

            $j = $i;
            while ($j < $n && ctype_space($source[$j])) $j++;
            if ($j >= $n || !(ctype_alpha($source[$j]) || $source[$j] === '_')) continue;
            $nameStart = $j++;
            while ($j < $n && (ctype_alnum($source[$j]) || $source[$j] === '_')) $j++;
            $name = substr($source, $nameStart, $j - $nameStart);
            while ($j < $n && ctype_space($source[$j])) $j++;
            if ($j >= $n || $source[$j] !== '{') continue; // ordinary `bag x;`

            $open = $j;
            $end = self::closingBrace($source, $open);
            $blockLine = 1 + substr_count(substr($source, 0, $wordStart), "\n");
            $blocks[] = [
                'name'=>$name,
                'start'=>$wordStart,
                'end'=>$end + 1,
                'body'=>substr($source, $open + 1, $end - $open - 1),
                'line'=>$blockLine,
            ];
            $i = $end + 1;
            $line = 1 + substr_count(substr($source, 0, $i), "\n");
        }
        return $blocks;
    }

    private static function closingBrace(string $source, int $open): int
    {
        $n = strlen($source);
        $depth = 1;
        for ($i = $open + 1; $i < $n; $i++) {
            $c = $source[$i];
            if ($c === '/' && $i + 1 < $n && $source[$i+1] === '/') {
                $i += 2; while ($i < $n && $source[$i] !== "\n") $i++; continue;
            }
            if ($c === '#') { while ($i < $n && $source[$i] !== "\n") $i++; continue; }
            if ($c === '/' && $i + 1 < $n && $source[$i+1] === '*') {
                $i += 2; while ($i + 1 < $n && !($source[$i] === '*' && $source[$i+1] === '/')) $i++; $i++; continue;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c;
                for ($i++; $i < $n; $i++) {
                    if ($source[$i] === '\\') { $i++; continue; }
                    if ($source[$i] === $quote) break;
                }
                continue;
            }
            if ($c === '{') $depth++;
            elseif ($c === '}' && --$depth === 0) return $i;
        }
        throw new SemanticException('Unterminated Bag declaration block', 'bag-source');
    }
}

final readonly class PreparedContainerSourceCompilation
{
    /**
     * @param array<string,PreparedBagDeclaration> $bags
     * @param array<string,int> $registers
     * @param array<string,int> $constants
     * @param array<int,int> $initialRegisters
     */
    public function __construct(
        public string $jxl,
        public PreparedContainerBindings $bindings,
        public array $bags,
        public array $registers,
        public array $constants,
        public array $initialRegisters,
    ) {}

    /** Raw eight-qword little-endian register window for admission/startup. */
    public function registerBinary(): string
    {
        $out = '';
        for ($i = 0; $i < 8; $i++) $out .= self::u64le($this->initialRegisters[$i] ?? 0);
        return $out;
    }

    public function metadata(): array
    {
        return [
            'format'=>'jx.jxl-container-source/1',
            'target'=>'x86_64-sysv',
            'instruction_bytes'=>JxlContainerInstruction::BYTES,
            'code_bytes'=>strlen($this->jxl),
            'code_sha256'=>hash('sha256', $this->jxl),
            'bags'=>array_map(static fn(PreparedBagDeclaration $b): array => $b->metadata(), $this->bags),
            'register_window'=>[
                'count'=>8,
                'payload'=>'u64',
                'variables'=>$this->registers,
                'constants'=>$this->constants,
                'initial'=>$this->initialRegisters,
            ],
            'bindings'=>$this->bindings->metadata(),
        ];
    }

    public function json(): string
    {
        return json_encode($this->metadata(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    }

    private static function u64le(int $value): string
    {
        if ($value < 0) throw new InvalidArgumentException('JXL source register initializer must be non-negative in v1');
        return pack('V2', $value & 0xFFFFFFFF, ($value >> 32) & 0xFFFFFFFF);
    }
}

/** Canonical Bag/member-call AST -> prepared fixed-width container JXL. */
final class PreparedContainerSourceCompiler
{
    private string $code = '';
    /** @var array<string,int> */ private array $registers = [];
    /** @var array<string,int> */ private array $constants = [];
    /** @var array<int,int> */ private array $initial = [];
    /** @var array<int,true> */ private array $usedSelectors = [];
    private bool $emitted = false;

    public function __construct(private readonly PreparedContainerBindings $bindings) {}

    public function compile(PreparedBagSourceUnit $unit, Program $program): PreparedContainerSourceCompilation
    {
        if ($unit->bags === []) throw new SemanticException('No canonical Bag discipline declarations found', 'bag-source');
        $this->code=''; $this->registers=[]; $this->constants=[]; $this->initial=[]; $this->usedSelectors=[]; $this->emitted=false;
        foreach ($program->statements as $statement) $this->statement($statement, $unit->bags);
        return new PreparedContainerSourceCompilation($this->code, $this->bindings, $unit->bags, $this->registers, $this->constants, $this->initial);
    }

    /** @param array<string,PreparedBagDeclaration> $bags */
    private function statement(Node $n, array $bags): void
    {
        if ($n->op === 'block') { foreach ($n->data['statements'] as $s) $this->statement($s, $bags); return; }
        if ($n->op === 'decl' && $n->type === Type::BAG && isset($bags[$n->data['name']])) return;

        if ($n->op === 'decl') {
            $name = $n->data['name']; $init = $n->data['init'];
            if ($init === null) { $this->variableSelector($name); return; }
            if ($this->isBagCall($init, $bags)) { $this->lowerCall($init, $bags, $this->variableSelector($name)); return; }
            if ($this->isRecordRead($init, $bags)) { $this->lowerRecordRead($init, $bags, $this->variableSelector($name)); return; }
            if ($this->literalValue($init, $literal)) { $this->initializeVariable($name, $literal, $n->line); return; }
            throw new SemanticException('Prepared container source declarations accept literals or Bag reads/calls', 'jxl-container-source', $n->line);
        }

        if ($n->op === 'expr') {
            $e = $n->data['expr'];
            if ($this->isBagCall($e, $bags)) { $this->lowerCall($e, $bags, null); return; }
            if ($e->op === 'assign' && $e->data['operator'] === '=') {
                $target = $e->data['target']; $value = $e->data['value'];
                if ($target->op === 'var') {
                    $dst = $this->variableSelector($target->data['name']);
                    if ($this->isBagCall($value, $bags)) { $this->lowerCall($value, $bags, $dst); return; }
                    if ($this->isRecordRead($value, $bags)) { $this->lowerRecordRead($value, $bags, $dst); return; }
                    if ($this->literalValue($value, $literal)) { $this->initializeVariable($target->data['name'], $literal, $n->line); return; }
                }
                if ($target->op === 'member' && $this->isRecordRead($target, $bags)) {
                    $this->lowerRecordWrite($target, $value, $bags); return;
                }
            }
            throw new SemanticException('Prepared container source currently accepts Bag calls, Bag field access, and register initialization statements', 'jxl-container-source', $n->line);
        }

        throw new SemanticException("Prepared container source cannot yet lower {$n->op}; keep control flow outside the native container stream", 'jxl-container-source', $n->line);
    }

    /** @param array<string,PreparedBagDeclaration> $bags */
    private function lowerCall(Node $call, array $bags, ?int $dst): void
    {
        $member = $call->data['callee'];
        $bagName = strtolower($member->data['object']->data['name']);
        $bag = $bags[$bagName];
        $binding = $this->bindings->bind($bag->handle, $bag->discipline, $member->data['name'], $bag->width, $bag->capacity, $bag->mask, $bag->flags);

        $expected = JxlContainerOpcode::sourceCount($binding->opcode);
        if ($bag->discipline === 'set' && $binding->operation === 'EMPLACE') $expected = 1;
        $args = $call->data['args'];
        if (count($args) !== $expected) {
            throw new SemanticException("{$bagName}.{$member->data['name']} expects {$expected} argument(s)", 'jxl-container-source', $call->line);
        }
        if ($dst !== null && !JxlContainerOpcode::returnsResult($binding->opcode)) {
            throw new SemanticException("{$bagName}.{$member->data['name']} does not return a value", 'jxl-container-source', $call->line);
        }

        $src0 = $expected >= 1 ? $this->selectorFor($args[0], $call->line) : null;
        $src1 = $expected >= 2 ? $this->selectorFor($args[1], $call->line) : null;
        // EMPLACE has a two-source opcode shape globally. Set insertion only
        // needs the key; the native set target overwrites RDX with its sentinel.
        if ($bag->discipline === 'set' && $binding->operation === 'EMPLACE') $src1 = $src0;

        $this->code .= JxlContainerInstruction::emit($binding, $src0, $src1, $dst);
        $this->emitted = true;
    }

    /** @param array<string,PreparedBagDeclaration> $bags */
    private function lowerRecordRead(Node $member, array $bags, int $dst): void
    {
        [$bag, $field] = $this->recordField($member, $bags);
        $slot = $this->constantSelector($field['slot']);
        $binding = $this->bindings->bind($bag->handle, 'record', 'get', $bag->width, $bag->capacity, $bag->mask, $bag->flags);
        $this->code .= JxlContainerInstruction::emit($binding, $slot, null, $dst);
        $this->emitted = true;
    }

    /** @param array<string,PreparedBagDeclaration> $bags */
    private function lowerRecordWrite(Node $member, Node $value, array $bags): void
    {
        [$bag, $field] = $this->recordField($member, $bags);
        $slot = $this->constantSelector($field['slot']);
        $src = $this->selectorFor($value, $member->line);
        $binding = $this->bindings->bind($bag->handle, 'record', 'put', $bag->width, $bag->capacity, $bag->mask, $bag->flags);
        $this->code .= JxlContainerInstruction::emit($binding, $slot, $src, null);
        $this->emitted = true;
    }

    /** @param array<string,PreparedBagDeclaration> $bags */
    private function isBagCall(Node $n, array $bags): bool
    {
        if ($n->op !== 'call' || $n->data['callee']->op !== 'member') return false;
        $object = $n->data['callee']->data['object'];
        return $object->op === 'var' && isset($bags[strtolower($object->data['name'])]);
    }

    /** @param array<string,PreparedBagDeclaration> $bags */
    private function isRecordRead(Node $n, array $bags): bool
    {
        if ($n->op !== 'member' || $n->data['object']->op !== 'var') return false;
        $name = strtolower($n->data['object']->data['name']);
        return isset($bags[$name]) && $bags[$name]->discipline === 'record' && isset($bags[$name]->fields[strtolower($n->data['name'])]);
    }

    /** @param array<string,PreparedBagDeclaration> $bags @return array{0:PreparedBagDeclaration,1:array{slot:int,type:string}} */
    private function recordField(Node $member, array $bags): array
    {
        $name = strtolower($member->data['object']->data['name']);
        $bag = $bags[$name] ?? null;
        if (!$bag || $bag->discipline !== 'record') throw new SemanticException('Named Bag fields require a record Bag', 'jxl-container-source', $member->line);
        $fieldName = strtolower($member->data['name']);
        $field = $bag->fields[$fieldName] ?? null;
        if ($field === null) throw new SemanticException("Unknown record Bag field {$fieldName}", 'jxl-container-source', $member->line);
        return [$bag, $field];
    }

    private function selectorFor(Node $n, int $line): int
    {
        if ($n->op === 'group') return $this->selectorFor($n->data['expr'], $line);
        if ($n->op === 'var') return $this->variableSelector($n->data['name']);
        if ($this->literalValue($n, $value)) return $this->constantSelector($value);
        throw new SemanticException('Native container arguments must already be a variable or integer/bool/null literal', 'jxl-container-source', $line);
    }

    private function variableSelector(string $name): int
    {
        $name = strtolower($name);
        if (isset($this->registers[$name])) return $this->registers[$name];
        return $this->registers[$name] = $this->allocateSelector();
    }

    private function constantSelector(int $value): int
    {
        if ($value < 0) throw new SemanticException('Native JXL container v1 constants must be non-negative', 'jxl-container-source');
        $key = (string)$value;
        if (isset($this->constants[$key])) return $this->constants[$key];
        $selector = $this->allocateSelector();
        $this->constants[$key] = $selector;
        $this->initial[$selector] = $value;
        return $selector;
    }

    private function initializeVariable(string $name, int $value, int $line): void
    {
        if ($value < 0) throw new SemanticException('Native JXL container v1 register initializers must be non-negative', 'jxl-container-source', $line);
        if ($this->emitted) throw new SemanticException('Register literal assignment after container execution begins needs numeric/JXL register-op integration', 'jxl-container-source', $line);
        $this->initial[$this->variableSelector($name)] = $value;
    }

    private function allocateSelector(): int
    {
        for ($i = 0; $i < 8; $i++) {
            if (!isset($this->usedSelectors[$i])) { $this->usedSelectors[$i] = true; return $i; }
        }
        throw new SemanticException('Prepared container source exhausted the eight-register JXL window', 'jxl-container-source');
    }

    private function literalValue(Node $n, ?int &$value): bool
    {
        if ($n->op === 'group') return $this->literalValue($n->data['expr'], $value);
        if ($n->op !== 'literal') return false;
        $literal = $n->data['value'];
        if (is_bool($literal)) { $value = $literal ? 1 : 0; return true; }
        if ($literal === null) { $value = 0; return true; }
        if (is_int($literal)) { $value = $literal; return true; }
        return false;
    }
}
