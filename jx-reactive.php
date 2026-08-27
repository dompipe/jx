<?php declare(strict_types=1);

namespace jx;

require_once __DIR__ . '/jx-environment.php';
require_once __DIR__ . '/jx-sql.php';
require_once __DIR__ . '/jx-bag-containers.php';

use InvalidArgumentException;
use RuntimeException;

interface ReactiveSource
{
    public function id(): string;
    public function revision(): int;
    public function value(): mixed;
    public function refresh(): bool;
    public function subscribe(callable $listener): int;
    public function unsubscribe(int $subscription): void;
}

abstract class AbstractReactiveSource implements ReactiveSource
{
    protected int $revision = 0;
    protected mixed $current = null;
    /** @var array<int,callable(ReactiveSource):void> */
    private array $listeners = [];
    private int $nextSubscription = 1;

    public function __construct(protected readonly string $sourceId) {}
    public function id(): string { return $this->sourceId; }
    public function revision(): int { return $this->revision; }
    public function value(): mixed { return $this->current; }

    public function subscribe(callable $listener): int
    {
        $id = $this->nextSubscription++;
        $this->listeners[$id] = $listener;
        return $id;
    }

    public function unsubscribe(int $subscription): void { unset($this->listeners[$subscription]); }

    protected function publish(mixed $value): bool
    {
        if ($this->same($this->current, $value)) return false;
        $this->current = $value;
        $this->revision++;
        foreach ($this->listeners as $listener) $listener($this);
        return true;
    }

    protected function same(mixed $a, mixed $b): bool
    {
        if (is_resource($a) || is_resource($b)) return $a === $b;
        return serialize($a) === serialize($b);
    }

    public static function stableId(string $domain, string $name): string
    {
        return strtolower($domain) . ':' . hash('xxh3', strtolower($domain) . "\0" . $name);
    }
}

/** Manually-pushed source for host events, sockets, timers, sensors, etc. */
final class MutableSource extends AbstractReactiveSource
{
    public function __construct(string $name, mixed $initial = null)
    {
        parent::__construct(self::stableId('mutable', $name));
        $this->current = $initial;
    }
    public function set(mixed $value): bool { return $this->publish($value); }
    public function refresh(): bool { return false; }
}

/** Pull source for arbitrary adapters. */
final class CallbackSource extends AbstractReactiveSource
{
    public function __construct(string $name, private readonly \Closure $reader)
    {
        parent::__construct(self::stableId('callback', $name));
        $this->current = ($this->reader)();
    }
    public function refresh(): bool { return $this->publish(($this->reader)()); }
}

/** Reactive view of an existing Bag container revision/payload. */
final class BagReactiveSource extends AbstractReactiveSource
{
    private int $seenRevision = -1;
    public function __construct(string $name, private readonly BagContainerContract $container)
    {
        parent::__construct(self::stableId('bag', $name));
        $this->refresh();
    }
    public function refresh(): bool
    {
        $canonical = $this->container->canonical();
        $rev = (int)($canonical['revision'] ?? 0);
        if ($rev === $this->seenRevision) return false;
        $this->seenRevision = $rev;
        return $this->publish($canonical['payload'] ?? $this->container->toArray());
    }
}

/** Filesystem source. Watching is host-specific; refresh() is the canonical poll boundary. */
class FileReactiveSource extends AbstractReactiveSource
{
    protected EnvironmentProfile $environment;
    public function __construct(string $path, ?EnvironmentProfile $environment = null)
    {
        $this->environment = $environment ?? EnvironmentProfile::cli('jx-reactive-file');
        $this->environment->require(Capability::FILESYSTEM, 'REACTIVE.FILE');
        parent::__construct(self::stableId('file', $path));
        $this->path = $path;
        $this->refresh();
    }

    protected string $path;
    public function path(): string { return $this->path; }
    public function refresh(): bool
    {
        clearstatcache(true, $this->path);
        $exists = is_file($this->path);
        $snapshot = ['path'=>$this->path, 'exists'=>$exists, 'size'=>null, 'mtime'=>null];
        if ($exists) {
            $snapshot['size'] = filesize($this->path);
            $snapshot['mtime'] = filemtime($this->path);
        }
        return $this->publish($snapshot);
    }
}

/** Media source adds media-oriented metadata while preserving file identity/revision semantics. */
final class MediaReactiveSource extends FileReactiveSource
{
    public function __construct(string $path, ?EnvironmentProfile $environment = null)
    {
        parent::__construct($path, $environment);
    }
    public function refresh(): bool
    {
        clearstatcache(true, $this->path);
        $exists = is_file($this->path);
        $mime = null;
        if ($exists && function_exists('mime_content_type')) $mime = @mime_content_type($this->path) ?: null;
        $snapshot = [
            'path'=>$this->path,
            'exists'=>$exists,
            'size'=>$exists ? filesize($this->path) : null,
            'mtime'=>$exists ? filemtime($this->path) : null,
            'mime'=>$mime,
            'extension'=>strtolower(pathinfo($this->path, PATHINFO_EXTENSION)),
        ];
        return $this->publish($snapshot);
    }
}

/** SQL query source. refresh() reruns a prepared query and only publishes on result change. */
final class SqlReactiveSource extends AbstractReactiveSource
{
    public function __construct(
        string $name,
        private readonly JxSql $sql,
        private readonly string $query,
        private array $params = [],
    ) {
        parent::__construct(self::stableId('sql', $name . "\0" . $query));
        $this->refresh();
    }
    public function params(array $params): self { $this->params = array_values($params); return $this; }
    public function refresh(): bool
    {
        $result = $this->sql->query($this->query, $this->params);
        return $this->publish(['rows'=>$result->rows, 'columns'=>$result->columns, 'row_count'=>$result->rowCount]);
    }
}

/** Derived node: only recomputes when one of its direct dependencies changes. */
final class DerivedReactiveSource extends AbstractReactiveSource
{
    /** @var list<ReactiveSource> */
    private array $dependencies;
    /** @var array<string,int> */
    private array $subscriptions = [];
    private bool $dirty = true;
    private int $runs = 0;

    /** @param list<ReactiveSource> $dependencies */
    public function __construct(string $name, array $dependencies, private readonly \Closure $compute)
    {
        if ($dependencies === []) throw new InvalidArgumentException('Derived reactive source needs at least one dependency');
        foreach ($dependencies as $dep) if (!$dep instanceof ReactiveSource) throw new InvalidArgumentException('Invalid reactive dependency');
        parent::__construct(self::stableId('derived', $name));
        $this->dependencies = array_values($dependencies);
        foreach ($this->dependencies as $dep) {
            $this->subscriptions[$dep->id()] = $dep->subscribe(function(): void { $this->dirty = true; });
        }
        $this->refresh();
    }

    public function runs(): int { return $this->runs; }
    public function refresh(): bool
    {
        if (!$this->dirty) return false;
        $this->dirty = false;
        $this->runs++;
        $values = array_map(static fn(ReactiveSource $s) => $s->value(), $this->dependencies);
        return $this->publish(($this->compute)(...$values));
    }
}

/** Canonical graph/refresh boundary. Hosts may call refreshSource from native watchers/events. */
final class ReactiveGraph
{
    /** @var array<string,ReactiveSource> */
    private array $sources = [];

    public function add(ReactiveSource $source): ReactiveSource
    {
        if (isset($this->sources[$source->id()]) && $this->sources[$source->id()] !== $source) {
            throw new RuntimeException('Reactive source identity collision: ' . $source->id());
        }
        return $this->sources[$source->id()] = $source;
    }

    public function source(string $id): ReactiveSource
    {
        return $this->sources[$id] ?? throw new RuntimeException("Unknown reactive source {$id}");
    }

    /** Pull all sources, then settle derived nodes until no dirty value changes remain. */
    public function refresh(): int
    {
        $changes = 0;
        foreach ($this->sources as $source) if (!$source instanceof DerivedReactiveSource && $source->refresh()) $changes++;
        foreach ($this->sources as $source) if ($source instanceof DerivedReactiveSource && $source->refresh()) $changes++;
        return $changes;
    }

    public function refreshSource(ReactiveSource $source): bool
    {
        $changed = $source->refresh();
        foreach ($this->sources as $candidate) if ($candidate instanceof DerivedReactiveSource) $candidate->refresh();
        return $changed;
    }

    /** @return array<string,array{revision:int,type:string}> */
    public function status(): array
    {
        $out = [];
        foreach ($this->sources as $id=>$source) $out[$id] = ['revision'=>$source->revision(), 'type'=>$source::class];
        return $out;
    }
}
