# JX Hot-Call ABI v4

## Status

This document is the release contract for the compact JX executable call encoding used by the JX runtime and by OSAura when JX code is hosted directly by the kernel.

The purpose of ABI v4 is to make the hot path mechanically obvious, cheap to decode, and hard to accidentally slow down in future releases.

The central law is:

```text
1xxxxxxx            = one-byte hot call
0xxxxxxx xxxxxxxx   = two-byte extended call
```

The most-significant bit of the first byte determines instruction length. No other interpretation is permitted.

That rule replaces the v3 ambiguity where bytes in the `0xC0..0xFF` region could begin fused microcalls that consumed additional encoded selector state. Under v4, **every byte whose MSB is 1 is complete by itself**.

---

## 1. Why this exists

JX keeps canonical source readable and descriptive, but execution should not repeatedly perform canonical work.

The intended pipeline is:

```text
canonical JX
    |
    | compile / verify / authorize / prelink
    v
.64B executable Book
    |
    | generation admission
    v
prepared call + shadow tables
    |
    v
hot execution
```

At hot execution time there should be no command-name parsing, JSON lookup, hash lookup, schema search, string comparison, policy resolution, ZIP decoding, or dynamic symbol resolution.

The hot path should already know the answer.

A useful rule for maintainers is:

> Canonical JX explains what a program means. Prepared tables remember what that meaning resolves to. Hot bytes only select already-resolved work.

---

## 2. The v4 bit law

### 2.1 One-byte form

```text
bit 7    bits 6..3    bits 2..0
  1        bank         shadow
```

The low three bits are always the local shadow selector:

```text
shadow = opcode & 0x07
```

Therefore every hot bank contains exactly eight directly selectable entries.

The remaining four payload bits select the prepared bank:

```text
bank = (opcode >> 3) & 0x0F
```

This gives:

- 16 banks
- 8 shadows per bank
- 128 total one-byte prepared call positions
- one branch on the MSB to determine instruction width

Conceptually:

```text
0x80..0x87  -> bank 0, shadows 0..7
0x88..0x8F  -> bank 1, shadows 0..7
...
0xF8..0xFF  -> bank 15, shadows 0..7
```

The exact meaning of a bank is generation-local. The executable Book and prelinker decide which canonical operations occupy those slots.

A bank is therefore not a language feature. It is a prepared execution resource.

### 2.2 Two-byte form

If bit 7 of the first byte is zero, the instruction consumes exactly two bytes:

```text
0xxxxxxx xxxxxxxx
```

The first byte identifies an extended family/page in the range `0..127`. The second byte is the extended slot in the range `0..255`.

Conceptually:

```text
family = code[0] & 0x7F
slot   = code[1]
```

The extended form is the stable fallback for calls that have not been promoted into a one-byte prepared bank.

### 2.3 Decoder invariant

A decoder must be expressible in this shape:

```c
uint8_t op = code[0];

if (op & 0x80u) {
    /* complete one-byte hot call */
} else {
    /* require exactly one continuation byte */
}
```

No range test such as `0x80..0xBF` versus `0xC0..0xFF` may alter instruction length.

No one-byte opcode may consume a following byte.

No extended call may begin with an opcode whose MSB is 1.

This is an ABI invariant, not an optimization suggestion.

---

## 3. Why the low three bits remain shadows

JX already uses an eight-way hot-register/shadow discipline. Keeping the low three bits as the shadow selector makes the executable encoding line up with the runtime's existing prepared structures.

That means a hot call naturally decomposes into:

```text
hot opcode
   |
   +--> bank   = prepared operation group
   +--> shadow = one of eight already-bound variants
```

This is deliberately friendly to both software dispatch tables and future native lowering.

The eight-shadow rule gives a stable physical shape across subsystems:

```text
prepared bank
    +-- shadow 0
    +-- shadow 1
    +-- shadow 2
    +-- shadow 3
    +-- shadow 4
    +-- shadow 5
    +-- shadow 6
    +-- shadow 7
```

Future optimizers may change which operations occupy banks between generations, but they must not change the basic `bank x 8 shadows` interpretation without a new ABI version.

---

## 4. What happened to v3 microcalls

ABI v3 used three tiers:

```text
0x00..0x7F + slot  = two-byte family/slot call
0x80..0xBF         = one-byte promoted call
0xC0..0xFF ...     = fused microcall encoding
```

The last tier violated the new length law because a high-bit opcode could still require more encoded information.

ABI v4 removes that ambiguity.

### Migration rule

A v3 microcall must be lowered in one of two ways:

1. **Fully prepared micro-op** — if all information needed for execution can be bound during compilation/prelink, assign it a one-byte bank/shadow slot.
2. **State-bearing extended call** — if execution still needs encoded state that cannot be represented by the selected prepared entry, use the two-byte extended form or move state into the prepared frame/data structure before entering the hot path.

The design preference is always the first option when it is semantically safe.

Do not recreate a hidden third instruction width inside the `1xxxxxxx` range.

---

## 5. Canonicality versus speed

The compact ABI must never become the canonical source language.

Canonical source remains the human- and AI-readable authority. Compact encodings are products of compilation and preparation.

The required separation is:

```text
SOURCE / CANONICAL LAYER
- names
- types
- Bags
- declarations
- readable control structure
- permissions
- schema
- diagnostics

            compile once
                 |
                 v
ADMISSION / PRELINK LAYER
- verify
- resolve names
- authorize
- select native operation
- bind frame layout
- assign bank/shadow
- construct extended tables
- build generation root

                 |
                 v
HOT EXECUTION LAYER
- inspect MSB
- index prepared table
- invoke already-resolved operation
```

A future release is going in the wrong direction if it makes the hot executor rediscover information that the compiler or generation prelinker could have resolved once.

---

## 6. Generation-scoped preparation

Hot-call tables belong to a generation.

They are not global mutable language dictionaries.

JX live replacement follows the existing generation law:

```text
active generation N
       |
       | prepare beside the running generation
       v
candidate generation N+1
       |
       +-- verify Book
       +-- validate continuity
       +-- build Bags
       +-- build prepared call banks
       +-- build extended tables
       +-- build hot-register/reaction routes
       |
       v
quiescent boundary
       |
       v
atomic root swap
```

The active generation is never rewritten instruction-by-instruction while it is running.

The release principle remains:

> Patch beside, validate completely, then swap at a safe boundary.

---

## 7. Hot-path promotion policy

A hot path should be promoted because measured execution makes it worth occupying scarce one-byte space, not because its canonical name appears important.

Promotion candidates should be ranked using execution evidence gathered outside the actual dispatch lookup.

Recommended policy:

1. Count hits beside already-prelinked targets.
2. Harvest counts at an epoch boundary.
3. Require stability across multiple epochs.
4. Rank deterministically.
5. Prepare a *next-generation* bank map.
6. Validate it.
7. Swap generation roots atomically.

Never mutate the live byte-to-target meaning because one operation suddenly became popular in the middle of an instruction stream.

### Scarcity rule

There are 128 one-byte positions. Treat them as a cache of prepared semantic work.

Do not reserve large permanent regions for speculative future features. Let measured hot paths earn positions, while a small explicitly documented core set may remain stable where ABI/tooling compatibility requires it.

---

## 8. Immediate future hot paths

When new hot operations are introduced, prefer this order:

```text
1. Is it already completely determined at generation admission?
   YES -> bind a one-byte bank/shadow entry if sufficiently hot.

2. Is the operation stable but not hot enough for one-byte space?
   YES -> use two-byte family/slot form.

3. Does it require runtime operands?
   Put operands in the frame/register/Bag state where possible, then select the
   prepared operation with the instruction byte.

4. Does it require variable-length metadata?
   Keep that metadata off the instruction path and resolve it before execution.
```

Examples that can become good hot-path candidates once measured include:

- Bag field access/update
- container push/pop/get/set
- OOP method dispatch after receiver shape is known
- arithmetic and comparison kernels
- branch/reaction targets
- JX11 window/event operations
- graphics primitive submission
- network queue operations
- channel send/receive
- scheduler/runtime transitions exposed through JX

Being a common feature does not automatically justify promotion. Frequency and preparation quality do.

---

## 9. Kernel/JX boundary

JX owns the executable meaning of JX bytecode and the generation-local prepared map.

OSAura owns kernel facilities and may expose prelinked kernel operations through the same eight-shadow physical discipline.

The boundary should look like:

```text
JX canonical operation
       |
       | admission / capability / policy resolution
       v
prepared JX call
       |
       +--> JX-native target
       |
       +--> OSAura service shadow
```

The kernel must not parse canonical JX names on the hot path.

The JX executor must not search kernel service names on the hot path.

Crossing the boundary should therefore reduce to a prepared numeric target plus already-validated context/capability state.

---

## 10. Security rules

Compact does not mean unchecked.

The one-byte path is only safe because validation happened earlier.

Before a target becomes reachable through a bank/shadow entry, admission must establish:

- executable Book integrity
- supported ABI version
- target existence
- call signature/frame compatibility
- required capability or permission
- generation ownership
- bounds for referenced Bag/register/frame state
- any kernel service policy required by OSAura

The hot executor must not bypass those checks; it relies on their completed result.

Untrusted bytes must never be allowed to install arbitrary function pointers into prepared tables.

---

## 11. Required tests

Every release that touches this ABI should prove at least the following.

### Encoding

- `0x80` consumes one byte.
- `0xFF` consumes one byte.
- `0x00 xx` consumes two bytes.
- `0x7F xx` consumes two bytes.
- a single byte in `0x00..0x7F` is rejected as truncated.
- a byte in `0x80..0xFF` never consumes the next byte.

### Mapping

- low three bits select shadows `0..7` exactly.
- upper payload bits select banks `0..15` exactly.
- all 128 one-byte values map uniquely.
- extended family/slot pairs map uniquely.

### Invocation

- a bound one-byte target invokes the expected function.
- an unbound one-byte slot fails cleanly.
- a bound extended target invokes the expected function.
- hit counters saturate rather than overflow.

### Generation safety

- preparing a candidate map does not alter the active map.
- a failed candidate validation leaves the active root unchanged.
- a successful cutover changes the root atomically.
- previously executing code does not observe a half-written table.

### Cross-runtime compatibility

- JX host tests and OSAura kernel tests use identical bit extraction rules.
- a fixture emitted by the JX compiler is decoded identically by the host runtime and the OSAura runtime.

---

## 12. Performance measurements to keep

Do not report only wall-clock runtime of a large program. Keep measurements that isolate the dispatch design.

Recommended benchmark set:

```text
A. one-byte prepared call dispatch
B. two-byte extended call dispatch
C. old v3 dispatch, while migration comparison remains useful
D. direct C/native function call baseline
E. Bag hot-route lookup + prepared call
F. OOP receiver hot-route + prepared method call
G. mixed workload using realistic hot/cold proportions
```

Useful outputs:

- ns/call or cycles/call
- instructions retired if available
- branch misses if available
- bytes/instruction stream
- one-byte hit percentage
- extended-call percentage
- table memory per generation

The goal is not merely to make a microbenchmark look small. The goal is to keep canonical source readable while ensuring repeated work collapses into prepared numeric dispatch.

---

## 13. Release checklist

When changing the compiler, runtime, JX executable, or OSAura bridge, walk this list before merging.

### ABI

- [ ] Does every `1xxxxxxx` instruction consume exactly one byte?
- [ ] Does every `0xxxxxxx` instruction consume exactly two bytes?
- [ ] Are low three bits still the shadow selector?
- [ ] Are there still exactly eight shadows per prepared bank?
- [ ] Did any new feature accidentally invent a third instruction width?

### Canonical/compiler

- [ ] Is canonical JX still the readable authority?
- [ ] Is all possible name/schema/policy work done before hot execution?
- [ ] Are new prepared bindings deterministic and reproducible?
- [ ] Can the compiler explain how a canonical call lowered to bank/shadow or family/slot?

### Runtime

- [ ] Is the hot decoder branch based on the MSB only?
- [ ] Does a one-byte call require no continuation read?
- [ ] Are tables generation-scoped?
- [ ] Is live remapping done only through candidate-generation preparation and root swap?

### Kernel

- [ ] Does OSAura use the same eight-shadow rule?
- [ ] Are kernel names/policies absent from the hot path?
- [ ] Are kernel calls pre-authorized before binding?
- [ ] Does the JX/OSAura bridge agree on selector extraction?

### Validation

- [ ] Unit tests cover all boundary opcode values.
- [ ] Host and OSAura fixture decoding agree.
- [ ] Existing `.64B` verification remains green.
- [ ] generation cutover/rollback tests remain green.
- [ ] benchmark results are compared to the previous release.

---

## 14. Migration from ABI v3

For code that currently uses `JX_ASM_CALL_VERSION 3`:

1. Bump the call ABI version to 4.
2. Remove `JX_ASM_CALL_MICRO_BASE` as an instruction-length discriminator.
3. Expand the one-byte prepared table from the prior promoted subset to all 128 high-bit opcodes.
4. Represent each high-bit opcode as `bank + shadow` or as a flat 128-entry table with helper accessors preserving that interpretation.
5. Rewrite `jx_asm_call_hot()` so every opcode with `opcode & 0x80` is eligible for direct prepared dispatch.
6. Rewrite `jx_asm_call_decode()` so the MSB alone decides 1 versus 2 bytes.
7. Convert v3 microcall bindings into prepared one-byte entries when selectors/state can be prebound.
8. Convert remaining state-bearing microcalls to extended calls or prepared-frame state.
9. Update the profiler to promote into the 128 one-byte positions without changing a live generation.
10. Update tests, benchmarks, documentation, `.64B` fixtures, and OSAura decoder code in the same release train.

Do not silently reinterpret an ABI v3 Book as ABI v4. The version boundary must be explicit.

---

## 15. Suggested implementation constants

Implementations may use names like:

```c
#define JX_ASM_CALL_VERSION          4u
#define JX_ASM_CALL_HOT_BIT          0x80u
#define JX_ASM_CALL_HOT_COUNT        128u
#define JX_ASM_CALL_BANK_COUNT       16u
#define JX_ASM_CALL_SHADOW_COUNT     8u
#define JX_ASM_CALL_SHADOW_MASK      0x07u
#define JX_ASM_CALL_BANK_MASK        0x0Fu
#define JX_ASM_CALL_FAMILY_COUNT     128u
#define JX_ASM_CALL_SLOT_COUNT       256u
```

And extraction helpers equivalent to:

```c
static inline int jx_call_is_hot(uint8_t opcode) {
    return (opcode & JX_ASM_CALL_HOT_BIT) != 0u;
}

static inline uint8_t jx_call_hot_index(uint8_t opcode) {
    return opcode & 0x7fu;
}

static inline uint8_t jx_call_bank(uint8_t opcode) {
    return (opcode >> 3) & JX_ASM_CALL_BANK_MASK;
}

static inline uint8_t jx_call_shadow(uint8_t opcode) {
    return opcode & JX_ASM_CALL_SHADOW_MASK;
}
```

A flat 128-entry array is acceptable and may be fastest. The bank/shadow interpretation is the ABI model; it does not require a physically nested array.

---

## 16. Release momentum rule

Future work should start from this question:

> Can this repeated operation be completely prepared before the loop runs?

If yes, bind it.

Then ask:

> Is it frequent enough to deserve one-byte space?

If yes, promote it into a bank/shadow position in the next generation.

If not, leave it in the two-byte extended table. That is not a failure; the extended path is the stable pressure-release valve that prevents the one-byte space from becoming overloaded or semantically messy.

This keeps future releases moving in one direction instead of repeatedly redesigning dispatch:

```text
canonical meaning
      -> verify once
      -> resolve once
      -> bind once
      -> execute numerically many times
```

The compact form is therefore not a second language. It is the prepared physical form of canonical JX.

---

## 17. Non-negotiable invariants

The following statements should survive future optimization work unless the ABI version is deliberately changed:

1. **MSB 1 means complete one-byte hot call.**
2. **MSB 0 means exactly one continuation byte.**
3. **The low three hot bits select one of eight shadows.**
4. **Canonical strings never belong in the repeated dispatch path.**
5. **Prepared mappings are generation-scoped.**
6. **Live generations are replaced by validated root swap, not rewritten in place.**
7. **Security/policy resolution happens before a target is made hot-reachable.**
8. **The extended form remains available so one-byte scarcity never forces bad canonical design.**

These invariants are the foundation future JX executable and OSAura kernel optimizations should build upon.
