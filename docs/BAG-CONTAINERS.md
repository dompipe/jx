# Bag-backed containers

JX treats containers as **Bag disciplines**, not as a second memory system.

```text
Bag ownership / capacity / identity / checkpoint law
                 |
          access discipline
  +--------+------+-----+------+-----+-----+
  |        |      |     |      |     |     |
record   vector stack  queue  deque  map   set
```

## Canonical rule

A coder describes one Bag and its discipline. The canonical object remains the source of truth. The compiler/runtime may keep a prepared native representation appropriate to that discipline.

The native representations are deliberately array-centered:

| Discipline | Hot/native strategy | Canonical meaning |
|---|---|---|
| record | fixed dense slots | named fields |
| vector | contiguous indexed array | ordered sequence |
| stack | contiguous array + LIFO law | stack |
| queue | circular/ring array | FIFO queue |
| deque | double-ended circular/ring array | deque |
| map | **ordered 2D array: keys[] + values[]** | keyed values |
| set | **ordered 1D unique array** | unique values |

Map and Set are not canonical hash tables.

> **Containers are arrangements of memory, not wrapper objects around unrelated algorithms.**

## Hot state versus canonical state

`jx-bag-containers.php` does not sign/commit the Bag on every operation. `checkpoint()` is the explicit boundary that serializes the current canonical container image through the existing Bag handshake. `restore()` reconstructs live state from that checkpoint.

> **Be native while working. Become canonical at the boundary.**

## Map invariant

A Map is always a two-dimensional key/value array.

Conceptually:

```text
[
  [key0, value0],
  [key1, value1],
  [key2, value2]
]
```

The native representation splits those dimensions for dense key searching:

```text
keys[]
values[]
```

The same index identifies one Map entry.

`PUT(key,value)` means:

```text
position = FIND(key)

found:
    overwrite values[position]

absent:
    insert key at keys[position]
    insert value at values[position]
```

There is no bucket, collision, probe, tombstone, load-factor, or rehash meaning in JX Map semantics.

## Set invariant

Set is the one-dimensional unique form of the same ordered-array law:

```text
[value0, value1, value2, ...]
```

`ADD(value)` means:

```text
position = FIND(value)

found:
    drop duplicate

absent:
    insert value at position
```

## FIND and marquee locality

Map and Set use an ordered position search. The current native implementation keeps a locality cursor:

```text
try cursor
try cursor + 1
otherwise lower_bound binary search
```

That makes sequential/local access behave like a marquee through the array while random access still has logarithmic position search.

Monotonic insertion also gets a direct append/last-key fast path.

## Bag hot-operation mnemonics

PASM/JX uses a small canonical command family. Readable aliases resolve before executable JXL and do not survive into the hot instruction stream.

| Canonical | Meaning | Common aliases |
|---|---|---|
| `BPUSH` | insert according to Bag discipline | `PUSH`, `APPEND`, `ADD`, `ENQUEUE`, `ENQ`, `QPUSH`, `SPUSH`, `VAPPEND` |
| `BPOP` | remove according to Bag discipline | `POP`, `TAKE`, `DEQUEUE`, `DEQ`, `QPOP`, `SPOP`, `VPOP` |
| `BPUSHF` | push deque front | `PUSHF`, `PUSHFRONT`, `UNSHIFT`, `DPUSHF` |
| `BPUSHB` | push deque back | `PUSHB`, `PUSHBACK`, `DPUSHB` |
| `BPOPF` | pop deque front | `POPF`, `POPFRONT`, `SHIFT`, `DPOPF` |
| `BPOPB` | pop deque back | `POPB`, `POPBACK`, `DPOPB` |
| `BEMPLACE` | find insertion position once and insert if absent | `EMPLACE`, `INSERT`, `PACKIN`, `PUTIFABSENT`, `ADDIFABSENT` |
| `BPEEK` | read current element without removal | `PEEK`, `TOP`, `FRONT` |
| `BRESERVE` | reserve a hot operation region | `RESERVE`, `ENSURE` |
| `BDIRTY` | mark native state dirty once | `DIRTY` |
| `BSYNC` | cross canonical Bag boundary | `SYNC`, `CHECKPOINT`, `COMMITBAG` |

### Discipline-aware meaning

```text
vector: BPUSH = append      BPOP = pop-back
stack:  BPUSH = push        BPOP = pop
queue:  BPUSH = enqueue     BPOP = dequeue
deque:  BPUSH = push-back   BPOP = pop-front
```

Record Bags resolve named fields to dense slots.

## BEMPLACE

### Vector / Stack

Calculate the insertion address once, move the tail once, and store once.

### Map

Map emplace means insert-if-absent on the ordered 2D array:

```text
i = FIND(key)
if found: return values[i]
insert keys[i]
insert values[i]
```

### Set

Set emplace uses the same ordered position rule:

```text
i = FIND(value)
if found: return existing value
insert value at i
```

Duplicate insertion leaves the Set unchanged.

## PHP Bag mirror

The PHP Bag implementation now mirrors the canonical memory law rather than advertising a different backend:

- `MapBag` stores synchronized `keys[]` and `values[]` lists internally.
- `SetBag` stores one ordered unique value list.
- Both use cursor locality plus lower-bound search.
- Map checkpoint payloads contain explicit key and value dimensions.
- Legacy associative Map checkpoint payloads are accepted during restore and immediately converted to the 2D representation.

This PHP layer is a semantic/reference implementation. The native prepared JXL path remains the performance target.

## Dirty/revision rule

A native region marks the Bag dirty once rather than incrementing canonical revision on every hot operation. Revision/checkpoint cost is paid when the region synchronizes through `BSYNC`.

## Checkpoint ABI

Checkpoints use:

```text
jx.bag.container/1
```

and include Bag identity, discipline, optional element type, revision, count, layout hint, and canonical payload.

## RecordBag and fixed offsets

`RecordBag` resolves human names to dense slots once:

```text
health -> slot 0
phi    -> slot 1
level  -> slot 2
```

A native shadow can lower those slots to fixed offsets.

## Queue and Deque

Queue/Deque remain power-of-two rings in the current native JXL path. They are the only container disciplines that require the prepared capacity mask.

## Native Map/Set ABI

For u64 JXL execution:

```text
Set:
  base -> keys[]
  head -> locality cursor
  tail -> count
  mask -> 0

Map:
  base -> keys[]
  aux  -> values[]
  head -> locality cursor
  tail -> count
  mask -> 0
```

The shared primitives are:

```text
jx_sorted_find_u64
jx_sorted_reserve_u64
```

with discipline-specific Map/Set get/put/emplace/has/remove routines built around them.

## Tests and benchmarks

```bash
php test-jx-bag-containers.php
php test-pasm-bag-hotops.php
php -d zend.assertions=1 -d assert.exception=1 test-jx-jxl-containers.php
php benchmark-jx-bag-containers.php 1000000 7
php benchmark-jxl-containers.php 1000000 9 2
php benchmark-container-suite.php 1000000 9 2
```

The benchmark contract must keep Map and Set array semantics intact while comparing PHP, PASM, Bag, and prepared JXL execution.
