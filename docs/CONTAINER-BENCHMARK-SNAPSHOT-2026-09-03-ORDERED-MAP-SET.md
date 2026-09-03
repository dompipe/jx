# JX Container Benchmark Snapshot — Ordered Map/Set — 2026-09-03

This snapshot records the first CI measurement after replacing the native JXL hash-backed Map/Set implementation with the canonical ordered-array law.

The earlier 2026-09-03 snapshot is intentionally retained as the before-state.

## Canonical change

```text
before
Map -> open-addressed hash slots
Set -> open-addressed hash slots

now
Map -> ordered 2D array: keys[] + values[]
Set -> ordered 1D unique keys[]
```

Map `PUT` now means:

```text
position = FIND(key)

if found:
    values[position] = new_value
else:
    insert key at position
    insert value at position
```

Set `ADD` means:

```text
position = FIND(value)

if found:
    drop duplicate
else:
    insert value at position
```

`FIND` uses the Map/Set locality cursor first (`cursor`, then `cursor + 1`) and falls back to lower-bound binary search for random access. Monotonic insertion uses a last-key/append fast path.

There are no current canonical JXL Map/Set buckets, probes, collisions, tombstones, load factors, or rehash operations.

## Identity

```text
repository: dompipe/jx
branch: main
ordered-array benchmark commit: 5f8c7021c168d7e23c2b4450fa16a3cc73f128f5
workflow: JX container benchmarks
job: container-matrix
runner: Ubuntu 24.04
PHP: 8.3
master workload: 1,000,000 total operations
measured repetitions: 5
warmups: 1
```

Later commits in the same change series extend the array invariant into compiler binding metadata, PASM/JX lowering, the PHP Bag reference implementation, tests, and documentation. The native Map/Set execution law measured here remains the same.

## Before and after

Times below are the one-million-operation master-matrix medians.

| Container | Previous hash JXL | Ordered-array JXL | Speedup | Time reduction |
|---|---:|---:|---:|---:|
| Map | 32.793 ms | **7.826 ms** | **4.19x** | **76.1%** |
| Set | 33.359 ms | **7.341 ms** | **4.54x** | **78.0%** |

The previous values come from the first unified JXL-native snapshot. The ordered-array values come from the first successful ordered-array CI run.

## Same-run comparison

| Container | PHP array | Ordered JXL native | JXL / PHP |
|---|---:|---:|---:|
| Map | 5.220 ms | 7.826 ms | 1.50x |
| Set | 7.353 ms | **7.341 ms** | ~1.00x |

Set reached practical parity with the PHP-array baseline in this workload. Map became much closer to the PHP baseline while preserving the JX rule that Map itself is a two-dimensional array rather than a hash object.

## Direct prepared-JXL measurement

The direct provider invocation in the same CI job measured the complete six-byte JXL execution path:

```text
6-byte JXL instruction
  -> native decoder
  -> prepared binding
  -> native function pointer
  -> assembly ordered-array operation
  -> result register-window writeback
```

| Container | Median | Min | p95 | Mops/s | ns/op |
|---|---:|---:|---:|---:|---:|
| Map | **7.569 ms** | 7.527 | 7.779 | **132.12** | **7.57** |
| Set | **7.346 ms** | 7.305 | 7.548 | **136.13** | **7.35** |

These measurements include the real prepared-JXL instruction dispatcher on every logical operation. Allocation, compiler lowering, binding construction, and canonical synchronization remain outside the timed hot phase.

## Why this workload improved

The benchmark uses monotonic insert followed by ordered reads. That exercises the intended marquee law directly:

```text
insert key > current last key
        -> append

next lookup near previous lookup
        -> cursor / cursor+1

random/nonlocal lookup
        -> lower_bound
```

The former hash implementation paid hashing and probing cost on every operation and carried a 24-byte hash-slot representation. The ordered representation uses dense u64 keys and a separate dense value dimension for Map.

## Important limit

This result does not prove that every random mutation workload is now 7 ms per million operations.

Random middle insertion/removal in a dense ordered array still requires shifting the tail. That is an intentional property of the current canonical array law. Future optimization may use reserved gaps or packed-array regions, but it must not turn Map back into a hash table.

The next benchmark expansion should therefore include:

1. random GET/HAS,
2. overwrite-heavy Map PUT,
3. duplicate-heavy Set ADD,
4. random middle insertion,
5. random removal,
6. sequential/local marquee access,
7. large-capacity cache behavior.

## Permanent invariant

> **Map is a 2D key/value array. Set is its 1D unique-key form. Optimization may change how those arrays are searched or spaced, but it must not replace their canonical memory law with hashing.**
