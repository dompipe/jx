<?php declare(strict_types=1);

namespace jx;

use JsonSerializable;
use WeakMap;
use WeakReference;

/**
 * JX RefId — bounded object reference, memory capsule, and call writer.
 *
 * A RefId never owns or writes the target object's memory. It owns only its
 * developer-sized local segment. Its state view is a filtered/depth-limited
 * projection of the referenced object. Calls are data-shaped invocation
 * records for the host/runtime to resolve against another RefId or a registered
 * function.
 *
 * Memory law:
 *   - a RefId may mutate only its own segment;
 *   - a call may request work from another object but may not write through it;
 *   - oversized state condenses to summary/hash metadata;
 *   - oversized memory/call writes are refused, not allowed to overflow.
 */
final class RefId implements JsonSerializable
{
    public const VERSION = 'jx.refid/1';
    private const MAX_DEPTH = 32;

    private Bag $memory;
    private int $sequence = 0;
    private int $invocationDepth = 0;
    private bool $saturated = false;

    /** @param list<string> $filter */
    public function __construct(
        private readonly string $id,
        private readonly string $kind,
        private readonly ?string $alias,
        private readonly int $segmentBytes,
        private int $captureDepth = 1,
        private array $filter = [],
    ) {
        if (!preg_match('/^[a-f0-9]{24}$/', $this->id)) {
            throw new JxException('Invalid RefId identity', 'refid', true);
        }
        $this->captureDepth = max(0, min(self::MAX_DEPTH, $this->captureDepth));
        $this->filter = self::normalizeFilter($this->filter);
        $this->memory = Bag::underwrite(max(256, $this->segmentBytes));
    }

    public function id(): string { return $this->id; }
    public function kind(): string { return $this->kind; }
    public function alias(): ?string { return $this->alias; }
    public function segmentBytes(): int { return $this->segmentBytes; }
    public function used(): int { return $this->memory->used(); }
    public function available(): int { return $this->memory->quotient(); }
    public function saturated(): bool { return $this->saturated; }
    public function invocationDepth(): int { return $this->invocationDepth; }

    /**
     * Produce another bounded view of the same identity with a different depth
     * or filter. The view gets its own local memory segment.
     *
     * @param list<string> $filter
     */
    public function view(?int $depth = null, ?array $filter = null, ?int $segmentBytes = null): self
    {
        return new self(
            $this->id,
            $this->kind,
            $this->alias,
            $segmentBytes ?? $this->segmentBytes,
            $depth ?? $this->captureDepth,
            $filter ?? $this->filter,
        );
    }

    /**
     * Store a value inside this RefId's own bounded memory.
     * Returns false instead of throwing on capacity refusal.
     */
    public function remember(string $at, mixed $value): bool
    {
        try {
            $this->memory->write($at, $value);
            return true;
        } catch (JxException $error) {
            if ($error->kind !== 'bag') throw $error;
            $this->saturated = true;
            return false;
        }
    }

    public function recall(string $at = '_default', mixed $default = null): mixed
    {
        return $this->memory->read($at, $default);
    }

    /** @return array<string,mixed> */
    public function memory(): array
    {
        return $this->memory->all();
    }

    /**
     * Capture a bounded state view of a registered object.
     * Oversized detailed state is replaced by a compact summary/hash.
     *
     * @param list<string>|null $filter
     * @return array<string,mixed>
     */
    public function capture(object $object, ?int $depth = null, ?array $filter = null): array
    {
        $depth = max(0, min(self::MAX_DEPTH, $depth ?? $this->captureDepth));
        $filter = self::normalizeFilter($filter ?? $this->filter);
        $raw = self::objectState($object);
        $projected = self::project($raw, $depth, $filter);
        $encoded = json_encode($projected, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encoded = is_string($encoded) ? $encoded : '';
        $summary = [
            'ref' => $this->id,
            'kind' => $this->kind,
            'alias' => $this->alias,
            'depth' => $depth,
            'filter' => $filter,
            'size' => strlen($encoded),
            'state' => substr(hash('sha256', $encoded), 0, 24),
            'capacity' => method_exists($object, 'capacity') ? (int)$object->capacity() : null,
            'used' => method_exists($object, 'used') ? (int)$object->used() : null,
        ];

        if ($this->remember('_state', ['summary' => $summary, 'view' => $projected])) {
            $summary['condensed'] = false;
            return $summary + ['view' => $projected];
        }

        // The requested detailed view does not fit this RefId segment. Keep a
        // bounded summary only; never reach into any neighboring object memory.
        $this->saturated = true;
        $this->remember('_state_summary', $summary + ['condensed' => true]);
        return $summary + ['condensed' => true];
    }

    /**
     * Write a method-call record addressed to another RefId.
     * No method is invoked here and no target memory is mutated here.
     *
     * @param array<int|string,mixed> $args
     * @return array<string,mixed>
     */
    public function call(RefId|string $target, string $method, array $args = [], ?string $into = null): array
    {
        $targetId = $target instanceof self ? $target->id() : self::normalizeTarget($target);
        return $this->writeCall('method', $targetId, $method, $args, $into);
    }

    /**
     * Write a call record for a host-registered function.
     *
     * @param array<int|string,mixed> $args
     * @return array<string,mixed>
     */
    public function callFunction(string $function, array $args = [], ?string $into = null): array
    {
        return $this->writeCall('function', null, self::name($function, 'function'), $args, $into);
    }

    /** @return list<array<string,mixed>> */
    public function calls(): array
    {
        $out = [];
        foreach ($this->memory->all() as $key => $value) {
            if (str_starts_with((string)$key, '_call:') && is_array($value)) $out[] = $value;
        }
        usort($out, static fn(array $a, array $b): int => ((int)($a['sequence'] ?? 0)) <=> ((int)($b['sequence'] ?? 0)));
        return $out;
    }

    /** Store this RefId descriptor in another Bag without storing a live object. */
    public function store(Bag $bag, string $at = '_refid'): bool
    {
        try {
            $bag->write($at, $this->jsonSerialize());
            return true;
        } catch (JxException $error) {
            if ($error->kind !== 'bag') throw $error;
            return false;
        }
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'version' => self::VERSION,
            'id' => $this->id,
            'kind' => $this->kind,
            'alias' => $this->alias,
            'segment' => [
                'capacity' => $this->segmentBytes,
                'used' => $this->used(),
                'available' => $this->available(),
                'saturated' => $this->saturated,
            ],
            'view' => [
                'depth' => $this->captureDepth,
                'filter' => $this->filter,
            ],
            'invocationDepth' => $this->invocationDepth,
            'state' => $this->memory->read('_state_summary', $this->memory->read('_state')),
        ];
    }

    /** @param array<int|string,mixed> $args @return array<string,mixed> */
    private function writeCall(string $type, ?string $target, string $callable, array $args, ?string $into): array
    {
        $callable = self::name($callable, $type === 'method' ? 'method' : 'function');
        $into = $into === null ? null : self::name($into, 'result node');
        $depth = $this->invocationDepth + 1;
        if ($depth > self::MAX_DEPTH) {
            return [
                'accepted' => false,
                'reason' => 'invocation-depth',
                'from' => $this->id,
                'depth' => $depth,
            ];
        }

        $this->sequence++;
        $record = [
            'version' => 'jx.call/1',
            'accepted' => true,
            'sequence' => $this->sequence,
            'type' => $type,
            'from' => $this->id,
            'target' => $target,
            'callable' => $callable,
            'args' => Boundary::import($args),
            'into' => $into,
            'depth' => $depth,
        ];
        $record['id'] = substr(hash('sha256', json_encode($record, JSON_UNESCAPED_SLASHES) ?: serialize($record)), 0, 24);

        if (!$this->remember('_call:' . $this->sequence, $record)) {
            return [
                'version' => 'jx.call/1',
                'accepted' => false,
                'reason' => 'refid-segment-full',
                'id' => $record['id'],
                'from' => $this->id,
                'target' => $target,
                'callable' => $callable,
                'depth' => $depth,
            ];
        }
        return $record;
    }

    /** @return array<string,mixed> */
    private static function objectState(object $object): array
    {
        if ($object instanceof Bag) {
            return [
                'id' => $object->id(),
                'capacity' => $object->capacity(),
                'used' => $object->used(),
                'available' => $object->quotient(),
                'data' => $object->all(),
                'bindings' => $object->bindings(),
            ];
        }
        if ($object instanceof JsonSerializable) {
            $value = $object->jsonSerialize();
            return is_array($value) ? $value : ['value' => $value];
        }
        $vars = get_object_vars($object);
        return Boundary::import($vars);
    }

    /** @param list<string> $filter */
    private static function project(mixed $value, int $depth, array $filter, int $level = 0): mixed
    {
        if ($level >= $depth) {
            if (is_array($value)) return ['_count' => count($value), '_hash' => substr(hash('sha256', serialize($value)), 0, 16)];
            if (is_object($value)) return ['_class' => $value::class];
            return $value;
        }
        if (!is_array($value)) return $value;

        $out = [];
        foreach ($value as $key => $item) {
            $key = (string)$key;
            if ($level === 0 && $filter !== [] && !in_array($key, $filter, true)) continue;
            $out[$key] = self::project($item, $depth, $filter, $level + 1);
        }
        return $out;
    }

    /** @param list<string> $filter @return list<string> */
    private static function normalizeFilter(array $filter): array
    {
        $out = [];
        foreach ($filter as $field) {
            $field = self::name((string)$field, 'filter field');
            if (!in_array($field, $out, true)) $out[] = $field;
        }
        return $out;
    }

    private static function normalizeTarget(string $target): string
    {
        $target = strtolower(trim($target));
        if (!preg_match('/^[a-f0-9]{24}$/', $target)) {
            throw new JxException('Invalid RefId target', 'refid.call', true, ['target' => $target]);
        }
        return $target;
    }

    private static function name(string $value, string $what): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 256 || str_contains($value, "\0") || !preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/', $value)) {
            throw new JxException("Invalid RefId {$what}", 'refid', true, [$what => $value]);
        }
        return $value;
    }
}

/**
 * Process-local RefId registry. Objects remain the authority for their memory;
 * this registry only resolves identity to live objects while they still exist.
 */
final class RefIds
{
    /** @var WeakMap<object,RefId>|null */
    private static ?WeakMap $objects = null;
    /** @var array<string,WeakReference> */
    private static array $live = [];

    /** @param list<string> $filter */
    public static function install(
        object $object,
        string $kind = 'object',
        ?string $alias = null,
        int $segmentBytes = 4096,
        int $depth = 1,
        array $filter = [],
    ): RefId {
        self::$objects ??= new WeakMap();
        if (isset(self::$objects[$object])) return self::$objects[$object];

        $kind = self::name($kind, 'kind');
        $alias = $alias === null ? null : self::name($alias, 'alias');
        $identitySeed = $kind . "\0" . ($alias ?? '') . "\0" . self::objectIdentity($object);
        $id = substr(hash('sha256', $identitySeed), 0, 24);
        $ref = new RefId($id, $kind, $alias, max(256, $segmentBytes), $depth, $filter);
        self::$objects[$object] = $ref;
        self::$live[$id] = WeakReference::create($object);
        return $ref;
    }

    public static function for(object $object): RefId
    {
        return self::install($object, self::kindOf($object), self::aliasOf($object));
    }

    public static function resolve(RefId|string $ref): ?object
    {
        $id = $ref instanceof RefId ? $ref->id() : strtolower(trim($ref));
        $weak = self::$live[$id] ?? null;
        if (!$weak) return null;
        $object = $weak->get();
        if (!$object) unset(self::$live[$id]);
        return $object;
    }

    public static function release(object $object): void
    {
        self::$objects ??= new WeakMap();
        if (!isset(self::$objects[$object])) return;
        $id = self::$objects[$object]->id();
        unset(self::$objects[$object], self::$live[$id]);
    }

    private static function objectIdentity(object $object): string
    {
        if (method_exists($object, 'id')) {
            try { return $object::class . ':' . (string)$object->id(); } catch (\Throwable) {}
        }
        return $object::class . ':runtime:' . spl_object_id($object);
    }

    private static function kindOf(object $object): string
    {
        if ($object instanceof Bag) return 'bag';
        return strtolower((new \ReflectionClass($object))->getShortName());
    }

    private static function aliasOf(object $object): ?string
    {
        if (method_exists($object, 'name')) {
            try {
                $name = (string)$object->name();
                return $name === '' ? null : $name;
            } catch (\Throwable) {}
        }
        return null;
    }

    private static function name(string $value, string $what): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128 || str_contains($value, "\0") || preg_match('/[^a-z0-9._:-]/i', $value)) {
            throw new JxException("Invalid RefId {$what}", 'refid', true, [$what => $value]);
        }
        return $value;
    }
}
