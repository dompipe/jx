# JX Container Benchmark Snapshot — 2026-09-03

This is the first CI snapshot produced by the unified container benchmark matrix with the native prepared-JXL provider enabled.

## Identity

```text
repository: dompipe/jx
branch: main
commit: 76ac95331fd740727cbc174c7cb2a4e2d6ed6b1d
workflow: JX container benchmarks
job: container-matrix
run: 33724677593
runner: Ubuntu 24.04.4 / ubuntu-24.04
PHP: 8.3.33
coverage: disabled
NASM: 2.16.01
master workload: 1,000,000 total operations
measured repetitions: 5
warmups: 1
```

The CI job completed successfully.

## Unified one-million-operation matrix

Times are medians in milliseconds from the same runner.

| Container | Legacy PASM/PHP | Canonical PASM/PHP | Bag/PHP | PHP array | PHP SPL | JXL VM | JXL native |
|---|---:|---:|---:|---:|---:|---:|---:|
| Record | N/A | N/A | 48.915 | **2.751** | 10.636 | TBD | 5.248 |
| Vector | 38.318 | 28.895 | 34.638 | **5.130** | 12.464 | TBD | 5.315 |
| Stack | 56.435 | 36.583 | 55.587 | 11.096 | 20.467 | TBD | **5.083** |
| Queue | 53.494 | 46.638 | 82.223 | 5.595 | 19.813 | TBD | **5.517** |
| Deque | 65.065 | 57.144 | 94.038 | 5.482 | 19.783 | TBD | **5.428** |
| Map | 33.437 | **31.026** | 36.543 | **4.969** | N/A | TBD | 32.793 |
| Set | 206.092 | 101.382 | 135.124 | **6.955** | N/A | TBD | 33.359 |

Bold is used only to make obvious comparisons easier to see; it is not a claim that the workload represents all applications.

## Native prepared-JXL detail

A second invocation in the same CI job measured the JXL provider directly:

| Container | Median ms | Min ms | p95 ms | Mops/s | ns/op |
|---|---:|---:|---:|---:|---:|
| Record | 5.242 | 5.204 | 5.278 | 190.78 | 5.24 |
| Vector | 5.215 | 5.162 | 5.942 | 191.76 | 5.21 |
| Stack | 4.937 | 4.896 | 5.093 | 202.57 | 4.94 |
| Queue | 6.233 | 5.283 | 9.555 | 160.44 | 6.23 |
| Deque | 5.643 | 5.280 | 9.647 | 177.22 | 5.64 |
| Map | 33.706 | 31.637 | 35.988 | 29.67 | 33.71 |
| Set | 34.243 | 34.164 | 36.483 | 29.20 | 34.24 |

The direct-provider invocation and master invocation are separate repeated benchmark runs on the same CI job, so their medians are expected to differ slightly.

## What this snapshot establishes

### Sequential containers

Prepared JXL has removed most of the PHP-hosted JX overhead for the contiguous/ring disciplines in this workload.

- Vector: 5.315 ms JXL versus 5.130 ms PHP array — near parity.
- Stack: 5.083 ms JXL versus 11.096 ms PHP array — roughly 2.18x faster in this workload.
- Queue: 5.517 ms JXL versus 5.595 ms PHP array — near parity.
- Deque: 5.428 ms JXL versus 5.482 ms PHP array — near parity.

Record is 5.248 ms JXL versus 2.751 ms for an associative-array PHP baseline. That row is useful but should not be interpreted as a fixed-offset-vs-fixed-offset comparison; a dedicated resolved-slot/fixed-layout Record microbenchmark remains appropriate.

### Hash containers

Map and Set are now the clear native optimization targets.

- Map: 32.793 ms JXL versus 4.969 ms PHP array.
- Set: 33.359 ms JXL versus 6.955 ms PHP array.

This does not mean the JXL architecture failed for hashes. The v1 native Map/Set currently uses a simple open-addressed 24-byte slot and performs the real prepared-JXL dispatch on every operation. PHP's production hash table is highly optimized. The benchmark now gives a stable target for improving probing, load factor, slot density, hashing, batch/resident execution, and admission strategy.

### PHP-hosted layers

The canonical PASM implementation still improves substantially over the historical implementation in the six historical disciplines, especially Set, but the Bag/PHP layer is not uniformly faster because it carries higher-level Bag semantics. These PHP-hosted rows remain useful implementation baselines; they are not the destination execution path.

## What the JXL number includes

The native JXL benchmark times:

```text
6-byte JXL instruction
  -> native instruction decoder
  -> prepared binding lookup
  -> native function pointer call
  -> assembly container operation
  -> result register-window writeback
```

It does not time allocation, buffer zeroing, binding construction, compiler lowering, JXL encoding, or canonical synchronization. Those costs are intentionally measured separately.

Each logical operation uses the real native JXL executor rather than directly calling a C container implementation.

## Next optimization targets

1. Keep Vector/Stack/Queue/Deque as regression guards near the PHP-array line.
2. Add a resolved-slot Record comparison that removes the associative-array mismatch.
3. Optimize Map/Set native slot density and probe behavior.
4. Add resident/batched JXL container-region measurements so single-operation host call overhead can be separated from the executor itself.
5. Keep `BSYNC`/checkpoint timing separate from hot mutation timing.

Do not replace this snapshot when newer results are produced. Add a new dated snapshot so performance history remains auditable.
