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
| queue | pointer/ring FIFO | FIFO queue |
| deque | double-ended pointer/ring | deque |
| map | target-native hash | keyed values |
| set | target-native hash set | unique values |

`nativeLayout()` exposes the compiler hint for the Canonical Shadow Machine. It is not the canonical data itself.

## Bag hot-operation mnemonics

PASM/JX uses a small canonical command family. Readable aliases are resolved at parse/link time and do not survive into the executable shadow.

| Canonical | Meaning | Common aliases |
|---|---|---|
| `BPUSH` | insert according to Bag discipline | `PUSH`, `APPEND`, `ADD`, `ENQUEUE`, `ENQ`, `QPUSH`, `SPUSH`, `VAPPEND` |
| `BPOP` | remove according to Bag discipline | `POP`, `TAKE`, `DEQUEUE`, `DEQ`, `QPOP`, `SPOP`, `VPOP` |
| `BPUSHF` | push deque front | `PUSHF`, `PUSHFRONT`, `UNSHIFT`, `DPUSHF` |
| `BPUSHB` | push deque back | `PUSHB`, `PUSHBACK`, `DPUSHB` |
| `BPOPF` | pop deque front | `POPF`, `POPFRONT`, `SHIFT`, `DPOPF` |
| `BPOPB` | pop deque back | `POPB`, `POPBACK`, `DPOPB` |
| `BPEEK` | read current element without removal | `PEEK`, `TOP`, `FRONT` |
| `BRESERVE` | reserve a hot operation region | `RESERVE`, `ENSURE` |
| `BDIRTY` | mark native shadow dirty once | `DIRTY` |
| `BSYNC` | cross canonical Bag boundary | `SYNC`, `CHECKPOINT`, `COMMITBAG` |

The key rule is **one canonical opcode, many compile-time names**. Aliases exist for programmer vocabulary only; the runtime never performs an alias lookup.

### Discipline-aware meaning

`BPUSH` and `BPOP` adapt to the Bag discipline:

```text
vector: BPUSH = append      BPOP = pop-back
stack:  BPUSH = push        BPOP = pop
queue:  BPUSH = enqueue     BPOP = dequeue
deque:  BPUSH = push-back   BPOP = pop-front
```

Record Bags do not use cursor push/pop. Their named fields resolve once to dense slots and then fixed native offsets.

## Two-instruction hot path

For fixed-width sequential Bags, the native lowering keeps cursor/head/tail registers resident across a hot region.

`BPUSH` for vector/stack:

```asm
mov [cursor], value
add cursor, width
```

`BPOP` for vector/stack:

```asm
sub cursor, width
mov value, [cursor]
```

`BPUSH` for queue/deque tail:

```asm
mov [tail], value
add tail, width
```

`BPOP` for queue/deque head:

```asm
mov value, [head]
add head, width
```

Explicit deque ends use the same two-instruction forms with the appropriate head/tail direction.

Bounds and growth are deliberately hoisted. `BRESERVE` can guard a region with approximately three instructions:

```asm
lea tmp, [cursor+bytes]
cmp tmp, end
ja .bag_grow
```

After that guard, each push/pop in the reserved region remains two instructions. Wrap, reallocation, underflow recovery, canonical synchronization and revision updates are out-of-line slow paths.

A native region marks the Bag dirty once rather than incrementing canonical revision on every operation. The revision/checkpoint cost is paid when the region synchronizes through `BSYNC`.

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

Queue/deque native shadows should prefer register-resident head/tail pointers in hot regions. Wrap handling is an out-of-line path. A power-of-two ring remains the fallback representation where pointer residency is not profitable or when a target backend prefers mask indexing.

## Tests

Run:

```bash
php test-jx-bag-containers.php
php test-pasm-bag-hotops.php
php benchmark-jx-bag-containers.php 1000000 7
```

The older PASM container implementation remains useful as historical/runtime evidence while JX converges container meaning on Bags.
