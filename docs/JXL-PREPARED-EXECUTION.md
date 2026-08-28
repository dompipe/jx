# JXL Prepared Execution Contract

## Status

**Authoritative for JXL.**

JXL is the prepared executable companion to canonical JX. It is deliberately **not** another programmer-facing source language and it is deliberately **not** the same byte grammar as the global JX Hot-Call ABI v4.

When this document conflicts with older exploratory `.8B` notes, this document wins for JXL.

The distinction is intentional:

```text
canonical JX           human / AI readable program meaning
JX ABI v4              global prepared call ABI used by JX/OSAura services
JXL / .8B              compact prepared executable stream for admitted code
.64B                   compiled Book/container that may carry JXL sections
```

The central design rule is:

> **Canonical JX explains. JXL remembers the compiler's decisions. The runtime repeats only the cheap part.**

---

## 1. JXL byte law

JXL uses a simple stream law:

```text
0xxxxxxx = executable JXL opcode
1xxxxxxx = attached extension/data byte; never an opcode
```

This is intentionally separate from global JX ABI v4, whose first-byte law is:

```text
1xxxxxxx                  = HOT / exactly one byte
0xxxxxxx xxxxxxxx         = EXTENDED / exactly two bytes
```

Do not feed a stream to the wrong decoder. A Book admission record selects the execution mode once and binds the correct decoder/executor before the repeat path begins.

Recommended session/admission mode identity:

```text
0 = JX ABI mode
1 = JXL mode
```

The mode is resolved once on admission. A hot loop must not repeatedly ask whether it is running JX ABI or JXL.

---

## 2. Why JXL exists

Canonical source should remain comfortable to read:

```jx
for ($i = 0; $i < 1000; $i++) {
    $sum += $i;
}
```

The compiler may know much more by the time that source is admitted:

- the identity of `$i` and `$sum`,
- register allocation,
- the loop's prepared body,
- branch destinations,
- Bag field offsets,
- method receiver shapes,
- permissions already established for a call,
- the native operation behind a canonical alias,
- the active register window,
- constants and immutable values,
- whether a value is hot enough to remain resident,
- which kernel/runtime service is prelinked.

JXL exists so those decisions do not have to be rediscovered on every iteration.

```text
canonical source
    -> parse
    -> validate
    -> resolve aliases/names/types
    -> authorize
    -> allocate/registerize
    -> form prepared blocks
    -> prelink operations
    -> JXL
    -> execute prepared work
```

---

## 3. Executable versus attached bytes

A JXL executable byte always has its high bit clear.

```text
00..7F = executable opcode namespace
```

Bytes with their high bit set are attached state/data for a preceding executable form or for a declared prepared record. They are not independently dispatched.

```text
80..FF = extension/data namespace
```

This lets an admitted block carry compact attached information without creating ambiguity about whether the byte begins a new operation.

A JXL decoder therefore has an invariant equivalent to:

```c
uint8_t b = *pc++;
if ((b & 0x80u) == 0u) {
    execute_jxl_opcode(b);
} else {
    reject_or_consume_only_as_declared_attachment(b);
}
```

An unattached high-bit byte is malformed input.

The exact number and meaning of attached bytes belong to the prepared opcode/block metadata, not to a second ad-hoc instruction grammar.

---

## 4. JXL and register windows

JXL retains the useful register-window idea: a large logical register file can be exposed to a compact block through an eight-entry local window.

Canonical v1 logical register identity:

```text
register ID = 8 bits     -> 0..255
register value = native 64-bit JX working value unless typed metadata says otherwise
```

A prepared window contains eight full register IDs:

```c
uint8_t register_id[8];
```

Example:

```text
W7 = [40, 41, 52, 53, 80, 81, 200, 201]
```

A prepared block binds to `W7` once. Local selectors `0..7` then point to those already-resolved registers.

Programmers do **not** write window-management instructions in ordinary JX. The compiler performs liveness analysis and block formation.

The rule remains:

> **Do not spend runtime instructions selecting a register page when the compiler can bind the page to the block.**

---

## 5. Bags versus registers

JXL does not replace Bags.

```text
Bags      = durable structured semantic state
registers = immediate hot working state
JXL       = prepared executable behavior
```

The intended relationship is:

> **Bags remember. Registers react. Prepared code executes.**

A hot Bag field may be promoted/cached into a register for a prepared region. A semantic write still belongs to the Bag's ownership/generation law. The register is acceleration, not a second authority.

For UI controls the same principle means:

```text
Control Bag = persistent identity/data/state
Control view = borrowed reference + placement
JXL code     = prepared reaction to event/change
```

Moving a view does not clone or rewrite the Bag.

---

## 6. JXL inside `.64B`

`.64B` is the broader 64-bit compiled Book container. It may carry:

- manifests,
- Bag schemas,
- generations,
- Page/control descriptions,
- assets,
- permissions/capability metadata,
- native ELF/PE sections,
- hot/prepared tables,
- one or more JXL executable sections,
- canonical/debug maps when intentionally retained.

Conceptually:

```text
BOOK.64B
|- JX64/header.bin
|- JX64/manifest.json
|- BAG/schema.bin
|- PAGE/layout.bin
|- HOT/bindings.bin
|- CODE/main.jxl
|- CODE/native.elf or native.pe (optional target section)
`- DEBUG/canonical-map.json (optional)
```

Native installation consumes compiled Books. It does not require PHP source at runtime.

---

## 7. Cold admission, hot execution

Admission is allowed to be careful and comparatively expensive. Repetition should be cheap.

### Cold/admission work

```text
verify Book bytes
verify version
verify hashes
choose JX ABI or JXL mode
resolve block table
resolve register windows
validate attached-byte declarations
resolve Bag/frame offsets
resolve canonical operation IDs
check capabilities/policy
bind native executor targets
prepare branch destinations
prepare generation root
```

### Hot/repeat work

```text
fetch executable JXL byte
invoke already-bound executor
use prepared register/Bag/frame references
advance/branch
```

If a hot executor has to look up a string, search a schema, parse JSON, find a method by name, ask what mode it is in, or rebuild a register window, preparation is incomplete.

---

## 8. Relationship to the global Hot-Call ABI v4

JXL and ABI v4 can coexist in one product because they solve different boundaries.

### ABI v4

Use when the executable contract itself is the global prepared call ABI shared with JX/OSAura services:

```text
1bbbbsss = one-byte hot bank/shadow
0fffffff ssssssss = two-byte extended family/slot
```

There are exactly eight shadows per hot bank.

`F0-FF` remains protected/unassigned in the fixed global subsystem mapping unless an explicit future ABI ratification says otherwise.

### JXL

Use for admitted prepared program streams:

```text
0xxxxxxx = executable
1xxxxxxx = attachment/data
```

A JXL executable may ultimately invoke a prelinked ABI-v4 target, but the JXL decoder must not reinterpret JXL bytes using ABI-v4 instruction-length rules.

---

## 9. Canonical lowering example

Canonical JX/PASL-like source:

```jx
$sum = 0;
for ($i = 0; $i < 100; $i++) {
    $sum += $i;
}
```

A compiler may determine:

```text
$sum -> R40
$i   -> R41
block window -> W3=[40,41,...]
loop condition target -> prepared address A
loop body target      -> prepared address B
step                   -> VINC R41
sum mutation           -> VADD R40,R41
```

JXL records the already-made decisions. It is not obligated to preserve the spelling `$sum`, `$i`, `for`, or `+=` in the executable stream. Debug/canonical maps may retain them outside the hot stream.

---

## 10. Types in JXL

Canonical JX owns developer-facing types. JXL owns prepared storage/execution representations.

A future typed register descriptor may include compact tags for values such as:

```text
integer
unsigned
float
boolean
handle
Bag reference
window/control handle
pointer-like host-internal reference (never exposed as a portable JX address)
complex pair
```

The important rule is not the exact tag numbering yet. It is that a JXL opcode should not repeatedly rediscover a type that admission can prove once.

Typed metadata must remain versioned and validated.

---

## 11. Branches and loops

Canonical loops stay readable. JXL should preserve prepared loop-space decisions.

Current compiler concepts include:

```text
LCHECK   prepared guard
LCALL    prepared body/step transfer
LREPEAT  loop-slot repetition
```

The current PASM target may lower a prepared loop call to a direct branch with a fixed continuation; a native target may use a machine call, tail branch, or inline body. JXL records the semantic prepared block relationship rather than requiring one machine implementation.

Nested loop-space is bounded at compile time. The current default semantic depth is eight.

---

## 12. Aliases disappear before JXL

JX allows readable aliases, but aliases do not belong in prepared execution.

Example:

```text
enqueue / append / push
        -> canonical BPUSH
        -> discipline/native lowering
        -> prepared JXL/native target
```

Diagnostics may retain provenance:

```text
source_spelling = enqueue
canonical       = BPUSH
```

The hot executor sees only the canonical/prepared identity.

---

## 13. JXL generation law

Prepared streams and their bindings belong to a generation.

Do not mutate the executable meaning underneath code that is currently running.

```text
generation N running
      -> prepare N+1 beside it
      -> validate Book/JXL/tables
      -> reach safe boundary
      -> atomically swap generation root
```

Rollback selects a previously validated generation/root. It does not attempt to reverse random individual mutations in live executable tables.

---

## 14. Security

Compact execution is safe only when preparation is trustworthy.

Before JXL executes, admission must establish as applicable:

- Book integrity and supported format,
- JXL format version,
- block/table bounds,
- legal attachment lengths,
- register IDs/window bounds,
- Bag/frame bounds,
- branch target validity,
- operation/signature compatibility,
- required capabilities,
- generation ownership,
- host/kernel service authorization,
- no arbitrary untrusted function-pointer installation.

A JXL runtime may rely on completed admission checks. It may not use compactness as a reason to skip them.

---

## 15. Required JXL tests

Every implementation should eventually prove:

### Stream law

- every executable byte has bit 7 clear,
- every high-bit byte is consumed only as declared attached data,
- an unattached high-bit byte is rejected,
- truncated attachments are rejected,
- one block cannot read beyond its declared code range.

### Preparation

- block -> register-window bindings are deterministic,
- local selector `0..7` resolves to the expected full register ID,
- no register-window search occurs in the repeat executor,
- branch targets resolve before execution,
- canonical alias spellings are absent from release JXL.

### Mode separation

- JX ABI fixture decodes only with the JX ABI decoder,
- JXL fixture decodes only with the JXL decoder,
- admission binds mode once,
- the repeat executor has no per-instruction mode branch.

### Host parity

The same validated JXL section should have the same semantics under:

```text
jx native host
WSJX64
OSAura64
```

Backend mechanics may differ; prepared meaning may not.

---

## 16. Source rule for programmers and AI

Never teach application programmers to hand-optimize JXL bytes.

Teach canonical JX.

A programmer should write:

```jx
$orders = Bag.underwrite(65536);
for ($i = 0; $i < $count; $i++) {
    // readable work
}
```

The compiler should decide:

```text
register residency
window selection
hot/cold partition
canonical alias lowering
prepared branch blocks
JXL opcode choice
native shadow target
Bag synchronization points
```

This rule is particularly important as AI-generated JX becomes common. Generated source should maximize correctness and readability; the compiler is responsible for compact execution.

---

## 17. The intended product path

```text
USER / AI
  writes canonical .jx
        |
        v
PHP-BACKED JX FRONT END
  parses / validates / resolves / explains
        |
        v
SEMANTIC IR + BAG / PAGE / TYPE INFORMATION
        |
        +--> web/PHP host when that is the chosen target
        |
        +--> JXL prepared executable sections
        |
        +--> target-native ELF/PE sections
        v
.64B COMPILED BOOK
        |
        v
JX / WSJX64 / OSAura64 admission
        |
        v
prepared execution
```

PHP is a powerful front end and web/server host. It is not required to remain in a native installed Book's hot execution path.

---

## 18. One sentence to retain

> **JX is the language people read; JXL is the execution the compiler remembers.**
