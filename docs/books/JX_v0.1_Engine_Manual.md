# JX v0.1 Engine Manual

## Follow the same thought all the way down

The Programming Guide begins with Books, Bags, Pages, Controls, and PASL. This manual follows those same ideas downward into PASM, bytecode, memory, containers, scheduling, compiler targets, and hosts.

The engine is allowed to be complicated.

The public language should not become complicated just because the engine is.

---

## 1. The stack

```text
JX surface
  Books / Bags / Pages / Controls / Delivery
                    |
                  PASL
                    |
          compiler / lowering
                    |
                  PASM
       assembly + bytecode + VM
                    |
       memory / containers / runtime
                    |
      browser / Win32 / X11 / native
```

A short way to remember it:

> **JX says what. PASL says it lower. PASM says it small. The host makes it happen.**

---

## 2. JX and PASM are not duplicates

`jx.php` defines the product-facing runtime ideas:

- `Bag`
- `RefSign`
- `BagWrite`
- `Task`
- `Page`
- `Book`
- `Delivery`
- `Complex`
- `SmartTable`
- `Sym`
- `Jx`

PASM supplies lower machinery:

- register-shaped operations,
- bytecode,
- optimizing assembler,
- memory arena,
- frames and segments,
- containers,
- executable master table,
- cooperative scheduler,
- runtime networking and atomics.

The implementation map in `jx/PASM_MAP.md` makes the intended relationship explicit: PASM frames become Page/Task state; PASM segments support Bag storage; PASM containers become Bag-resident structures; the master table grows toward the JX Smart Table.

---

## 3. The original PASM machine

The older PASM runtime in `pasm-double-digit.php` uses static register-like fields.

Representative registers include:

```text
ecx ah adx bdx cdx ddx edx rdx
```

alongside state such as:

```text
ZF OF CF
stack ST0 sp
string qword
err err_str
```

An old-style addition is shaped around register state:

```php
PASM::$ecx = 4;
PASM::$ah = 7;
PASM::add();
$result = PASM::$rdx;
```

That historical layer matters because it established the instruction vocabulary and the visual relationship between higher-level PHP and low-level operations.

But modern JX work should usually target the bytecode/runtime layers rather than growing the static instruction class indefinitely.

---

## 4. PASM bytecode

The modern executable vocabulary includes base instructions such as:

```text
HALT
MOVI MOVR
ADD SUB MUL DIV MOD
AND OR XOR SHL SHR
CMP
JMP JZ JNZ
PUSH POP
INC DEC NEG
LOAD32 STORE32
RET
```

These are deliberately small.

A compiler can take a larger source construct and reduce it to a sequence from that vocabulary.

Example conceptual lowering:

```pasl
$x = 2 + 3;
```

becomes assembly shaped like:

```asm
MOVI ecx, 2
MOVI ah, 3
ADD  rdx, ecx, ah
RET  rdx
```

and the assembler turns that text into compact bytecode.

---

## 5. Superinstructions

`pasm-bytecode-optimized.php` performs peephole fusion before encoding.

Current fused operations include:

```text
MOVI2_ADD
MOVI2_MUL
CMP_JZ
CMP_JNZ
DEC_CMP_JNZ
LOAD32_ADD
```

A common sequence such as:

```text
CMP a, b
JNZ somewhere
```

can become one dispatch:

```text
CMP_JNZ a, b, somewhere
```

The important optimization idea is simple:

> **Do not make the VM rediscover a pattern every time it runs. Recognize the pattern while compiling.**

Labels are barriers: the optimizer does not blindly fuse across control-flow boundaries.

---

## 6. PASL control flow

The numeric PASL compiler maps variables into a small register set:

```text
ecx ah adx bdx cdx ddx edx rdx
```

The root PASL compiler currently works with eight registers. Complex values use two registers each.

Real PASL:

```pasl
$sum = 0;
$i = 5;
while ($i) {
    $sum = $sum + $i;
    $i--;
}
```

Conceptually becomes:

```text
initialize sum
initialize i
while_head:
    compare/test i
    jump to end when false
    add i into sum
    decrement i
    jump to while_head
while_end:
```

The bytecode ISA's comparison path is strongly centered on zero/equality checks. That is why the restricted compiler favors conditions such as:

```text
==
!=
nonzero truthiness
```

and counting loops that can be expressed with increment/decrement plus equality.

---

## 7. Two PASL layers are converging

The repository contains an older/root PASL path:

```text
pasm-lang.php
pasm-lang-core.php
pasm-lang-compiler.php
pasm-lang-engine.php
```

and a newer integrated compiler package under:

```text
pasl/
```

The newer package expands beyond the numeric core with routes for strings, arrays, object/bag-like data, network fetch, live behavior, C output, architecture output, and host work.

`pasl\Package` is the important public router. Its current target methods include:

```php
Package::toC($source);
Package::toX86($source);
Package::toArm($source);
Package::toPasmAsm($source);
```

and:

```php
Package::compile($source, $mode);
```

JX v0.1 treats these as one compiler family that needs to converge, not two languages programmers should have to learn separately.

---

## 8. Compiler grammar stays rhetorical

The public idea is:

```text
source -> target -> options
```

The host PHP adapter already supports:

```php
$result = jx\Flow::compile($source, 'pasm');
```

The target may change:

```php
jx\Flow::compile($source, 'c');
jx\Flow::compile($source, 'x86');
jx\Flow::compile($source, 'arm');
```

As NASM and C-with-ASM routes are integrated, they belong in the same target position.

The compiler is allowed to perform several internal passes. The author should not have to learn a different call shape for each pass.

---

## 9. Resistant lowering

The Smart Table describes operations with information such as:

```text
id
module
side effect
requires reference?
memory class
purity score
native template
Resistant template
```

The first implementation uses a simple native/Resistant decision. The larger design is to make this decision from provable facts about arguments, memory, ownership, and target support.

The key rule for v0.1 is:

> **Resistant output still compiles.**

A long fallback is not supposed to stop at a comment saying "unsupported." If a legal fallback exists for the target environment, emit it and mark it.

That means:

```text
JX/PASL source
      |
 Smart Table
   /      \
short    long
native  Resistant
   \      /
    target
```

The public call stays stable.

---

## 10. PASM memory arena

`pasm-runtime.php` contains `PASMMemory`, a fixed-size byte-addressable arena.

Its basic shape includes:

```php
$memory = new PASMMemory(1_048_576);
$ptr = $memory->alloc(64);
$memory->writeU32($ptr, 1234);
$value = $memory->readU32($ptr);
$memory->free($ptr);
```

The runtime also exposes operations such as:

```text
read / write
memset / memcpy
readU8 / U16 / U32 / U64
writeU8 / U16 / U32 / U64
```

This gives PASM programs a concrete memory model without using arbitrary PHP variables as the only storage mechanism.

---

## 11. JX Bag memory law

The JX Bag sits at a different level than the raw PASM memory arena.

A Bag is underwritten:

```php
$bag = jx\Bag::underwrite(1024);
```

A write is prepared:

```php
$write = $bag->set($value, 'node');
```

and only becomes mutation through a live reference:

```php
$ref = $bag->sign('node');
$write->commit($ref);
```

Capacity is checked at commit time.

The lower PASM model can eventually implement the Bag more directly, but the language law should remain visible even if the storage mechanism changes.

---

## 12. Frames and segments

PASM's canonical architecture uses register frames and page-backed segmented storage.

The JX mapping is:

```text
PASM frame    -> Page / Task identity and hot state
PASM segment  -> Bag/container canonical backing
sync boundary -> handshake / commit / persistence boundary
```

This separation lets normal work stay hot while canonical state remains exportable.

---

## 13. Hot containers

The optimized PASM container design deliberately avoids encoding and segment work on every operation.

Hot paths use native PHP structures:

- List/Vector - packed PHP array.
- Stack - packed array with direct push/pop.
- Queue - append plus a head index and periodic compaction.
- Deque - power-of-two circular ring.
- Map - PHP hash table.
- Set - typed scalar signatures with fallback representation for complex values.

Canonical segmented state is materialized at explicit boundaries such as:

```text
dirtySegments()
clearDirty()
flush()
defrag()
register export
remote sync
persistence
```

The design rule is:

> **Be native while working. Become canonical when crossing a boundary.**

---

## 14. Container benchmark meaning

The included benchmark compares legacy PASM containers, the new hot-path canonical containers, and direct native PHP structures.

At one million operations, representative median measurements were:

| workload | legacy PASM | hot-path canonical | direct PHP |
|---|---:|---:|---:|
| Vector add/get | 53.924 ms | 42.449 ms | 8.189 ms |
| Stack push/pop | 80.414 ms | 46.253 ms | 14.645 ms |
| Queue enq/deq | 88.356 ms | 67.362 ms | 8.815 ms |
| Deque back/front | 96.006 ms | 83.465 ms | 9.306 ms |
| Map put/get | 48.917 ms | 45.220 ms | 9.232 ms |
| Set add/has | 240.272 ms | 152.258 ms | 10.117 ms |

The correct lesson is not that PASM has already beaten PHP's own containers.

The lesson is that moving segmentation/codec work out of every operation substantially reduced PASM overhead.

The pathological Deque case is even more dramatic: an old opposite-end pattern that took seconds was reduced to millisecond-scale work by switching to a circular ring.

Algorithm shape mattered more than micro-tuning.

---

## 15. Using PHP's native container strengths

The hot-container rewrite already follows an important direction: it does **not** try to reimplement PHP's hash table or packed-array machinery in slow PHP code.

It uses those native engine structures on the hot path and adds PASM identity/checkpoint behavior around them.

That is the right baseline for v0.1.

For a future native backend, the equivalent idea is:

```text
JX container meaning
        |
 target analysis
   /          \
PHP host     native binary
   |              |
PHP array      target-native
/hash/ring      structure
   \              /
 canonical PASM/JX boundary
```

Copying PHP's *public behavior* can be useful. Blindly copying Zend Engine internals into NASM would tightly bind JX to one PHP implementation and version. The better optimization target is to preserve the same useful data-structure strategies - packed arrays, hash tables, ring queues, amortized growth - in the native target.

---

## 16. Master Table: executable vocabulary

`pasm-master-table.php` stores reusable executable entries.

Entries can be:

```text
primitive
bytecode
composite
```

The table names primitive schemas such as `ADD`, `SUB`, `CMP`, and `RET`, and can also store compiled commands or larger blocks.

A block can be addressed by a stable content-derived ID. Packages can be exported with dependencies and imported elsewhere.

Conceptually:

```text
primitive
   |
command
   |
block
   |
composite
   |
reusable program vocabulary
```

This is the lower-level ancestor of the richer JX Smart Table.

---

## 17. Cooperative scheduler

The master-table runtime also includes `PASMCooperativeScheduler`.

A scheduled task owns a sequence of executable block references.

One tick executes at most one named block and then yields.

```text
Task A block 1 -> yield
Task B block 1 -> yield
Task A block 2 -> yield
Task B block 2 -> done
```

The current scheduler is intentionally simple. The important part is that execution boundaries are explicit enough to coordinate with frame state, canonical sync, and later Book/Page scheduling rules.

---

## 18. Networking and packets

`pasm-runtime.php` also contains a compact `PASMPacket` format and socket-backed `PASMNetwork`.

Packet fields include a magic marker, version, opcode, flags, sequence, length, CRC, and payload.

The network runtime can send register-shaped messages and apply received register updates.

This is low-level runtime plumbing. JX Books should normally use a higher host or library contract rather than exposing socket implementation details in every application.

---

## 19. Atomics and locks

`PASMAtomic` provides cooperative primitives such as:

```text
lock / unlock
compareExchange
exchange
increment
```

File locks provide cross-PHP-process exclusion for named locks.

Again, the rhetorical JX surface should describe intent at a higher level while PASM performs the positional operation underneath.

> **Rhetoric belongs at the human edge. Positional precision belongs at the machine edge.**

---

## 20. Books and XI/XIP

The XI/XIP subsystem treats a Book as an application/site section with:

- leaves,
- a spine,
- Binding state,
- channels,
- drops,
- tables,
- PASL programs,
- window metadata.

The runtime `jx\Book` separately owns Bags, Pages, and a memory quota.

The coherent JX Book is the convergence of those two views:

```text
Book
 |- quota / ownership
 |- Bags
 |- Pages / leaves
 |- Binding
 |- libraries
 |- channels
 |- Controls
 |- PASL programs
 |- window contract
 `- compiler artifacts
```

JX v0.1 should move toward one Book model, with XI acting as presentation/persistence rather than defining a second unrelated application object.

---

## 21. Binding

XI's `Binding` keeps:

```text
Book id
spine
cursor
history
leaf metadata
tables
channel bus
```

It supports forward, back, open, snapshot, and restore.

The important design principle is that navigation belongs to Book state rather than being assumed to exist only in browser history.

That makes the same Book more portable across browser and native window hosts.

---

## 22. JX host protocol

Browser, Win32, and X11 are intended to be peer hosts.

The stable event boundary is a versioned JSON drop identified as:

```text
jx.host/1
```

A host message carries fields such as:

```text
type
host
window
book
leaf
sequence
payload
```

The Book remains the application concept. The host supplies window creation, input, and presentation.

---

## 23. Browser PASM VM

The browser host currently includes a compact JavaScript PASM interpreter.

It supports a useful numeric/control subset and uses an instruction budget to prevent unbounded execution.

That JavaScript VM is replaceable.

A future WebAssembly or native browser execution engine should be able to replace it without changing Book, Binding, PASL program, or host-drop semantics.

---

## 24. Controls are a compiler/host contract

Controls are host-neutral descriptions rather than HTML widgets.

Current families include:

```text
text
spin
toggle
drawing
image
```

Movement and drawing operations include lines, curves, polygons, paths, image brushes, pins, output connectors, and Themes.

The rhetorical adapter in `jx/Flow.php` makes geometry read in human order:

```php
Flow::curve(
    'sweep',
    Flow::from(0, 80),
    Flow::through(40, 10),
    Flow::to(180, 80),
    Flow::like(['smooth' => 0.82]),
);
```

while the existing storage contract can keep whichever internal order is convenient.

This is an important compiler principle:

> **Surface grammar and storage grammar do not need to be identical.**

---

## 25. Rhetorical roles can become compiler evidence

`Flow::from()`, `through()`, `to()`, `like()`, `with()`, and `at()` carry role tags in their host representation.

Today those roles improve readability and permit adapter validation.

Later the parser/compiler can use the same concept to detect malformed calls before lowering.

A rhetorical order can therefore serve two purposes:

1. make the code easier for a human to continue reading;
2. give the compiler extra semantic evidence.

The role metadata should disappear after resolution so native output does not pay for prose-level structure at runtime.

---

## 26. Libraries should lower with the Book

Libraries are not supposed to become another runtime universe.

The coherent compile model is:

```text
Book
 |- library A
 |- library B
 |- page/home
 `- page/editor
        |
   resolve symbols
        |
 lower each artifact
        |
 PASM / C / NASM / target
```

A library should be reusable source or compiled vocabulary that can be linked at Book or wider library scope.

The public paragraph should stay:

```text
Book -> withLibrary -> withPage -> compileTo
```

The compiler handles ordering, symbols, target templates, and Resistant fallback underneath.

---

## 27. Plugins

The JX plugin catalog currently includes modules such as core, decimals, complex, Delivery, Smart Compiler, const, language bridge, and introduction material.

The target gate requires plugins to declare Windows, macOS, Linux, and web support in the current version.

Packages are also intended to be context-free: they should not depend on sibling plugin paths or installation order.

This is strict, but it protects one of JX's central goals: a Book or library should not silently become tied to one host merely because a helper package was installed.

---

## 28. The gaps that matter most

The original JX gap list correctly highlights:

- handshake detail,
- RefSign strength and lifetime,
- scheduling policy,
- server/browser protocol,
- Book versioning and hot reload,
- Resistant error semantics,
- const and Delivery interaction,
- PHP/JX crossing rules,
- complex accounting,
- live-state coherence.

The coherent v0.1 pass adds three structural priorities:

1. one Bag meaning across runtime and XI;
2. one Book meaning across runtime and XI;
3. one PASL compiler family instead of separate mental models.

The solution pattern should be convergence, not another wrapper layer for every mismatch.

---

## 29. Optimization priorities

When optimizing JX/PASM, use this order:

### First: remove bad algorithm shapes

The Deque rewrite proves why. Replacing repeated array rebuilds with a circular ring can dwarf instruction-level tuning.

### Second: keep hot state native

Use the host's efficient packed arrays/hash tables while work is local.

### Third: pay canonical cost at boundaries

Encode/segment/checkpoint when state crosses a meaningful boundary.

### Fourth: fuse common instruction sequences

Superinstructions reduce dispatch cost after the larger architecture is sound.

### Fifth: specialize by target

A native binary should use native data-structure and instruction strategies rather than pretending it is still inside PHP.

> **Factor first. Fuse second. Specialize last.**

---

## 30. What "native" should mean

There are several different meanings of native in this project. Keep them separate.

### Native host structure

Using PHP's own array/hash implementation on the PHP hot path.

### Native compiler output

Generating C or architecture assembly that can be built into an OS binary.

### Native window host

Rendering a Book through Win32, X11, or another OS surface instead of HTML.

### Native Smart Table lowering

Choosing the preferred short target template instead of the Resistant template.

A good JX manual should always make clear which kind of native it means.

---

## 31. The end product

The end product is not "PHP pretending to be assembly."

The coherent goal is:

```text
A programmer writes a JX Book.
The Book owns Bags, Pages, libraries, Controls, and state.
PASL lowers program work.
PASM supplies a small machine vocabulary and runtime boundaries.
The compiler chooses a target and a legal native/Resistant route.
The host presents the Book.
```

A younger programmer should be able to stop at the first three lines and build something.

An engine programmer should be able to follow the same thing all the way to registers, pages, segments, bytecode, and native code.

That is coherence.

---

# JX v0.1 engine mnemonics

> **JX says what. PASL says it lower. PASM says it small.**

> **Be native while working. Become canonical at the boundary.**

> **Factor first. Fuse second. Specialize last.**

> **Surface grammar and storage grammar do not need to match.**

> **Rhetoric belongs at the human edge. Positional precision belongs at the machine edge.**

> **Resistant means a safer road, not a different language.**

> **A Book owns the room. The engine carries the room downward.**
