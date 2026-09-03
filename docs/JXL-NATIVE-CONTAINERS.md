# JXL native containers

## Status

JX now has an **active prepared JXL container backend** and a **pure x86-64 assembly container runtime**.

Implemented now:

- operation-specific prepared container bindings,
- fixed six-byte JXL container instructions,
- 14-bit binding IDs,
- eight-register-window source/destination selectors,
- numeric native target IDs,
- a numeric assembly target-address table,
- pure assembly record/vector/stack/queue/deque/map/set operations,
- pure assembly fixed-width JXL container instruction execution,
- Bag dirty/generation synchronization primitives,
- deterministic binary/JSON binding metadata,
- native build and contract-test harnesses.

Not yet claimed as automatic:

- the documented `bag Inventory { ... }` declaration form is not yet accepted by the current semantic parser,
- arbitrary `$queue.enqueue($value)` AST nodes are not yet automatically assigned a Bag discipline/handle by the semantic compiler,
- `.64B` packaging does not yet automatically add a container-binding section,
- the JX native host/OSAura admission layer still needs to resolve durable Bag handles into the runtime binding pointers defined here.

Those are integration steps above/below the now-defined native container contract. They do not change the hot execution design.

---

## The rule

```text
canonical JX
    |
    | parse / type / Bag discipline / aliases
    v
prepared operation-specific binding
    |
    | exact native_id + Bag handle + layout
    v
fixed 6-byte JXL instruction
    |
    | admitted native binding table
    v
x86-64 JXL container executor
    |
    | direct function pointer
    v
pure assembly container operation
    |
    v
Bag memory
```

The native repeat path does **not** perform:

```text
PHP object lookup
method-name lookup
alias lookup
discipline lookup
schema search
native symbol-name lookup
variable-length container-instruction parsing
```

The binding has already answered those questions.

> **Resolve the container cold. Execute its memory law hot.**

---

## Compiler/admission API

`jx-jxl-containers.php` defines the prepared contract. `PreparedCompiler` exposes it directly.

Example:

```php
$compiler = new \jx\semantic\PreparedCompiler();

$push = $compiler->bindContainer(
    41,          // durable Bag handle
    'queue',
    'enqueue',   // source spelling; disappears here
    8,           // u64 width in v1
    1024         // prepared capacity
);

// R2 contains the value to enqueue.
$jxl = $compiler->emitContainer($push, 2);
```

The resulting binding contains canonical/native identity such as:

```text
operation     PUSH
opcode        40h
native_id     9
native_symbol jx_queue_push_u64
capacity      1024
mask          1023
```

The word `enqueue` is not part of the JXL hot instruction or runtime decision.

Identical prepared bindings are deduplicated.

---

## Fixed six-byte JXL container instruction

Every native container instruction is exactly six bytes:

```text
+0 opcode
+1 binding id low 7 bits   | 80h
+2 binding id high 7 bits  | 80h
+3 src0 selector           | 80h
+4 src1 selector           | 80h
+5 destination selector    | 80h
```

This directly follows the authoritative JXL byte law:

```text
0xxxxxxx = executable opcode
1xxxxxxx = attached data, never independently executed
```

Container opcodes currently occupy:

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

A selector payload is `0..7` for the current eight-entry prepared register window.

```text
7F = unused/discard selector
```

So all instructions remain six bytes even when an operation has zero or one arguments.

That avoids a container-specific variable-length parser in the native hot path.

---

## Operation-specific binding

A binding is not merely:

```text
Bag 41 is a queue
```

It is closer to:

```text
binding 7
Bag handle:   41
operation:    PUSH
discipline:   queue
payload:      u64
capacity:     1024
mask:         1023
native_id:    9
native target:jx_queue_push_u64
```

Therefore native execution does not do this:

```text
BPUSH
  -> what kind of Bag?
  -> queue
  -> what does push mean for queue?
  -> enqueue
  -> which implementation?
```

It does this:

```text
binding 7
  -> native_fn
  -> call
```

---

## Native runtime binding ABI

The runtime admission record is exactly 80 bytes on x86-64:

```text
+00 native function pointer
+08 base pointer
+16 head/origin pointer
+24 tail/count pointer
+32 capacity
+40 mask
+48 generation pointer
+56 flags pointer
+64 aux pointer
+72 aux2 pointer
```

The C layout contract is in:

```text
native/x86_64/jxl_container_runtime.h
```

The assembly implementation uses the same offsets.

These pointers are **runtime/admission state**, not canonical portable JX addresses and not serialized into the Book.

A `.64B` binding record stores stable data such as Bag handle and native target ID. Admission resolves those stable identities into host/kernel pointers for the current process/generation.

---

## Pure assembly JXL executor

`native/x86_64/jxl_container_executor.asm` executes one six-byte instruction.

Its path is deliberately small:

```text
validate executable + five attachment bytes
decode 14-bit binding id
binding = table + id * 80
load src selectors from current 8-register window
call [binding.native_fn]
optionally store RAX to dst selector
PC += 6
```

The executor does not branch on container discipline.

The operation-specific binding contains the function pointer selected during admission.

---

## Pure assembly containers

`native/x86_64/jxl_containers.asm` contains the actual hot operations.

### Record

Fixed u64 slots:

```text
jx_record_get_u64
jx_record_put_u64
```

### Vector / stack

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

### Queue / deque

Power-of-two ring with monotonic head/tail indexes:

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

### Map / set

The v1 assembly hash slot is 24 bytes:

```text
+0  state: 0 empty / 1 occupied / 2 tombstone
+8  u64 key
+16 u64 value
```

Operations:

```text
jx_map_probe_u64
jx_map_emplace_u64
jx_map_get_u64
jx_map_put_u64
jx_map_has_u64
jx_map_remove_u64

jx_set_add_u64
jx_set_has_u64
jx_set_remove_u64
jx_hash_reserve_u64
```

Hashing and probing are assembly operations too; there is no PHP hash object in the native path.

---

## Numeric native target table

`native/x86_64/jxl_container_native_table.asm` exports:

```text
jx_jxl_container_native_table
jx_jxl_container_native_count
```

Binding metadata carries a numeric `native_id`.

Admission therefore performs:

```text
native_fn = native_table[native_id]
```

rather than looking up `"jx_queue_push_u64"` by string.

The symbol name remains in JSON/debug metadata for humans, not as the native execution key.

---

## Bag dirty/generation boundary

Hot operations do not canonicalize the Bag every time.

```text
jx_bag_dirty
jx_bag_sync
```

`DIRTY` marks hot state once.

`SYNC` advances the Bag generation once when dirty and clears the flag.

This retains the existing JX rule:

> **Be native while working. Become canonical at the Bag boundary.**

---

## Capacity and slow paths

The hot assembly does not allocate memory.

`RESERVE` proves that a prepared region fits. A carry/failure result means the caller takes an already-prelinked cold grow/rehash service.

This keeps allocation, reallocation, policy, and error recovery out of the repeat instruction sequence.

---

## Build

Requires NASM and GNU `ld` for the current ELF64 target:

```bash
bash native/x86_64/build-jxl-containers.sh
```

Output:

```text
build/native/x86_64/jxl_containers.o
build/native/x86_64/jxl_container_executor.o
build/native/x86_64/jxl_container_native_table.o
build/native/x86_64/jxl_container_runtime.o
build/native/x86_64/jxl_container_runtime.symbols   # when nm exists
```

`jxl_container_runtime.o` is a relocatable combined object suitable for the native host/link step and future `.64B` native-section packaging.

---

## Contract test

```bash
php -d zend.assertions=1 -d assert.exception=1 test-jx-jxl-containers.php
```

The test checks:

- alias disappearance,
- operation-specific native target selection,
- Set `add` discipline override,
- binding deduplication,
- power-of-two mask preparation,
- no collision with current core JXL opcode constants,
- exactly six bytes per container instruction,
- executable high bit clear,
- all five operand high bits set,
- selector encode/decode,
- malformed attachment rejection,
- deterministic `JXCBIND1` output,
- required assembly symbols.

---

## Next compiler integration

The next language/compiler step is not to change the assembly contract. It is to make the semantic compiler know, from canonical JX declarations/types, that a variable is for example:

```text
Bag handle 41
queue<u64>
capacity 1024
```

Then a source expression such as an enqueue can mechanically become:

```text
source spelling
    -> canonical BPUSH/PUSH
    -> bindContainer(41, queue, PUSH)
    -> emitContainer(binding, value-selector)
```

At that point the compiler can automatically emit `HOT/container-bindings.bin` with the JXL section in `.64B`.

The architecture is intentionally arranged so that adding source-level knowledge does not add work back into the native repeat path.
