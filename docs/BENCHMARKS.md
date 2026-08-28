# JX Benchmarks

> **Measure the layers separately. Do not confuse front-end cost, VM cost, native target execution, and canonical synchronization.**

This document is the benchmark record for the current JX tree. It is intended to be cited by the README, the programming tutelage, and future PDF/book material.

The central performance principle remains:

> **Resolve cold -> bind once -> execute hot.**

JX deliberately separates a readable PHP-backed compiler/front end from prepared and native execution. The benchmark suite therefore measures those layers independently rather than presenting one blended number as "JX speed."

---

## Snapshot identity

These measurements were captured by GitHub Actions from:

```text
repository: dompipe/jx
branch: main
commit: f94da01ca96b60308a1d7c8f29a7932a4ccc2d87
workflow: JX compiler CI
run: 33209344634
job: full-runnable-gate
```

Runner environment:

```text
Ubuntu 24.04.4 LTS
GitHub ubuntu-24.04 hosted runner
PHP 8.3.33
Xdebug/PCOV disabled
```

The full runnable gate completed with:

```text
197 standalone PHP files
41 root regression tests
7 runnable root examples
8 benchmark harnesses
264 total runnable-gate steps
```

Timing results on shared CI hardware are snapshots, not hard pass/fail thresholds. The benchmark programs verify their result values, while timing is used for comparison and trend analysis.

---

# 1. Target execution benchmark

Harness: `benchmark-targets.php`

Workload:

```jx
$i = 0;
$sum = 0;
while ($i < 10000) {
    $sum += $i;
    $i++;
}
```

Expected result:

```text
49,995,000
```

The benchmark deliberately compares distinct execution layers.

| Target | Median | Repetitions | Prepared bytes | Relative to JX source path |
|---|---:|---:|---:|---:|
| JX source compile + run | 7.819179 ms | 9 | — | 1.000x |
| JX compile only | 0.093413 ms | 9 | 59 B | compile measurement |
| Packed PASM page | 7.639137 ms | 9 | 59 B | 1.024x |
| Browser JS PASM VM | 28.197410 ms | 9 | — | 0.277x |
| Native x86-64 target | **0.007362602 ms** | 5,000 | native code | **1,062.013x** |

## What the 1,062x result means

It means that for this specific 10,000-iteration arithmetic loop, the already-produced x86-64 function executes about 1,062 times faster than the benchmark's PHP-backed JX **source compile + packed-VM run** path on this CI runner.

It does **not** mean:

- every JX application is 1,062x faster than PHP;
- native compilation, linking, startup, I/O, UI, SQL, or Book loading are included in the 0.00736 ms figure;
- all workloads will receive the same native speedup;
- the current PHP-hosted JX runtime has already reached native-target performance.

This benchmark is important because it demonstrates the value of the architecture's destination: the readable source can lower to a native loop without carrying the PHP/PASM interpreter overhead into that hot loop.

The compile-only median is only about 0.093 ms for this tiny source, while most of the source-path time is spent executing through the current packed VM. That identifies the VM/native transition as the much larger opportunity for this workload.

---

# 2. Canonical OOP/container benchmark

Harness: `benchmark-pasm-oop-fast.php`

Each implementation runs in a fresh PHP process. `ops` is total API operations: half inserts/writes and half reads/removals.

## 100,000 operations

| Workload | Legacy | Canonical | Native PHP | Legacy / canonical |
|---|---:|---:|---:|---:|
| Vector add/get | 3.468 ms | **2.990 ms** | 0.325 ms | 1.16x |
| Stack push/pop | 5.425 ms | **3.627 ms** | 0.849 ms | 1.50x |
| Queue enqueue/dequeue | 4.786 ms | **4.161 ms** | 0.362 ms | 1.15x |
| Deque back/front | 6.119 ms | **5.447 ms** | 0.358 ms | 1.12x |
| Map put/get | **3.041 ms** | 3.114 ms | 0.314 ms | 0.98x |
| Set add/has | 19.832 ms | **9.602 ms** | 0.318 ms | 2.07x |

Peak memory:

```text
legacy:    8.5 MB
canonical: 8.5 MB
native:    2.0 MB
```

At this smaller size, five of six canonical workloads beat the legacy implementation. Map is a small regression at 100,000 operations and is retained in the report rather than hidden.

## 1,000,000 operations

| Workload | Legacy | Canonical | Native PHP | Legacy / canonical |
|---|---:|---:|---:|---:|
| Vector add/get | 36.974 ms | **28.712 ms** | 5.217 ms | 1.29x |
| Stack push/pop | 55.613 ms | **36.673 ms** | 10.398 ms | 1.52x |
| Queue enqueue/dequeue | 53.927 ms | **46.809 ms** | 5.525 ms | 1.15x |
| Deque back/front | 66.995 ms | **55.734 ms** | 5.221 ms | 1.20x |
| Map put/get | 34.690 ms | **30.755 ms** | 4.752 ms | 1.13x |
| Set add/has | 206.717 ms | **102.659 ms** | 4.694 ms | 2.01x |

Peak memory:

```text
legacy:    56.004 MB
canonical: 52.004 MB
native:    14.008 MB
```

At one million operations the canonical implementation beats the legacy implementation in all six measured workloads, while direct PHP arrays/structures remain substantially faster. That native-PHP gap is an active reason for JXL/native lowering rather than something the benchmark hides.

---

# 3. Opposite-end deque algorithm benchmark

Harness: `benchmark-pasm-oop-fast-deque.php`

This benchmark isolates a historically pathological opposite-end deque workload. The native comparison uses `SplDoublyLinkedList`, which has O(1) end operations.

## 10,000 operations

| Implementation | Time | Throughput |
|---|---:|---:|
| Legacy | 1,043.075 ms | ~0.01 Mops/s |
| Canonical/new | **1.230 ms** | **8.13 Mops/s** |
| Native deque baseline | 0.463 ms | 21.61 Mops/s |

Legacy -> canonical improvement: approximately **848x**.

## 20,000 operations

| Implementation | Time | Throughput |
|---|---:|---:|
| Legacy | 4,086.477 ms | <0.01 Mops/s |
| Canonical/new | **2.027 ms** | **9.87 Mops/s** |
| Native deque baseline | 0.652 ms | 30.69 Mops/s |

Legacy -> canonical improvement: approximately **2,016x**.

These enormous ratios are primarily an **algorithm/data-structure correction**, not a general JX language multiplier. They demonstrate why choosing a native-friendly Bag discipline matters: the architecture can eliminate a bad asymptotic behavior before low-level instruction tuning even begins.

---

# 4. Bag-backed container benchmark

Harness: `benchmark-jx-bag-containers.php`

Configuration:

```text
ops = 1,000,000
repetitions = 7
reported value = median
```

| Bag discipline | Median |
|---|---:|
| Vector append/pop | 103.561 ms |
| Queue enqueue/dequeue | 223.490 ms |
| Deque back/front | 250.150 ms |
| Map put/get | 78.833 ms |
| Record dense slot | 78.879 ms |
| Checkpoint boundary | **2.311 ms** |

This benchmark exercises the higher-level JX `BagContainers` API rather than the lower-level PASM OOP container benchmark above. It therefore includes more of the semantic Bag discipline and should not be numerically compared as if it were the same workload implementation.

---

# 5. Hot work versus canonical synchronization

Harness: `benchmark-pasm-oop-fast-sync.php`

JX's Bag rule is:

> **Be native while working; become canonical at the Bag boundary.**

This benchmark measures the hot operation phase separately from one canonical dirty-page export.

## 100,000 operations + one export

| Container | Hot work | Sync/export | Total | Dirty pages |
|---|---:|---:|---:|---:|
| Vector | 3.782 ms | 22.822 ms | 26.604 ms | 1,563 |
| Queue | 6.050 ms | 0.008 ms | 6.058 ms | 0 |
| Deque | 9.304 ms | 0.012 ms | 9.316 ms | 0 |
| Map | 3.942 ms | 47.758 ms | 51.700 ms | 3,126 |
| Set | 13.281 ms | 22.986 ms | 36.267 ms | 1,563 |

## 1,000,000 operations + one export

| Container | Hot work | Sync/export | Total | Dirty pages |
|---|---:|---:|---:|---:|
| Vector | 39.406 ms | 332.499 ms | 371.905 ms | 15,626 |
| Queue | 66.590 ms | 0.017 ms | 66.607 ms | 0 |
| Deque | 93.913 ms | 0.019 ms | 93.932 ms | 0 |
| Map | 41.917 ms | 876.920 ms | 918.838 ms | 31,251 |
| Set | 136.466 ms | 321.923 ms | 458.390 ms | 15,626 |

This is one of the clearest current optimization signals. For vector, map, and set, full canonical export can dominate the hot-operation time. The intended direction is therefore not to checkpoint every operation. Dirty tracking, generations, borrowed views, and explicit boundaries exist so canonicalization can be paid when semantics require it rather than on every mutation.

Queue and deque end the balanced benchmark empty, so they have no dirty pages left to export in this particular test; their tiny sync values should not be generalized into a claim that all queue/deque snapshots are free.

---

# 6. Compiled loop-space benchmark

Harnesses:

```text
benchmark-loop-active.php
benchmark-loop-legacy.php
```

Workload size:

```text
10,000 iterations
9 repetitions
```

## `for`

| Compiler | Compile | Assembly | Bytecode | Run |
|---|---:|---:|---:|---:|
| Active loop-space/fused | 0.100641 ms | 668 B | 69 B | 7.555913 ms |
| Legacy inline | **0.022328 ms** | **362 B** | **64 B** | **7.497561 ms** |

## `while`

| Compiler | Compile | Assembly | Bytecode | Run |
|---|---:|---:|---:|---:|
| Active loop-space/fused | 0.065742 ms | 611 B | 59 B | **7.462927 ms** |
| Legacy inline | **0.019660 ms** | **350 B** | **54 B** | 7.542998 ms |

The active loop-space design is currently a **semantic/structural investment**, not a demonstrated blanket speed win in the PHP VM. Runtime is effectively near parity in this small benchmark; compile time and emitted size are currently larger.

The value of the active design is its bounded loop slots, out-of-line bodies, stable continuation semantics, `break`/`continue` targets, and suitability for native lowering. Future work should make those structural benefits cheaper rather than claiming this snapshot already proves loop speed superiority.

---

# 7. Packed register-command benchmark

Harness: `benchmark-register-command.php`

Configuration:

```text
200,000 ADD operations
9 repetitions
```

| Executor | Stream bytes | Bytes / ADD | Median run |
|---|---:|---:|---:|
| Legacy 4-byte ADD stream | 800,002 B | 4 | **14.097802 ms** |
| Packed generic VM | 600,002 B | 3 | 100.555945 ms |
| Packed fast VM | 600,002 B | 3 | 18.373711 ms |

Structural result:

```text
800,002 B -> 600,002 B
~25% smaller instruction stream
```

Execution result:

```text
fast packed VM vs generic packed VM: 5.47x faster
fast packed VM vs legacy executor:   ~0.77x as fast
```

So the compact format wins on density, and the specialized fast VM removes most of the generic dispatch penalty, but it has **not yet beaten the tiny legacy PHP reference executor** in this microbenchmark. That is precisely the kind of gap that prepared/native JXL execution is intended to remove.

---

# 8. Structural byte-density checks

The CI suite also verifies compact encodings that are useful alongside timing results:

```text
Global JX ABI v4 hot call:       1 byte
Global JX ABI v4 extended call:  2 bytes
PASM named-memory address:        2 bytes
PASM method address:              2 bytes
PASM iterator op:                 2 bytes
Ordered MOVR:                     2 bytes
Ordered INC:                      2 bytes
Ordered ADD:                      3 bytes
Active packed mixed fixture:     44 bytes
```

These are encoding/contract measurements, not standalone speed claims.

JXL remains a separate prepared stream with its own byte law:

```text
0xxxxxxx = executable JXL opcode
1xxxxxxx = attached extension/data byte
```

Do not use the global ABI-v4 1-byte-HOT/2-byte-EXTENDED grammar as the JXL grammar.

---

# 9. What the current data says

## Proven strengths

1. **Native lowering is the major execution opportunity.** The arithmetic target benchmark shows orders-of-magnitude separation between the current PHP VM path and the generated x86-64 hot loop.
2. **The canonical container rewrite is materially better than the legacy container implementation.** At one million operations all six general workloads improve, with Set roughly doubling performance.
3. **Algorithm choice matters more than instruction cleverness when the old algorithm is wrong.** The opposite-end deque rewrite changes a multi-second workload into milliseconds.
4. **Prepared/packed representations reduce code density.** The register-command stream falls from four bytes to three bytes per ADD in the measured format.
5. **Canonical synchronization is a visible cost center.** Dirty-page export currently dominates several mutable container workloads, validating the design emphasis on explicit boundaries, generations, and borrowed state.

## Current gaps shown by the benchmarks

1. The PHP-hosted canonical containers still trail direct PHP native structures.
2. Packed-fast PHP dispatch is still slower than the minimal legacy reference executor despite being denser.
3. Active compiled loop-space has not yet produced a broad runtime win and currently increases compile/emission cost in the measured cases.
4. Browser JS PASM execution is substantially slower than the PHP packed-PASM path on the arithmetic target benchmark.
5. Native benchmark coverage is still narrow; broader native Bag/OOP/control/event workloads are needed.

That is useful information. A benchmark suite is most valuable when it identifies what remains expensive.

---

# 10. Benchmark roadmap

The next benchmark families should be added as the corresponding native paths become executable:

- canonical `.jx` -> JXL -> execution, measuring admission separately from repeat execution;
- global ABI-v4 HOT versus EXTENDED dispatch in the same native runtime;
- Bag field read/write through prepared offsets;
- JXL Bag/container push/pop/get/set;
- native OOP method dispatch after receiver shape is prepared;
- processor-bus publish/wake/ACK/RETURN latency by program count;
- JX11 event -> listener -> Bag mutation -> dirty compose -> present latency;
- JX11 compositor damaged-pixel cost by visible-surface count;
- `.64B` admission/verification/prelink time separately from steady-state execution;
- SQL/data-source update -> Bag generation latency;
- native OSAura64 versus WSJX64 behavioral/performance parity;
- realistic mixed application workloads, not only microbenchmarks.

The benchmark discipline should always keep these phases separate:

```text
compile
package
admit / verify
prelink / prepare
execute hot
synchronize canonical state
host I/O / presentation
```

Otherwise a fast executor can be hidden by compilation, or a slow synchronization boundary can be incorrectly blamed on the language's arithmetic path.

---

# 11. Reproducing the benchmarks

The full runnable gate automatically executes every root `benchmark-*.php` harness:

```bash
php -d zend.assertions=1 -d assert.exception=1 test-all.php
```

Or run them individually:

```bash
php benchmark-targets.php
php benchmark-pasm-oop-fast.php
php benchmark-pasm-oop-fast-deque.php
php benchmark-pasm-oop-fast-sync.php
php benchmark-jx-bag-containers.php
php benchmark-loop-active.php
php benchmark-loop-legacy.php
php benchmark-register-command.php
```

For comparisons intended for publication:

1. record commit SHA;
2. record OS, CPU/runner, PHP/compiler version, and optimization flags;
3. warm the path before sampling where appropriate;
4. use medians rather than one lucky sample;
5. verify output correctness during the benchmark;
6. never compare unlike workloads as if they were identical;
7. distinguish implementation speedups from algorithmic complexity fixes;
8. distinguish source-to-result latency from already-compiled native execution.

---

# 12. The benchmark thesis

JX is being designed so the programmer can keep this:

```text
readable, canonical, PHP-familiar source
```

while the machine increasingly gets this:

```text
resolved identities
prepared register windows
compact instructions
prelinked Bag/method routes
borrowed state instead of copies
native target code
explicit canonical synchronization
```

The existing numbers do not say the work is finished. They show **where the architecture is already paying off, where PHP-hosted compatibility still costs time, and why JXL/native execution is the next decisive performance layer.**
