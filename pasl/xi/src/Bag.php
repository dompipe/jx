<?php declare(strict_types=1);

$jxRuntime = dirname(__DIR__, 3) . '/jx.php';
if (!class_exists(\jx\Bag::class, false) && is_file($jxRuntime)) {
    require_once $jxRuntime;
}
unset($jxRuntime);

/**
 * XI compatibility adapter over the canonical JX Bag.
 *
 * XI historically exposed set(key, value). The canonical JX Bag deliberately
 * keeps its own mutation law and uses write(node, value) for one-shot writes.
 * This wrapper preserves the XI call surface while ensuring the storage,
 * capacity accounting, RefSign authorization, data-source bindings, and
 * serialization all belong to one canonical jx\Bag.
 *
 * Secrets never belong in host-visible channel Bags.
 */
final class Bag
{
    private \jx\Bag $inner;

    /** @param array<string,mixed> $data */
    public function __construct(array $data = [], ?int $capacity = null)
    {
        $capacity ??= max(65_536, strlen(serialize($data)) * 2 + 256);
        $this->inner = \jx\Bag::underwrite($capacity);

        foreach ($data as $key => $value) {
            $this->set((string)$key, $value);
        }
    }

    public static function empty(int $capacity = 65_536): self
    {
        return new self([], $capacity);
    }

    /** @param array<string,mixed> $data */
    public static function from(array $data, ?int $capacity = null): self
    {
        return new self($data, $capacity);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->read($key, $default);
    }

    /**
     * XI compatibility order: set(key, value).
     * Canonical lowering: jx\Bag::write(node, value).
     */
    public function set(string $key, mixed $value): void
    {
        if (preg_match('/secret|password|token|xi_/i', $key)) {
            return;
        }
        $this->inner->write($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->inner->all();
    }

    /**
     * Bind this channel Bag to a named source through the canonical JX Bag.
     * No live SQL/NoSQL connection is retained here.
     */
    public function bind(
        string $source,
        string $through,
        string $at = '_default',
        string $mode = 'auto',
        array $with = [],
    ): string {
        return $this->inner->bind($source, $through, $at, $mode, $with);
    }

    public function unbind(string $id): bool
    {
        return $this->inner->unbind($id);
    }

    /** @return list<array<string,mixed>> */
    public function bindings(): array
    {
        return $this->inner->bindings();
    }

    public function restoreBindings(array $bindings): void
    {
        $this->inner->restoreBindings($bindings);
    }

    /** @param list<string> $keys */
    public function dilate(array $keys): self
    {
        $out = self::empty(max(65_536, $this->inner->capacity()));
        foreach ($keys as $key) {
            if ($this->has($key)) {
                $out->set($key, $this->get($key));
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $other */
    public function merge(array $other, ?array $allowKeys = null): void
    {
        foreach ($other as $key => $value) {
            $key = (string)$key;
            if ($allowKeys !== null && !in_array($key, $allowKeys, true)) {
                continue;
            }
            $this->set($key, $value);
        }
    }

    public function toJson(): string
    {
        return $this->inner->toJson();
    }

    public static function fromJson(string $json, ?int $capacity = null): self
    {
        $data = json_decode($json, true);
        return is_array($data) ? self::from($data, $capacity) : self::empty($capacity ?? 65_536);
    }

    /** Canonical Bag for SQL/Book/runtime boundaries that understand jx\Bag. */
    public function canonical(): \jx\Bag
    {
        return $this->inner;
    }

    public function capacity(): int
    {
        return $this->inner->capacity();
    }

    public function used(): int
    {
        return $this->inner->used();
    }

    public function quotient(): int
    {
        return $this->inner->quotient();
    }
}
