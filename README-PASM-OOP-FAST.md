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

## Benchmark

PHP 8.4 with CLI OPcache. Times are median measurements from the included benchmark. `ops` means total API operations (half writes/pushes, half reads/pops).

### 100,000 operations

| workload | legacy PASM | hot-path canonical | speedup |
|---|---:|---:|---:|
| Vector add/get | 5.753 ms | 3.994 ms | 1.44x |
| Stack push/pop | 9.834 ms | 4.388 ms | 2.24x |
| Queue enqueue/dequeue | 8.523 ms | 6.997 ms | 1.22x |
| Deque back/front | 10.494 ms | 8.707 ms | 1.21x |
| Map put/get | 4.715 ms | 4.314 ms | 1.09x |
| Set add/has | 24.989 ms | 13.779 ms | 1.81x |

### 1,000,000 operations

| workload | legacy PASM | hot-path canonical | speedup |
|---|---:|---:|---:|
| Vector add/get | 53.924 ms | 42.449 ms | 1.27x |
| Stack push/pop | 80.414 ms | 46.253 ms | 1.74x |
| Queue enqueue/dequeue | 88.356 ms | 67.362 ms | 1.31x |
| Deque back/front | 96.006 ms | 83.465 ms | 1.15x |
| Map put/get | 48.917 ms | 45.220 ms | 1.08x |
| Set add/has | 240.272 ms | 152.258 ms | 1.58x |

Peak memory in the 1,000,000-op run dropped from about 56.0 MB legacy to about 52.0 MB canonical.

## Deque pathological-path fix

For 20,000 opposite-end operations (`pushFront` then `popBack`):

- Legacy PASM: about 5.31 seconds.
- New circular PASM Deque: about 1.81 ms.

That path is roughly 2,900x faster in this run because the old implementation rebuilt arrays while the new implementation uses a circular ring.

## Boundary behavior

The performance above measures hot container work. At an explicit canonical boundary, PASM materializes the current logical container image into page-backed segmented storage. This is intentional: the cost is paid once at `YIELD`/sync/defrag/persistence rather than on every container operation. If a Queue/Deque returns to its previously synchronized empty state before the boundary, no dirty pages need to be exported.

## Compatibility

The familiar APIs remain available:

- `Vector` / `PASMList`
- `Stack` / `PASMStack`
- `Queue` / `PASMQueue`
- `Deque` / `PASMDeque`
- `Map` / `PASMMap`
- `Set` / `PASMSet`

Canonical methods such as `forFrame()`, `frame()`, `containerId()`, `segmentIds()`, `dirtySegments()`, `clearDirty()`, `flush()`, `defrag()`, and canonical register bridging remain supported.
