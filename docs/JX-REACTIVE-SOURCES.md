# JX Reactive Sources

JX treats external and mutable data as canonical reactive sources rather than host-specific callbacks.

A reactive source has four machine-meaningful properties:

```text
stable source identity
current revision
current canonical value
refresh / publish boundary
```

Execution shadows carry those source identities as dependencies. If a source revision changes, only shadows indexed by that source become dirty. An unrelated source change does not rerun them.

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

That means SQL participates in the same dependency graph as Bags, media, files, or host events.

## Derived canonical values

`DerivedReactiveSource` remains useful when the desired result is itself another canonical reactive value:

```php
$total = new DerivedReactiveSource(
    'cart-total',
    [$prices, $quantities],
    fn ($p, $q) => calculate_total($p, $q)
);
```

If `$prices` changes, the node becomes dirty. If an unrelated media or socket source changes, it does not.

## Executable shadows

`jx-shadow-runtime.php` adds the execution layer. An `ExecutableShadow` contains:

```text
shadow identity
source dependency IDs
executable representation
run count / last result
dirty state
```

`ReactiveShadowRuntime` builds the reverse dependency index:

```text
source-id -> shadow-id, shadow-id, ...
```

A source publish therefore becomes:

```text
source revision changes
        ↓
lookup source-id
        ↓
mark only indexed shadows dirty
        ↓
execute only those shadows
```

There is no requirement to rerun a page or the surrounding source program.

### PASM shadows

`PASMExecutableShadow` is the compiled target. Reactive source identities are prelinked directly to PASM registers. When a source revision dispatches the shadow, the runtime loads those scalar values into the designated registers and enters packed PASM bytecode directly.

Example shape:

```text
source A -> ecx
source B -> ah

ADD bdx ecx ah
RET bdx
```

For this shadow, changing source A or B runs the bytecode. Changing an unrelated media, SQL, Bag, or host-event source does not.

The regression verifies that this sample is five bytes of active packed PASM bytecode and that unrelated source revisions leave the PASM shadow run count unchanged.

### Batched invalidation

With automatic dispatch disabled, several source revisions may dirty the same shadow before execution. `settle()` executes each dirty shadow once:

```text
A changes ─┐
           ├─> shadow X dirty
B changes ─┘

settle()
  -> shadow X runs once
```

That gives JX a path to frame batching, transaction batching, SQL notification coalescing, and media-event coalescing without widening the execution shadow.

## Automatic reactive shadow compilation

`jx-shadow-compiler.php` closes the compiler link.

Ordinary PASL scalar code can be compiled as a reactive shadow:

```php
$compiler = new ReactiveShadowCompiler(true);

$shadow = $compiler->compileInto(
    $runtime,
    'cart.total',
    <<<'PASL'
$sum = $left + $right;
$result = $sum * 2;
PASL,
    [
        'left'  => $leftSource,
        'right' => $rightSource,
    ]
);
```

The input map is compile-time information. The compiler:

```text
binds each source variable
allocates it through the normal PASL register allocator
records the actual hot register
removes the synthetic zero initialization from the execution shadow
assembles the remaining program to packed PASM
attaches the source IDs as the shadow dependency table
registers source -> shadow edges
```

After compilation, the PHP-ish/PASL text is not reparsed when data changes.

For the example above, dispatch is conceptually:

```text
left revision++
      ↓
source-id lookup
      ↓
cart.total dirty
      ↓
left value -> allocated register
right value -> allocated register
      ↓
packed PASM bytecode
      ↓
result
```

Both O0 and O1 compiler paths are regression tested.

## The shadow rule

The canonical source remains permanent meaning. The execution shadow is disposable acceleration.

A JX line is therefore not required to survive as one machine instruction. Several source statements may collapse into one shadow operation, and an unobservable statement may collapse into none. What must survive is the canonical relationship between source meaning, dependency identity, and executable shadow.

The direction is:

```text
PHP-ish JX / PASL
        ↓ compile once
canonical semantic meaning
        ↓
dependency IDs + register allocation
        ↓
minimal PASM/native shadow
        ↓
reactive source revision
        ↓
direct shadow dispatch
```

This is how SQL results, media files, Bags, timers, sockets, host events, and later native watchers can all drive the same compiled program without turning PHP into a required hot-path interpreter.

## Reactive graph

`ReactiveGraph` remains the host-neutral source refresh graph. `ReactiveShadowRuntime` is the execution graph. A host may use either independently or connect source refresh events directly into the shadow runtime.

The important separation is:

```text
host detects change
        ↓
canonical ReactiveSource publishes revision
        ↓
ReactiveShadowRuntime selects executable block
        ↓
PASM/native shadow runs
```

## Things Addressed

- SQL results use the same reactive contract as other sources.
- Bag container revisions are reactive without duplicating container state.
- File and media metadata can trigger reactive revisions.
- Arbitrary host events can enter through mutable/callback sources.
- Unchanged refreshed data does not increment revisions.
- Reactive identity is host-neutral and stable.
- Source-to-shadow dependency edges are indexed directly.
- Unrelated source changes do not execute unrelated shadows.
- PASM bytecode shadows execute directly after reactive dispatch.
- Multiple dirtying inputs can be coalesced into one shadow execution.
- PASL reactive inputs are automatically allocated to PASM registers.
- Synthetic input initialization is removed from the executable shadow.
- O0 and O1 reactive shadow compilation are both tested.
- Host watchers/pollers are adapters, not alternate language semantics.

## Post-chapter test

Run:

```bash
php test-jx-reactive.php
php test-jx-shadow-runtime.php
php test-jx-shadow-compiler.php
```

The harnesses exercise mutable sources, Bag revisions, file/media changes, prepared SQL query refresh, selective dependency invalidation, direct PASM shadow execution, batched settling, automatic source-to-register allocation, and optimized/unoptimized shadow compilation.
