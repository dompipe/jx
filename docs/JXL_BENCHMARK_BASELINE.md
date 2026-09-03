# PASL → JXL benchmark baseline

Baseline date: 2026-09-03

This is the first benchmark baseline after PASL's default prepared target moved from legacy PBC to JXL.

## What is being measured

The benchmark deliberately separates the current stages:

```text
PASL source
  ├─→ JXL compile
  │    ├─ validation
  │    └─ current execution: JXL → PASM assembly → PBC → PASM VM
  └─→ PBC compile → PASM VM   (legacy baseline)
```

The JXL execution figures in this baseline are **not direct native JXL execution**. PASL JXL opcodes `0x51..0x77` still pass through the compatibility admission/transcode path before the PASM VM runs them. This document therefore establishes the number that direct native JXL dispatch must beat.

Harness: `pasl/bench/bench-jxl.php`

Workflow: `.github/workflows/pasl-jxl-benchmark.yml`

GitHub Actions run: `33717760058`

Runner: Ubuntu 24.04, Linux x86_64, PHP 8.3.33.

Timing values are medians of repeated batches and are reported in microseconds per operation. Cloud-runner absolute timings vary, so the relative JXL/PBC ratios are the primary comparison.

## Optimized compiler

Configuration: 500 compile iterations, 80 run iterations, 7 rounds.

### Prepared size and compile time

| case | source B | JXL B | PBC B | JXL/PBC size | JXL compile µs | PBC compile µs | JXL/PBC compile |
|---|---:|---:|---:|---:|---:|---:|---:|
| scalar | 40 | 72 | 44 | 1.64x | 80.32 | 75.68 | 1.06x |
| movi64 | 33 | 42 | 27 | 1.56x | 53.58 | 51.40 | 1.04x |
| while64 | 51 | 90 | 61 | 1.48x | 101.28 | 93.65 | 1.08x |
| for128 | 58 | 102 | 71 | 1.44x | 129.33 | 117.96 | 1.10x |
| nested16 | 86 | 180 | 128 | 1.41x | 204.67 | 184.99 | 1.11x |
| signed128 | 55 | 90 | 61 | 1.48x | 101.18 | 89.72 | 1.13x |

### Admission and execution

| case | validate µs | admit/transcode µs | JXL run µs | PBC run µs | JXL/PBC run |
|---|---:|---:|---:|---:|---:|
| scalar | 1.93 | 18.89 | 46.40 | 20.64 | 2.25x |
| movi64 | 1.36 | 12.28 | 38.61 | 24.08 | 1.60x |
| while64 | 2.35 | 23.96 | 101.59 | 69.25 | 1.47x |
| for128 | 2.62 | 27.40 | 149.39 | 108.98 | 1.37x |
| nested16 | 6.07 | 46.14 | 327.57 | 271.08 | 1.21x |
| signed128 | 2.64 | 25.14 | 134.50 | 111.16 | 1.21x |

Geometric means:

- JXL/PBC prepared size: **1.496x**
- JXL/PBC compile time: **1.086x**
- JXL/PBC run time: **1.482x**
- admission/JXL-run fraction: **0.230x**

## Plain compiler

Configuration: 300 compile iterations, 50 run iterations, 5 rounds.

### Prepared size and compile time

| case | source B | JXL B | PBC B | JXL/PBC size | JXL compile µs | PBC compile µs | JXL/PBC compile |
|---|---:|---:|---:|---:|---:|---:|---:|
| scalar | 40 | 72 | 44 | 1.64x | 79.55 | 73.04 | 1.09x |
| movi64 | 33 | 42 | 27 | 1.56x | 47.31 | 49.78 | 0.95x |
| while64 | 51 | 90 | 61 | 1.48x | 88.20 | 78.71 | 1.12x |
| for128 | 58 | 108 | 76 | 1.42x | 96.62 | 90.16 | 1.07x |
| nested16 | 86 | 186 | 133 | 1.40x | 145.20 | 140.38 | 1.03x |
| signed128 | 55 | 90 | 61 | 1.48x | 89.60 | 76.67 | 1.17x |

### Admission and execution

| case | validate µs | admit/transcode µs | JXL run µs | PBC run µs | JXL/PBC run |
|---|---:|---:|---:|---:|---:|
| scalar | 2.15 | 20.69 | 46.77 | 22.87 | 2.04x |
| movi64 | 1.42 | 12.01 | 38.39 | 23.95 | 1.60x |
| while64 | 2.40 | 23.70 | 101.57 | 72.47 | 1.40x |
| for128 | 3.30 | 27.85 | 161.74 | 132.26 | 1.22x |
| nested16 | 5.72 | 51.45 | 340.63 | 279.25 | 1.22x |
| signed128 | 2.36 | 23.39 | 132.18 | 104.43 | 1.27x |

Geometric means:

- JXL/PBC prepared size: **1.492x**
- JXL/PBC compile time: **1.070x**
- JXL/PBC run time: **1.433x**
- admission/JXL-run fraction: **0.230x**

## Interpretation

### 1. JXL compile overhead is modest

The JXL compiler adds roughly **7–9%** to compile time on aggregate in this baseline. That is small enough that compile speed is not the first optimization target.

### 2. Fixed six-byte JXL cells trade density for regularity

Prepared JXL is about **1.49x** the byte size of the compact PBC encoding across these cases. This is expected from the fixed six-byte cell design. Direct addressing and predictable decode are the intended payoff; native execution must be benchmarked before deciding whether further cell compaction is worthwhile.

### 3. Compatibility admission is the immediate runtime target

Current JXL execution is **1.48x slower optimized** and **1.43x slower plain** than running already-prepared PBC. The admission/transcode operation alone is about **23% of total JXL run time** geometrically, and the JXL `runCode()` path also pays validation, conversion, assembly, and VM setup on each call.

The shortest performance path is therefore:

```text
current:
JXL cell → decode to PASM text → assemble PBC → PASM VM

next:
JXL cell → direct JXL dispatcher/native handler
```

### 4. Longer workloads dilute the admission tax

The smallest scalar case is 2.25x slower through the current JXL route, while the nested loop is 1.21x slower. This shows that one-time admission overhead dominates short programs and is progressively amortized by execution-heavy workloads.

## Next benchmark milestone

Implement direct execution for PASL JXL opcodes `0x51..0x77` and rerun this exact harness unchanged. The first success criterion is to remove the admission/transcode cost; the next is for JXL runtime to meet or beat the PBC baseline while preserving the fixed-cell and JXB packaging model.
