<?php declare(strict_types=1);
/**
 * jx (jinx) — single realized construct on top of PASM.
 *
 * Product name: jx. Engine: PASM (frames, segments, master table, bytecode).
 * Memory law: no free writes; only allowance + underwritten bag + handshake.
 */
namespace jx;

use InvalidArgumentException;
use RuntimeException;
use WeakMap;

// Optional PASM engine hooks (present in this repo).
foreach ([__DIR__, dirname(__DIR__)] as $base) {
    foreach (['pasm-runtime.php', 'pasm-master-table.php', 'pasm-lang-core.php'] as $f) {
        $p = $base . DIRECTORY_SEPARATOR . $f;
        if (is_file($p)) {
            require_once $p;
        }
    }
}

// ---------------------------------------------------------------------------
// Errors
// ---------------------------------------------------------------------------

class JxException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $kind = 'jx',
        public readonly bool $resistant = false,
    ) {
        parent::__construct(($resistant ? '[Resistant] ' : '[jx] ') . $message);
    }
}

// ---------------------------------------------------------------------------
// Complex (prefer pasm\lang\Complex when loaded)
// ---------------------------------------------------------------------------

final class Complex
{
    public function __construct(public float $re = 0.0, public float $im = 0.0) {}

    public static function of(float $re, float $im = 0.0): self
    {
        return new self($re, $im);
    }

    public static function parse(string $s): self
    {
        $s = str_replace(' ', '', strtolower(trim($s)));
        if ($s === '' || $s === '0') {
            return new self(0.0, 0.0);
        }
        if (preg_match('/^([+-]?\d+(?:\.\d+)?)?([+-]\d*(?:\.\d+)?)i$/', $s, $m)) {
            $re = ($m[1] === '' || $m[1] === null) ? 0.0 : (float)$m[1];
            $imPart = $m[2];
            if ($imPart === '+' || $imPart === '') {
                $im = 1.0;
            } elseif ($imPart === '-') {
                $im = -1.0;
            } else {
                $im = (float)$imPart;
            }
            return new self($re, $im);
        }
        if ($s === 'i') {
            return new self(0.0, 1.0);
        }
        if ($s === '-i') {
            return new self(0.0, -1.0);
        }
        if (is_numeric($s)) {
            return new self((float)$s, 0.0);
        }
        throw new JxException("Invalid complex literal: {$s}", 'complex');
    }

    public function add(self $o): self
    {
        return new self($this->re + $o->re, $this->im + $o->im);
    }

    public function sub(self $o): self
    {
        return new self($this->re - $o->re, $this->im - $o->im);
    }

    public function mul(self $o): self
    {
        return new self(
            $this->re * $o->re - $this->im * $o->im,
            $this->re * $o->im + $this->im * $o->re
        );
    }

    public function conj(): self
    {
        return new self($this->re, -$this->im);
    }

    public function mag(): float
    {
        return sqrt($this->re * $this->re + $this->im * $this->im);
    }

    public function __toString(): string
    {
        if ($this->im == 0.0) {
            return (string)$this->re;
        }
        $sign = $this->im >= 0 ? '+' : '-';
        $aim = abs($this->im);
        $imS = $aim == 1.0 ? 'i' : "{$aim}i";
        if ($this->re == 0.0) {
            return ($this->im < 0 ? '-' : '') . $imS;
        }
        return "{$this->re}{$sign}{$imS}";
    }
}

// ---------------------------------------------------------------------------
// RefSign — capability for a signed region inside a Bag
// ---------------------------------------------------------------------------

final class RefSign
{
    public function __construct(
        public readonly int $bagId,
        public readonly string $node,
        public readonly string $token,
        public readonly int $issuedAt,
    ) {}

    public function matches(Bag $bag, string $token): bool
    {
        return $this->bagId === $bag->id()
            && hash_equals($this->token, $token)
            && $bag->isLiveRef($this);
    }
}

// ---------------------------------------------------------------------------
// Bag — underwritten mutable container (memory law)
// ---------------------------------------------------------------------------

class Bag
{
    private static int $nextId = 1;

    private int $id;
    private int $capacity;
    private int $used = 0;
    /** @var array<string,mixed> */
    private array $cells = [];
    /** @var array<string,RefSign> node => ref */
    private array $refs = [];
    /** @var array<string,true> token set */
    private array $liveTokens = [];
    /** @var array<string,mixed> */
    private array $props = [];

    protected function __construct(int $capacity)
    {
        if ($capacity < 0) {
            throw new JxException('Bag capacity must be non-negative', 'bag');
        }
        $this->id = self::$nextId++;
        $this->capacity = $capacity;
    }

    public static function underwrite(int $size): self
    {
        return new self($size);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function used(): int
    {
        return $this->used;
    }

    /** Remaining free space (memory quotient). */
    public function quotient(): int
    {
        return max(0, $this->capacity - $this->used);
    }

    public function sign(string $node): RefSign
    {
        $token = bin2hex(random_bytes(16));
        $ref = new RefSign($this->id, $node, $token, time());
        $this->refs[$node] = $ref;
        $this->liveTokens[$token] = true;
        return $ref;
    }

    public function unsign(RefSign $ref): void
    {
        if ($ref->bagId !== $this->id) {
            throw new JxException('RefSign does not belong to this bag', 'bag');
        }
        unset($this->liveTokens[$ref->token]);
        if (isset($this->refs[$ref->node]) && $this->refs[$ref->node]->token === $ref->token) {
            unset($this->refs[$ref->node]);
        }
    }

    public function isLiveRef(RefSign $ref): bool
    {
        return $ref->bagId === $this->id && isset($this->liveTokens[$ref->token]);
    }

    private function assertRef(RefSign $ref): void
    {
        if (!$this->isLiveRef($ref)) {
            throw new JxException('Dead or foreign RefSign — write denied', 'bag');
        }
    }

    private function sizeOf(mixed $data): int
    {
        if (is_string($data)) {
            return strlen($data);
        }
        if (is_int($data) || is_float($data) || is_bool($data) || $data === null) {
            return 8;
        }
        if ($data instanceof Complex) {
            return 16;
        }
        if (is_array($data)) {
            $n = 16;
            foreach ($data as $k => $v) {
                $n += $this->sizeOf($k) + $this->sizeOf($v);
            }
            return $n;
        }
        return strlen(serialize($data));
    }

    /**
     * Handshake mutation: only legal write path.
     * Tight form: set + commit(ref).
     */
    public function set(mixed $data, ?string $node = null): BagWrite
    {
        return new BagWrite($this, $data, $node ?? '_default');
    }

    /** @internal */
    public function commitWrite(BagWrite $w, RefSign $ref): void
    {
        $this->assertRef($ref);
        $node = $w->node;
        $newSize = $this->sizeOf($w->data);
        $oldSize = isset($this->cells[$node]) ? $this->sizeOf($this->cells[$node]) : 0;
        $delta = $newSize - $oldSize;
        if ($delta > 0 && $this->quotient() < $delta) {
            throw new JxException(
                "Bag overflow: need {$delta} more bytes, quotient {$this->quotient()}",
                'bag',
                true
            );
        }
        $this->cells[$node] = $w->data;
        $this->used += $delta;
    }

    public function get(RefSign $ref, ?string $node = null): mixed
    {
        $this->assertRef($ref);
        $node ??= $ref->node;
        return $this->cells[$node] ?? null;
    }

    /** Read cell without ref (display / inspection only — not a write). */
    public function peek(string $node = '_default'): mixed
    {
        return $this->cells[$node] ?? null;
    }

    public function push(string $key, mixed $value): void
    {
        // Property preassignment: counted against capacity once.
        $size = $this->sizeOf($key) + $this->sizeOf($value);
        if (!array_key_exists($key, $this->props) && $this->quotient() < $size) {
            throw new JxException('Bag overflow on push', 'bag', true);
        }
        if (!array_key_exists($key, $this->props)) {
            $this->used += $size;
        }
        $this->props[$key] = $value;
    }

    public function prop(string $key): mixed
    {
        return $this->props[$key] ?? null;
    }

    /** Verbose placebo → same as tight. */
    public function tell(string $op, mixed ...$args): mixed
    {
        return match (strtolower($op)) {
            'push' => (function () use ($args) {
                $this->push((string)$args[0], $args[1] ?? null);
                return $this;
            })(),
            'sign' => $this->sign((string)($args[0] ?? '_default')),
            'unsign' => (function () use ($args) {
                $this->unsign($args[0]);
                return $this;
            })(),
            'set' => $this->set($args[0] ?? null, isset($args[1]) ? (string)$args[1] : null),
            'get' => $this->get($args[0], isset($args[1]) ? (string)$args[1] : null),
            'quotient' => $this->quotient(),
            'capacity' => $this->capacity(),
            'used' => $this->used(),
            'id' => $this->id(),
            default => throw new JxException("Unknown bag op: {$op}", 'bag', true),
        };
    }
}

/** Pending write awaiting commit(ref) — the handshake. */
final class BagWrite
{
    public function __construct(
        public readonly Bag $bag,
        public readonly mixed $data,
        public readonly string $node,
    ) {}

    public function commit(RefSign $ref): void
    {
        $this->bag->commitWrite($this, $ref);
    }

    /** Verbose: .pass(ref) == .commit(ref) */
    public function pass(RefSign $ref): void
    {
        $this->commit($ref);
    }
}

// ---------------------------------------------------------------------------
// Task — special Bag (execution identity + push + id)
// ---------------------------------------------------------------------------

final class Task extends Bag
{
    private string $name;
    private string $state = 'ready';

    private function __construct(int $capacity, string $name)
    {
        parent::__construct($capacity);
        $this->name = $name;
        $this->push('_task_name', $name);
    }

    public static function underwrite(int $size, string $name = 'task'): self
    {
        return new self($size, $name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }
}

// ---------------------------------------------------------------------------
// Page — runnable surface (X11-like); wraps a Task bag
// ---------------------------------------------------------------------------

final class Page
{
    private Task $task;
    /** @var callable|null */
    private $entry;

    private function __construct(Task $task, ?callable $entry)
    {
        $this->task = $task;
        $this->entry = $entry;
    }

    public static function spawn(?callable $entry = null, ?Bag $bag = null, int $size = 65536, string $name = 'page'): self
    {
        $task = Task::underwrite($size, $name);
        if ($bag !== null) {
            // Optional: share display bag reference as prop (no free merge of memory).
            $task->push('_display_bag_id', $bag->id());
        }
        return new self($task, $entry);
    }

    public function task(): Task
    {
        return $this->task;
    }

    public function id(): int
    {
        return $this->task->id();
    }

    public function run(): mixed
    {
        if ($this->entry === null) {
            return null;
        }
        $this->task->setState('running');
        try {
            $ret = ($this->entry)($this->task);
            $this->task->setState('done');
            return $ret;
        } catch (\Throwable $e) {
            $this->task->setState('error');
            throw $e;
        }
    }
}

// ---------------------------------------------------------------------------
// Book — compiled unit of pages / bags / libraries
// ---------------------------------------------------------------------------

final class Book
{
    private string $name;
    /** @var array<string,Page> */
    private array $pages = [];
    /** @var array<string,Bag> */
    private array $bags = [];
    private int $memoryQuota;
    private int $memoryUsed = 0;

    private function __construct(string $name, int $memoryQuota)
    {
        $this->name = $name;
        $this->memoryQuota = $memoryQuota;
    }

    public static function open(string $name, int $memoryQuota = 8_388_608): self
    {
        return new self($name, $memoryQuota);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function quota(): int
    {
        return $this->memoryQuota;
    }

    public function registerBag(string $key, Bag $bag): void
    {
        $need = $bag->capacity();
        if ($this->memoryUsed + $need > $this->memoryQuota) {
            throw new JxException('Book memory quota exceeded', 'book', true);
        }
        $this->bags[$key] = $bag;
        $this->memoryUsed += $need;
    }

    public function bag(string $key): Bag
    {
        if (!isset($this->bags[$key])) {
            throw new JxException("Unknown bag {$key}", 'book');
        }
        return $this->bags[$key];
    }

    public function registerPage(string $key, Page $page): void
    {
        $this->pages[$key] = $page;
    }

    public function page(string $key): Page
    {
        if (!isset($this->pages[$key])) {
            throw new JxException("Unknown page {$key}", 'book');
        }
        return $this->pages[$key];
    }

    public function pages(): array
    {
        return $this->pages;
    }
}

// ---------------------------------------------------------------------------
// Delivery — deep path extract / rebind
// ---------------------------------------------------------------------------

final class Delivery
{
    /**
     * @param array<int|string>|string $path list of keys or dot-string
     */
    public static function extract(mixed $root, array|string $path, mixed $default = null): mixed
    {
        $keys = is_string($path) ? self::splitPath($path) : $path;
        $cur = $root;
        foreach ($keys as $k) {
            if (is_array($cur) && array_key_exists($k, $cur)) {
                $cur = $cur[$k];
                continue;
            }
            if (is_object($cur) && (isset($cur->$k) || method_exists($cur, '__get'))) {
                $cur = $cur->$k;
                continue;
            }
            return $default; // Resistant-friendly: no crash
        }
        return $cur;
    }

    /** @return list<string> */
    public static function splitPath(string $path): array
    {
        $path = trim($path, ". \t");
        if ($path === '') {
            return [];
        }
        return explode('.', $path);
    }

    /**
     * Rebind: write value at path into array root (returns new/mutated array).
     * Does not touch Bags — caller must commit into a Bag via sign/handshake.
     */
    public static function rebind(array $root, array|string $path, mixed $value): array
    {
        $keys = is_string($path) ? self::splitPath($path) : $path;
        if ($keys === []) {
            throw new JxException('Empty delivery path', 'delivery', true);
        }
        $out = $root;
        $cursor = &$out;
        $last = count($keys) - 1;
        foreach ($keys as $i => $k) {
            if ($i === $last) {
                $cursor[$k] = $value;
                break;
            }
            if (!isset($cursor[$k]) || !is_array($cursor[$k])) {
                $cursor[$k] = [];
            }
            $cursor = &$cursor[$k];
        }
        return $out;
    }
}

// ---------------------------------------------------------------------------
// Const helper
// ---------------------------------------------------------------------------

final class ConstBox
{
    public function __construct(public readonly mixed $value) {}

    public static function wrap(mixed $value): self
    {
        return new self($value);
    }
}

function jx_const(mixed $value): ConstBox
{
    return ConstBox::wrap($value);
}

// ---------------------------------------------------------------------------
// Smart table bridge (names → metadata; extrusion hooks to PASM master table)
// ---------------------------------------------------------------------------

final class SmartTable
{
    /** @var array<string,array<string,mixed>> */
    private array $rows = [];

    public function __construct()
    {
        $this->seed();
    }

    private function seed(): void
    {
        $defs = [
            ['bag.underwrite', 'Bag', 'none', false, 'underwritten-only', 1.0],
            ['bag.sign', 'Bag', 'read', false, 'underwritten-only', 1.0],
            ['bag.set', 'Bag', 'write-bag', true, 'underwritten-only', 0.9],
            ['bag.commit', 'Bag', 'write-bag', true, 'underwritten-only', 0.9],
            ['bag.quotient', 'Bag', 'read', false, 'pure', 1.0],
            ['task.push', 'Task', 'write-bag', false, 'task-local', 0.95],
            ['task.id', 'Task', 'read', false, 'pure', 1.0],
            ['page.spawn', 'Page', 'schedule', false, 'task-local', 0.85],
            ['book.open', 'Book', 'none', false, 'pure', 1.0],
            ['delivery.extract', 'Delivery', 'read', false, 'pure', 0.8],
            ['delivery.rebind', 'Delivery', 'none', false, 'pure', 0.75],
            ['complex.parse', 'Complex', 'none', false, 'pure', 1.0],
        ];
        foreach ($defs as [$id, $module, $side, $reqRef, $mem, $purity]) {
            $this->rows[$id] = [
                'id' => $id,
                'name' => $id,
                'module' => $module,
                'side_effect' => $side,
                'requires_ref' => $reqRef,
                'memory_class' => $mem,
                'purity_score' => $purity,
                'native_template' => 'inline',
                'resistant_template' => 'safe_php',
            ];
        }
    }

    public function get(string $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function all(): array
    {
        return $this->rows;
    }

    /** Prefer native; if purity too low or unknown, mark Resistant. */
    public function extrude(string $id): array
    {
        $row = $this->get($id);
        if ($row === null) {
            return ['mode' => 'resistant', 'reason' => 'unknown_method', 'id' => $id];
        }
        if ($row['purity_score'] >= 0.85) {
            return ['mode' => 'native', 'row' => $row];
        }
        return ['mode' => 'resistant', 'reason' => 'low_purity', 'row' => $row];
    }
}

// ---------------------------------------------------------------------------
// Symbolic constants (assembler-friendly)
// ---------------------------------------------------------------------------

final class Sym
{
    public const STDIN = 0;
    public const STDOUT = 1;
    public const STDERR = 2;
    public const EXIT_SUCCESS = 0;
    public const EXIT_FAILURE = 1;

    public const SYS_READ = 0;
    public const SYS_WRITE = 1;
    public const SYS_OPEN = 2;
    public const SYS_CLOSE = 3;
    public const SYS_MMAP = 9;
    public const SYS_MUNMAP = 11;
    public const SYS_EXIT = 60;

    public const O_RDONLY = 0;
    public const O_WRONLY = 1;
    public const O_RDWR = 2;
    public const O_CREAT = 0x40;
    public const O_TRUNC = 0x200;
    public const O_APPEND = 0x400;

    public const PROT_READ = 1;
    public const PROT_WRITE = 2;
    public const PROT_EXEC = 4;
    public const MAP_PRIVATE = 0x02;
    public const MAP_ANONYMOUS = 0x20;
}

// ---------------------------------------------------------------------------
// Facade
// ---------------------------------------------------------------------------

final class Jx
{
    private static ?SmartTable $table = null;

    public static function table(): SmartTable
    {
        return self::$table ??= new SmartTable();
    }

    public static function bag(int $size): Bag
    {
        return Bag::underwrite($size);
    }

    public static function task(int $size, string $name = 'task'): Task
    {
        return Task::underwrite($size, $name);
    }

    public static function page(?callable $entry = null, int $size = 65536, string $name = 'page'): Page
    {
        return Page::spawn($entry, null, $size, $name);
    }

    public static function book(string $name, int $quota = 8_388_608): Book
    {
        return Book::open($name, $quota);
    }

    public static function delivery(mixed $root, array|string $path, mixed $default = null): mixed
    {
        return Delivery::extract($root, $path, $default);
    }

    public static function complex(float $re, float $im = 0.0): Complex
    {
        return Complex::of($re, $im);
    }
}

// End of jx.php — one mass, PASM-improved trail, named jx.
