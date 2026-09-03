# JXL native containers

## Status

JX has an active prepared JXL container backend and a pure x86-64 assembly container runtime.

Implemented now:

- operation-specific prepared container bindings,
- fixed six-byte JXL container instructions,
- 14-bit binding IDs,
- eight-register-window source/destination selectors,
- numeric native target IDs,
- pure assembly Record/Vector/Stack/Queue/Deque/Map/Set operations,
- Bag dirty/generation synchronization primitives,
- deterministic binary/JSON binding metadata,
- native build, correctness, and benchmark harnesses.

## Core rule

```text
canonical JX
    |
    v
prepared operation-specific binding
    |
    v
fixed 6-byte JXL instruction
    |
    v
admitted native binding
    |
    v
x86-64 JXL executor
    |
    v
pure assembly container operation
    |
    v
Bag memory
```

The repeat path performs no PHP object lookup, method-name lookup, alias lookup, discipline lookup, schema search, native symbol-name lookup, or variable-length container-instruction parsing.

> **Resolve the container cold. Execute its memory law hot.**

---

# Canonical container memory laws

JXL deliberately reduces the seven public Bag disciplines to a small number of machine-memory laws.

```text
DENSE
  Record
  Vector
  Stack

RING
  Queue
  Deque

ORDERED ARRAY
  Set
  Map
```

## Record

Fixed dense u64 slots.

```text
jx_record_get_u64
jx_record_put_u64
```

## Vector / Stack

Contiguous u64 storage with a count.

```text
jx_vector_push_u64
jx_vector_pop_u64
jx_vector_get_u64
jx_vector_put_u64
jx_vector_emplace_u64
jx_vector_peek_u64
jx_vector_reserve_u64
```

Stack push/pop/peek use the same contiguous machine law.

## Queue / Deque

Power-of-two ring storage with monotonic head/tail indexes.

```text
jx_queue_push_u64
jx_queue_pop_u64
jx_queue_peek_u64

jx_deque_push_front_u64
jx_deque_push_back_u64
jx_deque_pop_front_u64
jx_deque_pop_back_u64
jx_deque_peek_front_u64
jx_deque_peek_back_u64
jx_ring_reserve_u64
```

Only Queue and Deque require a power-of-two capacity/mask.

---

# Map is always a 2D array

This is a canonical JX invariant.

A Map is not a hash table. It is not defined by buckets, probing, collisions, tombstones, or load factor.

Its native u64 representation is two synchronized dense dimensions:

```text
keys[]
values[]
```

Conceptually:

```text
Map =
[
    [key0, value0],
    [key1, value1],
    [key2, value2],
    ...
]
```

The split native representation keeps key search dense and avoids pulling values through cache until a key position is known:

```text
keys:   [ K0 ][ K1 ][ K2 ][ K3 ] ...
values: [ V0 ][ V1 ][ V2 ][ V3 ] ...
                 ^
                 same index
```

The keys are maintained in ascending u64 order.

## PUT

`PUT(key, value)` has exactly two semantic outcomes:

```text
position = FIND(key)

if found:
    values[position] = value
else:
    INSERT(position, key, value)
```

Therefore an existing key overwrites its value memory. A missing key instantiates new synchronized key/value memory.

Example:

```text
before:
keys:   [ 4 ][ 17 ][ 31 ]
values: [ A ][ B  ][ C  ]

PUT(17, X)

keys:   [ 4 ][ 17 ][ 31 ]
values: [ A ][ X  ][ C  ]
```

No new key position is created.

For a missing key:

```text
PUT(22, D)

keys:   [ 4 ][ 17 ][ 22 ][ 31 ]
values: [ A ][ B  ][ D  ][ C  ]
```

## Other Map operations

```text
GET      FIND key -> values[index]
HAS      FIND key -> found boolean
EMPLACE  FIND key -> keep existing value, or insert missing key/value
REMOVE   FIND key -> remove keys[index] and values[index]
RESERVE  verify dense array capacity
```

Native operations:

```text
jx_sorted_find_u64
jx_map_emplace_u64
jx_map_get_u64
jx_map_put_u64
jx_map_has_u64
jx_map_remove_u64
jx_sorted_reserve_u64
```

---

# Set is the 1D Map law

A Set is the unique-key one-dimensional form of the same ordered-array machinery:

```text
Set = [ key0, key1, key2, ... ]
```

The array stays sorted and contains each value at most once.

`ADD(value)`:

```text
position = FIND(value)

if found:
    drop the insertion
else:
    INSERT(position, value)
```

`HAS` and `REMOVE` use the same `FIND` primitive.

Native operations:

```text
jx_sorted_find_u64
jx_set_add_u64
jx_set_has_u64
jx_set_remove_u64
jx_sorted_reserve_u64
```

---

# FIND: marquee locality plus lower_bound

Map and Set share one native position primitive:

```text
jx_sorted_find_u64
```

It returns:

```text
index
found / absent
```

The implementation uses two paths.

## Local / sequential access

Each admitted Map/Set may carry a locality cursor. The native finder first checks:

```text
cursor
cursor + 1
```

This implements the marquee behavior for ordered/sequential traffic: a walk through nearby positions does not restart a full search each time.

## Random access

If the key is not at the current or next cursor position, the implementation falls back to unsigned u64 `lower_bound` binary search.

Thus the same array supports:

```text
ordered/local access -> cursor walk
random access        -> binary lower_bound
```

Monotonic insertion has an additional last-key/append fast path, so adding already ascending keys does not binary-search or shift the array.

---

# Insertion and removal

A missing key is inserted at its ordered position. Dense array tails are shifted in assembly.

For Map, the key and value dimensions move in lockstep.

```text
keys:   shift right
values: shift right
write key/value
count++
```

Removal performs the corresponding left shift.

This keeps the canonical data itself simple and contiguous. Mutation-heavy random middle insertion can later be optimized with reserved gaps/packed-array techniques without changing the rule that Map is a 2D array and Set is a 1D unique array.

---

# No hash semantics

Canonical JXL Map/Set execution does **not** contain a current Map probe entry point and does not use:

```text
hash buckets
open addressing
collision resolution
tombstones
load factors
rehashing
hash-slot state words
```

Native target ID 28 is `jx_sorted_reserve_u64`.

For binary compatibility only, the assembly still exports the old symbol name:

```text
jx_hash_reserve_u64
```

as an alias to the same sorted-array reserve routine. New compiler metadata and the numeric native target table do not use the legacy name. The alias performs no hashing.

---

# Runtime binding ABI

The admission record remains 80 bytes, so the Map/Set correction does not require a new JXL instruction format.

```text
+00 native function pointer
+08 base
+16 head/cursor
+24 tail/count
+32 capacity
+40 mask
+48 generation
+56 flags
+64 aux
+72 aux2
```

Interpretation by discipline:

| Field | Dense | Queue/Deque | Set | Map |
|---|---|---|---|---|
| `base` | elements | ring | keys[] | keys[] |
| `head` | optional | head index | locality cursor | locality cursor |
| `tail` | count | tail index | count | count |
| `capacity` | slots | ring capacity | array capacity | array capacity |
| `mask` | 0 | ring mask | 0 | 0 |
| `aux` | helper | helper | none | values[] |

Map/Set capacities are ordinary array capacities. They do not have to be powers of two and their prepared mask is zero.

---

# Fixed six-byte JXL container instruction

Every native container instruction remains exactly six bytes:

```text
+0 opcode
+1 binding id low 7 bits   | 80h
+2 binding id high 7 bits  | 80h
+3 src0 selector           | 80h
+4 src1 selector           | 80h
+5 destination selector    | 80h
```

Container opcodes remain:

```text
40 PUSH
41 POP
42 PUSHF
43 PUSHB
44 POPF
45 POPB
46 EMPLACE
47 GET
48 PUT
49 HAS
4A REMOVE
4B PEEK
4C PEEKF
4D PEEKB
4E RESERVE
4F DIRTY
50 SYNC
```

The existing Map/Set opcode and native-ID assignments remain stable. The memory law behind their native symbols changed; prepared code does not need a new instruction width or opcode family.

---

# Native target table

`native/x86_64/jxl_container_native_table.asm` exports:

```text
jx_jxl_container_native_table
jx_jxl_container_native_count
```

Admission uses numeric target IDs rather than runtime symbol-name lookup.

Map/Set operation IDs stay compatible:

```text
18 MAP_EMPLACE
19 MAP_GET
20 MAP_PUT
21 MAP_HAS
22 MAP_REMOVE
23 SET_ADD
24 SET_HAS
25 SET_REMOVE
28 SORTED_RESERVE
```

---

# Bag boundary

Hot operations do not canonicalize the Bag every time.

```text
jx_bag_dirty
jx_bag_sync
```

The existing rule remains:

> **Be native while working. Become canonical at the Bag boundary.**

---

# Capacity and slow paths

The hot assembly does not allocate memory.

`RESERVE` checks whether the prepared region has enough capacity. Failure takes a cold/prelinked growth path outside the repeat sequence.

For Map, growth must preserve synchronized `keys[]` and `values[]` dimensions. For Set, it grows only `keys[]`.

There is no Map/Set rehash step.

---

# Build and tests

Build the native runtime:

```bash
bash native/x86_64/build-jxl-containers.sh
```

Run the prepared contract test:

```bash
php -d zend.assertions=1 -d assert.exception=1 test-jx-jxl-containers.php
```

The contract checks include:

- Map/Set resolve to ordered-array native targets,
- Map/Set accept non-power-of-two capacities,
- Map/Set prepared masks are zero,
- Queue/Deque still prepare ring masks,
- `jx_sorted_find_u64` and `jx_sorted_reserve_u64` exist,
- no current `jx_map_probe_u64` entry point exists,
- Set aliases still disappear before JXL,
- fixed six-byte instruction encoding remains unchanged.

The native benchmark also performs an ordered-array correctness preflight before recording timings. It verifies out-of-order insertion, Map overwrite, Set uniqueness, lookup, and removal through the real six-byte JXL executor.
