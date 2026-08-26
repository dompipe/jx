# Bag-backed containers

JX now treats containers as **Bag disciplines**, not as a second memory system.

```text
Bag ownership / capacity / identity / checkpoint law
                 |
          access discipline
  +--------+------+-----+------+-----+-----+
  |        |      |     |      |     |     |
record   vector stack  queue  deque  map   set
```

## Canonical rule

A coder describes one Bag and its discipline. The canonical object remains the source of truth. The compiler/runtime may choose a disposable native shadow appropriate to the discipline and target.

```jx
bag Inventory {
    type: vector
    of: Item
}

bag Jobs {
    type: queue
    of: Task
}

bag State {
    type: record
    health: int
    phi: int
    level: int
}
```

## Hot state versus canonical state

`jx-bag-containers.php` intentionally does **not** sign/commit the Bag on every push, get, pop, enqueue or dequeue. Hot operations remain in target-native state. `checkpoint()` is the explicit boundary that serializes the current canonical container image through the existing Bag handshake. `restore()` reconstructs hot state from that checkpoint.

This preserves the existing engine rule:

> Be native while working. Become canonical at the boundary.

## Disciplines and native shadows

| Discipline | Hot/native strategy | Canonical meaning |
|---|---|---|
| record | fixed dense slots / fixed field offsets | named fields |
| vector | contiguous indexed storage | ordered sequence |
| stack | contiguous storage + LIFO law | stack |
| queue | power-of-two ring | FIFO queue |
| deque | double-ended power-of-two ring | deque |
| map | target-native hash | keyed values |
| set | target-native hash set | unique values |

`nativeLayout()` exposes the compiler hint for the Canonical Shadow Machine. It is not the canonical data itself.

## Checkpoint ABI

Checkpoints use:

```text
jx.bag.container/1
```

and include Bag identity, discipline, optional element type, revision, count, native-layout hint and canonical payload.

## RecordBag and fixed offsets

`RecordBag` resolves human names to dense slots once:

```text
health -> slot 0
phi    -> slot 1
level  -> slot 2
```

A native ELF/EXE shadow can then lower those slots to `offsetof()`/fixed offsets. The earlier whole-language native microbenchmark measured fixed Bag field offsets at roughly 12x the repeated-name-resolution baseline. That measurement applies to the native compiled shadow benchmark, not to every PHP Bag operation.

## Queue and Deque

Queues and deques use power-of-two capacities, making ring selection:

```text
slot = index & mask
```

instead of a general modulo operation. Growth doubles capacity and preserves logical order.

## Tests

Run:

```bash
php test-jx-bag-containers.php
php benchmark-jx-bag-containers.php 1000000 7
```

The older PASM container implementation remains useful as historical/runtime evidence while JX converges container meaning on Bags.
