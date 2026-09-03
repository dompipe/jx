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
| map | **ordered keyed Vector: `Entry[]`, `Entry=[key,value]`** | keyed values |
| set | **ordered 1D unique keyed Vector** | unique values |

Map and Set are not canonical hash tables.

> **Containers are arrangements of memory, not wrapper objects around unrelated algorithms.**

A useful physical reduction is:

```text
Record -> fixed Vector
Vector -> contiguous Vector
Stack  -> Vector + LIFO cursor
Queue  -> Vector + ring cursors
Deque  -> Vector + two-ended ring law
Set    -> ordered Vector<Key>
Map    -> ordered Vector<Entry>, Entry=[Key,Value]
```

## Hot state versus canonical state

`jx-bag-containers.php` does not sign/commit the Bag on every operation. `checkpoint()` is the explicit boundary that serializes the current canonical container image through the existing Bag handshake. `restore()` reconstructs live state from that checkpoint.

> **Be native while working. Become canonical at the boundary.**

## Map invariant

A Map is always one keyed Vector. Every logical Vector element carries its own key and value:

```text
Map = Vector<Entry>
Entry = [key, value]

[
  [key0, value0],
  [key1, value1],
  [key2, value2]
]
```

For the current native u64 backend one entry occupies 16 contiguous bytes:

```text
entry 0  +00 key0   +08 value0
entry 1  +16 key1   +24 value1
entry 2  +32 key2   +40 value2
...
```

The address law is:

```text
entry(i) = base + i * 16
key(i)   = entry(i) + 0
value(i) = entry(i) + 8
```

There is no second values allocation behind the active Map and no bucket, collision, probe, tombstone, load-factor, or rehash meaning.

`PUT(key,value)` means:

```text
position = FIND(key)

found:
    overwrite Entry[position].value

absent:
    Vector.insert(position, Entry(key,value))
```

A successful overwrite never creates another key.

Example:

```text
before:
[ [4,A] ][ [17,B] ][ [31,C] ]

PUT(17,X)

[ [4,A] ][ [17,X] ][ [31,C] ]
```

For a missing key:

```text
PUT(22,D)

[ [4,A] ][ [17,B] ][ [22,D] ][ [31,C] ]
```

The Vector memory itself is the Map.

## Set invariant

Set is the one-dimensional unique keyed form of the same Vector law:

```text
Vector<Key>
[value0, value1, value2, ...]
```

`ADD(value)` means:

```text
position = FIND(value)

found:
    drop duplicate

absent:
    Vector.insert(position, value)
```

## FIND and marquee locality

Map and Set use an ordered position search. The current native implementation keeps a locality cursor:

```text
try cursor
try cursor + 1
otherwise lower_bound binary search
```

That makes sequential/local access behave like a marquee through the Vector while random access still has logarithmic position search.

Monotonic insertion also gets a direct append/last-key fast path. For a Map this is simply appending one complete `Entry` to the Vector.

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

Map emplace is the same Vector insertion law with a keyed element:

```text
i = FIND(key)
if found: return Entry[i].value
Vector.insert(i, Entry(key,value))
```

The tail move moves complete entries rather than separate key/value dimensions.

### Set

Set emplace uses the ordered one-word position rule:

```text
i = FIND(value)
if found: return existing value
Vector.insert(i, value)
```

Duplicate insertion leaves the Set unchanged.

## PHP Bag mirror

The PHP Bag implementation mirrors the canonical keyed-Vector law:

- `MapBag` stores one ordered `entries[]` list; every element is `[key,value]`.
- `SetBag` stores one ordered unique value list.
- Both use cursor locality plus lower-bound search.
- Map checkpoint payloads now contain `entries: [[key,value], ...]`.
- Restore remains backward compatible with the previous split `keys[]` + `values[]` checkpoint and the older associative Map payload; either old form is converted immediately to the keyed-Vector representation.

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
  base -> Key[]
  head -> locality cursor
  tail -> count
  mask -> 0

Map:
  base -> Entry[]
          Entry[0] = key
          Entry[1] = value
          stride   = 16 bytes
  head -> locality cursor
  tail -> entry count
  mask -> 0
  aux  -> unused by active keyed-Vector Map
```

Map native IDs 18 through 22 resolve to:

```text
jx_map_vector_emplace_u64
jx_map_vector_get_u64
jx_map_vector_put_u64
jx_map_vector_has_u64
jx_map_vector_remove_u64
```

The previous split-array `jx_map_*` routines remain linked as a comparison backend so the split and interleaved layouts can be benchmarked from the same runtime image. They are not selected by the canonical native Map IDs.

Set continues to use:

```text
jx_sorted_find_u64
jx_set_add_u64
jx_set_has_u64
jx_set_remove_u64
jx_sorted_reserve_u64
```

## Tests and benchmarks

Correctness:

```bash
php test-jx-bag-containers.php
php test-pasm-bag-hotops.php
php -d zend.assertions=1 -d assert.exception=1 test-jx-jxl-containers.php
bash test-jx-jxl-prepared-program-native.sh
```

The native smoke includes a dedicated keyed-Vector Map test that exercises out-of-order insertion, overwrite, emplace, lookup, and removal through the actual numeric native target table.

Benchmarking remains separate:

```bash
php benchmark-jx-bag-containers.php 1000000 7
php benchmark-jxl-containers.php 1000000 9 2
php benchmark-container-suite.php 1000000 9 2
```

The retained split backend exists specifically so a later A/B benchmark can compare **split `keys[]/values[]` versus interleaved `Entry[]`** under identical native/runtime conditions.
