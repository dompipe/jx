<?php declare(strict_types=1);

namespace pasm;

require_once __DIR__ . '/pasm-oop-containers.php';

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use OutOfBoundsException;
use RuntimeException;
use Traversable;
use UnderflowException;

/** Shared PASM bridge for non-container data structures. */
trait PASMStructureBridge
{
    abstract public function toArray(): array;

    public function loadRegister(string $register = 'ecx'): static
    {
        if (!property_exists(PASM::class, $register)) {
            throw new OutOfBoundsException("Unknown PASM register: {$register}");
        }
        PASM::${$register} = $this->toArray();
        return $this;
    }

    public function loadPasmStack(bool $replace = false): static
    {
        if ($replace) {
            PASM::$stack = [];
        }
        foreach ($this->toArray() as $value) {
            PASM::$stack[] = $value;
        }
        PASM::$ST0 = PASM::$stack === [] ? null : PASM::$stack[array_key_last(PASM::$stack)];
        PASM::$sp = PASM::$ST0;
        return $this;
    }

    protected static function pasmResult(mixed $value): mixed
    {
        PASM::$rdx = $value;
        return PASM::$rdx;
    }

    protected static function pasmFlag(bool $value): bool
    {
        PASM::$cl = $value ? 1 : 0;
        return $value;
    }
}

/** Singly linked node. */
final class PASMSinglyNode
{
    public function __construct(
        public mixed $value,
        public ?PASMSinglyNode $next = null,
    ) {}
}

/** Singly linked list with O(1) append/prepend and queue-like access. */
class PASMSinglyLinkedList implements Countable, IteratorAggregate, JsonSerializable
{
    use PASMStructureBridge;

    private ?PASMSinglyNode $head = null;
    private ?PASMSinglyNode $tail = null;
    private int $size = 0;

    public function __construct(iterable $values = [])
    {
        foreach ($values as $value) {
            $this->append($value);
        }
    }

    public function append(mixed $value): static
    {
        $node = new PASMSinglyNode($value);
        if ($this->tail === null) {
            $this->head = $this->tail = $node;
        } else {
            $this->tail->next = $node;
            $this->tail = $node;
        }
        $this->size++;
        PASM::$ecx = $value;
        return $this;
    }

    public function prepend(mixed $value): static
    {
        $node = new PASMSinglyNode($value, $this->head);
        $this->head = $node;
        if ($this->tail === null) {
            $this->tail = $node;
        }
        $this->size++;
        PASM::$ecx = $value;
        return $this;
    }

    public function shift(): mixed
    {
        if ($this->head === null) {
            throw new UnderflowException('Linked list is empty');
        }
        $value = $this->head->value;
        $this->head = $this->head->next;
        $this->size--;
        if ($this->head === null) {
            $this->tail = null;
        }
        return self::pasmResult($value);
    }

    public function first(): mixed
    {
        if ($this->head === null) {
            throw new UnderflowException('Linked list is empty');
        }
        return self::pasmResult($this->head->value);
    }

    public function last(): mixed
    {
        if ($this->tail === null) {
            throw new UnderflowException('Linked list is empty');
        }
        return self::pasmResult($this->tail->value);
    }

    public function contains(mixed $value, bool $strict = true): bool
    {
        for ($node = $this->head; $node !== null; $node = $node->next) {
            if ($strict ? $node->value === $value : $node->value == $value) {
                return self::pasmFlag(true);
            }
        }
        return self::pasmFlag(false);
    }

    public function removeFirst(mixed $value, bool $strict = true): bool
    {
        $prev = null;
        $node = $this->head;
        while ($node !== null) {
            $match = $strict ? $node->value === $value : $node->value == $value;
            if ($match) {
                if ($prev === null) {
                    $this->head = $node->next;
                } else {
                    $prev->next = $node->next;
                }
                if ($node === $this->tail) {
                    $this->tail = $prev;
                }
                $this->size--;
                if ($this->size === 0) {
                    $this->head = $this->tail = null;
                }
                return self::pasmFlag(true);
            }
            $prev = $node;
            $node = $node->next;
        }
        return self::pasmFlag(false);
    }

    public function clear(): static
    {
        $this->head = $this->tail = null;
        $this->size = 0;
        return $this;
    }

    public function count(): int { return $this->size; }
    public function isEmpty(): bool { return $this->size === 0; }

    public function toArray(): array
    {
        $out = [];
        for ($node = $this->head; $node !== null; $node = $node->next) {
            $out[] = $node->value;
        }
        return $out;
    }

    public function getIterator(): Traversable { return new ArrayIterator($this->toArray()); }
    public function jsonSerialize(): mixed { return $this->toArray(); }
}

/** Doubly linked node. */
final class PASMDoublyNode
{
    public function __construct(
        public mixed $value,
        public ?PASMDoublyNode $prev = null,
        public ?PASMDoublyNode $next = null,
    ) {}
}

/** Doubly linked list / deque with O(1) operations at both ends. */
class PASMDoublyLinkedList implements Countable, IteratorAggregate, JsonSerializable
{
    use PASMStructureBridge;

    private ?PASMDoublyNode $head = null;
    private ?PASMDoublyNode $tail = null;
    private int $size = 0;

    public function __construct(iterable $values = [])
    {
        foreach ($values as $value) {
            $this->pushBack($value);
        }
    }

    public function pushFront(mixed $value): static
    {
        $node = new PASMDoublyNode($value, null, $this->head);
        if ($this->head !== null) {
            $this->head->prev = $node;
        } else {
            $this->tail = $node;
        }
        $this->head = $node;
        $this->size++;
        PASM::$ecx = $value;
        return $this;
    }

    public function pushBack(mixed $value): static
    {
        $node = new PASMDoublyNode($value, $this->tail, null);
        if ($this->tail !== null) {
            $this->tail->next = $node;
        } else {
            $this->head = $node;
        }
        $this->tail = $node;
        $this->size++;
        PASM::$ecx = $value;
        return $this;
    }

    public function popFront(): mixed
    {
        if ($this->head === null) {
            throw new UnderflowException('Doubly linked list is empty');
        }
        $node = $this->head;
        $this->head = $node->next;
        if ($this->head !== null) {
            $this->head->prev = null;
        } else {
            $this->tail = null;
        }
        $this->size--;
        return self::pasmResult($node->value);
    }

    public function popBack(): mixed
    {
        if ($this->tail === null) {
            throw new UnderflowException('Doubly linked list is empty');
        }
        $node = $this->tail;
        $this->tail = $node->prev;
        if ($this->tail !== null) {
            $this->tail->next = null;
        } else {
            $this->head = null;
        }
        $this->size--;
        return self::pasmResult($node->value);
    }

    public function count(): int { return $this->size; }
    public function isEmpty(): bool { return $this->size === 0; }

    public function clear(): static
    {
        $this->head = $this->tail = null;
        $this->size = 0;
        return $this;
    }

    public function toArray(): array
    {
        $out = [];
        for ($node = $this->head; $node !== null; $node = $node->next) {
            $out[] = $node->value;
        }
        return $out;
    }

    public function reverseArray(): array
    {
        $out = [];
        for ($node = $this->tail; $node !== null; $node = $node->prev) {
            $out[] = $node->value;
        }
        return $out;
    }

    public function getIterator(): Traversable { return new ArrayIterator($this->toArray()); }
    public function jsonSerialize(): mixed { return $this->toArray(); }
}

/** Binary search tree node. */
final class PASMTreeNode
{
    public function __construct(
        public mixed $key,
        public mixed $value,
        public ?PASMTreeNode $left = null,
        public ?PASMTreeNode $right = null,
    ) {}
}

/** Binary search tree with injectable comparator. */
class PASMBinarySearchTree implements Countable, IteratorAggregate, JsonSerializable
{
    use PASMStructureBridge;

    private ?PASMTreeNode $root = null;
    private int $size = 0;
    /** @var callable */
    private $compare;

    public function __construct(?callable $compare = null)
    {
        $this->compare = $compare ?? static fn(mixed $a, mixed $b): int => $a <=> $b;
    }

    public function put(mixed $key, mixed $value = null): static
    {
        $value ??= $key;
        if ($this->root === null) {
            $this->root = new PASMTreeNode($key, $value);
            $this->size = 1;
            PASM::$ecx = $value;
            return $this;
        }

        $node = $this->root;
        while (true) {
            $cmp = ($this->compare)($key, $node->key);
            if ($cmp === 0) {
                $node->value = $value;
                PASM::$ecx = $value;
                return $this;
            }
            if ($cmp < 0) {
                if ($node->left === null) {
                    $node->left = new PASMTreeNode($key, $value);
                    break;
                }
                $node = $node->left;
            } else {
                if ($node->right === null) {
                    $node->right = new PASMTreeNode($key, $value);
                    break;
                }
                $node = $node->right;
            }
        }
        $this->size++;
        PASM::$ecx = $value;
        return $this;
    }

    public function has(mixed $key): bool
    {
        return self::pasmFlag($this->findNode($key) !== null);
    }

    public function get(mixed $key): mixed
    {
        $node = $this->findNode($key);
        if ($node === null) {
            throw new OutOfBoundsException('Tree key not found');
        }
        return self::pasmResult($node->value);
    }

    private function findNode(mixed $key): ?PASMTreeNode
    {
        $node = $this->root;
        while ($node !== null) {
            $cmp = ($this->compare)($key, $node->key);
            if ($cmp === 0) return $node;
            $node = $cmp < 0 ? $node->left : $node->right;
        }
        return null;
    }

    public function min(): mixed
    {
        if ($this->root === null) throw new UnderflowException('Tree is empty');
        $node = $this->root;
        while ($node->left !== null) $node = $node->left;
        return self::pasmResult($node->value);
    }

    public function max(): mixed
    {
        if ($this->root === null) throw new UnderflowException('Tree is empty');
        $node = $this->root;
        while ($node->right !== null) $node = $node->right;
        return self::pasmResult($node->value);
    }

    public function count(): int { return $this->size; }
    public function isEmpty(): bool { return $this->size === 0; }

    public function toArray(): array
    {
        $out = [];
        $stack = [];
        $node = $this->root;
        while ($node !== null || $stack !== []) {
            while ($node !== null) {
                $stack[] = $node;
                $node = $node->left;
            }
            /** @var PASMTreeNode $node */
            $node = array_pop($stack);
            $out[$this->normalizeKey($node->key)] = $node->value;
            $node = $node->right;
        }
        return $out;
    }

    private function normalizeKey(mixed $key): int|string
    {
        if (is_int($key) || is_string($key)) return $key;
        return serialize($key);
    }

    public function getIterator(): Traversable { return new ArrayIterator($this->toArray()); }
    public function jsonSerialize(): mixed { return $this->toArray(); }
}

/** Binary heap. Comparator returns true when first argument has higher priority. */
class PASMHeap implements Countable, IteratorAggregate, JsonSerializable
{
    use PASMStructureBridge;

    /** @var array<int,mixed> */
    private array $heap = [];
    /** @var callable */
    private $higherPriority;

    public function __construct(?callable $higherPriority = null, iterable $values = [])
    {
        $this->higherPriority = $higherPriority ?? static fn(mixed $a, mixed $b): bool => $a < $b;
        foreach ($values as $value) $this->push($value);
    }

    public static function minHeap(iterable $values = []): static
    {
        return new static(static fn(mixed $a, mixed $b): bool => $a < $b, $values);
    }

    public static function maxHeap(iterable $values = []): static
    {
        return new static(static fn(mixed $a, mixed $b): bool => $a > $b, $values);
    }

    public function push(mixed $value): static
    {
        $this->heap[] = $value;
        $i = count($this->heap) - 1;
        while ($i > 0) {
            $parent = intdiv($i - 1, 2);
            if (!(($this->higherPriority)($this->heap[$i], $this->heap[$parent]))) break;
            [$this->heap[$i], $this->heap[$parent]] = [$this->heap[$parent], $this->heap[$i]];
            $i = $parent;
        }
        PASM::$ecx = $value;
        return $this;
    }

    public function peek(): mixed
    {
        if ($this->heap === []) throw new UnderflowException('Heap is empty');
        return self::pasmResult($this->heap[0]);
    }

    public function pop(): mixed
    {
        if ($this->heap === []) throw new UnderflowException('Heap is empty');
        $root = $this->heap[0];
        $last = array_pop($this->heap);
        if ($this->heap !== []) {
            $this->heap[0] = $last;
            $n = count($this->heap);
            $i = 0;
            while (true) {
                $left = 2 * $i + 1;
                $right = $left + 1;
                $best = $i;
                if ($left < $n && ($this->higherPriority)($this->heap[$left], $this->heap[$best])) $best = $left;
                if ($right < $n && ($this->higherPriority)($this->heap[$right], $this->heap[$best])) $best = $right;
                if ($best === $i) break;
                [$this->heap[$i], $this->heap[$best]] = [$this->heap[$best], $this->heap[$i]];
                $i = $best;
            }
        }
        return self::pasmResult($root);
    }

    public function count(): int { return count($this->heap); }
    public function isEmpty(): bool { return $this->heap === []; }
    public function clear(): static { $this->heap = []; return $this; }
    public function toArray(): array { return $this->heap; }
    public function getIterator(): Traversable { return new ArrayIterator($this->heap); }
    public function jsonSerialize(): mixed { return $this->heap; }
}

/** Adjacency-list graph supporting directed or undirected edges. */
class PASMGraph implements Countable, IteratorAggregate, JsonSerializable
{
    use PASMStructureBridge;

    /** @var array<string,array{value:mixed,edges:array<string,float|int>}> */
    private array $nodes = [];

    public function addVertex(mixed $vertex): static
    {
        $id = $this->id($vertex);
        $this->nodes[$id] ??= ['value' => $vertex, 'edges' => []];
        PASM::$ecx = $vertex;
        return $this;
    }

    public function addEdge(mixed $from, mixed $to, int|float $weight = 1, bool $directed = false): static
    {
        $this->addVertex($from)->addVertex($to);
        $a = $this->id($from);
        $b = $this->id($to);
        $this->nodes[$a]['edges'][$b] = $weight;
        if (!$directed) $this->nodes[$b]['edges'][$a] = $weight;
        return $this;
    }

    public function hasVertex(mixed $vertex): bool
    {
        return self::pasmFlag(isset($this->nodes[$this->id($vertex)]));
    }

    public function neighbors(mixed $vertex): array
    {
        $id = $this->id($vertex);
        if (!isset($this->nodes[$id])) throw new OutOfBoundsException('Graph vertex not found');
        $out = [];
        foreach ($this->nodes[$id]['edges'] as $neighborId => $weight) {
            $out[] = ['vertex' => $this->nodes[$neighborId]['value'], 'weight' => $weight];
        }
        PASM::$rdx = $out;
        return $out;
    }

    /** Breadth-first traversal. */
    public function bfs(mixed $start): array
    {
        $startId = $this->id($start);
        if (!isset($this->nodes[$startId])) throw new OutOfBoundsException('Graph start vertex not found');
        $queue = [$startId];
        $head = 0;
        $seen = [$startId => true];
        $out = [];
        while (isset($queue[$head])) {
            $id = $queue[$head++];
            $out[] = $this->nodes[$id]['value'];
            foreach ($this->nodes[$id]['edges'] as $next => $_weight) {
                if (!isset($seen[$next])) {
                    $seen[$next] = true;
                    $queue[] = $next;
                }
            }
        }
        PASM::$rdx = $out;
        return $out;
    }

    /** Depth-first traversal. */
    public function dfs(mixed $start): array
    {
        $startId = $this->id($start);
        if (!isset($this->nodes[$startId])) throw new OutOfBoundsException('Graph start vertex not found');
        $stack = [$startId];
        $seen = [];
        $out = [];
        while ($stack !== []) {
            $id = array_pop($stack);
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $out[] = $this->nodes[$id]['value'];
            $neighbors = array_keys($this->nodes[$id]['edges']);
            for ($i = count($neighbors) - 1; $i >= 0; --$i) {
                if (!isset($seen[$neighbors[$i]])) $stack[] = $neighbors[$i];
            }
        }
        PASM::$rdx = $out;
        return $out;
    }

    public function count(): int { return count($this->nodes); }
    public function isEmpty(): bool { return $this->nodes === []; }
    public function clear(): static { $this->nodes = []; return $this; }

    public function toArray(): array
    {
        $out = [];
        foreach ($this->nodes as $id => $node) {
            $edges = [];
            foreach ($node['edges'] as $neighborId => $weight) {
                $edges[] = ['vertex' => $this->nodes[$neighborId]['value'], 'weight' => $weight];
            }
            $out[] = ['vertex' => $node['value'], 'edges' => $edges];
        }
        return $out;
    }

    private function id(mixed $value): string
    {
        return match (true) {
            is_object($value) => 'o:' . spl_object_id($value),
            is_resource($value) => 'r:' . get_resource_id($value),
            default => get_debug_type($value) . ':' . serialize($value),
        };
    }

    public function getIterator(): Traversable { return new ArrayIterator($this->toArray()); }
    public function jsonSerialize(): mixed { return $this->toArray(); }
}

/** Trie node for prefix/string lookup. */
final class PASMTrieNode
{
    /** @var array<string,PASMTrieNode> */
    public array $children = [];
    public bool $terminal = false;
    public mixed $value = null;
}

/** Prefix tree for string keys. */
class PASMTrie implements Countable, IteratorAggregate, JsonSerializable
{
    use PASMStructureBridge;

    private PASMTrieNode $root;
    private int $size = 0;

    public function __construct()
    {
        $this->root = new PASMTrieNode();
    }

    public function put(string $key, mixed $value = true): static
    {
        $node = $this->root;
        foreach (preg_split('//u', $key, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            $node->children[$char] ??= new PASMTrieNode();
            $node = $node->children[$char];
        }
        if (!$node->terminal) $this->size++;
        $node->terminal = true;
        $node->value = $value;
        PASM::$ecx = $value;
        return $this;
    }

    public function has(string $key): bool
    {
        $node = $this->findNode($key);
        return self::pasmFlag($node !== null && $node->terminal);
    }

    public function get(string $key): mixed
    {
        $node = $this->findNode($key);
        if ($node === null || !$node->terminal) throw new OutOfBoundsException('Trie key not found');
        return self::pasmResult($node->value);
    }

    /** @return array<string,mixed> */
    public function prefix(string $prefix): array
    {
        $node = $this->findNode($prefix);
        if ($node === null) return [];
        $out = [];
        $walk = function (PASMTrieNode $current, string $word) use (&$walk, &$out): void {
            if ($current->terminal) $out[$word] = $current->value;
            foreach ($current->children as $char => $child) $walk($child, $word . $char);
        };
        $walk($node, $prefix);
        PASM::$rdx = $out;
        return $out;
    }

    private function findNode(string $key): ?PASMTrieNode
    {
        $node = $this->root;
        foreach (preg_split('//u', $key, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
            if (!isset($node->children[$char])) return null;
            $node = $node->children[$char];
        }
        return $node;
    }

    public function count(): int { return $this->size; }
    public function isEmpty(): bool { return $this->size === 0; }
    public function clear(): static { $this->root = new PASMTrieNode(); $this->size = 0; return $this; }
    public function toArray(): array { return $this->prefix(''); }
    public function getIterator(): Traversable { return new ArrayIterator($this->toArray()); }
    public function jsonSerialize(): mixed { return $this->toArray(); }
}

/** Separate-chaining hash table, independent of PHP array key restrictions. */
class PASMHashTable implements Countable, IteratorAggregate, JsonSerializable
{
    use PASMStructureBridge;

    /** @var array<int,array<int,array{key:mixed,value:mixed}>> */
    private array $buckets;
    private int $size = 0;
    private int $capacity;

    public function __construct(int $capacity = 32)
    {
        if ($capacity < 4) throw new \InvalidArgumentException('Hash table capacity must be >= 4');
        $this->capacity = $capacity;
        $this->buckets = array_fill(0, $capacity, []);
    }

    public function put(mixed $key, mixed $value): static
    {
        if (($this->size + 1) / $this->capacity > 0.75) $this->resize($this->capacity * 2);
        $index = $this->bucketIndex($key);
        foreach ($this->buckets[$index] as &$entry) {
            if ($this->keysEqual($entry['key'], $key)) {
                $entry['value'] = $value;
                PASM::$ecx = $value;
                return $this;
            }
        }
        unset($entry);
        $this->buckets[$index][] = ['key' => $key, 'value' => $value];
        $this->size++;
        PASM::$ecx = $value;
        return $this;
    }

    public function get(mixed $key): mixed
    {
        $index = $this->bucketIndex($key);
        foreach ($this->buckets[$index] as $entry) {
            if ($this->keysEqual($entry['key'], $key)) return self::pasmResult($entry['value']);
        }
        throw new OutOfBoundsException('Hash key not found');
    }

    public function has(mixed $key): bool
    {
        $index = $this->bucketIndex($key);
        foreach ($this->buckets[$index] as $entry) {
            if ($this->keysEqual($entry['key'], $key)) return self::pasmFlag(true);
        }
        return self::pasmFlag(false);
    }

    public function remove(mixed $key): bool
    {
        $index = $this->bucketIndex($key);
        foreach ($this->buckets[$index] as $i => $entry) {
            if ($this->keysEqual($entry['key'], $key)) {
                array_splice($this->buckets[$index], $i, 1);
                $this->size--;
                return self::pasmFlag(true);
            }
        }
        return self::pasmFlag(false);
    }

    private function resize(int $newCapacity): void
    {
        $entries = $this->toArray();
        $this->capacity = $newCapacity;
        $this->buckets = array_fill(0, $newCapacity, []);
        $this->size = 0;
        foreach ($entries as $entry) $this->put($entry['key'], $entry['value']);
    }

    private function bucketIndex(mixed $key): int
    {
        $hash = crc32(serialize($key));
        return (int)(sprintf('%u', $hash) % $this->capacity);
    }

    private function keysEqual(mixed $a, mixed $b): bool
    {
        return is_object($a) && is_object($b) ? $a === $b : $a === $b;
    }

    public function count(): int { return $this->size; }
    public function isEmpty(): bool { return $this->size === 0; }
    public function clear(): static { $this->buckets = array_fill(0, $this->capacity, []); $this->size = 0; return $this; }

    /** @return array<int,array{key:mixed,value:mixed}> */
    public function toArray(): array
    {
        $out = [];
        foreach ($this->buckets as $bucket) foreach ($bucket as $entry) $out[] = $entry;
        return $out;
    }

    public function getIterator(): Traversable { return new ArrayIterator($this->toArray()); }
    public function jsonSerialize(): mixed { return $this->toArray(); }
}

/** Disjoint-set / union-find with path compression and union by rank. */
class PASMDisjointSet implements Countable, JsonSerializable
{
    use PASMStructureBridge;

    /** @var array<string,string> */
    private array $parent = [];
    /** @var array<string,int> */
    private array $rank = [];
    /** @var array<string,mixed> */
    private array $values = [];

    public function makeSet(mixed $value): static
    {
        $id = $this->id($value);
        if (!isset($this->parent[$id])) {
            $this->parent[$id] = $id;
            $this->rank[$id] = 0;
            $this->values[$id] = $value;
        }
        PASM::$ecx = $value;
        return $this;
    }

    private function findId(string $id): string
    {
        if (!isset($this->parent[$id])) throw new OutOfBoundsException('Value is not in disjoint set');
        if ($this->parent[$id] !== $id) $this->parent[$id] = $this->findId($this->parent[$id]);
        return $this->parent[$id];
    }

    public function find(mixed $value): mixed
    {
        $root = $this->findId($this->id($value));
        return self::pasmResult($this->values[$root]);
    }

    public function union(mixed $a, mixed $b): static
    {
        $this->makeSet($a)->makeSet($b);
        $ra = $this->findId($this->id($a));
        $rb = $this->findId($this->id($b));
        if ($ra === $rb) return $this;
        if ($this->rank[$ra] < $this->rank[$rb]) {
            $this->parent[$ra] = $rb;
        } elseif ($this->rank[$ra] > $this->rank[$rb]) {
            $this->parent[$rb] = $ra;
        } else {
            $this->parent[$rb] = $ra;
            $this->rank[$ra]++;
        }
        return $this;
    }

    public function connected(mixed $a, mixed $b): bool
    {
        $ia = $this->id($a);
        $ib = $this->id($b);
        if (!isset($this->parent[$ia], $this->parent[$ib])) return self::pasmFlag(false);
        return self::pasmFlag($this->findId($ia) === $this->findId($ib));
    }

    private function id(mixed $value): string
    {
        return match (true) {
            is_object($value) => 'o:' . spl_object_id($value),
            is_resource($value) => 'r:' . get_resource_id($value),
            default => get_debug_type($value) . ':' . serialize($value),
        };
    }

    public function count(): int { return count($this->parent); }
    public function isEmpty(): bool { return $this->parent === []; }
    public function clear(): static { $this->parent = $this->rank = $this->values = []; return $this; }

    public function toArray(): array
    {
        $groups = [];
        foreach ($this->values as $id => $value) {
            $root = $this->findId($id);
            $groups[$root][] = $value;
        }
        return array_values($groups);
    }

    public function jsonSerialize(): mixed { return $this->toArray(); }
}

// Conventional aliases.
class SinglyLinkedList extends PASMSinglyLinkedList {}
class DoublyLinkedList extends PASMDoublyLinkedList {}
class BinarySearchTree extends PASMBinarySearchTree {}
class BinaryHeap extends PASMHeap {}
class Graph extends PASMGraph {}
class Trie extends PASMTrie {}
class HashTable extends PASMHashTable {}
class DisjointSet extends PASMDisjointSet {}
