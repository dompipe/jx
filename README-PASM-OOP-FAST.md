# PASM OOP Hot-Path Rewrite

This version keeps the canonical PASM frame/segmentation model, but removes segmentation and cell-codec work from ordinary container operations.

## Architecture

Hot operations use frame-local PHP state only. The canonical segmented image is write-back storage and is materialized only at explicit boundaries such as `dirtySegments()`, `clearDirty()`, `flush()`, `defrag()`, canonical register export, remote synchronization, or persistence.

- `Vector/List`: packed hot array.
- `Stack`: packed hot array with direct push/pop.
- `Queue`: append + head index with periodic compaction.
- `Deque`: power-of-two circular ring; all four end operations are O(1) amortized.
- `Map`: PHP hash table on the hot path; canonical key/value image only at sync.
- `Set`: typed scalar signatures; serialization only for complex fallback values.
- Each container still belongs to a canonical `PASMRegisterFrame` and owns a logical PASM segment checkpoint.

## Benchmark — first stop

This is the first performance checkpoint for the current canonical OOP implementation.
The canonical source file and the checked-in benchmark result snapshot both come from
commit `d92a8d1c8af798b9b4f23b3d1577803ae40d466d`; the OOP source has not changed on
this branch since that snapshot. The numbers therefore remain the exact baseline for
the current OOP code, although they were not freshly re-executed by the documentation
update itself.

The benchmark uses fresh PHP processes for legacy, canonical OOP, and direct native
PHP. Times are median measurements from three repetitions. `ops` means total API
operations: half writes/pushes and half reads/pops.

Run the baseline locally with:

```bash
php benchmark-pasm-oop-fast.php
php benchmark-pasm-oop-fast-sync.php
php benchmark-pasm-oop-fast-deque.php
```

A reproducible workflow is also committed at:

```text
.github/workflows/oop-benchmark.yml
```

### 100,000 operations

| workload | legacy PASM | hot-path canonical | native PHP | legacy/new | canonical/native |
|---|---:|---:|---:|---:|---:|
| Vector add/get | 5.753 ms | 3.994 ms | 0.562 ms | 1.44x | 7.11x |
| Stack push/pop | 9.834 ms | 4.388 ms | 1.264 ms | 2.24x | 3.47x |
| Queue enqueue/dequeue | 8.523 ms | 6.997 ms | 0.724 ms | 1.22x | 9.66x |
| Deque back/front | 10.494 ms | 8.707 ms | 0.645 ms | 1.21x | 13.49x |
| Map put/get | 4.715 ms | 4.314 ms | 0.630 ms | 1.09x | 6.85x |
| Set add/has | 24.989 ms | 13.779 ms | 0.706 ms | 1.81x | 19.51x |

Peak memory:

```text
legacy    8.5 MB
canonical 8.5 MB
native    2.0 MB
```

### 1,000,000 operations

| workload | legacy PASM | hot-path canonical | native PHP | legacy/new | canonical/native |
|---|---:|---:|---:|---:|---:|
| Vector add/get | 53.924 ms | 42.449 ms | 8.189 ms | 1.27x | 5.18x |
| Stack push/pop | 80.414 ms | 46.253 ms | 14.645 ms | 1.74x | 3.16x |
| Queue enqueue/dequeue | 88.356 ms | 67.362 ms | 8.815 ms | 1.31x | 7.64x |
| Deque back/front | 96.006 ms | 83.465 ms | 9.306 ms | 1.15x | 8.97x |
| Map put/get | 48.917 ms | 45.220 ms | 9.232 ms | 1.08x | 4.90x |
| Set add/has | 240.272 ms | 152.258 ms | 10.117 ms | 1.58x | 15.05x |

Peak memory in the 1,000,000-op run:

```text
legacy    56.00 MB
canonical 52.00 MB
native    14.01 MB
```

### First-stop result

The canonical OOP rewrite is faster than legacy in every measured container workload.
At one million operations:

- Vector improves about **21.3%** over legacy.
- Stack improves about **42.5%**.
- Queue improves about **23.8%**.
- Deque improves about **13.1%**.
- Map improves about **7.6%**.
- Set improves about **36.6%**.

That is a real improvement over the legacy OOP layer, but it is not the end target.
Direct native PHP is still faster in every workload. The current largest relative gap
at one million operations is Set at roughly **15.05x** native; the smallest is Stack at
roughly **3.16x** native. Future OOP optimization should be judged against this stop,
not against claims made without the native column.

## Deque pathological-path fix

For 20,000 opposite-end operations (`pushFront` then `popBack`):

- Legacy PASM: about 5.31 seconds.
- New circular PASM Deque: about 1.81 ms.

That path is roughly 2,900x faster in this run because the old implementation rebuilt arrays while the new implementation uses a circular ring.

## Boundary behavior

The performance above measures hot container work. At an explicit canonical boundary, PASM materializes the current logical container image into page-backed segmented storage. This is intentional: the cost is paid once at `YIELD`/sync/defrag/persistence rather than on every container operation. If a Queue/Deque returns to its previously synchronized empty state before the boundary, no dirty pages need to be exported.

The companion `benchmark-pasm-oop-fast-sync.php` benchmark exists specifically to
keep the canonical checkpoint cost visible separately from hot API operations.

## Compatibility

The familiar APIs remain available:

- `Vector` / `PASMList`
- `Stack` / `PASMStack`
- `Queue` / `PASMQueue`
- `Deque` / `PASMDeque`
- `Map` / `PASMMap`
- `Set` / `PASMSet`

Canonical methods such as `forFrame()`, `frame()`, `containerId()`, `segmentIds()`, `dirtySegments()`, `clearDirty()`, `flush()`, `defrag()`, and canonical register bridging remain supported.
