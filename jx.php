<?php declare(strict_types=1);
/**
 * jx — coherent runtime on top of PASM.
 *
 * Reading rule:
 *   Bag -> sign a place -> prepare a value -> commit the change.
 *   Book -> own the state -> own the libraries -> choose a compiler target.
 */
namespace jx;

use RuntimeException;

foreach ([__DIR__, dirname(__DIR__)] as $base) {
    foreach (['pasm-runtime.php', 'pasm-master-table.php', 'pasm-lang-core.php'] as $file) {
        $path = $base . DIRECTORY_SEPARATOR . $file;
        if (is_file($path)) require_once $path;
    }
}

final class Resistant
{
    /** @var list<array<string,mixed>> */
    private static array $events = [];

    public static function mark(string $reason, string $source = '', array $context = []): array
    {
        $event = [
            'id' => substr(hash('sha256', $reason . "\0" . $source . "\0" . json_encode($context)), 0, 24),
            'at' => microtime(true),
            'reason' => $reason,
            'source' => $source,
            'context' => $context,
        ];
        self::$events[] = $event;
        return $event;
    }

    /** @return list<array<string,mixed>> */
    public static function events(): array { return self::$events; }
    public static function clear(): void { self::$events = []; }
}

class JxException extends RuntimeException
{
    public readonly ?array $resistantEvent;

    public function __construct(
        string $message,
        public readonly string $kind = 'jx',
        public readonly bool $resistant = false,
        array $context = [],
    ) {
        $this->resistantEvent = $resistant ? Resistant::mark($kind . ': ' . $message, '', $context) : null;
        parent::__construct(($resistant ? '[Resistant] ' : '[jx] ') . $message);
    }
}

final class Boundary
{
    /** PHP -> JX. Values cross; resources and arbitrary live objects do not. */
    public static function import(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 32) throw new JxException('Boundary nesting too deep', 'boundary', true);
        if ($value instanceof ConstBox || $value instanceof Complex || $value instanceof Bag) return $value;
        if (is_resource($value) || $value instanceof \Closure) {
            throw new JxException('Resource or Closure cannot cross the JX boundary', 'boundary', true);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) $out[is_int($key) ? $key : (string)$key] = self::import($item, $depth + 1);
            return $out;
        }
        if (is_object($value)) {
            if ($value instanceof \JsonSerializable) return self::import($value->jsonSerialize(), $depth + 1);
            throw new JxException('Unapproved object cannot cross the JX boundary', 'boundary', true, ['class' => $value::class]);
        }
        return $value;
    }

    /** JX -> host. Keep the result data-shaped. */
    public static function export(mixed $value): mixed
    {
        if ($value instanceof ConstBox) return self::export($value->value);
        if ($value instanceof Bag) return $value->all();
        if ($value instanceof Complex) return ['re' => $value->re, 'im' => $value->im];
        return $value;
    }
}

final class Complex
{
    public function __construct(public float $re = 0.0, public float $im = 0.0) {}
    public static function of(float $re, float $im = 0.0): self { return new self($re, $im); }

    public static function parse(string $source): self
    {
        $source = str_replace(' ', '', strtolower(trim($source)));
        if ($source === '' || $source === '0') return new self();
        if ($source === 'i') return new self(0.0, 1.0);
        if ($source === '-i') return new self(0.0, -1.0);
        if (is_numeric($source)) return new self((float)$source, 0.0);
        if (preg_match('/^([+-]?\d+(?:\.\d+)?)?([+-]\d*(?:\.\d+)?)i$/', $source, $m)) {
            $re = ($m[1] ?? '') === '' ? 0.0 : (float)$m[1];
            $part = $m[2] ?? '';
            $im = ($part === '+' || $part === '') ? 1.0 : ($part === '-' ? -1.0 : (float)$part);
            return new self($re, $im);
        }
        throw new JxException("Invalid complex literal: {$source}", 'complex', true);
    }

    public function add(self $other): self { return new self($this->re + $other->re, $this->im + $other->im); }
    public function sub(self $other): self { return new self($this->re - $other->re, $this->im - $other->im); }
    public function mul(self $other): self { return new self($this->re*$other->re - $this->im*$other->im, $this->re*$other->im + $this->im*$other->re); }
    public function conj(): self { return new self($this->re, -$this->im); }
    public function mag(): float { return hypot($this->re, $this->im); }

    public function __toString(): string
    {
        if ($this->im == 0.0) return (string)$this->re;
        $imaginary = abs($this->im);
        $is = $imaginary == 1.0 ? 'i' : "{$imaginary}i";
        if ($this->re == 0.0) return ($this->im < 0 ? '-' : '') . $is;
        return $this->re . ($this->im >= 0 ? '+' : '-') . $is;
    }
}

final class RefSign
{
    public function __construct(
        public readonly int $bagId,
        public readonly string $node,
        public readonly string $token,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
        public readonly int $generation,
        public readonly bool $oneShot,
    ) {}

    public function matches(Bag $bag, string $token): bool
    {
        return $this->bagId === $bag->id() && hash_equals($this->token, $token) && $bag->isLiveRef($this);
    }
}

class Bag
{
    private static int $nextId = 1;
    private int $id;
    private int $used = 0;
    /** @var array<string,mixed> */ private array $cells = [];
    /** @var array<string,mixed> */ private array $props = [];
    /** @var array<string,RefSign> */ private array $refs = [];
    /** @var array<string,array{node:string,expires:int,generation:int,oneShot:bool}> */ private array $liveTokens = [];
    /** @var array<string,int> */ private array $generations = [];

    protected function __construct(private int $capacity)
    {
        if ($capacity < 0) throw new JxException('Bag capacity must be non-negative', 'bag');
        $this->id = self::$nextId++;
    }

    public static function underwrite(int $size): static { return new static($size); }
    public static function empty(int $size = 65_536): static { return static::underwrite($size); }

    public static function from(array $data, ?int $capacity = null): static
    {
        $bag = static::underwrite($capacity ?? max(1024, strlen(serialize($data)) * 2 + 256));
        foreach ($data as $node => $value) $bag->write((string)$node, $value);
        return $bag;
    }

    public static function fromJson(string $json, ?int $capacity = null): static
    {
        $data = json_decode($json, true);
        return static::from(is_array($data) ? $data : [], $capacity);
    }

    public function toJson(): string
    {
        return json_encode($this->cells, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function id(): int { return $this->id; }
    public function capacity(): int { return $this->capacity; }
    public function used(): int { return $this->used; }
    public function quotient(): int { return max(0, $this->capacity - $this->used); }

    /** sign($place, $seconds, $oneShot): name the place before you change the place. */
    public function sign(string $node, int $ttlSeconds = 300, bool $oneShot = false): RefSign
    {
        $node = $this->normalizeNode($node);
        if (isset($this->refs[$node])) $this->unsign($this->refs[$node]);
        $generation = ($this->generations[$node] ?? 0) + 1;
        $this->generations[$node] = $generation;
        $now = time();
        $token = bin2hex(random_bytes(32));
        $ref = new RefSign($this->id, $node, $token, $now, $ttlSeconds > 0 ? $now + $ttlSeconds : 0, $generation, $oneShot);
        $this->refs[$node] = $ref;
        $this->liveTokens[$token] = ['node'=>$node, 'expires'=>$ref->expiresAt, 'generation'=>$generation, 'oneShot'=>$oneShot];
        return $ref;
    }

    /** write($place, $value): place first, value second. */
    public function write(string $node, mixed $value, int $ttlSeconds = 30): void
    {
        $ref = $this->sign($node, $ttlSeconds, true);
        $this->set($value, $node)->commit($ref);
    }

    public function read(string $node, mixed $default = null): mixed
    {
        return array_key_exists($node, $this->cells) ? $this->cells[$node] : $default;
    }

    public function unsign(RefSign $ref): void
    {
        if ($ref->bagId !== $this->id) throw new JxException('RefSign does not belong to this Bag', 'bag', true);
        unset($this->liveTokens[$ref->token]);
        if (($this->refs[$ref->node] ?? null)?->token === $ref->token) unset($this->refs[$ref->node]);
    }

    public function expireRefs(?int $now = null): int
    {
        $now ??= time();
        $count = 0;
        foreach ($this->liveTokens as $token => $meta) {
            if ($meta['expires'] > 0 && $meta['expires'] <= $now) {
                unset($this->liveTokens[$token]);
                if (($this->refs[$meta['node']] ?? null)?->token === $token) unset($this->refs[$meta['node']]);
                $count++;
            }
        }
        return $count;
    }

    public function isLiveRef(RefSign $ref): bool
    {
        $this->expireRefs();
        $meta = $this->liveTokens[$ref->token] ?? null;
        return $ref->bagId === $this->id
            && $meta !== null
            && $meta['node'] === $ref->node
            && $meta['generation'] === $ref->generation
            && ($this->generations[$ref->node] ?? -1) === $ref->generation;
    }

    public function set(mixed $value, ?string $node = null): BagWrite
    {
        return new BagWrite($this, $value, $node);
    }

    /** @internal */
    public function commitWrite(BagWrite $pending, RefSign $ref): void
    {
        $node = $pending->node === null ? $ref->node : $this->normalizeNode($pending->node);
        $this->assertRef($ref, $node);
        $value = Boundary::import($pending->data);
        $newSize = $this->sizeOf($value);
        $oldSize = array_key_exists($node, $this->cells) ? $this->sizeOf($this->cells[$node]) : 0;
        $delta = $newSize - $oldSize;
        if ($delta > 0 && $this->quotient() < $delta) {
            throw new JxException("Bag overflow: need {$delta} bytes, quotient {$this->quotient()}", 'bag', true, ['bag'=>$this->id, 'node'=>$node]);
        }
        $this->cells[$node] = $value;
        $this->used += $delta;
        if ($ref->oneShot) $this->unsign($ref);
    }

    public function get(RefSign $ref, ?string $node = null): mixed
    {
        $node = $node === null ? $ref->node : $this->normalizeNode($node);
        $this->assertRef($ref, $node);
        return $this->cells[$node] ?? null;
    }

    public function peek(string $node = '_default'): mixed { return $this->cells[$node] ?? null; }
    public function has(string $node): bool { return array_key_exists($node, $this->cells); }
    /** @return list<string> */ public function keys(): array { return array_keys($this->cells); }
    /** @return array<string,mixed> */ public function all(): array { return $this->cells; }

    public function push(string $key, mixed $value): void
    {
        $key = $this->normalizeNode($key);
        $value = Boundary::import($value);
        $old = array_key_exists($key, $this->props) ? $this->sizeOf($key) + $this->sizeOf($this->props[$key]) : 0;
        $new = $this->sizeOf($key) + $this->sizeOf($value);
        $delta = $new - $old;
        if ($delta > 0 && $this->quotient() < $delta) throw new JxException('Bag overflow on push', 'bag', true);
        $this->used += $delta;
        $this->props[$key] = $value;
    }

    public function prop(string $key): mixed { return $this->props[$key] ?? null; }

    public function absorb(Bag $source, ?array $keys = null, string $prefix = ''): void
    {
        foreach ($keys ?? $source->keys() as $key) {
            $key = (string)$key;
            if ($source->has($key)) $this->write($prefix . $key, $source->peek($key));
        }
    }

    public function tell(string $op, mixed ...$args): mixed
    {
        return match (strtolower($op)) {
            'push' => (function () use ($args) { $this->push((string)$args[0], $args[1] ?? null); return $this; })(),
            'sign' => $this->sign((string)($args[0] ?? '_default')),
            'unsign' => (function () use ($args) { $this->unsign($args[0]); return $this; })(),
            'set' => $this->set($args[0] ?? null, isset($args[1]) ? (string)$args[1] : null),
            'get' => $this->get($args[0], isset($args[1]) ? (string)$args[1] : null),
            'write' => (function () use ($args) { $this->write((string)$args[0], $args[1] ?? null); return $this; })(),
            'read' => $this->read((string)$args[0], $args[1] ?? null),
            'quotient' => $this->quotient(),
            'capacity' => $this->capacity(),
            'used' => $this->used(),
            'id' => $this->id(),
            default => throw new JxException("Unknown Bag op: {$op}", 'bag', true),
        };
    }

    private function assertRef(RefSign $ref, string $node): void
    {
        if (!$this->isLiveRef($ref)) throw new JxException('Dead, expired, superseded, or foreign RefSign — write denied', 'bag', true);
        if (!hash_equals($ref->node, $node)) throw new JxException("RefSign for {$ref->node} cannot authorize {$node}", 'bag', true);
    }

    private function normalizeNode(string $node): string
    {
        $node = trim($node);
        if ($node === '' || strlen($node) > 256 || str_contains($node, "\0")) throw new JxException('Invalid Bag node', 'bag', true);
        return $node;
    }

    private function sizeOf(mixed $data): int
    {
        if (is_string($data)) return strlen($data);
        if (is_int($data) || is_float($data) || is_bool($data) || $data === null) return 8;
        if ($data instanceof Complex) return 16;
        if ($data instanceof ConstBox) return $this->sizeOf($data->value);
        if (is_array($data)) {
            $size = 16;
            foreach ($data as $key => $value) $size += $this->sizeOf($key) + $this->sizeOf($value);
            return $size;
        }
        return strlen(serialize($data));
    }
}

final class BagWrite
{
    public function __construct(public readonly Bag $bag, public readonly mixed $data, public readonly ?string $node = null) {}
    public function commit(RefSign $ref): void { $this->bag->commitWrite($this, $ref); }
    public function pass(RefSign $ref): void { $this->commit($ref); }
}

final class Task extends Bag
{
    private string $state = 'ready';
    private const TRANSITIONS = [
        'ready'=>['running','error'],
        'running'=>['yielded','done','error'],
        'yielded'=>['running','done','error'],
        'done'=>[],
        'error'=>[],
    ];

    private function __construct(int $capacity, private string $name) { parent::__construct($capacity); $this->push('_task_name', $name); }
    public static function underwrite(int $size, string $name = 'task'): static { return new static($size, $name); }
    public function name(): string { return $this->name; }
    public function state(): string { return $this->state; }

    public function setState(string $state): void
    {
        if ($state === $this->state) return;
        if (!in_array($state, self::TRANSITIONS[$this->state] ?? [], true)) {
            throw new JxException("Invalid Task transition {$this->state} -> {$state}", 'schedule', true);
        }
        $this->state = $state;
    }

    public function yieldTask(): void { $this->setState('yielded'); }
}

final class Page
{
    /** @var callable|null */ private $entry;
    private function __construct(private Task $task, ?callable $entry) { $this->entry = $entry; }

    public static function spawn(?callable $entry = null, ?Bag $bag = null, int $size = 65_536, string $name = 'page'): self
    {
        $task = Task::underwrite($size, $name);
        if ($bag !== null) $task->push('_display_bag_id', $bag->id());
        return new self($task, $entry);
    }

    public function task(): Task { return $this->task; }
    public function id(): int { return $this->task->id(); }

    public function run(): mixed
    {
        if ($this->entry === null) return null;
        $this->task->setState('running');
        try {
            $result = ($this->entry)($this->task);
            if ($this->task->state() === 'running') $this->task->setState('done');
            return $result;
        } catch (\Throwable $error) {
            if ($this->task->state() !== 'error') $this->task->setState('error');
            throw $error;
        }
    }
}

class Book
{
    /** @var array<string,Page> */ private array $pages = [];
    /** @var array<string,Bag> */ private array $bags = [];
    /** @var array<string,array<string,mixed>> */ private array $libraries = [];
    private int $memoryUsed = 0;
    private array $config = [];
    private array $binding = [];
    private string $revision = '';
    private ?string $root = null;

    protected function __construct(private string $name, private int $memoryQuota, ?string $root = null, array $config = [])
    {
        $this->root = $root;
        $this->config = Boundary::import($config);
        $this->rehash();
    }

    public static function open(string $name, int $memoryQuota = 8_388_608): static { return new static($name, $memoryQuota); }

    public static function load(string $booksRoot, string $id, int $memoryQuota = 8_388_608): ?static
    {
        $id = preg_replace('/[^a-z0-9_-]/i', '', $id) ?? '';
        if ($id === '') return null;
        $root = rtrim($booksRoot, '/\\') . DIRECTORY_SEPARATOR . $id;
        $configFile = $root . DIRECTORY_SEPARATOR . 'book.json';
        if (!is_file($configFile)) return null;
        $config = json_decode((string)file_get_contents($configFile), true);
        if (!is_array($config)) return null;
        $book = new static($id, (int)($config['memory_quota'] ?? $memoryQuota), $root, $config);
        foreach ((array)($config['libraries'] ?? []) as $libraryName => $library) {
            if (is_string($library)) $book->registerLibrary((string)$libraryName, $library);
            elseif (is_array($library) && isset($library['path'])) {
                $book->registerLibrary((string)$libraryName, (string)$library['path'], (string)($library['kind'] ?? 'pasl'), $library);
            }
        }
        return $book;
    }

    public function name(): string { return $this->name; }
    public function id(): string { return $this->name; }
    public function root(): string { return $this->root ?? ''; }
    public function quota(): int { return $this->memoryQuota; }
    public function usedQuota(): int { return $this->memoryUsed; }
    public function quotient(): int { return max(0, $this->memoryQuota - $this->memoryUsed); }
    public function revision(): string { return $this->revision; }

    public function registerBag(string $name, Bag $bag): void
    {
        $old = $this->bags[$name] ?? null;
        $base = $this->memoryUsed - ($old?->capacity() ?? 0);
        if ($base + $bag->capacity() > $this->memoryQuota) throw new JxException('Book memory quota exceeded', 'book', true);
        $this->bags[$name] = $bag;
        $this->memoryUsed = $base + $bag->capacity();
        $this->rehash();
    }

    public function bag(string $name): Bag { return $this->bags[$name] ?? throw new JxException("Unknown Bag {$name}", 'book'); }
    /** @return array<string,Bag> */ public function bags(): array { return $this->bags; }

    public function registerPage(string $name, Page $page): void { $this->pages[$name] = $page; $this->rehash(); }
    public function page(string $name): Page { return $this->pages[$name] ?? throw new JxException("Unknown Page {$name}", 'book'); }
    public function pages(): array { return $this->pages; }

    /** library($name, $path, $kind, $options): name it, point to it, say what it is. */
    public function registerLibrary(string $name, string $path, string $kind = 'pasl', array $options = []): void
    {
        $clean = preg_replace('/[^a-z0-9._-]/i', '', $name) ?? '';
        if ($clean === '') throw new JxException('Invalid library name', 'book', true);
        $this->libraries[$clean] = ['name'=>$clean, 'path'=>$path, 'kind'=>$kind, 'options'=>Boundary::import($options)];
        $this->rehash();
    }

    public function libraries(): array { return $this->libraries; }
    public function configure(array $config): void { $this->config = Boundary::import($config); $this->rehash(); }
    public function config(): array { return $this->config; }
    public function spine(): array { return array_values(array_map('strval', is_array($this->config['spine'] ?? null) ? $this->config['spine'] : ['home'])); }
    public function leafMeta(): array { return is_array($this->config['leaves'] ?? null) ? $this->config['leaves'] : []; }
    public function tables(): array { return is_array($this->config['tables'] ?? null) ? $this->config['tables'] : []; }
    public function window(): array { return is_array($this->config['window'] ?? null) ? $this->config['window'] : []; }
    public function dropsEnabled(): bool { return !empty($this->config['drops']['enabled']); }
    public function dropChannel(): string { return (string)($this->config['drops']['channel'] ?? 'drop'); }

    public function bindState(array $snapshot): void { $this->binding = Boundary::import($snapshot); $this->rehash(); }
    public function bindingState(): array { return $this->binding; }

    public function assertRevision(string $revision): void
    {
        if (!hash_equals($this->revision, $revision)) {
            throw new JxException('Book revision conflict', 'book-version', true, ['expected'=>$revision, 'actual'=>$this->revision]);
        }
    }

    public function paslPath(string $leafId): ?string
    {
        $meta = $this->leafMeta()[$leafId] ?? [];
        $relative = is_array($meta) ? (string)($meta['pasl'] ?? '') : '';
        return $relative === '' ? null : $this->jailedPath($relative, $this->root ?? '');
    }

    public function pagePath(string $leafId): ?string
    {
        $leaf = preg_replace('/[^a-z0-9_-]/i', '', $leafId) ?? '';
        return $leaf === '' ? null : $this->jailedPath('pages/' . $leaf . '.php', ($this->root ?? '') . '/pages');
    }

    public function protocolPath(string $name): ?string
    {
        $protocol = preg_replace('/[^a-z0-9._-]/i', '', $name) ?? '';
        return $protocol === '' ? null : $this->jailedPath('protocol/' . $protocol . '.php', ($this->root ?? '') . '/protocol');
    }

    public function dataDir(string $siteData): string { return rtrim($siteData, '/\\') . '/books/' . $this->name; }
    public function inboxDir(string $siteData): string { return $this->dataDir($siteData) . '/inbox'; }
    public function channelsDir(string $siteData): string { return $this->dataDir($siteData) . '/channels'; }
    public function bindingPath(string $siteData): string { return $this->dataDir($siteData) . '/binding.json'; }

    /** The compiler-visible meat of a Book: libraries first, then PASL leaves. */
    public function artifactPlan(): array
    {
        $plan = [];
        foreach ($this->libraries as $name => $library) {
            $plan[] = ['name'=>$name, 'path'=>(string)$library['path'], 'kind'=>(string)$library['kind'], 'scope'=>'library', 'meta'=>$library['options']];
        }
        foreach ($this->leafMeta() as $leaf => $meta) {
            if (is_array($meta) && isset($meta['pasl'])) {
                $plan[] = ['name'=>'leaf.' . (string)$leaf, 'path'=>(string)$meta['pasl'], 'kind'=>'pasl', 'scope'=>'leaf', 'meta'=>$meta];
            }
        }
        return $plan;
    }

    private function jailedPath(string $relative, string $jailRoot): ?string
    {
        if ($this->root === null || str_contains($relative, "\0")) return null;
        $file = $this->root . '/' . ltrim(str_replace('\\', '/', $relative), '/');
        $fileReal = realpath($file);
        $rootReal = realpath($jailRoot ?: $this->root);
        if ($fileReal === false || $rootReal === false) return null;
        return ($fileReal === $rootReal || str_starts_with($fileReal, rtrim($rootReal, '/\\') . DIRECTORY_SEPARATOR)) ? $fileReal : null;
    }

    private function rehash(): void
    {
        $shape = [
            'name'=>$this->name,
            'quota'=>$this->memoryQuota,
            'bags'=>array_map(fn(Bag $bag) => [$bag->id(), $bag->capacity()], $this->bags),
            'pages'=>array_keys($this->pages),
            'libraries'=>$this->libraries,
            'config'=>$this->config,
            'binding'=>$this->binding,
        ];
        $this->revision = substr(hash('sha256', serialize($shape)), 0, 32);
    }
}

final class Delivery
{
    /** @return list<string> */
    public static function splitPath(string $path): array
    {
        $path = trim($path, ". \t");
        return $path === '' ? [] : explode('.', $path);
    }

    public static function extract(mixed $root, array|string $path, mixed $default = null): mixed
    {
        if ($root instanceof ConstBox) $root = $root->value;
        $keys = is_string($path) ? self::splitPath($path) : $path;
        $current = $root;
        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) { $current = $current[$key]; continue; }
            if (is_object($current) && (isset($current->$key) || method_exists($current, '__get'))) { $current = $current->$key; continue; }
            return $default;
        }
        return $current;
    }

    public static function rebind(array|ConstBox $root, array|string $path, mixed $value): array
    {
        if ($root instanceof ConstBox) throw new JxException('Delivery cannot rebind a const root', 'const', true);
        $keys = is_string($path) ? self::splitPath($path) : $path;
        if ($keys === []) throw new JxException('Empty Delivery path', 'delivery', true);
        $out = $root;
        $cursor = &$out;
        $last = count($keys) - 1;
        foreach ($keys as $index => $key) {
            if ($index === $last) { $cursor[$key] = Boundary::import($value); break; }
            if (!isset($cursor[$key]) || !is_array($cursor[$key])) $cursor[$key] = [];
            $cursor = &$cursor[$key];
        }
        return $out;
    }
}

final class ConstBox
{
    public function __construct(public readonly mixed $value) {}
    public static function wrap(mixed $value): self { return new self(Boundary::import($value)); }
}

function jx_const(mixed $value): ConstBox { return ConstBox::wrap($value); }

final class SmartTable
{
    /** @var array<string,array<string,mixed>> */ private array $rows = [];

    public function __construct()
    {
        $definitions = [
            ['bag.underwrite','Bag','none',false,'underwritten-only',1.00,'inline','checked-longform'],
            ['bag.sign','Bag','read',false,'underwritten-only',0.95,'capability','checked-longform'],
            ['bag.set','Bag','write-bag',true,'underwritten-only',0.90,'inline','checked-longform'],
            ['bag.commit','Bag','write-bag',true,'underwritten-only',0.90,'inline','checked-longform'],
            ['bag.write','Bag','write-bag',false,'underwritten-only',0.92,'inline','checked-longform'],
            ['bag.quotient','Bag','read',false,'pure',1.00,'inline','checked-longform'],
            ['task.push','Task','write-bag',false,'task-local',0.95,'inline','checked-longform'],
            ['task.id','Task','read',false,'pure',1.00,'inline','checked-longform'],
            ['page.spawn','Page','schedule',false,'task-local',0.85,'runtime','checked-longform'],
            ['book.open','Book','none',false,'book-local',1.00,'runtime','checked-longform'],
            ['book.library','Book','io',false,'book-local',0.85,'runtime','checked-longform'],
            ['delivery.extract','Delivery','read',false,'pure',0.80,'inline','checked-longform'],
            ['delivery.rebind','Delivery','none',false,'pure',0.75,'inline','checked-longform'],
            ['complex.parse','Complex','none',false,'pure',1.00,'inline','checked-longform'],
        ];
        foreach ($definitions as [$id,$module,$side,$requiresRef,$memory,$purity,$native,$resistant]) {
            $this->rows[$id] = [
                'id'=>$id, 'name'=>$id, 'module'=>$module, 'side_effect'=>$side,
                'requires_ref'=>$requiresRef, 'memory_class'=>$memory, 'purity_score'=>$purity,
                'native_template'=>$native, 'resistant_template'=>$resistant,
            ];
        }
    }

    public function get(string $id): ?array { return $this->rows[$id] ?? null; }
    public function all(): array { return $this->rows; }

    public function extrude(string $id, array $facts = []): array
    {
        $row = $this->get($id);
        if ($row === null) {
            return ['mode'=>'resistant', 'reason'=>'unknown_method', 'event'=>Resistant::mark('unknown_method', $id, $facts), 'id'=>$id];
        }
        if ($row['requires_ref'] && !($facts['live_ref'] ?? false)) {
            return ['mode'=>'resistant', 'reason'=>'missing_live_ref', 'event'=>Resistant::mark('missing_live_ref', $id, $facts), 'row'=>$row];
        }
        if (($facts['dynamic_shape'] ?? false) || $row['purity_score'] < 0.85) {
            return ['mode'=>'resistant', 'reason'=>'checked_fallback', 'event'=>Resistant::mark('checked_fallback', $id, $facts), 'row'=>$row];
        }
        return ['mode'=>'native', 'row'=>$row];
    }
}

final class Sym
{
    public const STDIN=0, STDOUT=1, STDERR=2, EXIT_SUCCESS=0, EXIT_FAILURE=1;
    public const SYS_READ=0, SYS_WRITE=1, SYS_OPEN=2, SYS_CLOSE=3, SYS_MMAP=9, SYS_MUNMAP=11, SYS_EXIT=60;
    public const O_RDONLY=0, O_WRONLY=1, O_RDWR=2, O_CREAT=0x40, O_TRUNC=0x200, O_APPEND=0x400;
    public const PROT_READ=1, PROT_WRITE=2, PROT_EXEC=4, MAP_PRIVATE=0x02, MAP_ANONYMOUS=0x20;
}

final class Jx
{
    private static ?SmartTable $table = null;
    public static function table(): SmartTable { return self::$table ??= new SmartTable(); }
    public static function bag(int $size): Bag { return Bag::underwrite($size); }
    public static function task(int $size, string $name = 'task'): Task { return Task::underwrite($size, $name); }
    public static function page(?callable $entry = null, int $size = 65_536, string $name = 'page'): Page { return Page::spawn($entry, null, $size, $name); }
    public static function book(string $name, int $quota = 8_388_608): Book { return Book::open($name, $quota); }
    public static function delivery(mixed $root, array|string $path, mixed $default = null): mixed { return Delivery::extract($root, $path, $default); }
    public static function complex(float $re, float $im = 0.0): Complex { return Complex::of($re, $im); }
}
