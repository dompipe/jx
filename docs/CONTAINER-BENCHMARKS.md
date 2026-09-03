# JX Container Benchmark Contract

This document defines the permanent benchmark contract for JX containers from the current PHP-hosted implementation through PASM, JXL VM execution, and native JXL execution.

The purpose is to prevent benchmark drift. A faster-looking result is not useful if a later backend changes the operation count, data shape, checksum, process model, or synchronization boundary.

## Canonical container family

JX has seven Bag disciplines:

1. `record`
2. `vector`
3. `stack`
4. `queue`
5. `deque`
6. `map`
7. `set`

Historical PASM OOP exposes Vector, Stack, Queue, Deque, Map, and Set. It does not have a Record class. Record therefore appears in the master matrix through `RecordBag` and PHP baselines; the historical PASM columns remain `TBD`/not applicable instead of inventing an implementation.

## Benchmark law

For paired workloads:

```text
N = total_ops / 2
N writes/inserts + N reads/removals = total_ops
```

Every measured implementation must return the same checksum for the same workload. A checksum mismatch invalidates the comparison and the harness throws.

Hot work, canonical synchronization, compilation, JXL admission, and native execution are different costs and must stay separate.

## Stable entry point

Run the complete comparison matrix with:

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

Explicit sizes are also accepted:

```bash
php benchmark-container-suite.php 1000,10000,100000,1000000 9 2
```

The final two arguments are measured repetitions and warmup repetitions.

The master report is written to:

```text
benchmark-container-suite-results.json
```

## Master matrix

The stable columns are:

```text
legacy PASM/PHP
canonical PASM/PHP
JX Bag/PHP
PHP array/idiomatic baseline
PHP SPL structural baseline
JXL VM
JXL native
```

Unimplemented JXL cells must remain `null` / `TBD`. Never estimate a JXL time from PHP, PASM, C, or another native microbenchmark.

## Metrics

General and Bag benchmarks report:

- median milliseconds
- minimum milliseconds
- p95 milliseconds
- Mops/s
- ns/op
- checksum
- process peak memory where available

The README/book may quote a snapshot, but the JSON result is the machine-readable record for a run.

## PHP baselines

Two PHP baseline ideas are kept distinct where useful:

### Idiomatic PHP

Examples:

- Vector: PHP array append/index
- Stack: PHP array + `array_pop()`
- Queue/Deque balanced baseline: PHP array + head cursor
- Map: associative array
- Set: associative array + `isset()`
- Record: associative array

### SPL structural baseline

Examples:

- Record/Vector fixed-slot comparison: `SplFixedArray`
- Stack: `SplStack`
- Queue: `SplQueue`
- Deque: `SplDoublyLinkedList`

Map and Set do not receive fake SPL rows when there is no directly useful structural counterpart.

## Complete Bag benchmark

Run:

```bash
php benchmark-jx-bag-containers.php 1000000 9 2
```

It covers every JX Bag discipline:

```text
record put/get
vector append/get
stack push/pop
queue enqueue/dequeue
deque back/front
map put/get
set add/contains
```

The former missing Stack and Set rows are part of the permanent suite.

Canonical checkpoint work is reported separately from these hot operations.

## Low-level PASM/PHP benchmark

Run:

```bash
php benchmark-pasm-oop-fast.php
```

Or:

```bash
php benchmark-pasm-oop-fast.php --stress 9 2
```

Each implementation runs in a fresh child PHP process. This prevents legacy/canonical class definitions, container state, and process-local caches from contaminating one another.

Record is intentionally not fabricated in historical PASM OOP. Use the Bag row for JX Record semantics.

## Specialized regression: opposite-end deque

Run:

```bash
php benchmark-pasm-oop-fast-deque.php
```

This test remains separate because it is primarily an algorithm/asymptotic regression test, not a generic language-speed benchmark.

Workload:

```text
pushFront N times
popBack N times
```

The native structural baseline is `SplDoublyLinkedList`, which has O(1) end operations.

The harness now uses warmups, repeated measurements, median/min/p95 reporting, throughput, checksum verification, and a JSON result file:

```text
benchmark-pasm-oop-fast-deque-results.json
```

Do not describe a very large legacy-to-canonical ratio from this test as a general JX multiplier. It demonstrates removal of pathological data-structure behavior.

## Specialized regression: hot work versus canonical export

Run:

```bash
php benchmark-pasm-oop-fast-sync.php
```

This covers:

```text
Vector
Stack
Queue
Deque
Map
Set
```

The benchmark records hot operation time and `dirtySegments()` export time independently.

Balanced Stack, Queue, and Deque workloads may end empty. A zero dirty-page result in such a case means that workload has no retained dirty pages; it does not mean all snapshots are free.

The design rule remains:

> Be native while working. Become canonical at the Bag boundary.

## JXL provider contract

The master suite checks for:

```text
benchmark-jxl-containers.php
```

Until that executable benchmark exists, the JXL VM and JXL native columns remain `TBD`.

When implemented, the provider should accept:

```bash
php benchmark-jxl-containers.php TOTAL_OPS REPS WARMUPS --json
```

and return a JSON object shaped like:

```json
{
  "vm": {
    "record": {"median_ms": 0, "min_ms": 0, "p95_ms": 0, "mops_s": 0, "ns_op": 0, "checksum": 0},
    "vector": {},
    "stack": {},
    "queue": {},
    "deque": {},
    "map": {},
    "set": {}
  },
  "native": {
    "record": {},
    "vector": {},
    "stack": {},
    "queue": {},
    "deque": {},
    "map": {},
    "set": {}
  }
}
```

The empty examples above describe shape only. Real entries must contain measured values.

The JXL provider must use the same operation law and checksum semantics as the PHP/PASM/Bag rows.

## JXL timing boundaries

Do not blend these phases into one number when comparing hot execution:

```text
JX source compile
PASM lowering
native/JXL encoding
JXL validation/admission
container allocation/reserve
hot operations
BSYNC/canonical checkpoint
restore
```

Recommended reporting:

```text
cold total
admission/setup
warm execution
sync/checkpoint
```

The master container table should use the warm operation phase for the direct PHP/PASM/JXL execution comparison. Cold-start numbers belong in a separate startup table.

## Per-discipline expansion workloads

The master row is the stable baseline. Additional targeted tests should be added without replacing it.

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

A benchmark result is evidence only for the workload that was measured.

Examples:

- a native x86 arithmetic loop does not prove native Map speed;
- the opposite-end deque repair does not imply a language-wide multiplier;
- a canonical checkpoint cost must not be charged to every hot mutation if the runtime only crosses that boundary occasionally;
- a JXL time must not be published until the actual JXL path executes the workload and passes checksum verification.

The performance architecture is therefore measured as a pipeline rather than collapsed into one marketing number:

```text
JX source
  -> PASM lowering
  -> prepared JXL
  -> JXL VM/native execution
  -> explicit canonical boundary when required
```
