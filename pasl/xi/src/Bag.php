<?php declare(strict_types=1);

$jxRuntime = dirname(__DIR__, 3) . '/jx.php';
if (!class_exists(\jx\Bag::class, false) && is_file($jxRuntime)) {
    require_once $jxRuntime;
}
unset($jxRuntime);

$jxRefId = dirname(__DIR__, 3) . '/jx/RefId.php';
if (!class_exists(\jx\RefId::class, false) && is_file($jxRefId)) {
    require_once $jxRefId;
}
unset($jxRefId);

/**
 * XI compatibility adapter over the canonical JX Bag.
 *
 * XI historically exposed set(key, value). The canonical JX Bag deliberately
 * keeps its own mutation law and uses write(node, value) for one-shot writes.
 * This wrapper preserves the XI call surface while ensuring the storage,
 * capacity accounting, RefSign authorization, data-source bindings,
 * coercion, RefId identity/call memory, and serialization all belong to JX.
 *
 * Every XI Bag installs a RefId at construction. The RefId owns a separate,
 * developer-sized bounded segment; it never expands or writes through the Bag's
 * own memory segment. Secrets never belong in host-visible channel Bags.
 */
final class Bag
{
    private const SERIAL_VERSION = 'jx.bag/1';

    private \jx\Bag $inner;
    private \jx\RefId $refId;

    /** @param array<string,mixed> $data */
    public function __construct(array $data = [], ?int $capacity = null, int $refSegmentBytes = 4096)
    {
        $capacity ??= max(65_536, strlen(serialize($data)) * 2 + 256);
        $this->inner = \jx\Bag::underwrite($capacity);
        $this->refId = \jx\RefIds::install(
            $this->inner,
            'bag',
            null,
            max(256, $refSegmentBytes),
            1,
            ['id', 'capacity', 'used', 'available', 'bindings'],
        );

        foreach ($data as $key => $value) {
            $this->set((string)$key, $value);
        }
        $this->refreshRefId();
    }

    public static function empty(int $capacity = 65_536, int $refSegmentBytes = 4096): self
    {
        return new self([], $capacity, $refSegmentBytes);
    }

    /** @param array<string,mixed> $data */
    public static function from(array $data, ?int $capacity = null, int $refSegmentBytes = 4096): self
    {
        return new self($data, $capacity, $refSegmentBytes);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->read($key, $default);
    }

    /** Read one Bag value through the canonical binding-coercion vocabulary. */
    public function bound(string $key = '_default', string $as = 'raw'): mixed
    {
        return $this->inner->bound($key, $as);
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
        $this->refreshRefId();
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
     *
     * Use `with['as']` for raw|string|algebra|number|integer|float|boolean|json.
     */
    public function bind(
        string $source,
        string $through,
        string $at = '_default',
        string $mode = 'auto',
        array $with = [],
    ): string {
        $id = $this->inner->bind($source, $through, $at, $mode, $with);
        $this->refreshRefId();
        return $id;
    }

    public function unbind(string $id): bool
    {
        $removed = $this->inner->unbind($id);
        if ($removed) $this->refreshRefId();
        return $removed;
    }

    /** @return list<array<string,mixed>> */
    public function bindings(): array
    {
        return $this->inner->bindings();
    }

    public function restoreBindings(array $bindings): void
    {
        $this->inner->restoreBindings($bindings);
        $this->refreshRefId();
    }

    /**
     * Every Bag has a RefId. Its memory is a separate bounded segment and its
     * call writer may address methods/functions without writing target memory.
     */
    public function refId(): \jx\RefId
    {
        return $this->refId;
    }

    /** Refresh the RefId's filtered size/state projection of this Bag. */
    public function refreshRefId(): void
    {
        $this->refId->capture(
            $this->inner,
            1,
            ['id', 'capacity', 'used', 'available', 'bindings'],
        );
    }

    /** @param list<string> $keys */
    public function dilate(array $keys): self
    {
        $out = self::empty(max(65_536, $this->inner->capacity()), $this->refId->segmentBytes());
        foreach ($keys as $key) {
            if ($this->has($key)) {
                $out->set($key, $this->get($key));
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $other */
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

    /**
     * Persist both Bag data and declarative source bindings.
     *
     * RefId itself is process-local identity and call memory; the serialized
     * descriptor is included for state/provenance, but restoring a Bag installs
     * a fresh live RefId for the new process/object instance.
     *
     * The envelope is versioned so ChannelBus can restore bindings after a
     * process restart. fromJson() remains compatible with legacy flat Bag JSON.
     */
    public function toJson(): string
    {
        return json_encode([
            '__jx_bag__' => self::SERIAL_VERSION,
            'capacity' => $this->inner->capacity(),
            'data' => $this->inner->all(),
            'bindings' => $this->inner->bindings(),
            'refid' => $this->refId->jsonSerialize(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public static function fromJson(string $json, ?int $capacity = null): self
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return self::empty($capacity ?? 65_536);
        }

        if (($decoded['__jx_bag__'] ?? null) === self::SERIAL_VERSION) {
            $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
            $bindings = is_array($decoded['bindings'] ?? null) ? $decoded['bindings'] : [];
            $storedCapacity = max(0, (int)($decoded['capacity'] ?? 0));
            $storedRefSegment = max(256, (int)($decoded['refid']['segment']['capacity'] ?? 4096));
            $resolvedCapacity = $capacity ?? max(
                65_536,
                $storedCapacity,
                strlen(serialize($data)) * 2 + 256,
            );

            $bag = self::from($data, $resolvedCapacity, $storedRefSegment);
            $bag->restoreBindings($bindings);
            return $bag;
        }

        // Legacy XI format: the JSON object itself was the Bag data.
        return self::from($decoded, $capacity);
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
