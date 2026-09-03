# JX (jinx)

> **Readable source. Prepared execution. Bags remember. Registers react. Compiled Books know how to wake.**

JX is a programming language, compiler/runtime system, and application model built around one recurring rule:

> **Resolve cold -> bind once -> execute hot.**

JX keeps canonical source readable while moving repeated interpretation, lookup, alias resolution, layout selection, and dispatch work out of the hot path. The current implementation uses PHP as a cold authoring/compiler host, PASM/PASL as the lowering lineage, JXL as the compact prepared-execution stream, and `.jxb` as the public compiled Book/package boundary.

JX is pronounced **jinx**.

---

## Current measured result

The seven native Bag disciplines now cluster in the same performance range instead of Map and Set being large outliers.

Latest corrected unified CI snapshot, **September 3, 2026**, commit `e465f314c53f7801a90b64db80df8139ad6cfd5b`, GitHub Actions run `33731702206`:

- Ubuntu 24.04.4 x86-64
- PHP 8.3.33
- NASM 2.16.01
- 1,000,000 logical operations per master workload
- 5 measured repetitions
- 1 warmup
- actual six-byte prepared JXL executor
- actual x86-64 assembly container routines
- the master `Map` row now measures the production **keyed-Vector Map**, not the retained split comparator

| Container | PHP array ms | JXL native ms | Relationship in this run |
|---|---:|---:|---|
| Record | 2.800 | 5.202 | PHP assoc baseline faster; not fixed-slot vs fixed-slot |
| Vector | 5.076 | 5.265 | near parity; JXL ~3.7% slower |
| Stack | 10.548 | 5.109 | JXL ~2.06x faster |
| Queue | 5.169 | 5.352 | near parity; JXL ~3.5% slower |
| Deque | 4.942 | 5.506 | JXL ~11.4% slower |
| **Map** | **4.497** | **6.986** | production keyed Vector; JXL ~1.55x PHP-array time |
| **Set** | **6.806** | **6.882** | effectively parity |

Absolute times vary between hosted CI runners, so **same-run A/B ratios are more authoritative for layout decisions than comparing milliseconds from different GitHub runners**.

### Map is now measured as the Map that JXL actually uses

The active native Map is an ordered **keyed Vector**:

```text
Map = Vector<Entry>
Entry = [ key, value ]

u64 -> u64 native memory:

[K0][V0][K1][V1][K2][V2]...
 \ entry0 / \ entry1 / \ entry2 /

entry(i) = base + i * 16
key      = entry + 0
value    = entry + 8
```

The older synchronized split representation remains linked in the same runtime image solely for comparison:

```text
keys:   [K0][K1][K2]...
values: [V0][V1][V2]...
```

Production JXL Map IDs resolve to the keyed-Vector routines.

### Split Map vs keyed-Vector Map — same executable, same runner

The dedicated A/B benchmark runs both layouts through the **same six-byte JXL decoder/dispatcher**, the same 80-byte admitted binding ABI, and the same native build. That isolates the memory-layout choice far better than comparing separate commits or runners.

Dedicated A/B run inside GitHub Actions run `33731702206`: 7 reps, 1 warmup.

| Workload | Logical ops | Split `keys[] + values[]` | Keyed `Vector<Entry>` | Vector / split | Winner |
|---|---:|---:|---:|---:|---|
| ordered append + get | 1,000,000 | 7.429 ms | **6.870 ms** | **0.925x** | **keyed Vector** |
| random update + get | 1,000,000 | **167.064 ms** | 189.129 ms | 1.132x | **split** |
| descending insert + get | 20,000 | 51.025 ms | **34.334 ms** | **0.673x** | **keyed Vector** |
| shuffled insert + get | 20,000 | 27.122 ms | **18.565 ms** | **0.685x** | **keyed Vector** |

The keyed Vector wins **three of four** measured layouts:

- standard ordered append/get: about **7.5% lower time**;
- descending insertion: about **32.7% lower time**, or roughly **1.49x faster**;
- shuffled insertion: about **31.5% lower time**, or roughly **1.46x faster**;
- random update/get is the exception: the split representation is about **11.7% lower time** than the keyed Vector in that workload.

That result supports the keyed Vector as the production Map. The split layout's random-update advantage is still useful evidence: its dense key-only array reduces the cache footprint of key searches. A future optimization can target that lookup weakness without giving up the keyed Vector's single-entry insertion and movement law.

### Benchmark-attribution correction

An earlier version of this README labeled a roughly `5.08 ms` Map result as keyed-Vector performance. Inspection of the harness found that `benchmark_jxl_containers.c` was still directly binding the retained split `jx_map_put_u64` / `jx_map_get_u64` comparison routines.

That number was therefore a **split-Map measurement**, not a keyed-Vector measurement.

The provider is now corrected: `benchmark-jxl-containers.php` runs the same-build A/B benchmark and uses the keyed-Vector result for the authoritative `native.map` metric. The split result remains available under the A/B data for provenance. The benchmark contract test and the corrected container workflow both pass.

Full benchmark tables and methodology are below.

---

## What JX is

JX is not "PHP with a new name," and native JX applications are not intended to execute PHP source in their hot runtime path.

PHP is useful today at the **front of the pipeline** because it provides a mature environment for parsing, compiler orchestration, development tools, testing, host integration, and the existing runtime library. JX progressively lowers canonical meaning into executable forms that do not need to rediscover that meaning every time they run.

```text
canonical .jx source
        |
        v
PHP-backed JX front end
parse / validate / canonicalize / resolve
        |
        v
semantic JX / PASL / PASM lowering
        |
        +-------------------------+
        |                         |
        v                         v
prepared JXL                native target sections
        |                         |
        +-------------+-----------+
                      v
                compiled .jxb Book
                      |
                      v
            JX host / WSJX64 / OSAura64
```

The programmer should not have to write assembly-like source to obtain a fast path.

> **Canonical readability belongs in the language. Preparation belongs in the compiler. Speed belongs in the prepared/native runtime.**

---

## Execution layers

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

### 2. PHP-backed front end — cold work

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

The rule is not "run everything through PHP forever." The rule is:

> **Use the front end to discover meaning once; make the executable remember the answer.**

### 3. PASM / PASL — lowering lineage

PASM is the Oracle-ASM-style execution/lowering lineage beneath JX. PASL provides the higher semantic/compiler surface used by parts of the current toolchain.

This layer is where canonical operations can become resolved addresses, compact operation IDs, loop-space blocks, native container laws, and prepared JXL instructions.

### 4. JXL — prepared execution

**JXL is not another source language.** A programmer normally should not hand-author it.

JXL is the compact executable/prepared stream used after canonical meaning has already been resolved.

The ratified JXL byte law is distinct from the global JX hot-call ABI:

```text
JXL stream

0xxxxxxx = executable JXL opcode
1xxxxxxx = attached extension/data byte; never an opcode
```

Prepared native container instructions are currently six bytes:

```text
+0 opcode
+1 binding id low 7 bits | 80h
+2 binding id high 7 bits| 80h
+3 src0 selector         | 80h
+4 src1 selector         | 80h
+5 destination selector  | 80h
```

A JX/JXL session selects its decoder once at admission. The repeat path should not continually ask which language mode, public alias, discipline, or native symbol it is executing.

See [`docs/JXL-PREPARED-EXECUTION.md`](docs/JXL-PREPARED-EXECUTION.md) and [`docs/JXL-NATIVE-CONTAINERS.md`](docs/JXL-NATIVE-CONTAINERS.md).

### 5. `.jxb` — compiled Book/package

`.jxb` is the public compiled Book/package extension.

A Book can carry:

```text
compiled code
JXL executable sections
Bag schemas/state
Book/Page metadata
prepared tables
generations
manifests
assets
native ELF/PE sections
```

Native installation consumes compiled Books rather than depending on PHP source at runtime.

---

## Status vocabulary

The repository uses explicit status words so fast-moving development does not turn future ideas into accidental claims.

| Status | Meaning |
|---|---|
| **ACTIVE** | Accepted by the current compiler/runtime and covered by tests |
| **PHP-BACKED** | Usable through the current PHP host/runtime API, but not necessarily native-lowered yet |
| **JXL** | Prepared executable representation; not canonical source syntax |
| **PLANNED** | Ratified/documented direction that is not yet claimed as implemented |

When documentation and implementation disagree, tests and the active compiler are authoritative for **ACTIVE** claims.

---

## Language surface today

The current compiler-backed control-flow surface includes assignments, arithmetic/bitwise mutation, conditions, `while`, `for`, selection, `break`, `continue`, and complex values.

```jx
$x = 1;
$x++;
$x += 4;
$x ^= 2;

if ($x > 3) {
    $x += 1;
} else {
    $x -= 1;
}

while ($x) {
    $x--;
}

for ($i = 0; $i < 10; $i++) {
    if ($i == 4) continue;
    if ($i == 8) break;
    $x += $i;
}

select ($x) {
    case 1:
        $x += 10;
    case 2:
        $x += 20;
    default:
        $x = 0;
}

complex $z = 3+4i;
complex $w = 1-2i;
complex $p;
$p = $z * $w;
```

See [`docs/JX-PROGRAMMING-TUTELAGE.md`](docs/JX-PROGRAMMING-TUTELAGE.md) for the programming book/manuscript.

---

## Compiled loop space

JX does not need to rediscover a loop body every iteration.

Active loop lowering can move bounded loop bodies into compiled blocks:

```text
LCHECK condition
LCALL  compiled_body
[LCALL compiled_step]
LREPEAT loop_slot
```

A native target may use a machine call, tail branch, direct branch, or inline block while preserving the same canonical JX meaning.

See [`docs/LOOP-SPACE.md`](docs/LOOP-SPACE.md).

---

## Bags are the semantic memory model

Bags are a central JX abstraction. A Bag supplies persistent identity, ownership, capacity, generations/checkpoints, and structured state. Containers are **disciplines over Bags**, not a second unrelated memory system.

```text
Bag
|- record -> fixed dense slots
|- vector -> contiguous indexed storage
|- stack  -> contiguous LIFO storage
|- queue  -> FIFO ring
|- deque  -> double-ended ring
|- map    -> ordered keyed Vector<Entry>
`- set    -> ordered unique Vector<Key>
```

The runtime rule is:

> **Be native while working; become canonical at the Bag boundary.**

### The seven native container laws

| Discipline | Active native law |
|---|---|
| Record | fixed dense slots |
| Vector | contiguous indexed array |
| Stack | contiguous Vector law + LIFO |
| Queue | power-of-two ring |
| Deque | double-ended power-of-two ring |
| Map | ordered keyed Vector of 16-byte `[u64 key, u64 value]` entries |
| Set | ordered unique u64 Vector |

### Map is a keyed Vector

The canonical native Map is physically one contiguous entry array:

```text
Map =
[
    [key0, value0],
    [key1, value1],
    [key2, value2],
    ...
]
```

For the current u64 native path:

```text
entry size = 16 bytes
key offset = 0
value offset = 8
```

The ordered key position is found once. `PUT` then has exactly two outcomes:

```text
position = FIND(key)

if found:
    entries[position].value = value
else:
    insert [key,value] at position
```

`EMPLACE` preserves the existing value when the key is present. `GET` and `HAS` use the same position law. `REMOVE` packs whole 16-byte entries left.

The native binding ABI does not need a second value-array pointer for the active Map, so `aux` is unused by keyed-Vector Map operations.

### Set is the 1D form

Set uses the same ordered position concept without a value half:

```text
Set = [ key0, key1, key2, ... ]
```

Insertion drops duplicates; lookup uses cursor locality followed by lower-bound search when needed.

### Preserved split Map backend

The former ordered split representation remains linked for measurement:

```text
keys:   [K0][K1][K2]...
values: [V0][V1][V2]...
```

It is comparison-only. Current JXL Map IDs 18-22 resolve to the keyed-Vector routines. Keeping both backends in one build allows a fair A/B benchmark without changing the compiler, executor, machine, or surrounding runtime between measurements.

### Bag hot operations

Canonical Bag hot operations include:

```text
BPUSH BPOP
BPUSHF BPUSHB BPOPF BPOPB
BEMPLACE
BPEEK BRESERVE BDIRTY BSYNC
```

Readable aliases resolve before hot execution:

```text
enqueue --+
append  ---+--> BPUSH --> prepared/native Bag operation
push    ---+
```

There should be no runtime string lookup merely because a coder preferred `enqueue` over `push`.

See:

- [`docs/BAG-CONTAINERS.md`](docs/BAG-CONTAINERS.md)
- [`docs/JXL-NATIVE-CONTAINERS.md`](docs/JXL-NATIVE-CONTAINERS.md)
- [`docs/JX-ALIASES.md`](docs/JX-ALIASES.md)

---

## Bits for truth, native words for numbers, Bags for structure

The machine model deliberately does not widen every state identifier just because the target CPU is 64-bit.

```text
address width        = 64-bit
native numeric state = native words
boolean state        = packed bits
register IDs         = compact IDs
Bag/window/task IDs  = compact handles where sensible
```

The shorthand is:

> **Bits for truth. Native words for numbers. Bags for structure.**

---

## Global JX hot-call ABI v4

The global JX/OSAura hot-call ABI is distinct from JXL.

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

That gives:

```text
16 banks x 8 shadows = 128 one-byte hot positions
```

The eight-shadow physical discipline is a core invariant across the machine. The final two banks remain protected/unassigned:

```text
F0-FF = PROTECTED / UNASSIGNED
```

See [`docs/HOT-CALL-ABI-V4.md`](docs/HOT-CALL-ABI-V4.md).

---

## Processor bus and attention model

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

Memory owns Bags. The processor keeps hot references/prepared state. The bus carries change information and wake intent rather than duplicated Bags.

---

## JX11

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

---

## OSAura and WSJX64

JX is the language/compiler/runtime layer. **OSAura** is the standalone x86-64 operating-system project built around the same semantics.

```text
canonical JX
     |
     v
JXL / native prepared sections
     |
     v
.jxb Book
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

---

# Container benchmarks

The repository has one master container benchmark contract so performance claims can be compared on the same semantic workloads rather than by unrelated microbenchmarks.

The standard operation law is:

```text
N writes/inserts + N reads/removals = total logical operations
```

The current master columns are:

1. historical legacy PASM/PHP,
2. canonical PASM/PHP,
3. JX Bag/PHP semantic mirror,
4. idiomatic PHP array baseline,
5. PHP SPL structural baseline where meaningful,
6. JXL VM (`TBD` until a separate non-native container VM exists),
7. JXL native x86-64.

`N/A` means the comparison does not exist or is not semantically appropriate. `TBD` means it is not implemented/measured and is never estimated.

## Corrected unified 1,000,000-operation matrix — September 3, 2026

Measured on GitHub Actions run `33731702206`, job `container-matrix`, commit `e465f314c53f7801a90b64db80df8139ad6cfd5b`.

Times are median milliseconds for **1,000,000 total logical operations**, 5 reps, 1 warmup.

| Container | Legacy PASM/PHP | Canonical PASM/PHP | Bag/PHP | PHP array | PHP SPL | JXL VM | **JXL native** |
|---|---:|---:|---:|---:|---:|---:|---:|
| Record | N/A | N/A | 46.996 | **2.800** | 10.674 | TBD | **5.202** |
| Vector | 38.066 | 28.609 | 37.031 | 5.076 | 12.252 | TBD | **5.265** |
| Stack | 56.523 | 35.251 | 52.779 | 10.548 | 19.929 | TBD | **5.109** |
| Queue | 53.731 | 46.259 | 79.474 | 5.169 | 20.265 | TBD | **5.352** |
| Deque | 64.845 | 57.312 | 90.693 | **4.942** | 19.154 | TBD | **5.506** |
| **Map** | 33.226 | 31.373 | 215.601 | **4.497** | N/A | TBD | **6.986** |
| **Set** | 200.277 | 102.636 | 404.027 | 6.806 | N/A | TBD | **6.882** |

### What this matrix says

- **Vector:** native JXL is within about 4% of the PHP-array baseline in this run.
- **Stack:** native JXL is about **2.06x faster** than the PHP-array baseline for this workload.
- **Queue:** native JXL is within about 4% of PHP array.
- **Deque:** native JXL is about 11% slower than PHP array on this runner.
- **Set:** native JXL and PHP array are effectively tied.
- **Map:** production keyed-Vector JXL is about **1.55x the PHP-array time** in the master workload.
- **Record:** the PHP baseline is an associative-array workload, while JXL Record uses resolved fixed slots. A dedicated fixed-offset-vs-fixed-offset benchmark is still needed before making a broad Record claim.

The PHP `Bag/PHP` Map and Set numbers are deliberately not hidden. PHP arrays-of-entry arrays are expensive and are a semantic/reference mirror, not the physical native representation. The native keyed-Vector Map is one contiguous 16-byte-entry region; PHP nested arrays are not.

## Direct prepared-JXL native provider

The master suite also invokes the native provider directly. The timed path is:

```text
6-byte prepared JXL instruction
        -> native JXL decoder/dispatcher
        -> operation-specific prepared binding
        -> pure x86-64 assembly container function
        -> Bag memory
```

Direct production-provider results from run `33731702206`:

| Container | Median ms | Min ms | p95 ms | Mops/s | ns/op |
|---|---:|---:|---:|---:|---:|
| Record | 5.292 | 5.129 | 5.304 | 188.98 | 5.29 |
| Vector | 5.405 | 5.222 | 6.156 | 185.02 | 5.40 |
| Stack | 5.126 | 4.948 | 5.270 | 195.07 | 5.13 |
| Queue | 5.460 | 5.272 | 5.966 | 183.14 | 5.46 |
| Deque | 5.371 | 5.225 | 5.496 | 186.19 | 5.37 |
| **Map — keyed Vector** | **6.775** | **6.767** | 7.437 | **147.59** | **6.78** |
| Set | 6.809 | 6.718 | 7.199 | 146.87 | 6.81 |

The provider's same-build comparison during that pass measured the split Map at `7.701 ms` and keyed Vector at `6.775 ms`, a `0.880x` vector/split ratio. The dedicated 7-repetition A/B table above is the preferred layout comparison because it uses more repetitions and includes additional workloads.

## Map layout A/B benchmark

Run it directly with:

```bash
php benchmark-jxl-map-layouts.php 1000000 20000 7 1
```

The benchmark intentionally has two operation scales:

- `1,000,000` operations for ordered append/get and random update/get;
- `20,000` operations for descending and shuffled insertion, because flat ordered-array middle insertion is intentionally O(n) movement and a one-million-operation worst-case shift test would mostly measure enormous copying rather than the hot comparison law.

Both layouts use identical logical keys, identical deterministic ordering, identical executor admission, and cross-check their checksums.

## Historical progress and runner variance

The first unified native container snapshot recorded Map at `32.793 ms` and Set at `33.359 ms` per million operations. Those numbers are useful as historical evidence that the native Map/Set path has improved substantially, but **they should not be used to infer the effect of the keyed-Vector layout by themselves**.

The dedicated same-run A/B benchmark is the authoritative answer for the layout question.

Hosted runners also differ materially. For example, recent runs of otherwise identical container code have moved the core native containers between roughly 4 and 8 ns/op. For that reason:

> **Use same-run ratios for architecture decisions; use absolute milliseconds to describe that measured run, not as a machine-independent constant.**

## Benchmark timing boundaries

The native JXL benchmark intentionally excludes allocation, zeroing, binding construction, instruction construction, and deterministic key-order construction from the native timed region. That measures the admitted hot path.

Some PHP/PASM/Bag closures create or grow structures inside their measured work, so the master matrix is not a claim that every column has identical cold-start accounting. Future reporting should continue separating:

```text
setup / reserve
hot operations
canonical checkpoint / BSYNC
```

The benchmark is also **not a whole-language benchmark**. It measures the stated container operation contract.

## Run the benchmarks

```bash
# Unified cross-layer matrix
php benchmark-container-suite.php 1000000 5 1

# Direct production six-byte JXL -> x86-64 assembly provider
php benchmark-jxl-containers.php 1000000 5 1

# Same-build split Map vs keyed-Vector Map
php benchmark-jxl-map-layouts.php 1000000 20000 7 1

# Bag/PHP semantic mirror
php benchmark-jx-bag-containers.php 1000000 7

# PASM/OOP historical and canonical paths
php benchmark-pasm-oop-fast.php

# Specialized regressions
php benchmark-pasm-oop-fast-sync.php
php benchmark-pasm-oop-fast-deque.php
```

See [`docs/CONTAINER-BENCHMARKS.md`](docs/CONTAINER-BENCHMARKS.md) for the benchmark contract and snapshot methodology.

---

## SQL, media, charts, and plugins

JX treats data and presentation as separate concerns.

SQL/data-source objects can feed Bags; Controls and Charts consume Bags rather than becoming database handles themselves. Changing the source should not destroy a control's persistent Bag identity.

Host-neutral chart types include:

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

Installable packages come from `plugins/` and use repository backup policy during installation.

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

### Run the active-tree gate

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
| `jx-bag-containers.php` | Bag-backed container disciplines / PHP semantic mirror |
| `pasm-bag-hotops.php` | Canonical Bag hot operations and lowering recipes |
| `jx-jxl-containers.php` | Prepared JXL container bindings/opcodes/native IDs |
| `native/x86_64/jxl_containers.asm` | Core assembly containers and preserved split Map backend |
| `native/x86_64/jxl_map_vector.asm` | Active keyed-Vector Map assembly backend |
| `native/x86_64/jxl_container_native_table.asm` | Numeric native target table |
| `native/x86_64/benchmark_jxl_map_layouts.c` | Native same-executor Map layout A/B harness |
| `benchmark-container-suite.php` | Unified seven-discipline cross-layer benchmark matrix |
| `benchmark-jxl-containers.php` | Production six-byte JXL -> native assembly benchmark provider |
| `benchmark-jxl-map-layouts.php` | Split-vs-keyed-Vector Map benchmark runner |
| `docs/CONTAINER-BENCHMARKS.md` | Benchmark contract and methodology |
| `docs/JXL-NATIVE-CONTAINERS.md` | Native container memory laws and ABI |
| `docs/BAG-CONTAINERS.md` | Bag/container architecture |
| `jx-alias.php` | Compile-time alias canonicalization/provenance |
| `docs/JX-PROGRAMMING-TUTELAGE.md` | Programming tutorial/book manuscript |
| `docs/JXL-PREPARED-EXECUTION.md` | JXL prepared-execution contract |
| `docs/HOT-CALL-ABI-V4.md` | Global JX/OSAura hot-call ABI |
| `docs/LOOP-SPACE.md` | Loop compiler design |
| `host/` | Native/browser host mechanisms |
| `plugins/` | Installable plugin source packages |
| `tests/`, `test-*.php` | Regression/conformance tests |
| `.github/workflows/` | Linux/Windows/native/compiler/runtime/benchmark CI |

---

## Documentation rules for a fast-growing language

When adding a feature:

1. say whether it is **ACTIVE**, **PHP-BACKED**, **JXL**, or **PLANNED**;
2. keep canonical source syntax separate from executable encodings;
3. do not describe a semantic model as accepted syntax until its parser/lowering exists;
4. preserve the eight-shadow law where the global hot subsystem applies;
5. keep `F0-FF` protected/unassigned unless explicitly ratified otherwise;
6. keep JXL's byte grammar separate from the global ABI-v4 byte grammar;
7. add tests for architectural contracts future compiler/AI work could accidentally reverse;
8. prefer one canonical explanation linked from specialized documents over contradictory copies;
9. publish benchmark numbers only when they are measured, and mark unavailable paths `N/A` or `TBD` rather than estimating them.

---

## What remains

JX already has substantial runtime/compiler machinery, but important work remains before the intended language is native end-to-end.

Major open areas include:

- generalized collection/loop surface lowering,
- broader function/class/method native language surface,
- complete canonical `.jx` -> JXL/native `.jxb` compiler path,
- full JXL admission and execution across native hosts,
- event -> prepared execution -> Bag mutation -> present as one foreground service turn,
- complete native/host semantic conformance,
- larger realistic application benchmarks,
- resident/batched JXL region benchmarks that separate host-call overhead,
- Map random-lookup/update optimization without losing keyed-Vector insertion locality,
- fixed-offset Record vs fixed-offset native/PHP/C baselines,
- continued JX11 control/window integration,
- continued SQL/NoSQL and plugin lowering.

See [`jx/GAPS.md`](jx/GAPS.md) for the maintained roadmap.

---

## Philosophy

JX is being built so a programmer can write understandable programs while the machine quietly remembers everything it can learn ahead of time.

```text
human-readable names
        -> canonical operations
        -> semantic resolution
        -> prepared identities
        -> compact executable state
        -> native mechanisms
```

The recurring rules are:

> **Canonical source is for coders. Prepared form is for execution.**

> **Bags remember. Registers react. Prepared code executes.**

> **Kernel owns mechanisms. JX owns meanings.**

> **Clone the view. Borrow the Bag. Copy only on semantic mutation.**

> **Resolve cold. Bind once. Execute hot.**

---

## Acknowledgments

JX recognizes people and projects whose work helped sharpen its approach to defensive execution, package trust, and incoming-code awareness.

A special acknowledgment is given to **Caleb Mazalevskis**, author of **phpMussel**, for the depth and insight of that package and for the early warning it provides against incoming, would-be predatory coding practices.

See [`docs/ACKNOWLEDGMENTS.md`](docs/ACKNOWLEDGMENTS.md).

---

## Lineage

JX converges earlier `dompipe/pasm-v2` and `dompipe/jx-lang` work while retaining history. PASM remains the execution-engine lineage beneath the JX language/compiler/runtime surface.

Historical material remains under `history/jx-lang/` and `docs/history/` for provenance rather than being silently rewritten into current behavior.

---

**JX — pronounced jinx. Readable canonical source, prepared JXL execution, Bag-native memory laws, and compiled `.jxb` Books.**