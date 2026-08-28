# JX (jinx)

> **Readable source. Prepared execution. Bags remember. Registers react. Compiled Books know how to wake.**

JX is a user-facing programming language, compiler/runtime system, and application model built around a deliberately simple principle:

> **Resolve cold -> bind once -> execute hot.**

JX keeps the source language readable and familiar while moving repeated work out of the execution path. The current toolchain uses **PHP as the authoring/compiler/front-end host**, PASM/PASL as the lowering engine, **JXL** as the compact prepared-execution direction, and **`.64B` Books** as the deterministic native application/container boundary.

JX is pronounced **jinx**.

---

## What JX is

JX is not "PHP with a new name," and native JX applications are not intended to execute PHP source at runtime.

PHP is currently valuable at the **front of the pipeline** because it gives JX a mature environment for parsing, compiler orchestration, development tools, host integration, testing, and the existing runtime library. JX then progressively lowers meaning into forms that do not need to rediscover that meaning every time they execute.

```text
canonical .jx source
        |
        v
PHP-backed JX front end
parse / validate / canonicalize / resolve
        |
        v
semantic JX / PASL lowering
        |
        +--------------------+
        |                    |
        v                    v
prepared JXL             native target code
        |                    |
        +----------+---------+
                   v
             compiled .64B Book
                   |
                   v
       JX host / WSJX64 / OSAura64
```

The programmer should not have to write assembly-like source to obtain a fast execution path. **Canonical readability belongs in the language. Preparation belongs in the compiler. Speed belongs in the prepared/native runtime.**

---

## The four layers

### 1. Canonical JX — what people write

Canonical JX is the human- and AI-readable authority.

It contains concepts such as:

- variables and expressions,
- control flow,
- Bags and Bag-backed containers,
- Books and Pages,
- Tasks and events,
- Controls and styles,
- SQL/data-source bindings,
- plugins,
- permissions and application semantics.

Example:

```jx
bag = Bag.underwrite(256);
ref = bag.sign("msg");
bag.set("hello-jx").commit(ref);
q = bag.quotient();
```

Run the current PHP-backed surface with:

```bash
jx --print examples/hello.jx
```

or directly:

```bash
php jx-run.php --print examples/hello.jx
```

### 2. PHP-backed front end — where cold work happens today

The PHP-backed toolchain performs work that should not be paid repeatedly by a hot program:

```text
source parsing
alias canonicalization
validation
name/type resolution
Bag/method resolution
loop compilation
schema/policy checks
native-layout selection
Book construction
prepared-binding construction
```

PHP therefore serves as a practical bridge between a friendly language and increasingly native execution.

The rule is not "run everything through PHP forever." The rule is:

> **Use the front end to discover meaning once; make the executable remember the answer.**

### 3. JXL — prepared execution

**JXL is not another source language.** A programmer normally should not hand-author it.

JXL is the prepared compact representation that the compiler/runtime can use once canonical meaning has already been resolved.

The current ratified JXL byte law is intentionally separate from the global JX ABI-v4 hot-call grammar:

```text
JXL / .8B stream

0xxxxxxx = executable JXL opcode
1xxxxxxx = attached extension/data byte; never an opcode
```

A JX/JXL session selects its decoder once at admission. The repeat path should not continually ask which language mode it is executing.

JXL is designed around prepared register windows, prelinked operations, compact state identifiers, and the rule that expensive lookup belongs outside the hot loop.

See [`docs/JXL-PREPARED-EXECUTION.md`](docs/JXL-PREPARED-EXECUTION.md).

### 4. `.64B` — the compiled Book

A `.64B` Book is the broader compiled 64-bit package/container.

It can carry:

```text
compiled code
JXL executable sections
Bag schemas/state
Book/Page metadata
hot/prepared tables
generations
manifests
assets
native ELF/PE sections
```

Native installation consumes **compiled Books, not PHP source**.

The file extension is descriptive; the package bytes and `JX64B001` identity are authoritative. `.64B` output is deterministic and checksum-verifiable.

See [`docs/NATIVE-64B.md`](docs/NATIVE-64B.md).

---

## Status vocabulary

The repository uses explicit status words so fast-moving development does not turn future ideas into accidental claims.

| Status | Meaning |
|---|---|
| **ACTIVE** | Accepted by the current compiler/runtime and covered by tests |
| **PHP-BACKED** | Usable through the current PHP host/runtime API, but not necessarily lowered through the native JX surface yet |
| **JXL** | Prepared executable representation; not canonical source syntax |
| **PLANNED** | Ratified or documented direction that is not yet claimed as implemented |

When documentation and implementation disagree, tests and the active compiler are authoritative for **ACTIVE** claims.

---

## Language surface today

The current compiler-backed control-flow surface includes:

```jx
// assignment and arithmetic mutation
$x = 1;
$x++;
$x--;
$x += 4;
$x -= 2;
$x *= 3;
$x /= 2;
$x %= 5;
$x &= 7;
$x |= 8;
$x ^= 2;
$x <<= 1;
$x >>= 1;

// conditions
if ($x > 3) {
    $x += 1;
} else {
    $x -= 1;
}

// while
while ($x) {
    $x--;
}

// for
for ($i = 0; $i < 10; $i++) {
    if ($i == 4) continue;
    if ($i == 8) break;
    $x += $i;
}

// select / switch-style lowering
select ($x) {
    case 1:
        $x += 10;
    case 2:
        $x += 20;
    default:
        $x = 0;
}

// complex values
complex $z = 3+4i;
complex $w = 1-2i;
complex $p;
$p = $z * $w;
```

`for`, `while`, `if/else`, `select`/`switch`-style selection, `break`, `continue`, integer/bitwise mutation, and complex declarations are compiler-backed today.

The semantic loop model also defines `foreach`, `do-while`, and `repeat`, but their complete surface lowering is **PLANNED**, not falsely presented as active syntax.

See [`docs/JX-PROGRAMMING-TUTELAGE.md`](docs/JX-PROGRAMMING-TUTELAGE.md) for the full programming book/manuscript.

---

## Compiled loop space

JX does not need to rediscover a loop body every iteration.

The current compiler lowers active `for` and `while` loops into bounded out-of-line compiled blocks. The canonical controller shape is:

```text
LCHECK condition
LCALL  compiled_body
[LCALL compiled_step]
LREPEAT loop_slot
```

On the current PASM ISA, `LCALL` may lower to a direct branch with a fixed continuation. A native target may instead use a machine call, tail branch, or inline block while preserving the same JX meaning.

Default active nesting depth is 8 and is explicitly bounded at compile time.

See [`docs/LOOP-SPACE.md`](docs/LOOP-SPACE.md).

---

## Bags are the semantic memory model

Bags are one of the central JX abstractions.

A Bag supplies persistent identity, ownership, capacity, generations/checkpoints, and structured state. Containers are **disciplines over Bags**, not a second unrelated memory system.

```text
Bag
|- record -> fixed dense fields
|- vector -> contiguous indexed storage
|- stack  -> LIFO
|- queue  -> FIFO ring
|- deque  -> double-ended ring
|- map    -> target-native hash discipline
`- set    -> target-native hash-set discipline
```

The runtime rule is:

> **Be native while working; become canonical at the Bag boundary.**

And for UI/control state:

> **A control is Bag-backed. Clone the view, borrow the Bag, copy only on semantic mutation.**

Moving a view should not destroy its data identity. Changing a data source should not erase unrelated control state. A semantic update advances a generation; stale generations can be rejected.

### Bag hot operations

Canonical Bag operations include:

```text
BPUSH BPOP
BPUSHF BPUSHB BPOPF BPOPB
BEMPLACE
BPEEK BRESERVE BDIRTY BSYNC
```

Human-friendly aliases are resolved before hot execution. For example:

```text
enqueue --+
append  ---+--> BPUSH --> prepared/native Bag operation
push    ---+
```

There should be no runtime string lookup merely because the programmer preferred `enqueue` over `push`.

See:

- [`docs/BAG-CONTAINERS.md`](docs/BAG-CONTAINERS.md)
- [`docs/JX-ALIASES.md`](docs/JX-ALIASES.md)

---

## Bits for truth, words for numbers, Bags for structure

The machine model deliberately does not widen every state identifier just because the target CPU is 64-bit.

```text
address width        = 64-bit
native numeric state = native words
boolean state        = packed bits
register IDs         = compact IDs
Bag/window/task IDs  = compact handles where sensible
```

For example, 256 booleans can occupy four 64-bit words:

```c
uint64_t boolreg[4];
```

The shorthand is:

> **Bits for truth. Native words for numbers. Bags for structure.**

---

## Global JX hot-call ABI v4

The **global JX/OSAura hot-call ABI** is distinct from JXL.

Its byte law is:

```text
1xxxxxxx                  -> HOT / exactly 1 byte
0xxxxxxx xxxxxxxx         -> EXTENDED / exactly 2 bytes
```

For a HOT byte:

```text
bit 7      = 1
bits 6..3  = bank 0..15
bits 2..0  = shadow 0..7
```

That gives exactly:

```text
16 banks x 8 shadows = 128 one-byte hot positions
```

The eight-shadow physical discipline is a core invariant across the machine.

The current OSAura map reserves the final two banks:

```text
F0-FF = PROTECTED / UNASSIGNED
```

**Do not consume F0-FF without an explicit ABI decision.**

See [`docs/HOT-CALL-ABI-V4.md`](docs/HOT-CALL-ABI-V4.md).

---

## The processor bus and attention model

JX/OSAura is moving toward a processor-owned multiplex model rather than copying application state around unnecessarily.

```text
Bag generation changes
        |
        v
processor-owned change descriptor
(borrowed reference, not Bag copy)
        |
        v
primary listener / foreground first
        |
        v
remaining programs in PID order
        |
        v
processor deals result
        |
        v
return through the same route
```

Memory owns Bags. The processor keeps hot references/prepared state. The bus carries change information and wake intent—not entire duplicated Bags.

For JX11, visual attention is connected to execution attention:

```text
top / focused JX11 window
        |
        v
primary listener PID
        |
        +--> first processor-bus listener
        |
        `--> direct listener-specific event delivery
```

Security subject identity remains separate from program PID/listener identity.

---

## JX11: windowing without making the host OS the language

JX11 is the host-neutral UI/window direction.

Current work includes:

- off-screen surfaces,
- alpha composition,
- damage tracking,
- compact window handles,
- parent/child windows,
- focus and hit-testing,
- pointer capture,
- keyboard/pointer routing,
- Bag-backed borrowed views,
- listener PID binding,
- listener-specific event routing,
- Win32 host input/presentation adapters,
- native X11/XCB host work.

Windows, X11, or a browser may provide mechanisms. They do not become the semantic JX object model.

A host can change without replacing the Book, Bag identity, Page state, or canonical application meaning.

---

## OSAura and WSJX64

JX is the language/compiler/runtime layer. **OSAura** is the standalone x86-64 operating-system project built around the same semantics and maintained in `dompipe/OSAura`.

```text
canonical JX
     |
     v
JXL / native prepared sections
     |
     v
.64B Book
     |
     v
JX runtime ABI
     |
     +--> WSJX64 hosted machine on Windows
     |
     `--> OSAura64 kernel
```

The boundary rule is:

> **Kernel owns mechanisms. JX owns meanings.**

Host-specific file handles, HWNDs, raw kernel pointers, and other native mechanism values should not leak into canonical JX identity.

---

## Benchmarks: what they show and what they do not

The repository carries benchmark harnesses for the current PHP/PASM implementations. These are useful measurements of implementation progress, not claims that every current JX path already beats native PHP.

### Canonical OOP container benchmark

#### 100,000 total operations

| Workload | Legacy ms | Canonical OOP ms | Native PHP ms | Legacy / new | Improvement vs legacy |
|---|---:|---:|---:|---:|---:|
| Vector add/get | 5.753 | 3.994 | 0.562 | 1.44x | 30.6% |
| Stack push/pop | 9.834 | 4.388 | 1.264 | 2.24x | 55.4% |
| Queue enq/deq | 8.523 | 6.997 | 0.724 | 1.22x | 17.9% |
| Deque back/front | 10.494 | 8.707 | 0.645 | 1.21x | 17.0% |
| Map put/get | 4.715 | 4.314 | 0.630 | 1.09x | 8.5% |
| Set add/has | 24.989 | 13.779 | 0.706 | 1.81x | 44.9% |

#### 1,000,000 total operations

| Workload | Legacy ms | Canonical OOP ms | Native PHP ms | Legacy / new | Improvement vs legacy |
|---|---:|---:|---:|---:|---:|
| Vector add/get | 53.924 | 42.449 | 8.189 | 1.27x | 21.3% |
| Stack push/pop | 80.414 | 46.253 | 14.645 | 1.74x | 42.5% |
| Queue enq/deq | 88.356 | 67.362 | 8.815 | 1.31x | 23.8% |
| Deque back/front | 96.006 | 83.465 | 9.306 | 1.15x | 13.1% |
| Map put/get | 48.917 | 45.220 | 9.232 | 1.08x | 7.6% |
| Set add/has | 240.272 | 152.258 | 10.117 | 1.58x | 36.6% |

At one million operations, the canonical implementation beats the legacy implementation in all six listed workloads. **Direct native PHP is still faster in these PHP-hosted measurements.** That gap is visible on purpose.

The native/JXL strategy is how JX intends to remove costs that those PHP-hosted benchmarks still contain:

```text
repeated name lookup       -> canonicalize once
repeated method resolution -> prelink once
runtime alias search       -> zero
wide repetitive encoding   -> compact prepared form
repeated z-order work      -> prepare once per damaged frame
large object copies        -> borrow stable Bag/view references
```

Run benchmark harnesses such as:

```bash
php benchmark-pasm-oop-fast.php
php benchmark-pasm-oop-fast-sync.php
php benchmark-pasm-oop-fast-deque.php
php benchmark-jx-bag-containers.php 1000000 7
```

Benchmark results should always identify which layer is being measured: PHP-backed runtime, PASM VM, prepared JXL, native host, or direct native baseline.

---

## SQL, media, charts, and plugins

JX treats data and presentation as separate concerns.

SQL/data-source objects can feed Bags; Controls and Charts consume Bags rather than becoming database handles themselves. This is especially important for controls because changing the source should not destroy the control's persistent Bag identity.

Host-neutral chart types currently include:

```text
pie
candle
bar
line
vectormap
```

Media/analysis is similarly Bag-oriented:

```text
media source
    -> media plugin
       -> optional processing/analysis
          -> Bag
             -> chart / algebra / Page / Control
```

Installable packages come from the repository's `plugins/` source tree and use pre/full backup policy during installation.

```bash
php jx-install.php list
php jx-install.php install intro
php jx-install.php backup-full
```

---

## Quick start

### Install command integration

```bash
# Linux / macOS
sudo php jx-install.php install-system

# Windows
php jx-install.php install-system
```

### Install required plugins

```bash
jx-install install-required
```

### Run canonical JX

```bash
jx --print examples/hello.jx
```

### Run the full active-tree gate

```bash
php -d zend.assertions=1 -d assert.exception=1 test-all.php
```

### Compile/run PASL examples

```bash
php pasm-run.php --print examples/pasl/complex-and-loops.pasl
```

---

## Repository map

| Path | Role |
|---|---|
| `jx.php` | Core PHP-backed JX runtime: Bags, Tasks, Pages, Books and core values |
| `jx-lang.php`, `jx-run.php` | JX language engine / executable front end |
| `pasm-lang-compiler-loop.php` | Active loop/control-flow compiler |
| `pasm-loop-space.php` | Canonical mutations and bounded loop-space model |
| `jx-bag-containers.php` | Bag-backed container disciplines |
| `pasm-bag-hotops.php` | Canonical Bag hot operations and lowering recipes |
| `jx-alias.php` | Compile-time alias canonicalization/provenance |
| `jx/` | Language-level docs and adapters |
| `docs/JX-PROGRAMMING-TUTELAGE.md` | Full programming tutorial/book manuscript |
| `docs/JXL-PREPARED-EXECUTION.md` | Authoritative JXL prepared-execution contract |
| `docs/NATIVE-64B.md` | Native compiled Book format/boundary |
| `docs/HOT-CALL-ABI-V4.md` | Global JX/OSAura hot-call ABI |
| `docs/BAG-CONTAINERS.md` | Bag/container architecture |
| `docs/LOOP-SPACE.md` | Loop compiler design |
| `docs/ACKNOWLEDGMENTS.md` | Project acknowledgments and lineage credits |
| `jx/COMPILER.md` | Compiler pipeline and status boundary |
| `jx/GAPS.md` | Status-aware implementation roadmap |
| `host/` | Native/browser host mechanisms |
| `plugins/` | Installable plugin source packages |
| `tests/`, `test-*.php` | Regression/conformance tests |
| `.github/workflows/` | Linux/Windows/native/compiler/runtime CI |

---

## Start with the programming book

The large JX tutorial is intentionally being written as a **PDF-ready programming book manuscript**, not merely as scattered API notes.

Read:

**[`docs/JX-PROGRAMMING-TUTELAGE.md`](docs/JX-PROGRAMMING-TUTELAGE.md)**

It covers, lesson by lesson:

- syntax and statements,
- values and types,
- arithmetic and bitwise operations,
- every current and planned loop family,
- branching and selection,
- Bags and containers,
- Books / Pages / Tasks,
- Controls, styles and data sources,
- SQL/NoSQL direction,
- OOP and aliases,
- plugins,
- errors and diagnostics,
- JX11 and event delivery,
- processor-bus semantics,
- JXL,
- `.64B`,
- native execution,
- the PHP-backed engine,
- performance methodology,
- the long-term information model.

The Markdown manuscript is intended to remain canonical so future PDF editions can be generated without maintaining a second divergent version of the language book.

---

## Documentation rules for a fast-growing language

JX is accumulating compiler, UI, OS, storage, data, plugin, and prepared-execution concepts quickly. That makes documentation discipline part of the architecture.

When adding a feature:

1. say whether it is **ACTIVE**, **PHP-BACKED**, **JXL**, or **PLANNED**;
2. keep canonical source syntax separate from executable encodings;
3. do not describe a semantic model as accepted syntax until its parser/lowering exists;
4. preserve the eight-shadow law where the global hot subsystem applies;
5. keep `F0-FF` protected/unassigned unless explicitly ratified otherwise;
6. keep JXL's byte grammar separate from the global ABI-v4 byte grammar;
7. add tests for architectural contracts that future compiler/AI work could accidentally reverse;
8. prefer one canonical explanation linked from specialized documents over contradictory copies.

`test-jx-language-doc-contract.php` enforces several of these documentation invariants in CI.

---

## What remains

JX already has substantial runtime/compiler machinery, but important work remains before the entire intended language is native end-to-end.

Among the major open areas:

- generalized `foreach`, `do-while`, and `repeat` surface lowering,
- broader function/class/method native language surface,
- complete canonical `.jx` -> JXL/native `.64B` compiler path,
- full JXL admission and execution in native hosts,
- event -> prepared execution -> Bag mutation -> present as one foreground service turn,
- complete native/host semantic conformance,
- larger realistic application benchmarks,
- continued JX11 control/window integration,
- continued SQL/NoSQL and plugin lowering,
- deeper AI/compiler documentation generated from the canonical status model.

See [`jx/GAPS.md`](jx/GAPS.md) for the maintained roadmap.

---

## Philosophy

JX is being built to let a programmer write understandable programs while the machine quietly remembers everything it can learn ahead of time.

```text
human-readable names
        -> canonical operations
        -> semantic resolution
        -> prepared identities
        -> compact executable state
        -> native mechanisms
```

The recurring design rules are:

> **Canonical source is for coders. Prepared form is for execution.**

> **Bags remember. Registers react. Prepared code executes.**

> **Kernel owns mechanisms. JX owns meanings.**

> **Clone the view. Borrow the Bag. Copy only on semantic mutation.**

> **Resolve cold. Bind once. Execute hot.**

---

## Acknowledgments

JX recognizes people and projects whose work helped sharpen its approach to defensive execution, package trust, and incoming-code awareness.

A special acknowledgment is given to **Caleb Mazalevskis**, author of **phpMussel**, for the depth and insight of that package and for the early warning it provides against incoming, would-be predatory coding practices.

See [`docs/ACKNOWLEDGMENTS.md`](docs/ACKNOWLEDGMENTS.md) for the full project acknowledgment.

---

## Lineage

JX converges the earlier `dompipe/pasm-v2` and `dompipe/jx-lang` work while retaining history. PASM remains the execution-engine lineage beneath the JX language/compiler/runtime surface.

Historical material remains under `history/jx-lang/` and `docs/history/` for provenance rather than being silently rewritten into current behavior.

---

**JX — pronounced jinx. A readable PHP-backed language front end being compiled toward prepared JXL and native `.64B` Books.**
