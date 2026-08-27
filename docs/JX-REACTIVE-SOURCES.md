# JX Reactive Sources

JX treats external and mutable data as canonical reactive sources rather than host-specific callbacks.

A reactive source has four machine-meaningful properties:

```text
stable source identity
current revision
current canonical value
refresh / publish boundary
```

Derived execution shadows subscribe to those identities. If a source revision changes, only the shadows that depend on that source become dirty. An unrelated source change does not rerun them.

## Canonical source contract

`ReactiveSource` exposes:

```php
id(): string
revision(): int
value(): mixed
refresh(): bool
subscribe(callable $listener): int
unsubscribe(int $subscription): void
```

The source identity is stable and domain separated. Runtime revisions are monotonic. A refresh only publishes when the canonical value actually changes.

This allows the source mechanism to stay independent of the host that detects change.

A browser can drive `refresh()` from DOM/media/network events. A server can drive it from database notifications, file watchers, sockets, queues, or polling. A native host can bind operating-system events. The JX dependency graph remains the same.

## Mutable and callback sources

`MutableSource` is the canonical push boundary for host events, sockets, timers, sensors, and other externally supplied values.

```php
$temperature = new MutableSource('temperature', 21);
$temperature->set(22);
```

`CallbackSource` represents arbitrary pull adapters:

```php
$source = new CallbackSource(
    'process-state',
    fn () => read_current_state()
);

$source->refresh();
```

## Bag sources

`BagReactiveSource` watches the canonical revision of any Bag container.

```php
$players = BagContainers::vector(65536, 'Player');
$source = new BagReactiveSource('players', $players);

$players->append($player);
$source->refresh();
```

If the Bag revision has not changed, no reactive revision is emitted.

## File and media sources

`FileReactiveSource` exposes canonical file metadata at the refresh boundary:

```text
path
exists
size
mtime
```

`MediaReactiveSource` extends that state with media-oriented metadata:

```text
MIME type
extension
```

Example:

```php
$video = new MediaReactiveSource('/media/intro.mp4');
$video->refresh();
```

JX does not require polling as the final implementation. Polling is only one host adapter. A filesystem watcher can invoke the same refresh boundary without changing the program's source semantics.

## SQL sources

`SqlReactiveSource` binds a prepared JX SQL query to the reactive graph.

```php
$db = new JxSql($config);

$orders = new SqlReactiveSource(
    'open-orders',
    $db,
    'SELECT id,total FROM orders WHERE status = ? ORDER BY id',
    ['open']
);
```

Refreshing the source reruns the prepared query. A new reactive revision is published only if rows/columns/result state changed.

That means SQL can participate in the same dependency graph as Bags, media, files, or host events.

## Derived shadows

`DerivedReactiveSource` is the first canonical selective execution shadow.

```php
$total = new DerivedReactiveSource(
    'cart-total',
    [$prices, $quantities],
    fn ($p, $q) => calculate_total($p, $q)
);
```

If `$prices` changes, the node becomes dirty. If an unrelated media or socket source changes, it does not.

The implementation tracks `runs()` so regression tests can prove selective invalidation rather than only verify output.

This is the execution rule JX should preserve as these nodes become PASM/native shadows:

```text
source revision changes
        ↓
mark dependent shadow dirty
        ↓
rerun only affected shadow
        ↓
publish new revision only if output changed
```

## Reactive graph

`ReactiveGraph` owns canonical sources and provides a host-neutral refresh boundary.

```php
$graph = new ReactiveGraph();
$graph->add($sqlSource);
$graph->add($mediaSource);
$graph->add($derived);

$graph->refresh();
```

Hosts may also refresh a single known source when they already know what changed.

The architectural target is for compiled JX/PASL shadows to carry the stable IDs of their source dependencies. At that point a source revision invalidates only the PASM/native block that consumes it; PHP remains the canonical fallback/compiler host rather than a required hot-path interpreter.

## Things Addressed

- SQL results use the same reactive contract as other sources.
- Bag container revisions are reactive without duplicating container state.
- File and media metadata can trigger reactive revisions.
- Arbitrary host events can enter through mutable/callback sources.
- Derived nodes rerun only when direct dependencies change.
- Unchanged refreshed data does not increment revisions.
- Reactive identity is host-neutral and stable.
- Host watchers/pollers are adapters, not alternate language semantics.
- The regression harness proves selective rerun behavior.

## Post-chapter test

Run:

```bash
php test-jx-reactive.php
```

The harness exercises mutable sources, Bag revisions, file/media changes, prepared SQL query refresh, selective dependency invalidation, and graph settling.
