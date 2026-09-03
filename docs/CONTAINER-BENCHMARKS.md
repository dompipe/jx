# JX Container Benchmark Contract

This document defines the permanent benchmark contract for JX containers from the PHP-hosted implementations through PASM, prepared JXL, and native JXL execution.

The purpose is to prevent benchmark drift. A result is comparable only when operation count, workload semantics, checksum, setup boundary, and execution layer remain explicit.

## Canonical container family

JX has seven Bag disciplines:

1. `record`
2. `vector`
3. `stack`
4. `queue`
5. `deque`
6. `map`
7. `set`

Historical PASM OOP exposes Vector, Stack, Queue, Deque, Map, and Set. It does not have a Record class. Record therefore appears through `RecordBag`, PHP baselines, and JXL native execution; the historical PASM Record cells stay not-applicable rather than inventing an implementation.

## Benchmark law

For paired workloads:

```text
N = total_ops / 2
N writes/inserts + N reads/removals = total_ops
```

Every measured implementation must return the same checksum for the same workload. A checksum mismatch invalidates the comparison and the harness throws.

Hot work, canonical synchronization, compilation, JXL admission, allocation, and native execution are different costs and must stay separate.

## Stable master entry point

Run the complete matrix with:

```bash
php benchmark-container-suite.php
```

Default sizes:

```text
1,000
10,000
100,000
1,000,000 total operations
```

Stress mode adds 10,000,000 operations:

```bash
php benchmark-container-suite.php --stress 9 2
```

Explicit sizes are accepted:

```bash
php benchmark-container-suite.php 1000,10000,100000,1000000 9 2
```

The final two arguments are measured repetitions and warmup repetitions.

Machine-readable output:

```text
benchmark-container-suite-results.json
```

## Stable columns

```text
legacy PASM/PHP
canonical PASM/PHP
JX Bag/PHP
PHP array / idiomatic baseline
PHP SPL structural baseline
JXL VM
JXL native
```

`JXL VM` remains `TBD` until a distinct non-native JXL container executor is actually measured. `JXL native` is now measured on supported hosts through the real six-byte prepared JXL executor and pure x86-64 container runtime.

Never estimate an unimplemented cell from PHP, C, PASM, or another native microbenchmark.

## Metrics

General, Bag, and JXL-native benchmarks report:

- median milliseconds
- minimum milliseconds
- p95 milliseconds
- Mops/s
- ns/op
- checksum
- process peak memory where meaningful

The README/book may quote snapshots, but JSON output and CI logs are the run records.

## PHP baselines

Two PHP baseline families stay distinct.

### Idiomatic PHP

- Record: associative array
- Vector: PHP array append/index
- Stack: PHP array + `array_pop()`
- Queue/Deque balanced baseline: PHP array + head cursor
- Map: associative array
- Set: associative array + `isset()`

### SPL structural baseline

- Record/Vector fixed-slot comparison: `SplFixedArray`
- Stack: `SplStack`
- Queue: `SplQueue`
- Deque: `SplDoublyLinkedList`

Map and Set do not receive fake SPL rows when there is no justified structural counterpart.

## Complete Bag benchmark

Run:

```bash
php benchmark-jx-bag-containers.php 1000000 9 2
```

It covers all seven disciplines:

```text
record put/get
vector append/get
stack push/pop
queue enqueue/dequeue
deque back/front
map put/get
set add/contains
```

Stack and Set are now permanent rows rather than missing cases.

Canonical checkpoint work is reported separately from hot operations.

## Low-level PASM/PHP benchmark

Run:

```bash
php benchmark-pasm-oop-fast.php
```

Or stress it explicitly:

```bash
php benchmark-pasm-oop-fast.php --stress 9 2
```

Each implementation runs in a fresh child PHP process so legacy/canonical class state and process-local caches do not contaminate one another.

Record is intentionally not fabricated in historical PASM OOP.

## Native JXL benchmark

Provider:

```text
benchmark-jxl-containers.php
```

Native harness:

```text
native/x86_64/benchmark_jxl_containers.c
```

Run directly on Linux x86-64 with NASM, `cc`, and GNU `ld`:

```bash
php benchmark-jxl-containers.php 1000000 9 2
```

Or machine-readable:

```bash
php benchmark-jxl-containers.php 1000000 9 2 --json
```

The provider builds the existing pure-assembly JXL container runtime and measures this path:

```text
6-byte prepared JXL instruction
    -> jx_jxl_container_execute
    -> operation-specific admitted binding
    -> pure x86-64 container routine
    -> Bag memory
```

Allocation, buffer zeroing, binding construction, and instruction construction stay outside the timed region.

The benchmark does **not** substitute a direct C container implementation. Each logical operation calls the real JXL executor. The register window is updated between operations so the workload uses the same changing indexes, keys, and values as the PHP/PASM rows.

Measured native operations are:

```text
record: PUT / GET
vector: PUSH / GET
stack:  PUSH / POP
queue:  PUSH / POP
deque:  PUSHB / POPF
map:    PUT / GET
set:    EMPLACE / HAS
```

The master matrix compares their checksums with the corresponding PHP/PASM/Bag workloads.

On unsupported hosts or hosts without the native toolchain, the provider returns an explicit `unavailable` result with empty native cells. It does not invent numbers and does not make portable PHP-only CI fail.

## Native benchmark CI

`.github/workflows/jx-container-benchmarks.yml` runs the one-million-operation master matrix on Ubuntu 24.04 with PHP 8.3 and NASM so PHP/PASM/Bag/JXL numbers come from the same runner.

The existing `prepared-jxl-native` CI job also builds and executes a smaller native container benchmark as a correctness smoke test.

This is important: JXL numbers are accepted only after the real native runtime compiles and the seven checksums pass.

## Specialized regression: opposite-end deque

Run:

```bash
php benchmark-pasm-oop-fast-deque.php
```

Workload:

```text
pushFront N times
popBack N times
```

This stays separate because it is primarily an algorithm/asymptotic regression test. `SplDoublyLinkedList` is the structural PHP baseline because both end operations are O(1).

The harness uses warmups, repeated measurements, median/min/p95, throughput, checksum verification, and writes:

```text
benchmark-pasm-oop-fast-deque-results.json
```

Do not describe a large legacy-to-canonical ratio from this test as a language-wide JX multiplier.

## Specialized regression: hot work versus canonical export

Run:

```bash
php benchmark-pasm-oop-fast-sync.php
```

Coverage:

```text
Vector
Stack
Queue
Deque
Map
Set
```

The benchmark records hot operation time and `dirtySegments()` export independently.

Balanced Stack, Queue, and Deque workloads may end empty. Zero retained dirty pages in such a case is a property of that workload, not a claim that snapshots are free.

The design rule remains:

> **Be native while working. Become canonical at the Bag boundary.**

## JXL timing boundaries

Do not blend these phases when comparing hot execution:

```text
JX source compile
PASM lowering
JXL encoding
JXL validation/admission
container allocation/reserve
prepared JXL hot operations
BSYNC / canonical checkpoint
restore
```

Recommended reporting:

```text
cold total
admission/setup
warm execution
sync/checkpoint
```

The master table uses the warm operation phase for the direct implementation comparison. Cold-start numbers belong in a separate startup table.

## Expansion workloads

The master row is the stable baseline. Targeted tests may extend it without replacing it.

### Record

- resolved-slot write/read
- repeated name-to-slot resolution
- fixed-field sequential access
- random field access

### Vector

- append
- pop-back
- sequential get
- random get
- put
- middle `BEMPLACE`
- reserve/growth
- iteration

### Stack

- push
- pop
- peek
- reserve/growth
- iteration

### Queue

- enqueue/dequeue
- peek
- ring wrap
- repeated wrap
- growth
- bounded steady state

### Deque

- pushBack/popBack
- pushBack/popFront
- pushFront/popFront
- pushFront/popBack
- alternating ends
- ring wrap
- growth

### Map

- insert new
- get hit
- get miss
- update existing
- delete
- emplace hit
- emplace miss
- integer keys
- string keys
- iteration

### Set

- add unique
- add duplicate
- contains hit
- contains miss
- discard
- emplace unique
- emplace duplicate
- integer values
- string values
- iteration

## Interpretation rule

A benchmark result is evidence only for the workload measured.

- A native arithmetic loop does not prove Map speed.
- The opposite-end deque repair does not imply a language-wide multiplier.
- Canonical checkpoint cost should not be charged to every mutation when the runtime crosses the boundary only when semantics require it.
- A JXL number is publishable only when the actual JXL executor ran the workload and checksum verification passed.

The measured architecture stays explicit:

```text
JX source
  -> PASM lowering
  -> prepared JXL
  -> JXL native execution
  -> explicit canonical boundary when required
```
