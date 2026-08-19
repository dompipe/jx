<?php declare(strict_types=1);

namespace pasm;

require_once __DIR__ . '/pasm-double-digit.php';

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use OutOfBoundsException;
use Traversable;
use UnderflowException;

/**
 * Common OOP container contract for PASM.
 *
 * Containers stay ordinary PHP objects while exposing zero-friction bridges
 * to PASM registers and its machine stack.
 */
interface PASMContainerContract extends Countable, IteratorAggregate, JsonSerializable
{
    public function clear(): static;
    public function isEmpty(): bool;
    public function toArray(): array;
    public function loadRegister(string $register = 'ecx'): static;
    public function loadPasmStack(bool $replace = false): static;
}

abstract class PASMContainer implements PASMContainerContract
{
    /** @var array<mixed> */
    protected array $items = [];

    public function __construct(iterable $items = [])
    {
        foreach ($items as $key => $value) {
            $this->import($key, $value);
        }
    }

    protected function import(mixed $key, mixed $value): void
    {
        $this->items[] = $value;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function clear(): static
    {
        $this->items = [];
        return $this;
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function jsonSerialize(): mixed
    {
        return $this->items;
    }

    /**
     * Put the complete container payload into a PASM register.
     */
    public function loadRegister(string $register = 'ecx'): static
    {
        self::assertRegister($register);
        PASM::${$register} = $this->items;
        return $this;
    }

    /**
     * Load all container values onto PASM::$stack.
     */
    public function loadPasmStack(bool $replace = false): static
    {
        if ($replace) {
            PASM::$stack = [];
        }
        foreach ($this->items as $value) {
            PASM::$stack[] = $value;
        }
        PASM::$ST0 = PASM::$stack === [] ? null : PASM::$stack[array_key_last(PASM::$stack)];
        PASM::$sp = PASM::$ST0;
        return $this;
    }

    /**
     * Replace this container with an iterable stored in a PASM register.
     */
    public function fromRegister(string $register = 'ecx'): static
    {
        self::assertRegister($register);
        $value = PASM::${$register};
        if (!is_iterable($value)) {
            throw new \UnexpectedValueException("PASM register {$register} is not iterable");
        }
        $this->clear();
        foreach ($value as $key => $item) {
            $this->import($key, $item);
        }
        return $this;
    }

    /**
     * Replace this container with PASM's current machine stack.
     */
    public function fromPasmStack(): static
    {
        $this->clear();
        foreach (PASM::$stack as $key => $item) {
            $this->import($key, $item);
        }
        return $this;
    }

    /**
     * Move one value through PASM::$ecx without retaining it in the register.
     */
    protected static function throughECX(mixed $value): mixed
    {
        PASM::$ecx = $value;
        return PASM::$ecx;
    }

    protected static function assertRegister(string $register): void
    {
        if (!property_exists(PASM::class, $register)) {
            throw new OutOfBoundsException("Unknown PASM register: {$register}");
        }
    }
}

/** Ordered, indexed sequence. */
class PASMList extends PASMContainer implements ArrayAccess
{
    public function add(mixed $value): static
    {
        $this->items[] = self::throughECX($value);
        return $this;
    }

    public function insert(int $index, mixed $value): static
    {
        $size = count($this->items);
        if ($index < 0 || $index > $size) {
            throw new OutOfBoundsException("Index {$index} out of bounds");
        }
        array_splice($this->items, $index, 0, [self::throughECX($value)]);
        return $this;
    }

    public function get(int $index): mixed
    {
        if (!array_key_exists($index, $this->items)) {
            throw new OutOfBoundsException("Index {$index} out of bounds");
        }
        PASM::$rdx = $this->items[$index];
        return PASM::$rdx;
    }

    public function set(int $index, mixed $value): static
    {
        if (!array_key_exists($index, $this->items)) {
            throw new OutOfBoundsException("Index {$index} out of bounds");
        }
        $this->items[$index] = self::throughECX($value);
        return $this;
    }

    public function removeAt(int $index): mixed
    {
        if (!array_key_exists($index, $this->items)) {
            throw new OutOfBoundsException("Index {$index} out of bounds");
        }
        $removed = array_splice($this->items, $index, 1)[0];
        PASM::$rdx = $removed;
        return $removed;
    }

    public function first(): mixed
    {
        if ($this->items === []) {
            throw new UnderflowException('List is empty');
        }
        return $this->items[0];
    }

    public function last(): mixed
    {
        if ($this->items === []) {
            throw new UnderflowException('List is empty');
        }
        return $this->items[array_key_last($this->items)];
    }

    public function contains(mixed $value, bool $strict = true): bool
    {
        PASM::$cl = in_array($value, $this->items, $strict) ? 1 : 0;
        return PASM::$cl === 1;
    }

    public function offsetExists(mixed $offset): bool { return isset($this->items[$offset]) || array_key_exists($offset, $this->items); }
    public function offsetGet(mixed $offset): mixed { return $this->get((int)$offset); }
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->add($value);
            return;
        }
        $index = (int)$offset;
        if ($index === count($this->items)) {
            $this->add($value);
            return;
        }
        $this->set($index, $value);
    }
    public function offsetUnset(mixed $offset): void { $this->removeAt((int)$offset); }
}

/** LIFO container. */
class PASMStack extends PASMContainer
{
    public function push(mixed $value): static
    {
        $this->items[] = self::throughECX($value);
        PASM::$ST0 = $value;
        return $this;
    }

    public function pop(): mixed
    {
        if ($this->items === []) {
            throw new UnderflowException('Stack is empty');
        }
        $value = array_pop($this->items);
        PASM::$rdx = $value;
        PASM::$ST0 = $this->items === [] ? null : $this->items[array_key_last($this->items)];
        return $value;
    }

    public function peek(): mixed
    {
        if ($this->items === []) {
            throw new UnderflowException('Stack is empty');
        }
        $value = $this->items[array_key_last($this->items)];
        PASM::$ST0 = $value;
        return $value;
    }
}

/** FIFO container with O(1) amortized dequeue via a head pointer. */
class PASMQueue extends PASMContainer
{
    private int $head = 0;

    public function count(): int
    {
        return count($this->items) - $this->head;
    }

    public function isEmpty(): bool
    {
        return $this->head >= count($this->items);
    }

    public function clear(): static
    {
        $this->items = [];
        $this->head = 0;
        return $this;
    }

    public function enqueue(mixed $value): static
    {
        $this->items[] = self::throughECX($value);
        return $this;
    }

    public function dequeue(): mixed
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Queue is empty');
        }
        $value = $this->items[$this->head++];
        PASM::$rdx = $value;
        $this->compactIfNeeded();
        return $value;
    }

    public function peek(): mixed
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Queue is empty');
        }
        return $this->items[$this->head];
    }

    public function toArray(): array
    {
        return $this->head === 0 ? $this->items : array_slice($this->items, $this->head);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function loadRegister(string $register = 'ecx'): static
    {
        self::assertRegister($register);
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

    private function compactIfNeeded(): void
    {
        // Avoid array_shift(): compact only when dead prefix is substantial.
        if ($this->head >= 1024 && $this->head * 2 >= count($this->items)) {
            $this->items = array_slice($this->items, $this->head);
            $this->head = 0;
        }
    }
}

/** Double-ended queue. */
class PASMDeque extends PASMQueue
{
    public function pushBack(mixed $value): static
    {
        $this->enqueue($value);
        return $this;
    }

    public function popFront(): mixed
    {
        return $this->dequeue();
    }

    public function pushFront(mixed $value): static
    {
        $current = $this->toArray();
        $this->clear();
        $this->enqueue($value);
        foreach ($current as $item) {
            $this->enqueue($item);
        }
        return $this;
    }

    public function popBack(): mixed
    {
        $current = $this->toArray();
        if ($current === []) {
            throw new UnderflowException('Deque is empty');
        }
        $value = array_pop($current);
        $this->clear();
        foreach ($current as $item) {
            $this->enqueue($item);
        }
        PASM::$rdx = $value;
        return $value;
    }

    public function peekFront(): mixed { return $this->peek(); }

    public function peekBack(): mixed
    {
        $current = $this->toArray();
        if ($current === []) {
            throw new UnderflowException('Deque is empty');
        }
        return $current[array_key_last($current)];
    }
}

/** Key/value associative container. */
class PASMMap extends PASMContainer implements ArrayAccess
{
    protected function import(mixed $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    public function put(int|string $key, mixed $value): static
    {
        $this->items[$key] = self::throughECX($value);
        return $this;
    }

    public function get(int|string $key, mixed $default = null): mixed
    {
        $value = $this->items[$key] ?? $default;
        PASM::$rdx = $value;
        return $value;
    }

    public function has(int|string $key): bool
    {
        PASM::$cl = array_key_exists($key, $this->items) ? 1 : 0;
        return PASM::$cl === 1;
    }

    public function remove(int|string $key): mixed
    {
        if (!array_key_exists($key, $this->items)) {
            return null;
        }
        $value = $this->items[$key];
        unset($this->items[$key]);
        PASM::$rdx = $value;
        return $value;
    }

    public function keys(): array { return array_keys($this->items); }
    public function values(): array { return array_values($this->items); }

    public function offsetExists(mixed $offset): bool { return array_key_exists($offset, $this->items); }
    public function offsetGet(mixed $offset): mixed { return $this->get($offset); }
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            throw new \InvalidArgumentException('PASMMap requires an explicit key');
        }
        $this->put($offset, $value);
    }
    public function offsetUnset(mixed $offset): void { $this->remove($offset); }
}

/** Unique-value container. */
class PASMSet extends PASMContainer
{
    /** @var array<string,mixed> */
    private array $index = [];

    public function __construct(iterable $items = [])
    {
        $this->items = [];
        $this->index = [];
        foreach ($items as $value) {
            $this->add($value);
        }
    }

    protected function import(mixed $key, mixed $value): void
    {
        $this->add($value);
    }

    public function clear(): static
    {
        $this->items = [];
        $this->index = [];
        return $this;
    }

    public function add(mixed $value): static
    {
        $key = self::hashValue($value);
        if (!array_key_exists($key, $this->index)) {
            $this->index[$key] = $value;
            $this->items[] = self::throughECX($value);
        }
        return $this;
    }

    public function has(mixed $value): bool
    {
        PASM::$cl = array_key_exists(self::hashValue($value), $this->index) ? 1 : 0;
        return PASM::$cl === 1;
    }

    public function remove(mixed $value): bool
    {
        $key = self::hashValue($value);
        if (!array_key_exists($key, $this->index)) {
            PASM::$cl = 0;
            return false;
        }
        unset($this->index[$key]);
        foreach ($this->items as $i => $item) {
            if (self::hashValue($item) === $key) {
                array_splice($this->items, $i, 1);
                break;
            }
        }
        PASM::$cl = 1;
        return true;
    }

    public function union(PASMSet $other): PASMSet
    {
        $result = new PASMSet($this->items);
        foreach ($other as $value) {
            $result->add($value);
        }
        return $result;
    }

    public function intersect(PASMSet $other): PASMSet
    {
        $result = new PASMSet();
        foreach ($this->items as $value) {
            if ($other->has($value)) {
                $result->add($value);
            }
        }
        return $result;
    }

    public function difference(PASMSet $other): PASMSet
    {
        $result = new PASMSet();
        foreach ($this->items as $value) {
            if (!$other->has($value)) {
                $result->add($value);
            }
        }
        return $result;
    }

    private static function hashValue(mixed $value): string
    {
        return match (true) {
            is_object($value) => 'o:' . spl_object_id($value),
            is_resource($value) => 'r:' . get_resource_id($value),
            default => get_debug_type($value) . ':' . serialize($value),
        };
    }
}

// Familiar aliases for code that wants conventional container names.
class Vector extends PASMList {}
class Stack extends PASMStack {}
class Queue extends PASMQueue {}
class Deque extends PASMDeque {}
class Map extends PASMMap {}
class Set extends PASMSet {}
