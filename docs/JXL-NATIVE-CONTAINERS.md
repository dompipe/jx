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
DENSE VECTOR MEMORY
  Record
  Vector
  Stack

RING VECTOR MEMORY
  Queue
  Deque

ORDERED KEYED VECTOR MEMORY
  Set = Vector<Key>
  Map = Vector<Entry>, Entry=[Key,Value]
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

# Map is a keyed Vector

This is the active canonical JX Map invariant.

A Map is not a hash table and is not a secondary lookup structure. It is one ordered contiguous Vector whose elements carry both the key and the value:

```text
Map = Vector<Entry>
Entry = [key, value]
```

For the current fixed-u64 native backend:

```text
Entry size = 16 bytes

base +  0 : key0
base +  8 : value0
base + 16 : key1
base + 24 : value1
base + 32 : key2
base + 40 : value2
...
```

Therefore:

```text
entry(i) = base + i * 16
key(i)   = entry(i) + 0
value(i) = entry(i) + 8
```

The entries remain sorted by unsigned u64 key.

## PUT

`PUT(key, value)` has exactly two outcomes:

```text
position = FIND(key)

if found:
    Entry[position].value = value
else:
    Vector.insert(position, Entry(key,value))
```

An existing key therefore performs one value-cell overwrite. It does not allocate another key position.

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

The tail movement is ordinary Vector insertion, except the element width is 16 bytes instead of 8 bytes.

## Other Map operations

```text
GET      FIND key -> Entry[index].value
HAS      FIND key -> found boolean
EMPLACE  FIND key -> keep existing value, or insert missing Entry(key,value)
REMOVE   FIND key -> remove one complete Entry and pack tail left
RESERVE  verify logical Entry capacity
```

Active native Map operations are:

```text
jx_map_vector_find_u64
jx_map_vector_emplace_u64
jx_map_vector_get_u64
jx_map_vector_put_u64
jx_map_vector_has_u64
jx_map_vector_remove_u64
```

---

# Set is the one-word keyed Vector

A Set is the unique-key one-dimensional form:

```text
Set = Vector<Key>
[ key0, key1, key2, ... ]
```

The Vector stays sorted and contains each value at most once.

`ADD(value)`:

```text
position = FIND(value)

if found:
    drop the insertion
else:
    Vector.insert(position, value)
```

`HAS` and `REMOVE` use the same ordered position law.

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

Map and Set both use cursor locality followed by lower-bound search.

For Map, the finder compares `Entry.key`. For Set it compares the key word directly.

The finder returns:

```text
index
found / absent
```

## Local / sequential access

Each admitted Map/Set may carry a locality cursor. The finder first checks:

```text
cursor
cursor + 1
```

This implements the marquee behavior for ordered/sequential traffic: a walk through nearby Vector positions does not restart a full search each time.

## Random access

If the key is not at the current or next position, the implementation falls back to unsigned u64 `lower_bound` binary search.

```text
ordered/local access -> cursor walk
random access        -> binary lower_bound
```

Monotonic insertion has an additional last-key/append fast path, so adding ascending Map keys simply appends a complete 16-byte Entry.

---

# Insertion and removal

A missing Map key is inserted at its ordered Vector position. Dense tails are shifted in assembly as complete entries:

```text
before:
[Entry0][Entry1][Entry2][Entry3]
                 ^ insert

move complete 16-byte entries right
write key
write value
count++
```

Removal performs the corresponding complete-entry left shift.

Set performs the same operation with an 8-byte element width.

Mutation-heavy random middle insertion can later be optimized with reserved gaps/packed-array techniques without changing the canonical law that Map is `Vector<Entry>`.

---

# No hash semantics

Canonical JXL Map/Set execution does **not** use:

```text
hash buckets
open addressing
collision resolution
tombstones
load factors
rehashing
hash-slot state words
```

Native target ID 28 remains `jx_sorted_reserve_u64`.

For binary compatibility only, the assembly still exports:

```text
jx_hash_reserve_u64
```

as an alias to the same sorted-array reserve routine. It performs no hashing.

---

# Split-array Map retained for A/B comparison

The previous ordered split representation:

```text
keys[]
values[]
```

was already dramatically faster than the older hash implementation. Its assembly functions remain linked under the older names:

```text
jx_map_emplace_u64
jx_map_get_u64
jx_map_put_u64
jx_map_has_u64
jx_map_remove_u64
```

They are **not selected by active native Map IDs 18 through 22**.

They remain in the same runtime image specifically so later benchmarks can compare:

```text
split ordered arrays     keys[] + values[]
versus
keyed Vector             Entry[] = [key,value][key,value]...
```

without comparing different commits, toolchains, or surrounding executor implementations.

---

# Runtime binding ABI

The admission record remains 80 bytes, so the keyed-Vector change does not require a new JXL instruction format.

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

| Field | Dense | Queue/Deque | Set | Active Map |
|---|---|---|---|---|
| `base` | elements | ring | `Key[]` | `Entry[]` |
| `head` | optional | head index | locality cursor | locality cursor |
| `tail` | count | tail index | count | Entry count |
| `capacity` | slots | ring capacity | key capacity | Entry capacity |
| `mask` | 0 | ring mask | 0 | 0 |
| `aux` | helper | helper | none | unused |

A v1 Map resolver allocates `capacity * 16` bytes for its `Entry[]` storage. Map/Set capacities remain ordinary logical array capacities; they do not have to be powers of two.

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

The existing Map/Set opcode and native-ID assignments remain stable. The physical Map representation changed; prepared instruction width and opcode numbering did not.

---

# Native target table

`native/x86_64/jxl_container_native_table.asm` exports:

```text
jx_jxl_container_native_table
jx_jxl_container_native_count
```

Admission uses numeric target IDs rather than runtime symbol-name lookup.

The active assignments are:

```text
18 MAP_EMPLACE -> jx_map_vector_emplace_u64
19 MAP_GET     -> jx_map_vector_get_u64
20 MAP_PUT     -> jx_map_vector_put_u64
21 MAP_HAS     -> jx_map_vector_has_u64
22 MAP_REMOVE  -> jx_map_vector_remove_u64
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

`RESERVE` checks whether the prepared region has enough logical capacity. Failure takes a cold/prelinked growth path outside the repeat sequence.

For the active Map, growth reallocates one `Entry[]` Vector. Set grows one `Key[]` Vector. There is no Map/Set rehash step.

---

# Build and tests

Build the native runtime:

```bash
bash native/x86_64/build-jxl-containers.sh
```

Run the prepared contract tests:

```bash
php -d zend.assertions=1 -d assert.exception=1 test-jx-jxl-containers.php
php test-jx-bag-containers.php
php test-pasm-bag-hotops.php
bash test-jx-jxl-prepared-program-native.sh
```

The native shell test includes a dedicated keyed-Vector Map harness that goes through the numeric native target table and verifies:

- out-of-order insertion,
- ordered Entry layout,
- overwrite-in-place,
- emplace-does-not-overwrite,
- lookup and missing-key behavior,
- complete-entry removal/packing.

The split-vs-keyed-Vector performance comparison is intentionally separate. The old split assembly remains available so that benchmark can be performed afterward without reconstructing an older runtime.
