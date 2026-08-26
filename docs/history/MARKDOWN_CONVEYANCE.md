# dompipe Markdown Conveyance

Generated: 2026-08-25T21:32:24-04:00

Source repositories:
- `pasm-v2`: branch `main`, HEAD `6662e9e0c8e4`
- `jx-lang`: branch `main`, HEAD `6dc123045ad5`

Scope: all reachable Markdown-like paths (`.md`, `.markdown`) in both repositories, current files first, with Git-followed first versions and latest blame summaries.

## Convergence Map

- `complex.md` (different latest content): `pasm-v2:jx/complex.md`; `jx-lang:docs/complex.md`
- `conversation_log.md` (different latest content): `pasm-v2:jx/CONVERSATION_LOG.md`; `jx-lang:docs/CONVERSATION_LOG.md`
- `delivery.md` (different latest content): `pasm-v2:jx/delivery.md`; `jx-lang:docs/delivery.md`
- `edge-cases.md` (different latest content): `pasm-v2:jx/edge-cases.md`; `jx-lang:tests/edge-cases.md`
- `gaps.md` (different latest content): `pasm-v2:jx/GAPS.md`; `jx-lang:docs/GAPS.md`
- `hosting-api.md` (different latest content): `pasm-v2:jx/hosting-api.md`; `jx-lang:docs/hosting-api.md`
- `readme.md` (different latest content): `pasm-v2:README.md`; `pasm-v2:jx/README.md`; `jx-lang:README.md`
- `smart-table.md` (different latest content): `pasm-v2:jx/smart-table.md`; `jx-lang:docs/smart-table.md`
- `spec.md` (different latest content): `pasm-v2:jx/SPEC.md`; `jx-lang:SPEC.md`

## Repository Markdown Timelines

### pasm-v2

- Current Markdown files: 27
- Historical Markdown paths: 27

- `d92a8d1` 2026-08-19T12:18:58-04:00 wise-penny: pasm-v2
- `cdf4207` 2026-08-22T11:45:34-04:00 dompipe: Add program builder: arbitrary PHP frame, ASM frame, OOP containers, finalize package
- `ff23341` 2026-08-22T11:50:33-04:00 dompipe: Unified bytecode output from containers+ASM; structured error handling
- `b1cc70b` 2026-08-22T11:56:38-04:00 dompipe: Add expression compiler: PHP-like assignments/operators lower to PASM bytecode
- `9afffe6` 2026-08-22T12:02:16-04:00 dompipe: Add control-flow compiler: while/for/if/else/select lower to JMP/JZ/JNZ bytecode
- `083ded4` 2026-08-22T12:48:31-04:00 dompipe: PASL language compiler, runner, manuals, examples (full sources)
- `b7ee0a4` 2026-08-22T12:59:51-04:00 dompipe: PASL x86-64 NASM backend: real assembly, no I/O, sys_exit result
- `85b62db` 2026-08-22T13:01:37-04:00 dompipe: Fix PASL core Exception::$line; runner --x86; x86-sum example
- `e74790f` 2026-08-22T13:11:48-04:00 dompipe: PASL O(n) refactor: single-pass IR, x86 + PASM backends
- `21569ca` 2026-08-22T13:18:22-04:00 dompipe: PASL docs + ARM64 backend CLI + manuals
- `d5e962f` 2026-08-22T13:26:10-04:00 dompipe: PASL portable C backend: Linux binaries + Windows EXE path
- `98d39bb` 2026-08-22T13:32:44-04:00 dompipe: PASL major release docs: benchmarks table, PHP-to-EXE synopsis, tooling
- `53416d3` 2026-08-22T13:46:19-04:00 dompipe: PASL strings + network abilities; README update
- `d2daeaa` 2026-08-22T14:07:37-04:00 dompipe: PASL v3: arrays, full type surface, programming guide, benchmarks
- `780c316` 2026-08-22T14:08:42-04:00 dompipe: PASL Programming Guide (complete step-by-step)
- `a2d591c` 2026-08-22T14:16:54-04:00 dompipe: Document unified pasl\Package entry for strnet + core
- `542b349` 2026-08-22T14:47:44-04:00 dompipe: Integrate docs: README + refresh vs smooth live semantics
- `8093de0` 2026-08-25T18:09:29-04:00 dompipe: Add xi XIP book server (core engine, CLI, Makefile, Docker)
- `ef6f6b6` 2026-08-25T18:17:46-04:00 dompipe: xi: XipEngine (b64 parts + loader) — completes runnable book server
- `e599163` 2026-08-25T18:28:02-04:00 dompipe: xi: README note on XipEngine assembly
- `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- `a30639b` 2026-08-25T20:20:29-04:00 dompipe: Realize jx as one code construct on PASM: Bag/Task/Page/Book, memory law, Delivery, smart table bridge
- `be8fd24` 2026-08-25T20:25:12-04:00 dompipe: jx executable compiler/interpreter: jx-run.php, JxEngine, .jx source path through PASL bytecode when possible
- `20daf6b` 2026-08-25T20:32:46-04:00 dompipe: Complete jx install: plugin source dir, sequential installs, dual backups, decimals, intro, smart compiler modules
- `ca14210` 2026-08-25T20:36:37-04:00 dompipe: Plugin allow-gate: must target windows, mac, linux, and web (jx) before install
- `e4de552` 2026-08-25T20:46:10-04:00 dompipe: Hard reject non-portable plugins; collect all errors into jxerr.log

### jx-lang

- Current Markdown files: 9
- Historical Markdown paths: 9

- `0452fc2` 2026-08-25T20:12:19-04:00 dompipe: Initial commit
- `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- `6dc1230` 2026-08-25T20:16:11-04:00 dompipe: Add full design conversation log and reflective gaps (perfection is amiss)

## pasm-v2 Files

### `PASL_Language_Manual.md`

- Current lines: 108
- Original reachable commit: `083ded4` 2026-08-22T12:48:31-04:00 dompipe: PASL language compiler, runner, manuals, examples (full sources)
- Latest Markdown-touching commit: `083ded4` 2026-08-22T12:48:31-04:00 dompipe: PASL language compiler, runner, manuals, examples (full sources)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 108
- Latest blame by commit:
  - `083ded4` 108 lines dompipe: PASL language compiler, runner, manuals, examples (full sources)

<details>
<summary>Latest line blame</summary>

````markdown
083ded4d (dompipe 2026-08-22   1) # PASL Language Manual
083ded4d (dompipe 2026-08-22   2) 
083ded4d (dompipe 2026-08-22   3) **PASM Language — PHP-like Compiler for PASM Bytecode**  
083ded4d (dompipe 2026-08-22   4) Version 1.0 · 2026 · dompipe/pasm-v2
083ded4d (dompipe 2026-08-22   5) 
083ded4d (dompipe 2026-08-22   6) Silent by default · Optimized by default · Portable `.pbc` bytecode
083ded4d (dompipe 2026-08-22   7) 
083ded4d (dompipe 2026-08-22   8) ## 1. Overview
083ded4d (dompipe 2026-08-22   9) 
083ded4d (dompipe 2026-08-22  10) PASL is a restricted, PHP-like language that compiles to the existing PASM binary ISA. It is **not** a full PHP compiler. Every construct has a precise lowering to registers, jumps, and memory ops the bytecode VM can execute.
083ded4d (dompipe 2026-08-22  11) 
083ded4d (dompipe 2026-08-22  12) Goals:
083ded4d (dompipe 2026-08-22  13) 
083ded4d (dompipe 2026-08-22  14) 1. Feel like small PHP numeric scripts  
083ded4d (dompipe 2026-08-22  15) 2. Emit optimizable bytecode  
083ded4d (dompipe 2026-08-22  16) 3. Write portable `.pbc` files  
083ded4d (dompipe 2026-08-22  17) 4. First-class complex numbers  
083ded4d (dompipe 2026-08-22  18) 5. Stay silent unless asked to print  
083ded4d (dompipe 2026-08-22  19) 
083ded4d (dompipe 2026-08-22  20) | Is | Is not |
083ded4d (dompipe 2026-08-22  21) |----|--------|
083ded4d (dompipe 2026-08-22  22) | Integer & complex arithmetic | Full PHP objects/strings/arrays |
083ded4d (dompipe 2026-08-22  23) | while / for / if / select | foreach over iterators |
083ded4d (dompipe 2026-08-22  24) | Optimized bytecode + .pbc | Native x86/ARM binaries |
083ded4d (dompipe 2026-08-22  25) | Runs on PASM VM (PHP host) | OS executable without PHP |
083ded4d (dompipe 2026-08-22  26) 
083ded4d (dompipe 2026-08-22  27) ## 2. Invocation
083ded4d (dompipe 2026-08-22  28) 
083ded4d (dompipe 2026-08-22  29) ```bash
083ded4d (dompipe 2026-08-22  30) php pasm-run.php -o out.pbc examples/pasl/arith.pasl   # compile (silent)
083ded4d (dompipe 2026-08-22  31) php pasm-run.php out.pbc                           # run (silent)
083ded4d (dompipe 2026-08-22  32) php pasm-run.php --print out.pbc                   # print return value
083ded4d (dompipe 2026-08-22  33) php pasm-run.php --print -c '$x=1; $x++;'
083ded4d (dompipe 2026-08-22  34) php pasm-run.php -O0 --print -c '$x=2*3;'
083ded4d (dompipe 2026-08-22  35) ```
083ded4d (dompipe 2026-08-22  36) 
083ded4d (dompipe 2026-08-22  37) ## 3. Types
083ded4d (dompipe 2026-08-22  38) 
083ded4d (dompipe 2026-08-22  39) ### Integer
083ded4d (dompipe 2026-08-22  40) 
083ded4d (dompipe 2026-08-22  41) Mapped to `ecx, ah, adx, bdx, cdx, ddx, edx, rdx`.
083ded4d (dompipe 2026-08-22  42) 
083ded4d (dompipe 2026-08-22  43) ```pasl
083ded4d (dompipe 2026-08-22  44) $addedto = 0;
083ded4d (dompipe 2026-08-22  45) $addedto = $addedto + 1;
083ded4d (dompipe 2026-08-22  46) $addedto++;
083ded4d (dompipe 2026-08-22  47) $addedto += 1;
083ded4d (dompipe 2026-08-22  48) $addedto = $addedto * 2;
083ded4d (dompipe 2026-08-22  49) ```
083ded4d (dompipe 2026-08-22  50) 
083ded4d (dompipe 2026-08-22  51) ### Complex
083ded4d (dompipe 2026-08-22  52) 
083ded4d (dompipe 2026-08-22  53) Pair `(re, im)` in two registers. Literals: `3+4i`, `1-2i`, `i`, `-i`.
083ded4d (dompipe 2026-08-22  54) 
083ded4d (dompipe 2026-08-22  55) | Op | Expansion |
083ded4d (dompipe 2026-08-22  56) |----|-----------|
083ded4d (dompipe 2026-08-22  57) | z+w | re+re, im+im |
083ded4d (dompipe 2026-08-22  58) | z-w | re-re, im-im |
083ded4d (dompipe 2026-08-22  59) | z*w | (ac−bd)+(ad+bc)i |
083ded4d (dompipe 2026-08-22  60) 
083ded4d (dompipe 2026-08-22  61) ```pasl
083ded4d (dompipe 2026-08-22  62) complex $z = 3+4i;
083ded4d (dompipe 2026-08-22  63) complex $w = 1-2i;
083ded4d (dompipe 2026-08-22  64) complex $p = $z * $w;   // 11+2i
083ded4d (dompipe 2026-08-22  65) ```
083ded4d (dompipe 2026-08-22  66) 
083ded4d (dompipe 2026-08-22  67) ## 4. Control flow
083ded4d (dompipe 2026-08-22  68) 
083ded4d (dompipe 2026-08-22  69) ZF-only: `==`, `!=`, nonzero. No `< > <= >=`.
083ded4d (dompipe 2026-08-22  70) 
083ded4d (dompipe 2026-08-22  71) ```pasl
083ded4d (dompipe 2026-08-22  72) while ($i) { $sum = $sum + $i; $i--; }
083ded4d (dompipe 2026-08-22  73) for ($k = 0; $k != 4; $k++) { $j = $j + 1; }
083ded4d (dompipe 2026-08-22  74) if ($mode == 2) { ... } else { ... }
083ded4d (dompipe 2026-08-22  75) select ($mode) {
083ded4d (dompipe 2026-08-22  76)   case 1: ...
083ded4d (dompipe 2026-08-22  77)   case 2: ...
083ded4d (dompipe 2026-08-22  78)   default: ...
083ded4d (dompipe 2026-08-22  79) }
083ded4d (dompipe 2026-08-22  80) ```
083ded4d (dompipe 2026-08-22  81) 
083ded4d (dompipe 2026-08-22  82) ## 5. Optimizations (-O1 default)
083ded4d (dompipe 2026-08-22  83) 
083ded4d (dompipe 2026-08-22  84) - Drop `x=x`, `x+=0`, `x*=1`
083ded4d (dompipe 2026-08-22  85) - `y*2` → `ADD y,y`
083ded4d (dompipe 2026-08-22  86) - Constant folding
083ded4d (dompipe 2026-08-22  87) - Backend peephole (`PASMOptimizingAssembler`)
083ded4d (dompipe 2026-08-22  88) 
083ded4d (dompipe 2026-08-22  89) ## 6. `.pbc` format
083ded4d (dompipe 2026-08-22  90) 
083ded4d (dompipe 2026-08-22  91) ```
083ded4d (dompipe 2026-08-22  92) Magic PBC1 | ver | flags | len | entry | crc32 | code | symbols…
083ded4d (dompipe 2026-08-22  93) ```
083ded4d (dompipe 2026-08-22  94) 
083ded4d (dompipe 2026-08-22  95) Portable VM image: any computer with PHP + PASM runtime.
083ded4d (dompipe 2026-08-22  96) 
083ded4d (dompipe 2026-08-22  97) ## 7. Embed API
083ded4d (dompipe 2026-08-22  98) 
083ded4d (dompipe 2026-08-22  99) ```php
083ded4d (dompipe 2026-08-22 100) use pasm\lang\Engine;
083ded4d (dompipe 2026-08-22 101) $eng = new Engine(optimize: true, verbose: false);
083ded4d (dompipe 2026-08-22 102) $eng->compileFile($src, 'app.pbc');
083ded4d (dompipe 2026-08-22 103) $result = $eng->runFile('app.pbc');
083ded4d (dompipe 2026-08-22 104) ```
083ded4d (dompipe 2026-08-22 105) 
083ded4d (dompipe 2026-08-22 106) ## 8. Limits
083ded4d (dompipe 2026-08-22 107) 
083ded4d (dompipe 2026-08-22 108) 8 registers; no relational compares; no strings/arrays/objects; no complex÷; single entry; not native machine code.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# PASL Language Manual

**PASM Language — PHP-like Compiler for PASM Bytecode**  
Version 1.0 · 2026 · dompipe/pasm-v2

Silent by default · Optimized by default · Portable `.pbc` bytecode

## 1. Overview

PASL is a restricted, PHP-like language that compiles to the existing PASM binary ISA. It is **not** a full PHP compiler. Every construct has a precise lowering to registers, jumps, and memory ops the bytecode VM can execute.

Goals:

1. Feel like small PHP numeric scripts  
2. Emit optimizable bytecode  
3. Write portable `.pbc` files  
4. First-class complex numbers  
5. Stay silent unless asked to print  

| Is | Is not |
|----|--------|
| Integer & complex arithmetic | Full PHP objects/strings/arrays |
| while / for / if / select | foreach over iterators |
| Optimized bytecode + .pbc | Native x86/ARM binaries |
| Runs on PASM VM (PHP host) | OS executable without PHP |

## 2. Invocation

```bash
php pasm-run.php -o out.pbc examples/pasl/arith.pasl   # compile (silent)
php pasm-run.php out.pbc                           # run (silent)
php pasm-run.php --print out.pbc                   # print return value
php pasm-run.php --print -c '$x=1; $x++;'
php pasm-run.php -O0 --print -c '$x=2*3;'
```

## 3. Types

### Integer

Mapped to `ecx, ah, adx, bdx, cdx, ddx, edx, rdx`.

```pasl
$addedto = 0;
$addedto = $addedto + 1;
$addedto++;
$addedto += 1;
$addedto = $addedto * 2;
```

### Complex

Pair `(re, im)` in two registers. Literals: `3+4i`, `1-2i`, `i`, `-i`.

| Op | Expansion |
|----|-----------|
| z+w | re+re, im+im |
| z-w | re-re, im-im |
| z*w | (ac−bd)+(ad+bc)i |

```pasl
complex $z = 3+4i;
complex $w = 1-2i;
complex $p = $z * $w;   // 11+2i
```

## 4. Control flow

ZF-only: `==`, `!=`, nonzero. No `< > <= >=`.

```pasl
while ($i) { $sum = $sum + $i; $i--; }
for ($k = 0; $k != 4; $k++) { $j = $j + 1; }
if ($mode == 2) { ... } else { ... }
select ($mode) {
  case 1: ...
  case 2: ...
  default: ...
}
```

## 5. Optimizations (-O1 default)

- Drop `x=x`, `x+=0`, `x*=1`
- `y*2` → `ADD y,y`
- Constant folding
- Backend peephole (`PASMOptimizingAssembler`)

## 6. `.pbc` format

```
Magic PBC1 | ver | flags | len | entry | crc32 | code | symbols…
```

Portable VM image: any computer with PHP + PASM runtime.

## 7. Embed API

```php
use pasm\lang\Engine;
$eng = new Engine(optimize: true, verbose: false);
$eng->compileFile($src, 'app.pbc');
$result = $eng->runFile('app.pbc');
```

## 8. Limits

8 registers; no relational compares; no strings/arrays/objects; no complex÷; single entry; not native machine code.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# PASL Language Manual

**PASM Language — PHP-like Compiler for PASM Bytecode**  
Version 1.0 · 2026 · dompipe/pasm-v2

Silent by default · Optimized by default · Portable `.pbc` bytecode

## 1. Overview

PASL is a restricted, PHP-like language that compiles to the existing PASM binary ISA. It is **not** a full PHP compiler. Every construct has a precise lowering to registers, jumps, and memory ops the bytecode VM can execute.

Goals:

1. Feel like small PHP numeric scripts  
2. Emit optimizable bytecode  
3. Write portable `.pbc` files  
4. First-class complex numbers  
5. Stay silent unless asked to print  

| Is | Is not |
|----|--------|
| Integer & complex arithmetic | Full PHP objects/strings/arrays |
| while / for / if / select | foreach over iterators |
| Optimized bytecode + .pbc | Native x86/ARM binaries |
| Runs on PASM VM (PHP host) | OS executable without PHP |

## 2. Invocation

```bash
php pasm-run.php -o out.pbc examples/pasl/arith.pasl   # compile (silent)
php pasm-run.php out.pbc                           # run (silent)
php pasm-run.php --print out.pbc                   # print return value
php pasm-run.php --print -c '$x=1; $x++;'
php pasm-run.php -O0 --print -c '$x=2*3;'
```

## 3. Types

### Integer

Mapped to `ecx, ah, adx, bdx, cdx, ddx, edx, rdx`.

```pasl
$addedto = 0;
$addedto = $addedto + 1;
$addedto++;
$addedto += 1;
$addedto = $addedto * 2;
```

### Complex

Pair `(re, im)` in two registers. Literals: `3+4i`, `1-2i`, `i`, `-i`.

| Op | Expansion |
|----|-----------|
| z+w | re+re, im+im |
| z-w | re-re, im-im |
| z*w | (ac−bd)+(ad+bc)i |

```pasl
complex $z = 3+4i;
complex $w = 1-2i;
complex $p = $z * $w;   // 11+2i
```

## 4. Control flow

ZF-only: `==`, `!=`, nonzero. No `< > <= >=`.

```pasl
while ($i) { $sum = $sum + $i; $i--; }
for ($k = 0; $k != 4; $k++) { $j = $j + 1; }
if ($mode == 2) { ... } else { ... }
select ($mode) {
  case 1: ...
  case 2: ...
  default: ...
}
```

## 5. Optimizations (-O1 default)

- Drop `x=x`, `x+=0`, `x*=1`
- `y*2` → `ADD y,y`
- Constant folding
- Backend peephole (`PASMOptimizingAssembler`)

## 6. `.pbc` format

```
Magic PBC1 | ver | flags | len | entry | crc32 | code | symbols…
```

Portable VM image: any computer with PHP + PASM runtime.

## 7. Embed API

```php
use pasm\lang\Engine;
$eng = new Engine(optimize: true, verbose: false);
$eng->compileFile($src, 'app.pbc');
$result = $eng->runFile('app.pbc');
```

## 8. Limits

8 registers; no relational compares; no strings/arrays/objects; no complex÷; single entry; not native machine code.
````

</details>

### `README-PASL.md`

- Current lines: 24
- Original reachable commit: `083ded4` 2026-08-22T12:48:31-04:00 dompipe: PASL language compiler, runner, manuals, examples (full sources)
- Latest Markdown-touching commit: `85b62db` 2026-08-22T13:01:37-04:00 dompipe: Fix PASL core Exception::$line; runner --x86; x86-sum example
- Markdown-touching commits for this path: 3
- Latest blame by author: dompipe 24
- Latest blame by commit:
  - `083ded4` 11 lines dompipe: PASL language compiler, runner, manuals, examples (full sources)
  - `85b62db` 7 lines dompipe: Fix PASL core Exception::$line; runner --x86; x86-sum example
  - `b7ee0a4` 6 lines dompipe: PASL x86-64 NASM backend: real assembly, no I/O, sys_exit result

<details>
<summary>Latest line blame</summary>

````markdown
083ded4d (dompipe 2026-08-22  1) # PASL — PASM Language
083ded4d (dompipe 2026-08-22  2) 
85b62dbe (dompipe 2026-08-22  3) PHP-like language → **PASM bytecode** or **x86-64 NASM**.
083ded4d (dompipe 2026-08-22  4) 
85b62dbe (dompipe 2026-08-22  5) ## Bytecode VM
083ded4d (dompipe 2026-08-22  6) 
083ded4d (dompipe 2026-08-22  7) ```bash
083ded4d (dompipe 2026-08-22  8) php pasm-run.php -o out.pbc examples/pasl/arith.pasl
083ded4d (dompipe 2026-08-22  9) php pasm-run.php --print out.pbc
083ded4d (dompipe 2026-08-22 10) ```
083ded4d (dompipe 2026-08-22 11) 
85b62dbe (dompipe 2026-08-22 12) ## Native x86-64 (Linux)
083ded4d (dompipe 2026-08-22 13) 
b7ee0a46 (dompipe 2026-08-22 14) ```bash
b7ee0a46 (dompipe 2026-08-22 15) php pasm-run.php --x86 -o sum.s examples/pasl/x86-sum.pasl
b7ee0a46 (dompipe 2026-08-22 16) nasm -f elf64 sum.s -o sum.o
b7ee0a46 (dompipe 2026-08-22 17) ld sum.o -o sum
85b62dbe (dompipe 2026-08-22 18) ./sum; echo $?   # 15
b7ee0a46 (dompipe 2026-08-22 19) ```
b7ee0a46 (dompipe 2026-08-22 20) 
85b62dbe (dompipe 2026-08-22 21) Freestanding: prologue, GPRs, `cmp`/`jmp` loops, complex as register pairs.
85b62dbe (dompipe 2026-08-22 22) **No printf/write** — only `sys_exit` with the result in `rdi`.
083ded4d (dompipe 2026-08-22 23) 
85b62dbe (dompipe 2026-08-22 24) See `pasm-lang-x86.php` and sample `examples/pasl/x86-sum.s`.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# PASL — PASM Language

PHP-like restricted language → optimized PASM bytecode → portable `.pbc` files.

## Quick start

```bash
php pasm-run.php -o out.pbc examples/pasl/arith.pasl
php pasm-run.php --print out.pbc
```

Silent by default. Use `--print` for the return value.

## Features

- Integers and **complex numbers** (`3+4i`)
- `while` / `for` / `if` / `select`
- Optimizations (`-O1` default)
- Manuals: `PASL_Language_Manual.md` / `PASL_Language_Manual.pdf`

See the manuals for operator maps, `.pbc` layout, and limits.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# PASL — PASM Language

PHP-like language → **PASM bytecode** or **x86-64 NASM**.

## Bytecode VM

```bash
php pasm-run.php -o out.pbc examples/pasl/arith.pasl
php pasm-run.php --print out.pbc
```

## Native x86-64 (Linux)

```bash
php pasm-run.php --x86 -o sum.s examples/pasl/x86-sum.pasl
nasm -f elf64 sum.s -o sum.o
ld sum.o -o sum
./sum; echo $?   # 15
```

Freestanding: prologue, GPRs, `cmp`/`jmp` loops, complex as register pairs.
**No printf/write** — only `sys_exit` with the result in `rdi`.

See `pasm-lang-x86.php` and sample `examples/pasl/x86-sum.s`.
````

</details>

### `README-PASM-OOP-FAST.md`

- Current lines: 69
- Original reachable commit: `d92a8d1` 2026-08-19T12:18:58-04:00 wise-penny: pasm-v2
- Latest Markdown-touching commit: `d92a8d1` 2026-08-19T12:18:58-04:00 wise-penny: pasm-v2
- Markdown-touching commits for this path: 1
- Latest blame by author: wise-penny 69
- Latest blame by commit:
  - `d92a8d1` 69 lines wise-penny: pasm-v2

<details>
<summary>Latest line blame</summary>

```markdown
^d92a8d1 (wise-penny 2026-08-19  1) # PASM OOP Hot-Path Rewrite
^d92a8d1 (wise-penny 2026-08-19  2) 
^d92a8d1 (wise-penny 2026-08-19  3) This version keeps the canonical PASM frame/segmentation model, but removes segmentation and cell-codec work from ordinary container operations.
^d92a8d1 (wise-penny 2026-08-19  4) 
^d92a8d1 (wise-penny 2026-08-19  5) ## Architecture
^d92a8d1 (wise-penny 2026-08-19  6) 
^d92a8d1 (wise-penny 2026-08-19  7) Hot operations use frame-local PHP state only. The canonical segmented image is write-back storage and is materialized only at explicit boundaries such as `dirtySegments()`, `clearDirty()`, `flush()`, `defrag()`, canonical register export, remote synchronization, or persistence.
^d92a8d1 (wise-penny 2026-08-19  8) 
^d92a8d1 (wise-penny 2026-08-19  9) - `Vector/List`: packed hot array.
^d92a8d1 (wise-penny 2026-08-19 10) - `Stack`: packed hot array with direct push/pop.
^d92a8d1 (wise-penny 2026-08-19 11) - `Queue`: append + head index with periodic compaction.
^d92a8d1 (wise-penny 2026-08-19 12) - `Deque`: power-of-two circular ring; all four end operations are O(1) amortized.
^d92a8d1 (wise-penny 2026-08-19 13) - `Map`: PHP hash table on the hot path; canonical key/value image only at sync.
^d92a8d1 (wise-penny 2026-08-19 14) - `Set`: typed scalar signatures; serialization only for complex fallback values.
^d92a8d1 (wise-penny 2026-08-19 15) - Each container still belongs to a canonical `PASMRegisterFrame` and owns a logical PASM segment checkpoint.
^d92a8d1 (wise-penny 2026-08-19 16) 
^d92a8d1 (wise-penny 2026-08-19 17) ## Benchmark
^d92a8d1 (wise-penny 2026-08-19 18) 
^d92a8d1 (wise-penny 2026-08-19 19) PHP 8.4 with CLI OPcache. Times are median measurements from the included benchmark. `ops` means total API operations (half writes/pushes, half reads/pops).
^d92a8d1 (wise-penny 2026-08-19 20) 
^d92a8d1 (wise-penny 2026-08-19 21) ### 100,000 operations
^d92a8d1 (wise-penny 2026-08-19 22) 
^d92a8d1 (wise-penny 2026-08-19 23) | workload | legacy PASM | hot-path canonical | speedup |
^d92a8d1 (wise-penny 2026-08-19 24) |---|---:|---:|---:|
^d92a8d1 (wise-penny 2026-08-19 25) | Vector add/get | 5.753 ms | 3.994 ms | 1.44x |
^d92a8d1 (wise-penny 2026-08-19 26) | Stack push/pop | 9.834 ms | 4.388 ms | 2.24x |
^d92a8d1 (wise-penny 2026-08-19 27) | Queue enqueue/dequeue | 8.523 ms | 6.997 ms | 1.22x |
^d92a8d1 (wise-penny 2026-08-19 28) | Deque back/front | 10.494 ms | 8.707 ms | 1.21x |
^d92a8d1 (wise-penny 2026-08-19 29) | Map put/get | 4.715 ms | 4.314 ms | 1.09x |
^d92a8d1 (wise-penny 2026-08-19 30) | Set add/has | 24.989 ms | 13.779 ms | 1.81x |
^d92a8d1 (wise-penny 2026-08-19 31) 
^d92a8d1 (wise-penny 2026-08-19 32) ### 1,000,000 operations
^d92a8d1 (wise-penny 2026-08-19 33) 
^d92a8d1 (wise-penny 2026-08-19 34) | workload | legacy PASM | hot-path canonical | speedup |
^d92a8d1 (wise-penny 2026-08-19 35) |---|---:|---:|---:|
^d92a8d1 (wise-penny 2026-08-19 36) | Vector add/get | 53.924 ms | 42.449 ms | 1.27x |
^d92a8d1 (wise-penny 2026-08-19 37) | Stack push/pop | 80.414 ms | 46.253 ms | 1.74x |
^d92a8d1 (wise-penny 2026-08-19 38) | Queue enqueue/dequeue | 88.356 ms | 67.362 ms | 1.31x |
^d92a8d1 (wise-penny 2026-08-19 39) | Deque back/front | 96.006 ms | 83.465 ms | 1.15x |
^d92a8d1 (wise-penny 2026-08-19 40) | Map put/get | 48.917 ms | 45.220 ms | 1.08x |
^d92a8d1 (wise-penny 2026-08-19 41) | Set add/has | 240.272 ms | 152.258 ms | 1.58x |
^d92a8d1 (wise-penny 2026-08-19 42) 
^d92a8d1 (wise-penny 2026-08-19 43) Peak memory in the 1,000,000-op run dropped from about 56.0 MB legacy to about 52.0 MB canonical.
^d92a8d1 (wise-penny 2026-08-19 44) 
^d92a8d1 (wise-penny 2026-08-19 45) ## Deque pathological-path fix
^d92a8d1 (wise-penny 2026-08-19 46) 
^d92a8d1 (wise-penny 2026-08-19 47) For 20,000 opposite-end operations (`pushFront` then `popBack`):
^d92a8d1 (wise-penny 2026-08-19 48) 
^d92a8d1 (wise-penny 2026-08-19 49) - Legacy PASM: about 5.31 seconds.
^d92a8d1 (wise-penny 2026-08-19 50) - New circular PASM Deque: about 1.81 ms.
^d92a8d1 (wise-penny 2026-08-19 51) 
^d92a8d1 (wise-penny 2026-08-19 52) That path is roughly 2,900x faster in this run because the old implementation rebuilt arrays while the new implementation uses a circular ring.
^d92a8d1 (wise-penny 2026-08-19 53) 
^d92a8d1 (wise-penny 2026-08-19 54) ## Boundary behavior
^d92a8d1 (wise-penny 2026-08-19 55) 
^d92a8d1 (wise-penny 2026-08-19 56) The performance above measures hot container work. At an explicit canonical boundary, PASM materializes the current logical container image into page-backed segmented storage. This is intentional: the cost is paid once at `YIELD`/sync/defrag/persistence rather than on every container operation. If a Queue/Deque returns to its previously synchronized empty state before the boundary, no dirty pages need to be exported.
^d92a8d1 (wise-penny 2026-08-19 57) 
^d92a8d1 (wise-penny 2026-08-19 58) ## Compatibility
^d92a8d1 (wise-penny 2026-08-19 59) 
^d92a8d1 (wise-penny 2026-08-19 60) The familiar APIs remain available:
^d92a8d1 (wise-penny 2026-08-19 61) 
^d92a8d1 (wise-penny 2026-08-19 62) - `Vector` / `PASMList`
^d92a8d1 (wise-penny 2026-08-19 63) - `Stack` / `PASMStack`
^d92a8d1 (wise-penny 2026-08-19 64) - `Queue` / `PASMQueue`
^d92a8d1 (wise-penny 2026-08-19 65) - `Deque` / `PASMDeque`
^d92a8d1 (wise-penny 2026-08-19 66) - `Map` / `PASMMap`
^d92a8d1 (wise-penny 2026-08-19 67) - `Set` / `PASMSet`
^d92a8d1 (wise-penny 2026-08-19 68) 
^d92a8d1 (wise-penny 2026-08-19 69) Canonical methods such as `forFrame()`, `frame()`, `containerId()`, `segmentIds()`, `dirtySegments()`, `clearDirty()`, `flush()`, `defrag()`, and canonical register bridging remain supported.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
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
```

</details>

<details>
<summary>Latest content</summary>

```markdown
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
```

</details>

### `README-PROGRAM.md`

- Current lines: 63
- Original reachable commit: `cdf4207` 2026-08-22T11:45:34-04:00 dompipe: Add program builder: arbitrary PHP frame, ASM frame, OOP containers, finalize package
- Latest Markdown-touching commit: `9afffe6` 2026-08-22T12:02:16-04:00 dompipe: Add control-flow compiler: while/for/if/else/select lower to JMP/JZ/JNZ bytecode
- Markdown-touching commits for this path: 4
- Latest blame by author: dompipe 63
- Latest blame by commit:
  - `9afffe6` 45 lines dompipe: Add control-flow compiler: while/for/if/else/select lower to JMP/JZ/JNZ bytecode
  - `cdf4207` 10 lines dompipe: Add program builder: arbitrary PHP frame, ASM frame, OOP containers, finalize package
  - `b1cc70b` 5 lines dompipe: Add expression compiler: PHP-like assignments/operators lower to PASM bytecode
  - `ff23341` 3 lines dompipe: Unified bytecode output from containers+ASM; structured error handling

<details>
<summary>Latest line blame</summary>

````markdown
cdf4207d (dompipe 2026-08-22  1) # PASM Program Builder
cdf4207d (dompipe 2026-08-22  2) 
9afffe62 (dompipe 2026-08-22  3) ## Control flow → bytecode
cdf4207d (dompipe 2026-08-22  4) 
9afffe62 (dompipe 2026-08-22  5) Pass **restricted** statement source through `expr()` / `PASMExprCompiler` (not raw PHP files).
cdf4207d (dompipe 2026-08-22  6) 
9afffe62 (dompipe 2026-08-22  7) ### Supported
cdf4207d (dompipe 2026-08-22  8) 
9afffe62 (dompipe 2026-08-22  9) | Construct | Lowers to |
9afffe62 (dompipe 2026-08-22 10) |-----------|-----------|
9afffe62 (dompipe 2026-08-22 11) | `while ($i) { ... }` | label + `CMP`/`JNZ`/`JMP` |
9afffe62 (dompipe 2026-08-22 12) | `for ($k=0; $k != 3; $k++) { ... }` | init + head/step/end labels |
9afffe62 (dompipe 2026-08-22 13) | `if (cond) { ... } else { ... }` | `JZ`/`JNZ` + `JMP` |
9afffe62 (dompipe 2026-08-22 14) | `select ($x) { case 1: ...; default: ...; }` | sequential `CMP`/`JNZ` (also `switch`) |
9afffe62 (dompipe 2026-08-22 15) | `break` / `continue` | `JMP` to end / step of innermost loop |
9afffe62 (dompipe 2026-08-22 16) | assignments, `++`, `+=`, arithmetic, bitwise | as before |
ff233415 (dompipe 2026-08-22 17) 
9afffe62 (dompipe 2026-08-22 18) ### Conditions
ff233415 (dompipe 2026-08-22 19) 
9afffe62 (dompipe 2026-08-22 20) Because the binary ISA only exposes a **zero flag** (`CMP` + `JZ`/`JNZ`):
ff233415 (dompipe 2026-08-22 21) 
9afffe62 (dompipe 2026-08-22 22) - Fully supported: `==`, `!=`, and nonzero truthiness (`while ($i)`).
9afffe62 (dompipe 2026-08-22 23) - **Not** supported on this ISA: `<`, `>`, `<=`, `>=` (no sign flag). Structure counting loops with `++`/`--` and `!=` / nonzero tests instead.
cdf4207d (dompipe 2026-08-22 24) 
9afffe62 (dompipe 2026-08-22 25) ### Not supported
cdf4207d (dompipe 2026-08-22 26) 
9afffe62 (dompipe 2026-08-22 27) | Feature | Alternative |
9afffe62 (dompipe 2026-08-22 28) |---------|-------------|
9afffe62 (dompipe 2026-08-22 29) | `foreach` over arrays/objects | `for ($i=0; $i != n; $i++)` or OOP containers + ASM |
9afffe62 (dompipe 2026-08-22 30) | `do`/`while` | rewrite as `while` |
9afffe62 (dompipe 2026-08-22 31) | `goto` | labels in ASM frame |
9afffe62 (dompipe 2026-08-22 32) | exceptions, `match`, generators | `php()` stage (stays PHP) |
cdf4207d (dompipe 2026-08-22 33) 
9afffe62 (dompipe 2026-08-22 34) ### Example
b1cc70bf (dompipe 2026-08-22 35) 
b1cc70bf (dompipe 2026-08-22 36) ```php
9afffe62 (dompipe 2026-08-22 37) $prog->expr(<<<'SRC'
9afffe62 (dompipe 2026-08-22 38)     $sum = 0;
9afffe62 (dompipe 2026-08-22 39)     $i = 5;
9afffe62 (dompipe 2026-08-22 40)     while ($i) {
9afffe62 (dompipe 2026-08-22 41)         $sum = $sum + $i;
9afffe62 (dompipe 2026-08-22 42)         $i--;
9afffe62 (dompipe 2026-08-22 43)     }
9afffe62 (dompipe 2026-08-22 44)     for ($k = 0; $k != 3; $k++) {
9afffe62 (dompipe 2026-08-22 45)         $sum = $sum + 1;
9afffe62 (dompipe 2026-08-22 46)     }
9afffe62 (dompipe 2026-08-22 47)     select ($k) {
9afffe62 (dompipe 2026-08-22 48)         case 0: $sum = $sum + 100;
9afffe62 (dompipe 2026-08-22 49)         default: $sum = $sum + 1;
9afffe62 (dompipe 2026-08-22 50)     }
9afffe62 (dompipe 2026-08-22 51) SRC);
b1cc70bf (dompipe 2026-08-22 52) 
9afffe62 (dompipe 2026-08-22 53) echo $prog->finalize()->runExpr();
9afffe62 (dompipe 2026-08-22 54) ```
b1cc70bf (dompipe 2026-08-22 55) 
cdf4207d (dompipe 2026-08-22 56) ```bash
9afffe62 (dompipe 2026-08-22 57) php examples/control-flow-bytecode.php
b1cc70bf (dompipe 2026-08-22 58) php examples/expr-to-bytecode.php
cdf4207d (dompipe 2026-08-22 59) ```
9afffe62 (dompipe 2026-08-22 60) 
9afffe62 (dompipe 2026-08-22 61) ### Unified bytecode
9afffe62 (dompipe 2026-08-22 62) 
9afffe62 (dompipe 2026-08-22 63) `finalize()` still merges: container prelude + `expr()` chunks + user `asm()` into `toBytecode()`.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# PASM Program Builder

`pasm-program.php` adds a whole-program layer on top of the existing runtime.

## What it is

| Piece | Role |
|-------|------|
| **Canonical blocks** | Immutable instruction arrays (code identity) |
| **OOP containers** | Vector, Stack, Queue, Deque, Map, Set — hot PHP until `finalize()` |
| **ASM frame** | Free-form PASM assembly → binary bytecode via the existing assembler |
| **PHP frame** | Arbitrary PHP callables — **run as PHP**, not compiled to bytecode |
| **Kernels** | Named extra assembly routines |

## What it is not

There is **no** general PHP-to-bytecode compiler. Arbitrary PHP is registered with `php($name, callable)` and executed with `runPhp($name)`. Only the ASM frame and named kernels become binary bytecode.

## Quick start

```php
require_once __DIR__ . '/pasm-program.php';
use pasm\PASMProgram;

$prog = new PASMProgram();

$prog->block('add-two', [
    ['ADD', 'R2', 'R0', 'R1'],
    ['RET', 'R2'],
]);

$v = $prog->vector([1, 2, 3]);
$v->add(4);

$prog->php('setup', function (PASMProgram $p) {
    // arbitrary PHP: prepare memory, call APIs, mutate containers, etc.
});

$prog->asm(<<<'ASM'
        MOVI ecx 40
        MOVI ah  2
        ADD  adx ecx ah
        RET  adx
ASM);

$prog->runPhp('setup');
echo $prog->runAsm();           // 42

$package = $prog->finalize();   // flush containers, compile ASM/kernels
$package->runAsm();
$package->runPhp('setup');
```

## Example

```bash
php examples/program-php-asm-oop.php
```

## Finalize boundary

`finalize()` materializes every tracked container (`flush` + `loadRegister` into `P0`…), compiles the ASM frame and kernels to bytecode, and returns a `PASMProgramPackage` that can:

- `invoke($block)` — run a canonical block
- `runAsm()` — run main bytecode
- `runKernel($name)` — run a named kernel
- `runPhp($name)` — run a PHP stage
- `describe()` — human summary
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# PASM Program Builder

## Control flow → bytecode

Pass **restricted** statement source through `expr()` / `PASMExprCompiler` (not raw PHP files).

### Supported

| Construct | Lowers to |
|-----------|-----------|
| `while ($i) { ... }` | label + `CMP`/`JNZ`/`JMP` |
| `for ($k=0; $k != 3; $k++) { ... }` | init + head/step/end labels |
| `if (cond) { ... } else { ... }` | `JZ`/`JNZ` + `JMP` |
| `select ($x) { case 1: ...; default: ...; }` | sequential `CMP`/`JNZ` (also `switch`) |
| `break` / `continue` | `JMP` to end / step of innermost loop |
| assignments, `++`, `+=`, arithmetic, bitwise | as before |

### Conditions

Because the binary ISA only exposes a **zero flag** (`CMP` + `JZ`/`JNZ`):

- Fully supported: `==`, `!=`, and nonzero truthiness (`while ($i)`).
- **Not** supported on this ISA: `<`, `>`, `<=`, `>=` (no sign flag). Structure counting loops with `++`/`--` and `!=` / nonzero tests instead.

### Not supported

| Feature | Alternative |
|---------|-------------|
| `foreach` over arrays/objects | `for ($i=0; $i != n; $i++)` or OOP containers + ASM |
| `do`/`while` | rewrite as `while` |
| `goto` | labels in ASM frame |
| exceptions, `match`, generators | `php()` stage (stays PHP) |

### Example

```php
$prog->expr(<<<'SRC'
    $sum = 0;
    $i = 5;
    while ($i) {
        $sum = $sum + $i;
        $i--;
    }
    for ($k = 0; $k != 3; $k++) {
        $sum = $sum + 1;
    }
    select ($k) {
        case 0: $sum = $sum + 100;
        default: $sum = $sum + 1;
    }
SRC);

echo $prog->finalize()->runExpr();
```

```bash
php examples/control-flow-bytecode.php
php examples/expr-to-bytecode.php
```

### Unified bytecode

`finalize()` still merges: container prelude + `expr()` chunks + user `asm()` into `toBytecode()`.
````

</details>

### `README.md`

- Current lines: 53
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `20daf6b` 2026-08-25T20:32:46-04:00 dompipe: Complete jx install: plugin source dir, sequential installs, dual backups, decimals, intro, smart compiler modules
- Markdown-touching commits for this path: 2
- Latest blame by author: dompipe 53
- Latest blame by commit:
  - `20daf6b` 36 lines dompipe: Complete jx install: plugin source dir, sequential installs, dual backups, decimals, intro, smart compiler modules
  - `4b802d6` 17 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

````markdown
4b802d6d (dompipe 2026-08-25  1) # jx (jinx)
4b802d6d (dompipe 2026-08-25  2) 
20daf6bf (dompipe 2026-08-25  3) **Formerly pasm-v2.** Product name **jx**; PASM is the engine.
4b802d6d (dompipe 2026-08-25  4) 
20daf6bf (dompipe 2026-08-25  5) ## Quick start
4b802d6d (dompipe 2026-08-25  6) 
20daf6bf (dompipe 2026-08-25  7) ```bash
20daf6bf (dompipe 2026-08-25  8) # Install required plugins (one-by-one, with pre + full backups)
20daf6bf (dompipe 2026-08-25  9) php jx-install.php install-required
4b802d6d (dompipe 2026-08-25 10) 
20daf6bf (dompipe 2026-08-25 11) # Run
20daf6bf (dompipe 2026-08-25 12) php jx-run.php --print examples/hello.jx
20daf6bf (dompipe 2026-08-25 13) php examples/jx-smoke.php
20daf6bf (dompipe 2026-08-25 14) ```
4b802d6d (dompipe 2026-08-25 15) 
20daf6bf (dompipe 2026-08-25 16) ## Layout
4b802d6d (dompipe 2026-08-25 17) 
20daf6bf (dompipe 2026-08-25 18) | Path | Role |
20daf6bf (dompipe 2026-08-25 19) |------|------|
20daf6bf (dompipe 2026-08-25 20) | `jx.php` | Core runtime (Bag, Task, Page, Book, Delivery, Complex, SmartTable, Sym) |
20daf6bf (dompipe 2026-08-25 21) | `jx-lang.php` / `jx-run.php` | Language engine + executable compiler |
20daf6bf (dompipe 2026-08-25 22) | `plugins/` | **Single source directory** for all module plugins |
20daf6bf (dompipe 2026-08-25 23) | `host/modules/` | Active installed plugins |
20daf6bf (dompipe 2026-08-25 24) | `host/backups/pre/` | Snapshot before each new plugin install |
20daf6bf (dompipe 2026-08-25 25) | `host/backups/full/` | Full install snapshot (restore / redirect) |
20daf6bf (dompipe 2026-08-25 26) | `jx/INTRO.md` | Introduction materials |
20daf6bf (dompipe 2026-08-25 27) | `jx/INSTALL.md` | Install & plugin policy |
20daf6bf (dompipe 2026-08-25 28) | `jx/COMPILER.md` | Compiler pipeline |
4b802d6d (dompipe 2026-08-25 29) 
20daf6bf (dompipe 2026-08-25 30) ## Plugins
4b802d6d (dompipe 2026-08-25 31) 
20daf6bf (dompipe 2026-08-25 32) - Sourced **only** from `plugins/`
20daf6bf (dompipe 2026-08-25 33) - Installed **one at a time**; new installs append **last** after need is assessed
20daf6bf (dompipe 2026-08-25 34) - Dual backup: **pre** (per install) + **full** (total install)
4b802d6d (dompipe 2026-08-25 35) 
20daf6bf (dompipe 2026-08-25 36) ```bash
20daf6bf (dompipe 2026-08-25 37) php jx-install.php list
20daf6bf (dompipe 2026-08-25 38) php jx-install.php install intro
20daf6bf (dompipe 2026-08-25 39) php jx-install.php backup-full
20daf6bf (dompipe 2026-08-25 40) php jx-install.php restore-full <timestamp>
20daf6bf (dompipe 2026-08-25 41) ```
4b802d6d (dompipe 2026-08-25 42) 
20daf6bf (dompipe 2026-08-25 43) ## Includes
4b802d6d (dompipe 2026-08-25 44) 
20daf6bf (dompipe 2026-08-25 45) - Decimals (`plugins/decimals` → `jx\Decimal`)
20daf6bf (dompipe 2026-08-25 46) - Complex, Delivery, const, smart compiler, lang bridge
20daf6bf (dompipe 2026-08-25 47) - Memory law, Books/Bags/Pages, Resistant path
4b802d6d (dompipe 2026-08-25 48) 
20daf6bf (dompipe 2026-08-25 49) See `jx/INTRO.md` for the guided introduction.
4b802d6d (dompipe 2026-08-25 50) 
4b802d6d (dompipe 2026-08-25 51) ---
4b802d6d (dompipe 2026-08-25 52) 
4b802d6d (dompipe 2026-08-25 53) jx — pronounced jinx.
````

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# jx (jinx)

**Formerly pasm-v2.** This repository is the home of **jx** (pronounced *jinx*) — the PHP-derived server-side language and runtime that grows out of PASM.

PASM remains the low-level frame, segment, register, and bytecode engine. **jx** is the language and product name that sits on top of it: Books, Bags, Pages, strict memory law, Delivery, complex numbers, smart table extrusion, and Resistant fallback.

> **Repo rename:** GitHub does not allow automated rename via API from this flow. In GitHub → Settings → General → Repository name, rename `pasm-v2` → `jx` (or `jx-lang`) when you are ready. Until then this tree is the canonical jx source; the old name is legacy.

## What jx is

| Layer | Role |
|-------|------|
| **PASM** (this codebase) | Frames, canonical registers, segmented pages, hot-path containers, bytecode VM, master table, x86 lowering sketches |
| **jx language** | Books, Bags, Pages, Task-as-Bag, `push`, sign/handshake writes, Delivery, `const`, complex, rhetorical natives, smart table maker |
| **Hosting module** | Embeds PHP, loads Books under isolation, server→browser coherent protocol |

## Ontology (jx)

- **Book** — compiled unit (pages, bags, libraries, entry points)
- **Page** — runnable surface in an X11-style memory state (maps onto PASM frames / page segments)
- **Bag** — only mutable container; underwritten capacity; writes only via allowance + sign + handshake
- **Task** — special Bag (execution context + `push` preassignments + `id()`)
- **Delivery** — deep path `parent.child.subchild...` extract/rebind
- **Resistant code** — safe fallback when pure native extrusion is not possible

## Memory law (non-negotiable)

No free memory writes. A write is legal only when:

1. A buffer of allowance is supplied  
2. It is handed to an underwritten bag  
3. Mutation goes through an event handshake  

PASM’s frame-local hot path + explicit canonical boundary (`dirtySegments` / `flush` / `defrag`) is the concrete substrate for this law.

## Map: PASM → jx

| PASM concept | jx name / role |
|--------------|----------------|
| Register frame | Page / Task memory state |
| Segment / page-aligned storage | Bag backing (underwritten region) |
| Hot-path container (Vector, Stack, …) | Bag contents on the fast path |
| `dirtySegments` / `flush` / boundary | Handshake / commit boundary |
| Master table / bytecode ops | Smart table maker rows |
| Program / entry | Book entry / Page spawn |
| Network packet / runtime | Hosting module transport (future protocol) |

## Quick pointers

- Runtime & containers: `pasm-runtime.php`, `pasm-oop-containers.php`, `pasm-canonical*.php`
- Bytecode & assembler: `pasm-bytecode.php`, `pasm-bytecode-optimized.php`
- Master table: `pasm-master-table.php` → evolves into jx smart table
- Language sketches: `pasm-lang*.php`, `pasl/`, `PASL_Language_Manual.md`
- jx design docs: [`jx/`](jx/)

## Status

PASM hot-path work is real and benchmarked. jx language rules, Book/Bag/Page API, Delivery, complex, and edge-case posture are specified under `jx/` and are being integrated into this tree. Perfection is amiss; gaps are tracked in `jx/GAPS.md`.

---

jx — pronounced jinx.
```

</details>

<details>
<summary>Latest content</summary>

````markdown
# jx (jinx)

**Formerly pasm-v2.** Product name **jx**; PASM is the engine.

## Quick start

```bash
# Install required plugins (one-by-one, with pre + full backups)
php jx-install.php install-required

# Run
php jx-run.php --print examples/hello.jx
php examples/jx-smoke.php
```

## Layout

| Path | Role |
|------|------|
| `jx.php` | Core runtime (Bag, Task, Page, Book, Delivery, Complex, SmartTable, Sym) |
| `jx-lang.php` / `jx-run.php` | Language engine + executable compiler |
| `plugins/` | **Single source directory** for all module plugins |
| `host/modules/` | Active installed plugins |
| `host/backups/pre/` | Snapshot before each new plugin install |
| `host/backups/full/` | Full install snapshot (restore / redirect) |
| `jx/INTRO.md` | Introduction materials |
| `jx/INSTALL.md` | Install & plugin policy |
| `jx/COMPILER.md` | Compiler pipeline |

## Plugins

- Sourced **only** from `plugins/`
- Installed **one at a time**; new installs append **last** after need is assessed
- Dual backup: **pre** (per install) + **full** (total install)

```bash
php jx-install.php list
php jx-install.php install intro
php jx-install.php backup-full
php jx-install.php restore-full <timestamp>
```

## Includes

- Decimals (`plugins/decimals` → `jx\Decimal`)
- Complex, Delivery, const, smart compiler, lang bridge
- Memory law, Books/Bags/Pages, Resistant path

See `jx/INTRO.md` for the guided introduction.

---

jx — pronounced jinx.
````

</details>

### `jx/COMPILER.md`

- Current lines: 32
- Original reachable commit: `be8fd24` 2026-08-25T20:25:12-04:00 dompipe: jx executable compiler/interpreter: jx-run.php, JxEngine, .jx source path through PASL bytecode when possible
- Latest Markdown-touching commit: `be8fd24` 2026-08-25T20:25:12-04:00 dompipe: jx executable compiler/interpreter: jx-run.php, JxEngine, .jx source path through PASL bytecode when possible
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 32
- Latest blame by commit:
  - `be8fd24` 32 lines dompipe: jx executable compiler/interpreter: jx-run.php, JxEngine, .jx source path through PASL bytecode when possible

<details>
<summary>Latest line blame</summary>

````markdown
be8fd24c (dompipe 2026-08-25  1) # jx executable compiler
be8fd24c (dompipe 2026-08-25  2) 
be8fd24c (dompipe 2026-08-25  3) ## Commands
be8fd24c (dompipe 2026-08-25  4) 
be8fd24c (dompipe 2026-08-25  5) ```bash
be8fd24c (dompipe 2026-08-25  6) # Interpret .jx (Bags, Tasks, Delivery, …)
be8fd24c (dompipe 2026-08-25  7) php jx-run.php --print examples/hello.jx
be8fd24c (dompipe 2026-08-25  8) 
be8fd24c (dompipe 2026-08-25  9) # PASL arithmetic → bytecode VM (same stack as pasm-run.php)
be8fd24c (dompipe 2026-08-25 10) php jx-run.php --print examples/arith.pasl
be8fd24c (dompipe 2026-08-25 11) php jx-run.php -o out.pbc examples/arith.pasl
be8fd24c (dompipe 2026-08-25 12) php jx-run.php --print out.pbc
be8fd24c (dompipe 2026-08-25 13) 
be8fd24c (dompipe 2026-08-25 14) # Inline
be8fd24c (dompipe 2026-08-25 15) php jx-run.php --print -c 'bag = Bag.underwrite(64); ref = bag.sign("a"); bag.set(1).commit(ref);'
be8fd24c (dompipe 2026-08-25 16) php jx-run.php --print -c '$x = 1 + 2 * 3;'
be8fd24c (dompipe 2026-08-25 17) ```
be8fd24c (dompipe 2026-08-25 18) 
be8fd24c (dompipe 2026-08-25 19) ## Pipeline
be8fd24c (dompipe 2026-08-25 20) 
be8fd24c (dompipe 2026-08-25 21) 1. **jx-run.php** — CLI entry (executable compiler driver)
be8fd24c (dompipe 2026-08-25 22) 2. **JxEngine** (`jx-lang.php`) — parses jx statements; executes Bag/Task/Book/Delivery on `jx.php`
be8fd24c (dompipe 2026-08-25 23) 3. **PASL Engine** (`pasm-lang-engine.php`) — pure arithmetic / complex / control flow → assembler → bytecode VM
be8fd24c (dompipe 2026-08-25 24) 4. **SmartTable** — records extrusion mode (native vs Resistant) for known methods
be8fd24c (dompipe 2026-08-25 25) 
be8fd24c (dompipe 2026-08-25 26) `.jx` programs that use bags are **interpreted** under the memory law.  
be8fd24c (dompipe 2026-08-25 27) Pure PASL fragments are **compiled** to bytecode and run on the PASM VM — the same executable compiler path as `pasm-run.php`.
be8fd24c (dompipe 2026-08-25 28) 
be8fd24c (dompipe 2026-08-25 29) ## Relation to pasm-run.php
be8fd24c (dompipe 2026-08-25 30) 
be8fd24c (dompipe 2026-08-25 31) `pasm-run.php` remains the PASL-only runner.  
be8fd24c (dompipe 2026-08-25 32) `jx-run.php` is the jx product entry: it understands `.jx` and delegates lowerable code to the same compiler/VM.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# jx executable compiler

## Commands

```bash
# Interpret .jx (Bags, Tasks, Delivery, …)
php jx-run.php --print examples/hello.jx

# PASL arithmetic → bytecode VM (same stack as pasm-run.php)
php jx-run.php --print examples/arith.pasl
php jx-run.php -o out.pbc examples/arith.pasl
php jx-run.php --print out.pbc

# Inline
php jx-run.php --print -c 'bag = Bag.underwrite(64); ref = bag.sign("a"); bag.set(1).commit(ref);'
php jx-run.php --print -c '$x = 1 + 2 * 3;'
```

## Pipeline

1. **jx-run.php** — CLI entry (executable compiler driver)
2. **JxEngine** (`jx-lang.php`) — parses jx statements; executes Bag/Task/Book/Delivery on `jx.php`
3. **PASL Engine** (`pasm-lang-engine.php`) — pure arithmetic / complex / control flow → assembler → bytecode VM
4. **SmartTable** — records extrusion mode (native vs Resistant) for known methods

`.jx` programs that use bags are **interpreted** under the memory law.  
Pure PASL fragments are **compiled** to bytecode and run on the PASM VM — the same executable compiler path as `pasm-run.php`.

## Relation to pasm-run.php

`pasm-run.php` remains the PASL-only runner.  
`jx-run.php` is the jx product entry: it understands `.jx` and delegates lowerable code to the same compiler/VM.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# jx executable compiler

## Commands

```bash
# Interpret .jx (Bags, Tasks, Delivery, …)
php jx-run.php --print examples/hello.jx

# PASL arithmetic → bytecode VM (same stack as pasm-run.php)
php jx-run.php --print examples/arith.pasl
php jx-run.php -o out.pbc examples/arith.pasl
php jx-run.php --print out.pbc

# Inline
php jx-run.php --print -c 'bag = Bag.underwrite(64); ref = bag.sign("a"); bag.set(1).commit(ref);'
php jx-run.php --print -c '$x = 1 + 2 * 3;'
```

## Pipeline

1. **jx-run.php** — CLI entry (executable compiler driver)
2. **JxEngine** (`jx-lang.php`) — parses jx statements; executes Bag/Task/Book/Delivery on `jx.php`
3. **PASL Engine** (`pasm-lang-engine.php`) — pure arithmetic / complex / control flow → assembler → bytecode VM
4. **SmartTable** — records extrusion mode (native vs Resistant) for known methods

`.jx` programs that use bags are **interpreted** under the memory law.  
Pure PASL fragments are **compiled** to bytecode and run on the PASM VM — the same executable compiler path as `pasm-run.php`.

## Relation to pasm-run.php

`pasm-run.php` remains the PASL-only runner.  
`jx-run.php` is the jx product entry: it understands `.jx` and delegates lowerable code to the same compiler/VM.
````

</details>

### `jx/CONVERSATION_LOG.md`

- Current lines: 14
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 14
- Latest blame by commit:
  - `4b802d6` 14 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

```markdown
4b802d6d (dompipe 2026-08-25  1) # Design conversation log (integrated)
4b802d6d (dompipe 2026-08-25  2) 
4b802d6d (dompipe 2026-08-25  3) Summary of the arc that produced jx on top of PASM:
4b802d6d (dompipe 2026-08-25  4) 
4b802d6d (dompipe 2026-08-25  5) 1. Axis/yaxis tables → ingest buffer idea; rename language to **jx** (jinx).
4b802d6d (dompipe 2026-08-25  6) 2. Assembly-friendly rhetorical natives + symbolic constants.
4b802d6d (dompipe 2026-08-25  7) 3. Hard memory law: no writes without allowance + underwritten bag + handshake; Docker-like isolation.
4b802d6d (dompipe 2026-08-25  8) 4. Bag/Task surface: sign, quotient, push (ex-preassign), tight vs verbose lowering.
4b802d6d (dompipe 2026-08-25  9) 5. X11-like pages → multitasking TaskHandler; Task is special Bag.
4b802d6d (dompipe 2026-08-25 10) 6. Smart table maker + Resistant code; AI interpreter should table-drive to asm.
4b802d6d (dompipe 2026-08-25 11) 7. Pillars A–F: X11 page staging, Books, Delivery, const, complex, PHP hosting module.
4b802d6d (dompipe 2026-08-25 12) 8. Edge-case suite; perfection is amiss — gaps tracked in GAPS.md.
4b802d6d (dompipe 2026-08-25 13) 
4b802d6d (dompipe 2026-08-25 14) **Integration decision:** all of the above lands in **pasm-v2** (this repo), not a separate greenfield tree. Product name is jx; PASM is the engine.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# Design conversation log (integrated)

Summary of the arc that produced jx on top of PASM:

1. Axis/yaxis tables → ingest buffer idea; rename language to **jx** (jinx).
2. Assembly-friendly rhetorical natives + symbolic constants.
3. Hard memory law: no writes without allowance + underwritten bag + handshake; Docker-like isolation.
4. Bag/Task surface: sign, quotient, push (ex-preassign), tight vs verbose lowering.
5. X11-like pages → multitasking TaskHandler; Task is special Bag.
6. Smart table maker + Resistant code; AI interpreter should table-drive to asm.
7. Pillars A–F: X11 page staging, Books, Delivery, const, complex, PHP hosting module.
8. Edge-case suite; perfection is amiss — gaps tracked in GAPS.md.

**Integration decision:** all of the above lands in **pasm-v2** (this repo), not a separate greenfield tree. Product name is jx; PASM is the engine.
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# Design conversation log (integrated)

Summary of the arc that produced jx on top of PASM:

1. Axis/yaxis tables → ingest buffer idea; rename language to **jx** (jinx).
2. Assembly-friendly rhetorical natives + symbolic constants.
3. Hard memory law: no writes without allowance + underwritten bag + handshake; Docker-like isolation.
4. Bag/Task surface: sign, quotient, push (ex-preassign), tight vs verbose lowering.
5. X11-like pages → multitasking TaskHandler; Task is special Bag.
6. Smart table maker + Resistant code; AI interpreter should table-drive to asm.
7. Pillars A–F: X11 page staging, Books, Delivery, const, complex, PHP hosting module.
8. Edge-case suite; perfection is amiss — gaps tracked in GAPS.md.

**Integration decision:** all of the above lands in **pasm-v2** (this repo), not a separate greenfield tree. Product name is jx; PASM is the engine.
```

</details>

### `jx/GAPS.md`

- Current lines: 25
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 25
- Latest blame by commit:
  - `4b802d6` 25 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

```markdown
4b802d6d (dompipe 2026-08-25  1) # Gaps — perfection is amiss
4b802d6d (dompipe 2026-08-25  2) 
4b802d6d (dompipe 2026-08-25  3) ## High
4b802d6d (dompipe 2026-08-25  4) 
4b802d6d (dompipe 2026-08-25  5) 1. Handshake protocol detail  
4b802d6d (dompipe 2026-08-25  6) 2. RefSign unforgeability  
4b802d6d (dompipe 2026-08-25  7) 3. Ref lifetime (auto vs explicit unsign)  
4b802d6d (dompipe 2026-08-25  8) 4. Scheduling policy  
4b802d6d (dompipe 2026-08-25  9) 5. Server→browser protocol  
4b802d6d (dompipe 2026-08-25 10) 
4b802d6d (dompipe 2026-08-25 11) ## Medium
4b802d6d (dompipe 2026-08-25 12) 
4b802d6d (dompipe 2026-08-25 13) 6. Book versioning / hot reload  
4b802d6d (dompipe 2026-08-25 14) 7. Error model vs Resistant  
4b802d6d (dompipe 2026-08-25 15) 8. Const propagation through Delivery/complex  
4b802d6d (dompipe 2026-08-25 16) 9. PHP↔jx crossing rules  
4b802d6d (dompipe 2026-08-25 17) 10. Complex-in-Bag accounting  
4b802d6d (dompipe 2026-08-25 18) 
4b802d6d (dompipe 2026-08-25 19) ## Process
4b802d6d (dompipe 2026-08-25 20) 
4b802d6d (dompipe 2026-08-25 21) 11. One-shot sign-and-write sugar  
4b802d6d (dompipe 2026-08-25 22) 12. AI interpreter + live state coherence  
4b802d6d (dompipe 2026-08-25 23) 13. Meta-tests for Resistant markers  
4b802d6d (dompipe 2026-08-25 24) 
4b802d6d (dompipe 2026-08-25 25) Close a gap only when SPEC/docs + tests are updated.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# Gaps — perfection is amiss

## High

1. Handshake protocol detail  
2. RefSign unforgeability  
3. Ref lifetime (auto vs explicit unsign)  
4. Scheduling policy  
5. Server→browser protocol  

## Medium

6. Book versioning / hot reload  
7. Error model vs Resistant  
8. Const propagation through Delivery/complex  
9. PHP↔jx crossing rules  
10. Complex-in-Bag accounting  

## Process

11. One-shot sign-and-write sugar  
12. AI interpreter + live state coherence  
13. Meta-tests for Resistant markers  

Close a gap only when SPEC/docs + tests are updated.
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# Gaps — perfection is amiss

## High

1. Handshake protocol detail  
2. RefSign unforgeability  
3. Ref lifetime (auto vs explicit unsign)  
4. Scheduling policy  
5. Server→browser protocol  

## Medium

6. Book versioning / hot reload  
7. Error model vs Resistant  
8. Const propagation through Delivery/complex  
9. PHP↔jx crossing rules  
10. Complex-in-Bag accounting  

## Process

11. One-shot sign-and-write sugar  
12. AI interpreter + live state coherence  
13. Meta-tests for Resistant markers  

Close a gap only when SPEC/docs + tests are updated.
```

</details>

### `jx/INSTALL.md`

- Current lines: 49
- Original reachable commit: `20daf6b` 2026-08-25T20:32:46-04:00 dompipe: Complete jx install: plugin source dir, sequential installs, dual backups, decimals, intro, smart compiler modules
- Latest Markdown-touching commit: `ca14210` 2026-08-25T20:36:37-04:00 dompipe: Plugin allow-gate: must target windows, mac, linux, and web (jx) before install
- Markdown-touching commits for this path: 2
- Latest blame by author: dompipe 49
- Latest blame by commit:
  - `ca14210` 27 lines dompipe: Plugin allow-gate: must target windows, mac, linux, and web (jx) before install
  - `20daf6b` 22 lines dompipe: Complete jx install: plugin source dir, sequential installs, dual backups, decimals, intro, smart compiler modules

<details>
<summary>Latest line blame</summary>

````markdown
20daf6bf (dompipe 2026-08-25  1) # jx install & plugin policy
20daf6bf (dompipe 2026-08-25  2) 
20daf6bf (dompipe 2026-08-25  3) ## Single source directory
20daf6bf (dompipe 2026-08-25  4) 
20daf6bf (dompipe 2026-08-25  5) ```
20daf6bf (dompipe 2026-08-25  6) plugins/                 ← only source of module plugins
ca142103 (dompipe 2026-08-25  7)   catalog.json
ca142103 (dompipe 2026-08-25  8)   TARGETS.md             ← windows / mac / linux / web allow gate
ca142103 (dompipe 2026-08-25  9)   core/ decimals/ complex/ delivery/ smart-compiler/ const/ lang/ intro/
20daf6bf (dompipe 2026-08-25 10) ```
20daf6bf (dompipe 2026-08-25 11) 
20daf6bf (dompipe 2026-08-25 12) ```
20daf6bf (dompipe 2026-08-25 13) host/
ca142103 (dompipe 2026-08-25 14)   modules/
ca142103 (dompipe 2026-08-25 15)   state.json
ca142103 (dompipe 2026-08-25 16)   backups/pre/<ts>/
ca142103 (dompipe 2026-08-25 17)   backups/full/<ts>/
20daf6bf (dompipe 2026-08-25 18) ```
20daf6bf (dompipe 2026-08-25 19) 
ca142103 (dompipe 2026-08-25 20) ## Allow gate (mandatory)
20daf6bf (dompipe 2026-08-25 21) 
ca142103 (dompipe 2026-08-25 22) A plugin is **allowed to install only if** it declares and passes compile/verify for:
20daf6bf (dompipe 2026-08-25 23) 
ca142103 (dompipe 2026-08-25 24) - **windows**
ca142103 (dompipe 2026-08-25 25) - **mac**
ca142103 (dompipe 2026-08-25 26) - **linux**
ca142103 (dompipe 2026-08-25 27) - **web** (jx hosting / server–browser path)
20daf6bf (dompipe 2026-08-25 28) 
20daf6bf (dompipe 2026-08-25 29) ```bash
ca142103 (dompipe 2026-08-25 30) php jx-install.php check-targets
ca142103 (dompipe 2026-08-25 31) php jx-install.php check-targets decimals
ca142103 (dompipe 2026-08-25 32) ```
20daf6bf (dompipe 2026-08-25 33) 
ca142103 (dompipe 2026-08-25 34) `install` and `install-required` run this gate first. Failure → install denied.
20daf6bf (dompipe 2026-08-25 35) 
ca142103 (dompipe 2026-08-25 36) Details: `plugins/TARGETS.md`.
20daf6bf (dompipe 2026-08-25 37) 
ca142103 (dompipe 2026-08-25 38) ## Other rules
20daf6bf (dompipe 2026-08-25 39) 
ca142103 (dompipe 2026-08-25 40) 1. Plugins only from `plugins/`.
ca142103 (dompipe 2026-08-25 41) 2. Install one at a time; new plugins append last.
ca142103 (dompipe 2026-08-25 42) 3. Pre-backup before each install; full backup of total install for restore/redirect.
20daf6bf (dompipe 2026-08-25 43) 
ca142103 (dompipe 2026-08-25 44) ```bash
ca142103 (dompipe 2026-08-25 45) php jx-install.php install-required
ca142103 (dompipe 2026-08-25 46) php jx-install.php install intro
ca142103 (dompipe 2026-08-25 47) php jx-install.php backup-full
ca142103 (dompipe 2026-08-25 48) php jx-install.php restore-full <timestamp>
20daf6bf (dompipe 2026-08-25 49) ```
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# jx install & plugin policy

## Single source directory

```
plugins/                 ← only source of module plugins
  catalog.json           ← ordered list of available plugins
  core/
  decimals/
  complex/
  delivery/
  smart-compiler/
  const/
  lang/
  intro/
```

After install, the host space holds compiled/active copies:

```
host/
  modules/               ← active plugins (order = install order)
  state.json             ← what is installed
  backups/
    pre/<timestamp>/     ← snapshot before each new plugin install
    full/<timestamp>/    ← complete install snapshot (restore / redirect target)
```

## Rules

1. Plugins are taken **only** from `plugins/`.
2. Install **one plugin at a time**.
3. **New** plugins are always appended **last** (after the host assesses need).
4. **Before** each new install: copy current `host/modules` (+ state) → `host/backups/pre/<ts>/`.
5. **Full backup**: on demand or after a successful batch, copy total install → `host/backups/full/<ts>/`.
6. Restore / redirect: point the host at a `full` backup directory to recover uptime without rebuilding from scratch.

## Commands

```bash
# List catalog vs installed
php jx-install.php list

# Install required plugins in catalog order (one-by-one, with pre backups)
php jx-install.php install-required

# Install one optional/extra plugin (appended last)
php jx-install.php install intro

# Full backup of current host install
php jx-install.php backup-full

# Restore modules from a full backup id
php jx-install.php restore-full <timestamp>

# Show status
php jx-install.php status
```

## Smart compiler

The `smart-compiler` plugin activates `Jx::table()` extrusion. It is a required plugin and installs with the core set.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# jx install & plugin policy

## Single source directory

```
plugins/                 ← only source of module plugins
  catalog.json
  TARGETS.md             ← windows / mac / linux / web allow gate
  core/ decimals/ complex/ delivery/ smart-compiler/ const/ lang/ intro/
```

```
host/
  modules/
  state.json
  backups/pre/<ts>/
  backups/full/<ts>/
```

## Allow gate (mandatory)

A plugin is **allowed to install only if** it declares and passes compile/verify for:

- **windows**
- **mac**
- **linux**
- **web** (jx hosting / server–browser path)

```bash
php jx-install.php check-targets
php jx-install.php check-targets decimals
```

`install` and `install-required` run this gate first. Failure → install denied.

Details: `plugins/TARGETS.md`.

## Other rules

1. Plugins only from `plugins/`.
2. Install one at a time; new plugins append last.
3. Pre-backup before each install; full backup of total install for restore/redirect.

```bash
php jx-install.php install-required
php jx-install.php install intro
php jx-install.php backup-full
php jx-install.php restore-full <timestamp>
```
````

</details>

### `jx/INTEGRATION.md`

- Current lines: 35
- Original reachable commit: `a30639b` 2026-08-25T20:20:29-04:00 dompipe: Realize jx as one code construct on PASM: Bag/Task/Page/Book, memory law, Delivery, smart table bridge
- Latest Markdown-touching commit: `a30639b` 2026-08-25T20:20:29-04:00 dompipe: Realize jx as one code construct on PASM: Bag/Task/Page/Book, memory law, Delivery, smart table bridge
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 35
- Latest blame by commit:
  - `a30639b` 35 lines dompipe: Realize jx as one code construct on PASM: Bag/Task/Page/Book, memory law, Delivery, smart table bridge

<details>
<summary>Latest line blame</summary>

````markdown
a30639b9 (dompipe 2026-08-25  1) # jx realized construct
a30639b9 (dompipe 2026-08-25  2) 
a30639b9 (dompipe 2026-08-25  3) ## Entry
a30639b9 (dompipe 2026-08-25  4) 
a30639b9 (dompipe 2026-08-25  5) ```php
a30639b9 (dompipe 2026-08-25  6) require_once __DIR__ . '/jx.php';  // from repo root: require 'jx.php';
a30639b9 (dompipe 2026-08-25  7) 
a30639b9 (dompipe 2026-08-25  8) use jx\Jx;
a30639b9 (dompipe 2026-08-25  9) ```
a30639b9 (dompipe 2026-08-25 10) 
a30639b9 (dompipe 2026-08-25 11) ## What `jx.php` is
a30639b9 (dompipe 2026-08-25 12) 
a30639b9 (dompipe 2026-08-25 13) One file mass that implements:
a30639b9 (dompipe 2026-08-25 14) 
a30639b9 (dompipe 2026-08-25 15) - `Bag` — underwrite, sign, unsign, quotient, set→commit(ref) handshake, push, tell/pass
a30639b9 (dompipe 2026-08-25 16) - `Task` extends `Bag` — named task identity
a30639b9 (dompipe 2026-08-25 17) - `Page` — spawn + run around a Task
a30639b9 (dompipe 2026-08-25 18) - `Book` — quota-isolated registry of bags/pages
a30639b9 (dompipe 2026-08-25 19) - `Delivery` — deep extract/rebind
a30639b9 (dompipe 2026-08-25 20) - `Complex` — first-class complex
a30639b9 (dompipe 2026-08-25 21) - `SmartTable` — method catalogue + extrude(native|resistant)
a30639b9 (dompipe 2026-08-25 22) - `Sym` — symbolic OS/asm constants
a30639b9 (dompipe 2026-08-25 23) - `Jx` facade
a30639b9 (dompipe 2026-08-25 24) 
a30639b9 (dompipe 2026-08-25 25) PASM files in this repo remain the engine (bytecode, frames, master table). jx is the improved product surface renamed onto that trail.
a30639b9 (dompipe 2026-08-25 26) 
a30639b9 (dompipe 2026-08-25 27) ## Run smoke
a30639b9 (dompipe 2026-08-25 28) 
a30639b9 (dompipe 2026-08-25 29) ```bash
a30639b9 (dompipe 2026-08-25 30) php examples/jx-smoke.php
a30639b9 (dompipe 2026-08-25 31) ```
a30639b9 (dompipe 2026-08-25 32) 
a30639b9 (dompipe 2026-08-25 33) ## Rename
a30639b9 (dompipe 2026-08-25 34) 
a30639b9 (dompipe 2026-08-25 35) GitHub repo may still be `pasm-v2`; product name is **jx**. Rename the repo in Settings when ready.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# jx realized construct

## Entry

```php
require_once __DIR__ . '/jx.php';  // from repo root: require 'jx.php';

use jx\Jx;
```

## What `jx.php` is

One file mass that implements:

- `Bag` — underwrite, sign, unsign, quotient, set→commit(ref) handshake, push, tell/pass
- `Task` extends `Bag` — named task identity
- `Page` — spawn + run around a Task
- `Book` — quota-isolated registry of bags/pages
- `Delivery` — deep extract/rebind
- `Complex` — first-class complex
- `SmartTable` — method catalogue + extrude(native|resistant)
- `Sym` — symbolic OS/asm constants
- `Jx` facade

PASM files in this repo remain the engine (bytecode, frames, master table). jx is the improved product surface renamed onto that trail.

## Run smoke

```bash
php examples/jx-smoke.php
```

## Rename

GitHub repo may still be `pasm-v2`; product name is **jx**. Rename the repo in Settings when ready.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# jx realized construct

## Entry

```php
require_once __DIR__ . '/jx.php';  // from repo root: require 'jx.php';

use jx\Jx;
```

## What `jx.php` is

One file mass that implements:

- `Bag` — underwrite, sign, unsign, quotient, set→commit(ref) handshake, push, tell/pass
- `Task` extends `Bag` — named task identity
- `Page` — spawn + run around a Task
- `Book` — quota-isolated registry of bags/pages
- `Delivery` — deep extract/rebind
- `Complex` — first-class complex
- `SmartTable` — method catalogue + extrude(native|resistant)
- `Sym` — symbolic OS/asm constants
- `Jx` facade

PASM files in this repo remain the engine (bytecode, frames, master table). jx is the improved product surface renamed onto that trail.

## Run smoke

```bash
php examples/jx-smoke.php
```

## Rename

GitHub repo may still be `pasm-v2`; product name is **jx**. Rename the repo in Settings when ready.
````

</details>

### `jx/INTRO.md`

- Current lines: 46
- Original reachable commit: `20daf6b` 2026-08-25T20:32:46-04:00 dompipe: Complete jx install: plugin source dir, sequential installs, dual backups, decimals, intro, smart compiler modules
- Latest Markdown-touching commit: `20daf6b` 2026-08-25T20:32:46-04:00 dompipe: Complete jx install: plugin source dir, sequential installs, dual backups, decimals, intro, smart compiler modules
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 46
- Latest blame by commit:
  - `20daf6b` 46 lines dompipe: Complete jx install: plugin source dir, sequential installs, dual backups, decimals, intro, smart compiler modules

<details>
<summary>Latest line blame</summary>

````markdown
20daf6bf (dompipe 2026-08-25  1) # Introduction to jx (jinx)
20daf6bf (dompipe 2026-08-25  2) 
20daf6bf (dompipe 2026-08-25  3) **jx** is a PHP-derived server-side language and runtime built on the PASM engine.
20daf6bf (dompipe 2026-08-25  4) 
20daf6bf (dompipe 2026-08-25  5) ## What you get after install
20daf6bf (dompipe 2026-08-25  6) 
20daf6bf (dompipe 2026-08-25  7) | Piece | Purpose |
20daf6bf (dompipe 2026-08-25  8) |-------|---------|
20daf6bf (dompipe 2026-08-25  9) | **Bag** | Only mutable memory; underwritten capacity; writes via sign + handshake |
20daf6bf (dompipe 2026-08-25 10) | **Task / Page / Book** | Execution and packaging (X11-like pages) |
20daf6bf (dompipe 2026-08-25 11) | **Decimal** | Fixed-scale decimal arithmetic |
20daf6bf (dompipe 2026-08-25 12) | **Complex** | `3+4i` style complex numbers |
20daf6bf (dompipe 2026-08-25 13) | **Delivery** | Deep path `a.b.c` extract/rebind |
20daf6bf (dompipe 2026-08-25 14) | **const** | Immutable bindings |
20daf6bf (dompipe 2026-08-25 15) | **Smart compiler** | Method table → native or Resistant code |
20daf6bf (dompipe 2026-08-25 16) | **jx-run.php** | Executable compiler / interpreter |
20daf6bf (dompipe 2026-08-25 17) 
20daf6bf (dompipe 2026-08-25 18) ## Memory law
20daf6bf (dompipe 2026-08-25 19) 
20daf6bf (dompipe 2026-08-25 20) No free writes. Only:
20daf6bf (dompipe 2026-08-25 21) 
20daf6bf (dompipe 2026-08-25 22) 1. buffer of allowance  
20daf6bf (dompipe 2026-08-25 23) 2. underwritten bag  
20daf6bf (dompipe 2026-08-25 24) 3. event handshake (`set` → `commit(ref)`)
20daf6bf (dompipe 2026-08-25 25) 
20daf6bf (dompipe 2026-08-25 26) `quotient()` reports remaining capacity so overflows fail closed.
20daf6bf (dompipe 2026-08-25 27) 
20daf6bf (dompipe 2026-08-25 28) ## First program
20daf6bf (dompipe 2026-08-25 29) 
20daf6bf (dompipe 2026-08-25 30) ```jx
20daf6bf (dompipe 2026-08-25 31) bag = Bag.underwrite(256);
20daf6bf (dompipe 2026-08-25 32) ref = bag.sign("msg");
20daf6bf (dompipe 2026-08-25 33) bag.set("hello").commit(ref);
20daf6bf (dompipe 2026-08-25 34) ```
20daf6bf (dompipe 2026-08-25 35) 
20daf6bf (dompipe 2026-08-25 36) ```bash
20daf6bf (dompipe 2026-08-25 37) php jx-run.php --print examples/hello.jx
20daf6bf (dompipe 2026-08-25 38) ```
20daf6bf (dompipe 2026-08-25 39) 
20daf6bf (dompipe 2026-08-25 40) ## Plugins
20daf6bf (dompipe 2026-08-25 41) 
20daf6bf (dompipe 2026-08-25 42) All modules live under **`plugins/`** (one source directory).  
20daf6bf (dompipe 2026-08-25 43) The host installs them **one at a time**. New plugins are added **last**, after you assess need.  
20daf6bf (dompipe 2026-08-25 44) Each install is preceded by a **pre-install backup**; a **full backup** of the total install can be restored or redirected for uptime-friendly admin.
20daf6bf (dompipe 2026-08-25 45) 
20daf6bf (dompipe 2026-08-25 46) See `jx-install.php` and `jx/INSTALL.md`.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# Introduction to jx (jinx)

**jx** is a PHP-derived server-side language and runtime built on the PASM engine.

## What you get after install

| Piece | Purpose |
|-------|---------|
| **Bag** | Only mutable memory; underwritten capacity; writes via sign + handshake |
| **Task / Page / Book** | Execution and packaging (X11-like pages) |
| **Decimal** | Fixed-scale decimal arithmetic |
| **Complex** | `3+4i` style complex numbers |
| **Delivery** | Deep path `a.b.c` extract/rebind |
| **const** | Immutable bindings |
| **Smart compiler** | Method table → native or Resistant code |
| **jx-run.php** | Executable compiler / interpreter |

## Memory law

No free writes. Only:

1. buffer of allowance  
2. underwritten bag  
3. event handshake (`set` → `commit(ref)`)

`quotient()` reports remaining capacity so overflows fail closed.

## First program

```jx
bag = Bag.underwrite(256);
ref = bag.sign("msg");
bag.set("hello").commit(ref);
```

```bash
php jx-run.php --print examples/hello.jx
```

## Plugins

All modules live under **`plugins/`** (one source directory).  
The host installs them **one at a time**. New plugins are added **last**, after you assess need.  
Each install is preceded by a **pre-install backup**; a **full backup** of the total install can be restored or redirected for uptime-friendly admin.

See `jx-install.php` and `jx/INSTALL.md`.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# Introduction to jx (jinx)

**jx** is a PHP-derived server-side language and runtime built on the PASM engine.

## What you get after install

| Piece | Purpose |
|-------|---------|
| **Bag** | Only mutable memory; underwritten capacity; writes via sign + handshake |
| **Task / Page / Book** | Execution and packaging (X11-like pages) |
| **Decimal** | Fixed-scale decimal arithmetic |
| **Complex** | `3+4i` style complex numbers |
| **Delivery** | Deep path `a.b.c` extract/rebind |
| **const** | Immutable bindings |
| **Smart compiler** | Method table → native or Resistant code |
| **jx-run.php** | Executable compiler / interpreter |

## Memory law

No free writes. Only:

1. buffer of allowance  
2. underwritten bag  
3. event handshake (`set` → `commit(ref)`)

`quotient()` reports remaining capacity so overflows fail closed.

## First program

```jx
bag = Bag.underwrite(256);
ref = bag.sign("msg");
bag.set("hello").commit(ref);
```

```bash
php jx-run.php --print examples/hello.jx
```

## Plugins

All modules live under **`plugins/`** (one source directory).  
The host installs them **one at a time**. New plugins are added **last**, after you assess need.  
Each install is preceded by a **pre-install backup**; a **full backup** of the total install can be restored or redirected for uptime-friendly admin.

See `jx-install.php` and `jx/INSTALL.md`.
````

</details>

### `jx/PASM_MAP.md`

- Current lines: 41
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 41
- Latest blame by commit:
  - `4b802d6` 41 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

```markdown
4b802d6d (dompipe 2026-08-25  1) # PASM ↔ jx implementation map
4b802d6d (dompipe 2026-08-25  2) 
4b802d6d (dompipe 2026-08-25  3) Use this when changing code in this repo so the engine and the language stay aligned.
4b802d6d (dompipe 2026-08-25  4) 
4b802d6d (dompipe 2026-08-25  5) ## Frames and Pages
4b802d6d (dompipe 2026-08-25  6) 
4b802d6d (dompipe 2026-08-25  7) - `PASMRegisterFrame` / frame pool → **Page** (and Task) memory state
4b802d6d (dompipe 2026-08-25  8) - Frame-local hot arrays in OOP containers → Bag hot path (no segment tax until boundary)
4b802d6d (dompipe 2026-08-25  9) - `M_FRAME` and frame id → `Task.id()` / Page identity
4b802d6d (dompipe 2026-08-25 10) 
4b802d6d (dompipe 2026-08-25 11) ## Segments and Bags
4b802d6d (dompipe 2026-08-25 12) 
4b802d6d (dompipe 2026-08-25 13) - Page-aligned segments (`SEGNEW` / segmented storage) → **Bag** underwritten capacity
4b802d6d (dompipe 2026-08-25 14) - `dirtySegments()` / `clearDirty()` / `flush()` / `defrag()` → **handshake / commit** boundary
4b802d6d (dompipe 2026-08-25 15) - Write only at boundary or through signed path → jx memory law
4b802d6d (dompipe 2026-08-25 16) 
4b802d6d (dompipe 2026-08-25 17) ## Containers
4b802d6d (dompipe 2026-08-25 18) 
4b802d6d (dompipe 2026-08-25 19) - `PASMList` / Vector, Stack, Queue, Deque, Map, Set → Bag-resident structures
4b802d6d (dompipe 2026-08-25 20) - Hot-path mutation stays frame-local; canonical image is write-back → matches “no free write; commit at boundary”
4b802d6d (dompipe 2026-08-25 21) 
4b802d6d (dompipe 2026-08-25 22) ## Tables and compilation
4b802d6d (dompipe 2026-08-25 23) 
4b802d6d (dompipe 2026-08-25 24) - `pasm-master-table.php` → seed for **smart table maker** (`jx/smart-table.md`)
4b802d6d (dompipe 2026-08-25 25) - `pasm-bytecode*.php` / assembler → native / Resistant extrusion targets
4b802d6d (dompipe 2026-08-25 26) - `pasm-lang-compiler.php` / PASL → front door for jx surface syntax over time
4b802d6d (dompipe 2026-08-25 27) 
4b802d6d (dompipe 2026-08-25 28) ## Program and Book
4b802d6d (dompipe 2026-08-25 29) 
4b802d6d (dompipe 2026-08-25 30) - `pasm-program.php` / run entry → **Book** load + Page spawn
4b802d6d (dompipe 2026-08-25 31) - Isolation today is frame-level; Book-level quota is the jx hosting-module target
4b802d6d (dompipe 2026-08-25 32) 
4b802d6d (dompipe 2026-08-25 33) ## Rename discipline
4b802d6d (dompipe 2026-08-25 34) 
4b802d6d (dompipe 2026-08-25 35) - User-facing docs and new APIs: prefer **jx** names (Book, Bag, Page, Delivery, quotient, push, Resistant)
4b802d6d (dompipe 2026-08-25 36) - Internal PHP class names may stay `PASM*` until a deliberate rename pass
4b802d6d (dompipe 2026-08-25 37) - New files for language surface: prefer `jx-*.php` or under `jx/` / `pasl/` as appropriate
4b802d6d (dompipe 2026-08-25 38) 
4b802d6d (dompipe 2026-08-25 39) ## Resistant code
4b802d6d (dompipe 2026-08-25 40) 
4b802d6d (dompipe 2026-08-25 41) When a lowering cannot satisfy memory law + pure native template, emit the safe path and mark it. Edge cases: `jx/edge-cases.md`.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# PASM ↔ jx implementation map

Use this when changing code in this repo so the engine and the language stay aligned.

## Frames and Pages

- `PASMRegisterFrame` / frame pool → **Page** (and Task) memory state
- Frame-local hot arrays in OOP containers → Bag hot path (no segment tax until boundary)
- `M_FRAME` and frame id → `Task.id()` / Page identity

## Segments and Bags

- Page-aligned segments (`SEGNEW` / segmented storage) → **Bag** underwritten capacity
- `dirtySegments()` / `clearDirty()` / `flush()` / `defrag()` → **handshake / commit** boundary
- Write only at boundary or through signed path → jx memory law

## Containers

- `PASMList` / Vector, Stack, Queue, Deque, Map, Set → Bag-resident structures
- Hot-path mutation stays frame-local; canonical image is write-back → matches “no free write; commit at boundary”

## Tables and compilation

- `pasm-master-table.php` → seed for **smart table maker** (`jx/smart-table.md`)
- `pasm-bytecode*.php` / assembler → native / Resistant extrusion targets
- `pasm-lang-compiler.php` / PASL → front door for jx surface syntax over time

## Program and Book

- `pasm-program.php` / run entry → **Book** load + Page spawn
- Isolation today is frame-level; Book-level quota is the jx hosting-module target

## Rename discipline

- User-facing docs and new APIs: prefer **jx** names (Book, Bag, Page, Delivery, quotient, push, Resistant)
- Internal PHP class names may stay `PASM*` until a deliberate rename pass
- New files for language surface: prefer `jx-*.php` or under `jx/` / `pasl/` as appropriate

## Resistant code

When a lowering cannot satisfy memory law + pure native template, emit the safe path and mark it. Edge cases: `jx/edge-cases.md`.
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# PASM ↔ jx implementation map

Use this when changing code in this repo so the engine and the language stay aligned.

## Frames and Pages

- `PASMRegisterFrame` / frame pool → **Page** (and Task) memory state
- Frame-local hot arrays in OOP containers → Bag hot path (no segment tax until boundary)
- `M_FRAME` and frame id → `Task.id()` / Page identity

## Segments and Bags

- Page-aligned segments (`SEGNEW` / segmented storage) → **Bag** underwritten capacity
- `dirtySegments()` / `clearDirty()` / `flush()` / `defrag()` → **handshake / commit** boundary
- Write only at boundary or through signed path → jx memory law

## Containers

- `PASMList` / Vector, Stack, Queue, Deque, Map, Set → Bag-resident structures
- Hot-path mutation stays frame-local; canonical image is write-back → matches “no free write; commit at boundary”

## Tables and compilation

- `pasm-master-table.php` → seed for **smart table maker** (`jx/smart-table.md`)
- `pasm-bytecode*.php` / assembler → native / Resistant extrusion targets
- `pasm-lang-compiler.php` / PASL → front door for jx surface syntax over time

## Program and Book

- `pasm-program.php` / run entry → **Book** load + Page spawn
- Isolation today is frame-level; Book-level quota is the jx hosting-module target

## Rename discipline

- User-facing docs and new APIs: prefer **jx** names (Book, Bag, Page, Delivery, quotient, push, Resistant)
- Internal PHP class names may stay `PASM*` until a deliberate rename pass
- New files for language surface: prefer `jx-*.php` or under `jx/` / `pasl/` as appropriate

## Resistant code

When a lowering cannot satisfy memory law + pure native template, emit the safe path and mark it. Edge cases: `jx/edge-cases.md`.
```

</details>

### `jx/README.md`

- Current lines: 17
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 17
- Latest blame by commit:
  - `4b802d6` 17 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

```markdown
4b802d6d (dompipe 2026-08-25  1) # jx design docs (integrated into pasm-v2)
4b802d6d (dompipe 2026-08-25  2) 
4b802d6d (dompipe 2026-08-25  3) This directory holds the language-level specification that is being integrated into the PASM engine in this repository.
4b802d6d (dompipe 2026-08-25  4) 
4b802d6d (dompipe 2026-08-25  5) | File | Content |
4b802d6d (dompipe 2026-08-25  6) |------|---------|
4b802d6d (dompipe 2026-08-25  7) | [SPEC.md](SPEC.md) | Language specification v0.1 |
4b802d6d (dompipe 2026-08-25  8) | [smart-table.md](smart-table.md) | Smart table maker (evolution of pasm-master-table) |
4b802d6d (dompipe 2026-08-25  9) | [delivery.md](delivery.md) | Delivery (deep path) |
4b802d6d (dompipe 2026-08-25 10) | [complex.md](complex.md) | Complex numbers |
4b802d6d (dompipe 2026-08-25 11) | [hosting-api.md](hosting-api.md) | Book / Page / Bag API |
4b802d6d (dompipe 2026-08-25 12) | [edge-cases.md](edge-cases.md) | Resistant-code stress tests |
4b802d6d (dompipe 2026-08-25 13) | [CONVERSATION_LOG.md](CONVERSATION_LOG.md) | Full design conversation |
4b802d6d (dompipe 2026-08-25 14) | [GAPS.md](GAPS.md) | Known gaps — perfection is amiss |
4b802d6d (dompipe 2026-08-25 15) | [PASM_MAP.md](PASM_MAP.md) | Concrete PASM ↔ jx mapping for implementers |
4b802d6d (dompipe 2026-08-25 16) 
4b802d6d (dompipe 2026-08-25 17) The standalone repo `dompipe/jx-lang` was an early dump of these ideas; **this tree (`pasm-v2` → jx) is the integration target.**
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# jx design docs (integrated into pasm-v2)

This directory holds the language-level specification that is being integrated into the PASM engine in this repository.

| File | Content |
|------|---------|
| [SPEC.md](SPEC.md) | Language specification v0.1 |
| [smart-table.md](smart-table.md) | Smart table maker (evolution of pasm-master-table) |
| [delivery.md](delivery.md) | Delivery (deep path) |
| [complex.md](complex.md) | Complex numbers |
| [hosting-api.md](hosting-api.md) | Book / Page / Bag API |
| [edge-cases.md](edge-cases.md) | Resistant-code stress tests |
| [CONVERSATION_LOG.md](CONVERSATION_LOG.md) | Full design conversation |
| [GAPS.md](GAPS.md) | Known gaps — perfection is amiss |
| [PASM_MAP.md](PASM_MAP.md) | Concrete PASM ↔ jx mapping for implementers |

The standalone repo `dompipe/jx-lang` was an early dump of these ideas; **this tree (`pasm-v2` → jx) is the integration target.**
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# jx design docs (integrated into pasm-v2)

This directory holds the language-level specification that is being integrated into the PASM engine in this repository.

| File | Content |
|------|---------|
| [SPEC.md](SPEC.md) | Language specification v0.1 |
| [smart-table.md](smart-table.md) | Smart table maker (evolution of pasm-master-table) |
| [delivery.md](delivery.md) | Delivery (deep path) |
| [complex.md](complex.md) | Complex numbers |
| [hosting-api.md](hosting-api.md) | Book / Page / Bag API |
| [edge-cases.md](edge-cases.md) | Resistant-code stress tests |
| [CONVERSATION_LOG.md](CONVERSATION_LOG.md) | Full design conversation |
| [GAPS.md](GAPS.md) | Known gaps — perfection is amiss |
| [PASM_MAP.md](PASM_MAP.md) | Concrete PASM ↔ jx mapping for implementers |

The standalone repo `dompipe/jx-lang` was an early dump of these ideas; **this tree (`pasm-v2` → jx) is the integration target.**
```

</details>

### `jx/SPEC.md`

- Current lines: 33
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 33
- Latest blame by commit:
  - `4b802d6` 33 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

```markdown
4b802d6d (dompipe 2026-08-25  1) # jx Language Specification (v0.1)
4b802d6d (dompipe 2026-08-25  2) 
4b802d6d (dompipe 2026-08-25  3) Integrated target: this repository (pasm-v2 → jx).
4b802d6d (dompipe 2026-08-25  4) 
4b802d6d (dompipe 2026-08-25  5) ## Identity
4b802d6d (dompipe 2026-08-25  6) 
4b802d6d (dompipe 2026-08-25  7) - Name: **jx** · Pronunciation: *jinx*
4b802d6d (dompipe 2026-08-25  8) - Foundation: PHP + PASM engine in this tree
4b802d6d (dompipe 2026-08-25  9) - Compilation: smart table → native preferred, Resistant fallback
4b802d6d (dompipe 2026-08-25 10) - Memory: strict; Docker-like isolation; X11-style page staging
4b802d6d (dompipe 2026-08-25 11) 
4b802d6d (dompipe 2026-08-25 12) ## Ontology
4b802d6d (dompipe 2026-08-25 13) 
4b802d6d (dompipe 2026-08-25 14) - **Book** — compiled unit
4b802d6d (dompipe 2026-08-25 15) - **Page** — runnable X11-like surface (PASM frame)
4b802d6d (dompipe 2026-08-25 16) - **Bag** — only mutable container (segment-backed, hot path frame-local)
4b802d6d (dompipe 2026-08-25 17) - **Task** — special Bag (`push`, inner scope, `id()`)
4b802d6d (dompipe 2026-08-25 18) - **Delivery** — deep path extract/rebind
4b802d6d (dompipe 2026-08-25 19) - **Resistant** — marked safe fallback code
4b802d6d (dompipe 2026-08-25 20) 
4b802d6d (dompipe 2026-08-25 21) ## Keywords (selected)
4b802d6d (dompipe 2026-08-25 22) 
4b802d6d (dompipe 2026-08-25 23) `const` (castable), complex literals (`3+4i`), delivery paths, rhetorical natives (`put`/`take` direction), symbolic asm constants (`SYS_*`, `STDOUT`, …).
4b802d6d (dompipe 2026-08-25 24) 
4b802d6d (dompipe 2026-08-25 25) ## Memory law
4b802d6d (dompipe 2026-08-25 26) 
4b802d6d (dompipe 2026-08-25 27) Writes only with allowance + underwritten bag + event handshake. Quotient oversight prevents server-crashing overflow.
4b802d6d (dompipe 2026-08-25 28) 
4b802d6d (dompipe 2026-08-25 29) ## Tight vs verbose
4b802d6d (dompipe 2026-08-25 30) 
4b802d6d (dompipe 2026-08-25 31) Verbose (`tell`/`pass`) lowers only to tight methods before codegen/assembler.
4b802d6d (dompipe 2026-08-25 32) 
4b802d6d (dompipe 2026-08-25 33) See sibling files in this directory for smart table, Delivery, complex, hosting API, edge cases, conversation log, and gaps.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# jx Language Specification (v0.1)

Integrated target: this repository (pasm-v2 → jx).

## Identity

- Name: **jx** · Pronunciation: *jinx*
- Foundation: PHP + PASM engine in this tree
- Compilation: smart table → native preferred, Resistant fallback
- Memory: strict; Docker-like isolation; X11-style page staging

## Ontology

- **Book** — compiled unit
- **Page** — runnable X11-like surface (PASM frame)
- **Bag** — only mutable container (segment-backed, hot path frame-local)
- **Task** — special Bag (`push`, inner scope, `id()`)
- **Delivery** — deep path extract/rebind
- **Resistant** — marked safe fallback code

## Keywords (selected)

`const` (castable), complex literals (`3+4i`), delivery paths, rhetorical natives (`put`/`take` direction), symbolic asm constants (`SYS_*`, `STDOUT`, …).

## Memory law

Writes only with allowance + underwritten bag + event handshake. Quotient oversight prevents server-crashing overflow.

## Tight vs verbose

Verbose (`tell`/`pass`) lowers only to tight methods before codegen/assembler.

See sibling files in this directory for smart table, Delivery, complex, hosting API, edge cases, conversation log, and gaps.
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# jx Language Specification (v0.1)

Integrated target: this repository (pasm-v2 → jx).

## Identity

- Name: **jx** · Pronunciation: *jinx*
- Foundation: PHP + PASM engine in this tree
- Compilation: smart table → native preferred, Resistant fallback
- Memory: strict; Docker-like isolation; X11-style page staging

## Ontology

- **Book** — compiled unit
- **Page** — runnable X11-like surface (PASM frame)
- **Bag** — only mutable container (segment-backed, hot path frame-local)
- **Task** — special Bag (`push`, inner scope, `id()`)
- **Delivery** — deep path extract/rebind
- **Resistant** — marked safe fallback code

## Keywords (selected)

`const` (castable), complex literals (`3+4i`), delivery paths, rhetorical natives (`put`/`take` direction), symbolic asm constants (`SYS_*`, `STDOUT`, …).

## Memory law

Writes only with allowance + underwritten bag + event handshake. Quotient oversight prevents server-crashing overflow.

## Tight vs verbose

Verbose (`tell`/`pass`) lowers only to tight methods before codegen/assembler.

See sibling files in this directory for smart table, Delivery, complex, hosting API, edge cases, conversation log, and gaps.
```

</details>

### `jx/complex.md`

- Current lines: 9
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 9
- Latest blame by commit:
  - `4b802d6` 9 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

````markdown
4b802d6d (dompipe 2026-08-25 1) # Complex numbers
4b802d6d (dompipe 2026-08-25 2) 
4b802d6d (dompipe 2026-08-25 3) ```jx
4b802d6d (dompipe 2026-08-25 4) c = 3 + 4i
4b802d6d (dompipe 2026-08-25 5) c = complex(3, 4)
4b802d6d (dompipe 2026-08-25 6) c.re / c.im / c.conj / c.mag / c.arg
4b802d6d (dompipe 2026-08-25 7) ```
4b802d6d (dompipe 2026-08-25 8) 
4b802d6d (dompipe 2026-08-25 9) Native: paired f64 (or platform best). Smart table holds native and Resistant arithmetic templates. Obeys `const` and Bag rules.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# Complex numbers

```jx
c = 3 + 4i
c = complex(3, 4)
c.re / c.im / c.conj / c.mag / c.arg
```

Native: paired f64 (or platform best). Smart table holds native and Resistant arithmetic templates. Obeys `const` and Bag rules.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# Complex numbers

```jx
c = 3 + 4i
c = complex(3, 4)
c.re / c.im / c.conj / c.mag / c.arg
```

Native: paired f64 (or platform best). Smart table holds native and Resistant arithmetic templates. Obeys `const` and Bag rules.
````

</details>

### `jx/delivery.md`

- Current lines: 12
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 12
- Latest blame by commit:
  - `4b802d6` 12 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

````markdown
4b802d6d (dompipe 2026-08-25  1) # Delivery
4b802d6d (dompipe 2026-08-25  2) 
4b802d6d (dompipe 2026-08-25  3) ```jx
4b802d6d (dompipe 2026-08-25  4) val = config.server.ports.https.delivery()
4b802d6d (dompipe 2026-08-25  5) val = config.server.ports.https.delivery(default = 443)
4b802d6d (dompipe 2026-08-25  6) newVar.delivery(config.server.ports.https)
4b802d6d (dompipe 2026-08-25  7) val = delivery(config, ["server", "ports", "https"])
4b802d6d (dompipe 2026-08-25  8) ```
4b802d6d (dompipe 2026-08-25  9) 
4b802d6d (dompipe 2026-08-25 10) - Static check when path is constant; else runtime checks (often Resistant).
4b802d6d (dompipe 2026-08-25 11) - No free write: mutation into a Bag still needs sign + handshake.
4b802d6d (dompipe 2026-08-25 12) - Delivery into `const` target is rejected.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# Delivery

```jx
val = config.server.ports.https.delivery()
val = config.server.ports.https.delivery(default = 443)
newVar.delivery(config.server.ports.https)
val = delivery(config, ["server", "ports", "https"])
```

- Static check when path is constant; else runtime checks (often Resistant).
- No free write: mutation into a Bag still needs sign + handshake.
- Delivery into `const` target is rejected.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# Delivery

```jx
val = config.server.ports.https.delivery()
val = config.server.ports.https.delivery(default = 443)
newVar.delivery(config.server.ports.https)
val = delivery(config, ["server", "ports", "https"])
```

- Static check when path is constant; else runtime checks (often Resistant).
- No free write: mutation into a Bag still needs sign + handshake.
- Delivery into `const` target is rejected.
````

</details>

### `jx/edge-cases.md`

- Current lines: 14
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 14
- Latest blame by commit:
  - `4b802d6` 14 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

```markdown
4b802d6d (dompipe 2026-08-25  1) # Edge cases (Resistant stress)
4b802d6d (dompipe 2026-08-25  2) 
4b802d6d (dompipe 2026-08-25  3) 1. Delivery into missing structure  
4b802d6d (dompipe 2026-08-25  4) 2. Delivery into const  
4b802d6d (dompipe 2026-08-25  5) 3. Quotient exhaustion  
4b802d6d (dompipe 2026-08-25  6) 4. Sign/unsign races  
4b802d6d (dompipe 2026-08-25  7) 5. Complex overflow/inf  
4b802d6d (dompipe 2026-08-25  8) 6. Const-cast violations  
4b802d6d (dompipe 2026-08-25  9) 7. Hostile dynamic shapes  
4b802d6d (dompipe 2026-08-25 10) 8. One-shot sign-and-write over capacity  
4b802d6d (dompipe 2026-08-25 11) 9. Cross-Page push without ref  
4b802d6d (dompipe 2026-08-25 12) 10. Resistant markers must be introspectable  
4b802d6d (dompipe 2026-08-25 13) 
4b802d6d (dompipe 2026-08-25 14) Fail closed; never crash the server on Bag overflow.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# Edge cases (Resistant stress)

1. Delivery into missing structure  
2. Delivery into const  
3. Quotient exhaustion  
4. Sign/unsign races  
5. Complex overflow/inf  
6. Const-cast violations  
7. Hostile dynamic shapes  
8. One-shot sign-and-write over capacity  
9. Cross-Page push without ref  
10. Resistant markers must be introspectable  

Fail closed; never crash the server on Bag overflow.
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# Edge cases (Resistant stress)

1. Delivery into missing structure  
2. Delivery into const  
3. Quotient exhaustion  
4. Sign/unsign races  
5. Complex overflow/inf  
6. Const-cast violations  
7. Hostile dynamic shapes  
8. One-shot sign-and-write over capacity  
9. Cross-Page push without ref  
10. Resistant markers must be introspectable  

Fail closed; never crash the server on Bag overflow.
```

</details>

### `jx/hosting-api.md`

- Current lines: 15
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 15
- Latest blame by commit:
  - `4b802d6` 15 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

````markdown
4b802d6d (dompipe 2026-08-25  1) # Hosting API — Book / Page / Bag
4b802d6d (dompipe 2026-08-25  2) 
4b802d6d (dompipe 2026-08-25  3) ```jx
4b802d6d (dompipe 2026-08-25  4) book = Book.load(path) | Book.compile(source)
4b802d6d (dompipe 2026-08-25  5) page = Page.spawn(entry, bag?)
4b802d6d (dompipe 2026-08-25  6) bag  = Bag.underwrite(size)
4b802d6d (dompipe 2026-08-25  7) task = Task.underwrite(size)
4b802d6d (dompipe 2026-08-25  8) task.push(key, value)
4b802d6d (dompipe 2026-08-25  9) ref  = task.sign(node)
4b802d6d (dompipe 2026-08-25 10) task.set(data).commit(ref)
4b802d6d (dompipe 2026-08-25 11) remaining = task.quotient()
4b802d6d (dompipe 2026-08-25 12) id = task.id()
4b802d6d (dompipe 2026-08-25 13) ```
4b802d6d (dompipe 2026-08-25 14) 
4b802d6d (dompipe 2026-08-25 15) Hosting module embeds PHP, isolates Books, owns server→browser protocol. PASM frames/segments are the current implementation substrate.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# Hosting API — Book / Page / Bag

```jx
book = Book.load(path) | Book.compile(source)
page = Page.spawn(entry, bag?)
bag  = Bag.underwrite(size)
task = Task.underwrite(size)
task.push(key, value)
ref  = task.sign(node)
task.set(data).commit(ref)
remaining = task.quotient()
id = task.id()
```

Hosting module embeds PHP, isolates Books, owns server→browser protocol. PASM frames/segments are the current implementation substrate.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# Hosting API — Book / Page / Bag

```jx
book = Book.load(path) | Book.compile(source)
page = Page.spawn(entry, bag?)
bag  = Bag.underwrite(size)
task = Task.underwrite(size)
task.push(key, value)
ref  = task.sign(node)
task.set(data).commit(ref)
remaining = task.quotient()
id = task.id()
```

Hosting module embeds PHP, isolates Books, owns server→browser protocol. PASM frames/segments are the current implementation substrate.
````

</details>

### `jx/smart-table.md`

- Current lines: 30
- Original reachable commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Latest Markdown-touching commit: `4b802d6` 2026-08-25T20:18:20-04:00 dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 30
- Latest blame by commit:
  - `4b802d6` 30 lines dompipe: Integrate jx language design into pasm-v2; project identity becomes jx (jinx)

<details>
<summary>Latest line blame</summary>

```markdown
4b802d6d (dompipe 2026-08-25  1) # Smart Table Maker (jx)
4b802d6d (dompipe 2026-08-25  2) 
4b802d6d (dompipe 2026-08-25  3) Evolution of `pasm-master-table.php`.
4b802d6d (dompipe 2026-08-25  4) 
4b802d6d (dompipe 2026-08-25  5) ## Purpose
4b802d6d (dompipe 2026-08-25  6) 
4b802d6d (dompipe 2026-08-25  7) Catalogue every method so compiler and AI interpreter can extrude native sequences that obey the memory law, or emit **Resistant** code.
4b802d6d (dompipe 2026-08-25  8) 
4b802d6d (dompipe 2026-08-25  9) ## Schema (v0.1)
4b802d6d (dompipe 2026-08-25 10) 
4b802d6d (dompipe 2026-08-25 11) | Column | Description |
4b802d6d (dompipe 2026-08-25 12) |--------|-------------|
4b802d6d (dompipe 2026-08-25 13) | id | Stable id (`bag.set`, `task.push`, …) |
4b802d6d (dompipe 2026-08-25 14) | name | Surface name |
4b802d6d (dompipe 2026-08-25 15) | module | Bag, Task, Book, global, … |
4b802d6d (dompipe 2026-08-25 16) | arity | Argument count / range |
4b802d6d (dompipe 2026-08-25 17) | arg_shapes | Accepted shapes |
4b802d6d (dompipe 2026-08-25 18) | side_effect | none / read / write-bag / schedule / io |
4b802d6d (dompipe 2026-08-25 19) | requires_ref | Live refSign required? |
4b802d6d (dompipe 2026-08-25 20) | memory_class | pure / underwritten-only / task-local |
4b802d6d (dompipe 2026-08-25 21) | native_template | Preferred lowering |
4b802d6d (dompipe 2026-08-25 22) | resistant_template | Fallback |
4b802d6d (dompipe 2026-08-25 23) | purity_score | 1.0 = pure native |
4b802d6d (dompipe 2026-08-25 24) | notes | Guidance |
4b802d6d (dompipe 2026-08-25 25) 
4b802d6d (dompipe 2026-08-25 26) ## Process
4b802d6d (dompipe 2026-08-25 27) 
4b802d6d (dompipe 2026-08-25 28) Resolve call → match shapes → prefer native_template under memory/const/delivery facts → else resistant_template and mark.
4b802d6d (dompipe 2026-08-25 29) 
4b802d6d (dompipe 2026-08-25 30) Seed rows should be derived from existing PASM opcodes and container methods in this repo.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# Smart Table Maker (jx)

Evolution of `pasm-master-table.php`.

## Purpose

Catalogue every method so compiler and AI interpreter can extrude native sequences that obey the memory law, or emit **Resistant** code.

## Schema (v0.1)

| Column | Description |
|--------|-------------|
| id | Stable id (`bag.set`, `task.push`, …) |
| name | Surface name |
| module | Bag, Task, Book, global, … |
| arity | Argument count / range |
| arg_shapes | Accepted shapes |
| side_effect | none / read / write-bag / schedule / io |
| requires_ref | Live refSign required? |
| memory_class | pure / underwritten-only / task-local |
| native_template | Preferred lowering |
| resistant_template | Fallback |
| purity_score | 1.0 = pure native |
| notes | Guidance |

## Process

Resolve call → match shapes → prefer native_template under memory/const/delivery facts → else resistant_template and mark.

Seed rows should be derived from existing PASM opcodes and container methods in this repo.
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# Smart Table Maker (jx)

Evolution of `pasm-master-table.php`.

## Purpose

Catalogue every method so compiler and AI interpreter can extrude native sequences that obey the memory law, or emit **Resistant** code.

## Schema (v0.1)

| Column | Description |
|--------|-------------|
| id | Stable id (`bag.set`, `task.push`, …) |
| name | Surface name |
| module | Bag, Task, Book, global, … |
| arity | Argument count / range |
| arg_shapes | Accepted shapes |
| side_effect | none / read / write-bag / schedule / io |
| requires_ref | Live refSign required? |
| memory_class | pure / underwritten-only / task-local |
| native_template | Preferred lowering |
| resistant_template | Fallback |
| purity_score | 1.0 = pure native |
| notes | Guidance |

## Process

Resolve call → match shapes → prefer native_template under memory/const/delivery facts → else resistant_template and mark.

Seed rows should be derived from existing PASM opcodes and container methods in this repo.
```

</details>

### `pasl/PASL_Manual.md`

- Current lines: 62
- Original reachable commit: `21569ca` 2026-08-22T13:18:22-04:00 dompipe: PASL docs + ARM64 backend CLI + manuals
- Latest Markdown-touching commit: `21569ca` 2026-08-22T13:18:22-04:00 dompipe: PASL docs + ARM64 backend CLI + manuals
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 62
- Latest blame by commit:
  - `21569ca` 62 lines dompipe: PASL docs + ARM64 backend CLI + manuals

<details>
<summary>Latest line blame</summary>

````markdown
21569ca3 (dompipe 2026-08-22  1) # PASL Language & Compiler Manual
21569ca3 (dompipe 2026-08-22  2) 
21569ca3 (dompipe 2026-08-22  3) **Version 2.0 — O(n) multi-target compiler**  
21569ca3 (dompipe 2026-08-22  4) Targets: **x86-64**, **AArch64 (ARM64)**, **PASM bytecode assembly**
21569ca3 (dompipe 2026-08-22  5) 
21569ca3 (dompipe 2026-08-22  6) ## Pipeline
21569ca3 (dompipe 2026-08-22  7) 
21569ca3 (dompipe 2026-08-22  8) ```
21569ca3 (dompipe 2026-08-22  9) source  --scan O(n)--> tokens
21569ca3 (dompipe 2026-08-22 10) tokens  --parse O(n)--> IR
21569ca3 (dompipe 2026-08-22 11) IR      --emit  O(m)--> x86-64 NASM | AArch64 GAS | PASM assembly
21569ca3 (dompipe 2026-08-22 12) ```
21569ca3 (dompipe 2026-08-22 13) 
21569ca3 (dompipe 2026-08-22 14) ## CLI
21569ca3 (dompipe 2026-08-22 15) 
21569ca3 (dompipe 2026-08-22 16) ```bash
21569ca3 (dompipe 2026-08-22 17) php pasl/pasl-run.php --x86 -o sum.s  file.pasl
21569ca3 (dompipe 2026-08-22 18) nasm -f elf64 sum.s -o sum.o && ld sum.o -o sum && ./sum; echo $?
21569ca3 (dompipe 2026-08-22 19) 
21569ca3 (dompipe 2026-08-22 20) php pasl/pasl-run.php --arm -o sum.s  file.pasl
21569ca3 (dompipe 2026-08-22 21) as -o sum.o sum.s && ld -o sum sum.o && ./sum; echo $?
21569ca3 (dompipe 2026-08-22 22) 
21569ca3 (dompipe 2026-08-22 23) php pasl/pasl-run.php --pasm -o sum.asm file.pasl
21569ca3 (dompipe 2026-08-22 24) php pasl/pasl-run.php --print --arm -c '$x=1; $x++;'
21569ca3 (dompipe 2026-08-22 25) ```
21569ca3 (dompipe 2026-08-22 26) 
21569ca3 (dompipe 2026-08-22 27) Silent on success unless `--print`.
21569ca3 (dompipe 2026-08-22 28) 
21569ca3 (dompipe 2026-08-22 29) ## Language
21569ca3 (dompipe 2026-08-22 30) 
21569ca3 (dompipe 2026-08-22 31) - Integers: `=`, `++`, `+=`, `+ - * / % & | ^ << >>`
21569ca3 (dompipe 2026-08-22 32) - Complex: `complex $z = 3+4i;` and `+ - *` on complex vars
21569ca3 (dompipe 2026-08-22 33) - Control: `while`, `for`, `if/else`, `select`/`switch`, `break`, `continue`
21569ca3 (dompipe 2026-08-22 34) - Conditions: `==`, `!=`, nonzero only
21569ca3 (dompipe 2026-08-22 35) 
21569ca3 (dompipe 2026-08-22 36) ## Backends
21569ca3 (dompipe 2026-08-22 37) 
21569ca3 (dompipe 2026-08-22 38) | Target | Tooling | Exit |
21569ca3 (dompipe 2026-08-22 39) |--------|---------|------|
21569ca3 (dompipe 2026-08-22 40) | x86-64 | nasm + ld | sys_exit 60, rdi |
21569ca3 (dompipe 2026-08-22 41) | AArch64 | as + ld | svc #0, x8=93, x0 |
21569ca3 (dompipe 2026-08-22 42) | PASM | PASM assembler/VM | RET reg |
21569ca3 (dompipe 2026-08-22 43) 
21569ca3 (dompipe 2026-08-22 44) Native backends are **freestanding** (no printf/write).
21569ca3 (dompipe 2026-08-22 45) 
21569ca3 (dompipe 2026-08-22 46) ## API
21569ca3 (dompipe 2026-08-22 47) 
21569ca3 (dompipe 2026-08-22 48) ```php
21569ca3 (dompipe 2026-08-22 49) $c = new pasl\Compiler();
21569ca3 (dompipe 2026-08-22 50) $c->toIr($src);
21569ca3 (dompipe 2026-08-22 51) $c->toX86($src);
21569ca3 (dompipe 2026-08-22 52) $c->toArm($src);
21569ca3 (dompipe 2026-08-22 53) $c->toPasmAsm($src);
21569ca3 (dompipe 2026-08-22 54) ```
21569ca3 (dompipe 2026-08-22 55) 
21569ca3 (dompipe 2026-08-22 56) ## Complexity
21569ca3 (dompipe 2026-08-22 57) 
21569ca3 (dompipe 2026-08-22 58) Scan O(n) · Parse O(n) · Emit O(m)=O(n) · Runtime loops O(iterations)
21569ca3 (dompipe 2026-08-22 59) 
21569ca3 (dompipe 2026-08-22 60) ## Files
21569ca3 (dompipe 2026-08-22 61) 
21569ca3 (dompipe 2026-08-22 62) `pasl-front.php` (IR/scan/parse) · `pasl-back.php` (x86/ARM/PASM backends) · `pasl-run.php` · `PASL_Manual.pdf`
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# PASL Language & Compiler Manual

**Version 2.0 — O(n) multi-target compiler**  
Targets: **x86-64**, **AArch64 (ARM64)**, **PASM bytecode assembly**

## Pipeline

```
source  --scan O(n)--> tokens
tokens  --parse O(n)--> IR
IR      --emit  O(m)--> x86-64 NASM | AArch64 GAS | PASM assembly
```

## CLI

```bash
php pasl/pasl-run.php --x86 -o sum.s  file.pasl
nasm -f elf64 sum.s -o sum.o && ld sum.o -o sum && ./sum; echo $?

php pasl/pasl-run.php --arm -o sum.s  file.pasl
as -o sum.o sum.s && ld -o sum sum.o && ./sum; echo $?

php pasl/pasl-run.php --pasm -o sum.asm file.pasl
php pasl/pasl-run.php --print --arm -c '$x=1; $x++;'
```

Silent on success unless `--print`.

## Language

- Integers: `=`, `++`, `+=`, `+ - * / % & | ^ << >>`
- Complex: `complex $z = 3+4i;` and `+ - *` on complex vars
- Control: `while`, `for`, `if/else`, `select`/`switch`, `break`, `continue`
- Conditions: `==`, `!=`, nonzero only

## Backends

| Target | Tooling | Exit |
|--------|---------|------|
| x86-64 | nasm + ld | sys_exit 60, rdi |
| AArch64 | as + ld | svc #0, x8=93, x0 |
| PASM | PASM assembler/VM | RET reg |

Native backends are **freestanding** (no printf/write).

## API

```php
$c = new pasl\Compiler();
$c->toIr($src);
$c->toX86($src);
$c->toArm($src);
$c->toPasmAsm($src);
```

## Complexity

Scan O(n) · Parse O(n) · Emit O(m)=O(n) · Runtime loops O(iterations)

## Files

`pasl-front.php` (IR/scan/parse) · `pasl-back.php` (x86/ARM/PASM backends) · `pasl-run.php` · `PASL_Manual.pdf`
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# PASL Language & Compiler Manual

**Version 2.0 — O(n) multi-target compiler**  
Targets: **x86-64**, **AArch64 (ARM64)**, **PASM bytecode assembly**

## Pipeline

```
source  --scan O(n)--> tokens
tokens  --parse O(n)--> IR
IR      --emit  O(m)--> x86-64 NASM | AArch64 GAS | PASM assembly
```

## CLI

```bash
php pasl/pasl-run.php --x86 -o sum.s  file.pasl
nasm -f elf64 sum.s -o sum.o && ld sum.o -o sum && ./sum; echo $?

php pasl/pasl-run.php --arm -o sum.s  file.pasl
as -o sum.o sum.s && ld -o sum sum.o && ./sum; echo $?

php pasl/pasl-run.php --pasm -o sum.asm file.pasl
php pasl/pasl-run.php --print --arm -c '$x=1; $x++;'
```

Silent on success unless `--print`.

## Language

- Integers: `=`, `++`, `+=`, `+ - * / % & | ^ << >>`
- Complex: `complex $z = 3+4i;` and `+ - *` on complex vars
- Control: `while`, `for`, `if/else`, `select`/`switch`, `break`, `continue`
- Conditions: `==`, `!=`, nonzero only

## Backends

| Target | Tooling | Exit |
|--------|---------|------|
| x86-64 | nasm + ld | sys_exit 60, rdi |
| AArch64 | as + ld | svc #0, x8=93, x0 |
| PASM | PASM assembler/VM | RET reg |

Native backends are **freestanding** (no printf/write).

## API

```php
$c = new pasl\Compiler();
$c->toIr($src);
$c->toX86($src);
$c->toArm($src);
$c->toPasmAsm($src);
```

## Complexity

Scan O(n) · Parse O(n) · Emit O(m)=O(n) · Runtime loops O(iterations)

## Files

`pasl-front.php` (IR/scan/parse) · `pasl-back.php` (x86/ARM/PASM backends) · `pasl-run.php` · `PASL_Manual.pdf`
````

</details>

### `pasl/PASL_Programming_Guide.md`

- Current lines: 132
- Original reachable commit: `780c316` 2026-08-22T14:08:42-04:00 dompipe: PASL Programming Guide (complete step-by-step)
- Latest Markdown-touching commit: `780c316` 2026-08-22T14:08:42-04:00 dompipe: PASL Programming Guide (complete step-by-step)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 132
- Latest blame by commit:
  - `780c316` 132 lines dompipe: PASL Programming Guide (complete step-by-step)

<details>
<summary>Latest line blame</summary>

````markdown
780c3161 (dompipe 2026-08-22   1) # PASL Complete Programming Guide
780c3161 (dompipe 2026-08-22   2) 
780c3161 (dompipe 2026-08-22   3) **Version 3.0** — PHP-like language → C → native binary / Windows EXE  
780c3161 (dompipe 2026-08-22   4) Integers · Complex · Strings · **Arrays** · Bags · Network
780c3161 (dompipe 2026-08-22   5) 
780c3161 (dompipe 2026-08-22   6) ---
780c3161 (dompipe 2026-08-22   7) 
780c3161 (dompipe 2026-08-22   8) ## 1. What PASL is
780c3161 (dompipe 2026-08-22   9) 
780c3161 (dompipe 2026-08-22  10) PASL is a restricted, PHP-like language compiled through a linear IR into portable **C** (→ ELF/EXE), x86-64 NASM, AArch64 GAS, or PASM text. Silent CLI. Result = **process exit status**.
780c3161 (dompipe 2026-08-22  11) 
780c3161 (dompipe 2026-08-22  12) ### Two tiers
780c3161 (dompipe 2026-08-22  13) 
780c3161 (dompipe 2026-08-22  14) | Tier | Features | Flag |
780c3161 (dompipe 2026-08-22  15) |------|----------|------|
780c3161 (dompipe 2026-08-22  16) | Numeric core | int, complex, loops, if/select | `--c` / `--x86` / `--arm` / `--pasm` |
780c3161 (dompipe 2026-08-22  17) | Full surface | + strings, **arrays**, bags, network | `--c` (auto) |
780c3161 (dompipe 2026-08-22  18) 
780c3161 (dompipe 2026-08-22  19) ---
780c3161 (dompipe 2026-08-22  20) 
780c3161 (dompipe 2026-08-22  21) ## 2. First binary — every step
780c3161 (dompipe 2026-08-22  22) 
780c3161 (dompipe 2026-08-22  23) ```bash
780c3161 (dompipe 2026-08-22  24) git clone https://github.com/dompipe/pasm-v2.git
780c3161 (dompipe 2026-08-22  25) cd pasm-v2
780c3161 (dompipe 2026-08-22  26) php pasl/pasl-run.php --c -o hello.c -c '$x=40; $x++; $x++;'
780c3161 (dompipe 2026-08-22  27) gcc -O2 -o hello hello.c
780c3161 (dompipe 2026-08-22  28) ./hello; echo $?    # 42
780c3161 (dompipe 2026-08-22  29) ```
780c3161 (dompipe 2026-08-22  30) 
780c3161 (dompipe 2026-08-22  31) ### Windows EXE
780c3161 (dompipe 2026-08-22  32) 
780c3161 (dompipe 2026-08-22  33) ```bash
780c3161 (dompipe 2026-08-22  34) x86_64-w64-mingw32-gcc -O2 -o hello.exe hello.c
780c3161 (dompipe 2026-08-22  35) # MSVC: cl /O2 hello.c /Fe:hello.exe
780c3161 (dompipe 2026-08-22  36) # Network MinGW: -lws2_32
780c3161 (dompipe 2026-08-22  37) ```
780c3161 (dompipe 2026-08-22  38) 
780c3161 (dompipe 2026-08-22  39) ---
780c3161 (dompipe 2026-08-22  40) 
780c3161 (dompipe 2026-08-22  41) ## 3. All types
780c3161 (dompipe 2026-08-22  42) 
780c3161 (dompipe 2026-08-22  43) ### Integers
780c3161 (dompipe 2026-08-22  44) ```pasl
780c3161 (dompipe 2026-08-22  45) $x = 0; $x++; $x += 2; $y = $a * $b + $c;
780c3161 (dompipe 2026-08-22  46) ```
780c3161 (dompipe 2026-08-22  47) 
780c3161 (dompipe 2026-08-22  48) ### Complex
780c3161 (dompipe 2026-08-22  49) ```pasl
780c3161 (dompipe 2026-08-22  50) complex $z = 3+4i; complex $p = $z * $w;
780c3161 (dompipe 2026-08-22  51) ```
780c3161 (dompipe 2026-08-22  52) 
780c3161 (dompipe 2026-08-22  53) ### Strings
780c3161 (dompipe 2026-08-22  54) ```pasl
780c3161 (dompipe 2026-08-22  55) string $a = "hello"; $a = $a . " world"; $n = strlen($a);
780c3161 (dompipe 2026-08-22  56) $t = substr($a, 0, 5); if ($a == "hello world") { }
780c3161 (dompipe 2026-08-22  57) ```
780c3161 (dompipe 2026-08-22  58) 
780c3161 (dompipe 2026-08-22  59) ### Simple arrays
780c3161 (dompipe 2026-08-22  60) ```pasl
780c3161 (dompipe 2026-08-22  61) array $a = [10, 20, 30];
780c3161 (dompipe 2026-08-22  62) $a[1] = 7;
780c3161 (dompipe 2026-08-22  63) $x = $a[0] + $a[1] + $a[2];  // 47
780c3161 (dompipe 2026-08-22  64) $n = count($a);               // 3
780c3161 (dompipe 2026-08-22  65) ```
780c3161 (dompipe 2026-08-22  66) 
780c3161 (dompipe 2026-08-22  67) ### Prototype bags (non-classical)
780c3161 (dompipe 2026-08-22  68) ```pasl
780c3161 (dompipe 2026-08-22  69) object $o = {};
780c3161 (dompipe 2026-08-22  70) $o.x = 10; $o.y = 5;
780c3161 (dompipe 2026-08-22  71) $s = $o.x + $o.y;  // 15
780c3161 (dompipe 2026-08-22  72) ```
780c3161 (dompipe 2026-08-22  73) 
780c3161 (dompipe 2026-08-22  74) ### Network
780c3161 (dompipe 2026-08-22  75) ```pasl
780c3161 (dompipe 2026-08-22  76) string $body = net_http_get("example.com", "/", 80);
780c3161 (dompipe 2026-08-22  77) $fd = net_connect("example.com", 80);
780c3161 (dompipe 2026-08-22  78) net_send($fd, "..."); string $resp = net_recv($fd, 4096); net_close($fd);
780c3161 (dompipe 2026-08-22  79) ```
780c3161 (dompipe 2026-08-22  80) 
780c3161 (dompipe 2026-08-22  81) ---
780c3161 (dompipe 2026-08-22  82) 
780c3161 (dompipe 2026-08-22  83) ## 4. Control flow
780c3161 (dompipe 2026-08-22  84) 
780c3161 (dompipe 2026-08-22  85) `while`, `for`, `if`/`else`, `select`/`switch`, `break`, `continue`. Conditions: `==`, `!=`, nonzero.
780c3161 (dompipe 2026-08-22  86) 
780c3161 (dompipe 2026-08-22  87) ---
780c3161 (dompipe 2026-08-22  88) 
780c3161 (dompipe 2026-08-22  89) ## 5. Pipeline
780c3161 (dompipe 2026-08-22  90) 
780c3161 (dompipe 2026-08-22  91) ```
780c3161 (dompipe 2026-08-22  92) source → scan O(n) → parse O(n) → IR → emit → C → gcc/mingw → EXE/ELF
780c3161 (dompipe 2026-08-22  93) ```
780c3161 (dompipe 2026-08-22  94) 
780c3161 (dompipe 2026-08-22  95) ---
780c3161 (dompipe 2026-08-22  96) 
780c3161 (dompipe 2026-08-22  97) ## 6. CLI
780c3161 (dompipe 2026-08-22  98) 
780c3161 (dompipe 2026-08-22  99) ```text
780c3161 (dompipe 2026-08-22 100) php pasl/pasl-run.php [--c|--x86|--arm|--pasm|--strnet] [--bin] [-o out] [-c src|file] [--print]
780c3161 (dompipe 2026-08-22 101) ```
780c3161 (dompipe 2026-08-22 102) 
780c3161 (dompipe 2026-08-22 103) ---
780c3161 (dompipe 2026-08-22 104) 
780c3161 (dompipe 2026-08-22 105) ## 7. Benchmarks (toC, 500 iters, PHP 8.3.6)
780c3161 (dompipe 2026-08-22 106) 
780c3161 (dompipe 2026-08-22 107) | Case | Bytes | µs | compiles/s |
780c3161 (dompipe 2026-08-22 108) |------|------:|---:|-----------:|
780c3161 (dompipe 2026-08-22 109) | num_tiny | 11 | 11.7 | ~85k |
780c3161 (dompipe 2026-08-22 110) | num_loop | 41 | 30.6 | ~33k |
780c3161 (dompipe 2026-08-22 111) | str_concat | 55 | 37.4 | ~27k |
780c3161 (dompipe 2026-08-22 112) | bag_fields | 44 | 38.3 | ~26k |
780c3161 (dompipe 2026-08-22 113) | arr_sum | 51 | 46.0 | ~22k |
780c3161 (dompipe 2026-08-22 114) | mixed | 82 | 57.2 | ~17k |
780c3161 (dompipe 2026-08-22 115) 
780c3161 (dompipe 2026-08-22 116) ```bash
780c3161 (dompipe 2026-08-22 117) php pasl/bench/bench-all.php 500
780c3161 (dompipe 2026-08-22 118) ```
780c3161 (dompipe 2026-08-22 119) 
780c3161 (dompipe 2026-08-22 120) ---
780c3161 (dompipe 2026-08-22 121) 
780c3161 (dompipe 2026-08-22 122) ## 8. Limits
780c3161 (dompipe 2026-08-22 123) 
780c3161 (dompipe 2026-08-22 124) Not full PHP. Array elements int64. Bags have no methods. Strings/arrays/bags/network need **C path**. Exit status is 8-bit in shells.
780c3161 (dompipe 2026-08-22 125) 
780c3161 (dompipe 2026-08-22 126) ---
780c3161 (dompipe 2026-08-22 127) 
780c3161 (dompipe 2026-08-22 128) ## 9. Examples
780c3161 (dompipe 2026-08-22 129) 
780c3161 (dompipe 2026-08-22 130) `examples/arrays.pasl` · `examples/bags.pasl` · `examples/strings.pasl` · `examples/http_get.pasl`
780c3161 (dompipe 2026-08-22 131) 
780c3161 (dompipe 2026-08-22 132) *PASL Programming Guide v3 · github.com/dompipe/pasm-v2*
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# PASL Complete Programming Guide

**Version 3.0** — PHP-like language → C → native binary / Windows EXE  
Integers · Complex · Strings · **Arrays** · Bags · Network

---

## 1. What PASL is

PASL is a restricted, PHP-like language compiled through a linear IR into portable **C** (→ ELF/EXE), x86-64 NASM, AArch64 GAS, or PASM text. Silent CLI. Result = **process exit status**.

### Two tiers

| Tier | Features | Flag |
|------|----------|------|
| Numeric core | int, complex, loops, if/select | `--c` / `--x86` / `--arm` / `--pasm` |
| Full surface | + strings, **arrays**, bags, network | `--c` (auto) |

---

## 2. First binary — every step

```bash
git clone https://github.com/dompipe/pasm-v2.git
cd pasm-v2
php pasl/pasl-run.php --c -o hello.c -c '$x=40; $x++; $x++;'
gcc -O2 -o hello hello.c
./hello; echo $?    # 42
```

### Windows EXE

```bash
x86_64-w64-mingw32-gcc -O2 -o hello.exe hello.c
# MSVC: cl /O2 hello.c /Fe:hello.exe
# Network MinGW: -lws2_32
```

---

## 3. All types

### Integers
```pasl
$x = 0; $x++; $x += 2; $y = $a * $b + $c;
```

### Complex
```pasl
complex $z = 3+4i; complex $p = $z * $w;
```

### Strings
```pasl
string $a = "hello"; $a = $a . " world"; $n = strlen($a);
$t = substr($a, 0, 5); if ($a == "hello world") { }
```

### Simple arrays
```pasl
array $a = [10, 20, 30];
$a[1] = 7;
$x = $a[0] + $a[1] + $a[2];  // 47
$n = count($a);               // 3
```

### Prototype bags (non-classical)
```pasl
object $o = {};
$o.x = 10; $o.y = 5;
$s = $o.x + $o.y;  // 15
```

### Network
```pasl
string $body = net_http_get("example.com", "/", 80);
$fd = net_connect("example.com", 80);
net_send($fd, "..."); string $resp = net_recv($fd, 4096); net_close($fd);
```

---

## 4. Control flow

`while`, `for`, `if`/`else`, `select`/`switch`, `break`, `continue`. Conditions: `==`, `!=`, nonzero.

---

## 5. Pipeline

```
source → scan O(n) → parse O(n) → IR → emit → C → gcc/mingw → EXE/ELF
```

---

## 6. CLI

```text
php pasl/pasl-run.php [--c|--x86|--arm|--pasm|--strnet] [--bin] [-o out] [-c src|file] [--print]
```

---

## 7. Benchmarks (toC, 500 iters, PHP 8.3.6)

| Case | Bytes | µs | compiles/s |
|------|------:|---:|-----------:|
| num_tiny | 11 | 11.7 | ~85k |
| num_loop | 41 | 30.6 | ~33k |
| str_concat | 55 | 37.4 | ~27k |
| bag_fields | 44 | 38.3 | ~26k |
| arr_sum | 51 | 46.0 | ~22k |
| mixed | 82 | 57.2 | ~17k |

```bash
php pasl/bench/bench-all.php 500
```

---

## 8. Limits

Not full PHP. Array elements int64. Bags have no methods. Strings/arrays/bags/network need **C path**. Exit status is 8-bit in shells.

---

## 9. Examples

`examples/arrays.pasl` · `examples/bags.pasl` · `examples/strings.pasl` · `examples/http_get.pasl`

*PASL Programming Guide v3 · github.com/dompipe/pasm-v2*
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# PASL Complete Programming Guide

**Version 3.0** — PHP-like language → C → native binary / Windows EXE  
Integers · Complex · Strings · **Arrays** · Bags · Network

---

## 1. What PASL is

PASL is a restricted, PHP-like language compiled through a linear IR into portable **C** (→ ELF/EXE), x86-64 NASM, AArch64 GAS, or PASM text. Silent CLI. Result = **process exit status**.

### Two tiers

| Tier | Features | Flag |
|------|----------|------|
| Numeric core | int, complex, loops, if/select | `--c` / `--x86` / `--arm` / `--pasm` |
| Full surface | + strings, **arrays**, bags, network | `--c` (auto) |

---

## 2. First binary — every step

```bash
git clone https://github.com/dompipe/pasm-v2.git
cd pasm-v2
php pasl/pasl-run.php --c -o hello.c -c '$x=40; $x++; $x++;'
gcc -O2 -o hello hello.c
./hello; echo $?    # 42
```

### Windows EXE

```bash
x86_64-w64-mingw32-gcc -O2 -o hello.exe hello.c
# MSVC: cl /O2 hello.c /Fe:hello.exe
# Network MinGW: -lws2_32
```

---

## 3. All types

### Integers
```pasl
$x = 0; $x++; $x += 2; $y = $a * $b + $c;
```

### Complex
```pasl
complex $z = 3+4i; complex $p = $z * $w;
```

### Strings
```pasl
string $a = "hello"; $a = $a . " world"; $n = strlen($a);
$t = substr($a, 0, 5); if ($a == "hello world") { }
```

### Simple arrays
```pasl
array $a = [10, 20, 30];
$a[1] = 7;
$x = $a[0] + $a[1] + $a[2];  // 47
$n = count($a);               // 3
```

### Prototype bags (non-classical)
```pasl
object $o = {};
$o.x = 10; $o.y = 5;
$s = $o.x + $o.y;  // 15
```

### Network
```pasl
string $body = net_http_get("example.com", "/", 80);
$fd = net_connect("example.com", 80);
net_send($fd, "..."); string $resp = net_recv($fd, 4096); net_close($fd);
```

---

## 4. Control flow

`while`, `for`, `if`/`else`, `select`/`switch`, `break`, `continue`. Conditions: `==`, `!=`, nonzero.

---

## 5. Pipeline

```
source → scan O(n) → parse O(n) → IR → emit → C → gcc/mingw → EXE/ELF
```

---

## 6. CLI

```text
php pasl/pasl-run.php [--c|--x86|--arm|--pasm|--strnet] [--bin] [-o out] [-c src|file] [--print]
```

---

## 7. Benchmarks (toC, 500 iters, PHP 8.3.6)

| Case | Bytes | µs | compiles/s |
|------|------:|---:|-----------:|
| num_tiny | 11 | 11.7 | ~85k |
| num_loop | 41 | 30.6 | ~33k |
| str_concat | 55 | 37.4 | ~27k |
| bag_fields | 44 | 38.3 | ~26k |
| arr_sum | 51 | 46.0 | ~22k |
| mixed | 82 | 57.2 | ~17k |

```bash
php pasl/bench/bench-all.php 500
```

---

## 8. Limits

Not full PHP. Array elements int64. Bags have no methods. Strings/arrays/bags/network need **C path**. Exit status is 8-bit in shells.

---

## 9. Examples

`examples/arrays.pasl` · `examples/bags.pasl` · `examples/strings.pasl` · `examples/http_get.pasl`

*PASL Programming Guide v3 · github.com/dompipe/pasm-v2*
````

</details>

### `pasl/README.md`

- Current lines: 96
- Original reachable commit: `e74790f` 2026-08-22T13:11:48-04:00 dompipe: PASL O(n) refactor: single-pass IR, x86 + PASM backends
- Latest Markdown-touching commit: `542b349` 2026-08-22T14:47:44-04:00 dompipe: Integrate docs: README + refresh vs smooth live semantics
- Markdown-touching commits for this path: 8
- Latest blame by author: dompipe 96
- Latest blame by commit:
  - `542b349` 66 lines dompipe: Integrate docs: README + refresh vs smooth live semantics
  - `98d39bb` 12 lines dompipe: PASL major release docs: benchmarks table, PHP-to-EXE synopsis, tooling
  - `d2daeaa` 8 lines dompipe: PASL v3: arrays, full type surface, programming guide, benchmarks
  - `a2d591c` 7 lines dompipe: Document unified pasl\Package entry for strnet + core
  - `e74790f` 3 lines dompipe: PASL O(n) refactor: single-pass IR, x86 + PASM backends

<details>
<summary>Latest line blame</summary>

````markdown
542b349d (dompipe 2026-08-22  1) # PASL — PHP-like language → native binary / Windows EXE
e74790fd (dompipe 2026-08-22  2) 
542b349d (dompipe 2026-08-22  3) **One require. Compile. Run like an `.exe`.**
a2d591cd (dompipe 2026-08-22  4) 
a2d591cd (dompipe 2026-08-22  5) ```php
542b349d (dompipe 2026-08-22  6) require 'pasl/pasl.php';
a2d591cd (dompipe 2026-08-22  7) use pasl\Package;
542b349d (dompipe 2026-08-22  8) echo Package::toC($source);   // auto-picks numeric vs full surface
a2d591cd (dompipe 2026-08-22  9) ```
a2d591cd (dompipe 2026-08-22 10) 
542b349d (dompipe 2026-08-22 11) ```bash
542b349d (dompipe 2026-08-22 12) php pasl/pasl-run.php --c -o app.c examples/arrays.pasl
542b349d (dompipe 2026-08-22 13) gcc -O2 -o app app.c && ./app; echo $?
a2d591cd (dompipe 2026-08-22 14) 
542b349d (dompipe 2026-08-22 15) # HTTPS / live need OpenSSL:
542b349d (dompipe 2026-08-22 16) gcc -O2 -o app app.c -lssl -lcrypto
542b349d (dompipe 2026-08-22 17) ```
98d39bb1 (dompipe 2026-08-22 18) 
542b349d (dompipe 2026-08-22 19) ## What you get
d2daeaad (dompipe 2026-08-22 20) 
542b349d (dompipe 2026-08-22 21) | Capability | Example | Target |
542b349d (dompipe 2026-08-22 22) |------------|---------|--------|
542b349d (dompipe 2026-08-22 23) | Integers & control | `$x++; while ($i) {…}` | C / x86 / ARM / PASM |
542b349d (dompipe 2026-08-22 24) | Complex | `complex $z = 3+4i;` | numeric core |
542b349d (dompipe 2026-08-22 25) | Strings | `string $s = "hi";` | C / EXE |
542b349d (dompipe 2026-08-22 26) | Arrays | `array $a = [1,2,3];` | C / EXE |
542b349d (dompipe 2026-08-22 27) | Bags | `object $o = {}; $o.x = 1;` | C / EXE |
542b349d (dompipe 2026-08-22 28) | `fetch` (HTTP + HTTPS/TLS) | `fetch("https://…")` | C / EXE + OpenSSL |
542b349d (dompipe 2026-08-22 29) | Live pages | `live_file` / `live_dom` / `live_run` | C / EXE |
e74790fd (dompipe 2026-08-22 30) 
542b349d (dompipe 2026-08-22 31) Silent by default. **Exit status = result**.
542b349d (dompipe 2026-08-22 32) 
542b349d (dompipe 2026-08-22 33) ## Package layout (integrated)
e74790fd (dompipe 2026-08-22 34) 
d2daeaad (dompipe 2026-08-22 35) ```
542b349d (dompipe 2026-08-22 36) pasl.php              ← single entry
542b349d (dompipe 2026-08-22 37) pasl-package.php      ← pasl\Package auto-route
542b349d (dompipe 2026-08-22 38) pasl-front + pasl-back ← numeric O(n)
542b349d (dompipe 2026-08-22 39) pasl-strnet.php       ← strings, arrays, bags, fetch, live, TLS
542b349d (dompipe 2026-08-22 40) pasl-run.php          ← CLI
542b349d (dompipe 2026-08-22 41) examples/
542b349d (dompipe 2026-08-22 42) ```
542b349d (dompipe 2026-08-22 43) 
542b349d (dompipe 2026-08-22 44) ## Live updates — refresh vs smooth
542b349d (dompipe 2026-08-22 45) 
542b349d (dompipe 2026-08-22 46) **Does it act like pressing the browser Refresh button?**
98d39bb1 (dompipe 2026-08-22 47) 
542b349d (dompipe 2026-08-22 48) | Mode | Like F5? | What you can lose |
542b349d (dompipe 2026-08-22 49) |------|----------|-------------------|
542b349d (dompipe 2026-08-22 50) | **`live_file` + meta refresh** | **Yes** — whole document | Scroll, focus, typing in that page |
542b349d (dompipe 2026-08-22 51) | **`/plain` meta refresh** | **Yes** — whole page | Same as F5 |
542b349d (dompipe 2026-08-22 52) | **`live_dom` + iframe `/stream`** | **Only the slot** | Slot content; **outer shell stays** |
542b349d (dompipe 2026-08-22 53) 
542b349d (dompipe 2026-08-22 54) Meta-refresh / backing-file modes **are** full document reloads (like F5). That can drop scroll, focus, and in-progress form fields in the reloaded document.
542b349d (dompipe 2026-08-22 55) 
542b349d (dompipe 2026-08-22 56) **Smoother without a SPA:** `live_dom` + multipart `/stream` — outer chrome is not torn down; only the connected slot is replaced.
98d39bb1 (dompipe 2026-08-22 57) 
d2daeaad (dompipe 2026-08-22 58) ```pasl
542b349d (dompipe 2026-08-22 59) // Stable outer shell:
542b349d (dompipe 2026-08-22 60) live_dom("pasl-root", "slot content");
542b349d (dompipe 2026-08-22 61) live_run(8765, 2);
542b349d (dompipe 2026-08-22 62) 
542b349d (dompipe 2026-08-22 63) // Full-document refresh (like F5):
542b349d (dompipe 2026-08-22 64) live_file("/tmp/pasl-live.html");
542b349d (dompipe 2026-08-22 65) live_set("content");
542b349d (dompipe 2026-08-22 66) live_run(8765, 2);
98d39bb1 (dompipe 2026-08-22 67) ```
98d39bb1 (dompipe 2026-08-22 68) 
542b349d (dompipe 2026-08-22 69) ## CLI
98d39bb1 (dompipe 2026-08-22 70) 
542b349d (dompipe 2026-08-22 71) ```text
542b349d (dompipe 2026-08-22 72) php pasl/pasl-run.php [--c|--strnet|--x86|--arm|--pasm] [--bin] [-o out] [-c 'src'|file]
98d39bb1 (dompipe 2026-08-22 73) ```
98d39bb1 (dompipe 2026-08-22 74) 
542b349d (dompipe 2026-08-22 75) ## Examples
98d39bb1 (dompipe 2026-08-22 76) 
542b349d (dompipe 2026-08-22 77) `arrays` · `bags` · `strings` · `fetch` · `fetch_https` · `live_file` · `live_dom`
542b349d (dompipe 2026-08-22 78) 
542b349d (dompipe 2026-08-22 79) ## Benchmarks
542b349d (dompipe 2026-08-22 80) 
542b349d (dompipe 2026-08-22 81) | Case | µs | compiles/s |
542b349d (dompipe 2026-08-22 82) |------|---:|-----------:|
542b349d (dompipe 2026-08-22 83) | num_tiny | ~12 | ~85k |
542b349d (dompipe 2026-08-22 84) | arr_sum | ~46 | ~22k |
542b349d (dompipe 2026-08-22 85) | mixed | ~58 | ~17k |
98d39bb1 (dompipe 2026-08-22 86) 
d2daeaad (dompipe 2026-08-22 87) ```bash
d2daeaad (dompipe 2026-08-22 88) php pasl/bench/bench-all.php 500
d2daeaad (dompipe 2026-08-22 89) ```
d2daeaad (dompipe 2026-08-22 90) 
d2daeaad (dompipe 2026-08-22 91) ## Docs
98d39bb1 (dompipe 2026-08-22 92) 
a2d591cd (dompipe 2026-08-22 93) - [PASL_Programming_Guide.pdf](PASL_Programming_Guide.pdf)
542b349d (dompipe 2026-08-22 94) - [PASL_Programming_Guide.md](PASL_Programming_Guide.md)
98d39bb1 (dompipe 2026-08-22 95) 
542b349d (dompipe 2026-08-22 96) *PASL integrated · github.com/dompipe/pasm-v2*
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# PASL O(n) Compiler

Single-pass pipeline (each stage linear in input size):

```
source  --scan O(n)--> tokens
tokens  --parse O(n)--> IR ops
IR      --emit  O(m)--> x86-64 NASM | PASM assembly   (m = |IR| ≤ O(n))
```

No O(n²) global optimization passes. Register allocation is greedy first-fit over a fixed bank.

## CLI

```bash
# Native x86-64 (Linux SysV, freestanding — no printf)
php pasl/pasl-run.php --x86 -o sum.s examples/pasl/x86-sum.pasl
nasm -f elf64 sum.s -o sum.o && ld sum.o -o sum && ./sum; echo $?

# PASM assembly text
php pasl/pasl-run.php --pasm -o sum.asm examples/pasl/x86-sum.pasl
```

Silent by default; use `--print` to write assembly to stdout.

## Complexity

| Stage | Bound |
|-------|--------|
| Comment strip + scan | O(n) |
| Parse → IR | O(n) |
| Backend emit | O(m) = O(n) |
| Generated loops | O(iterations) at run time |

## API

```php
require 'pasl/pasl.php';
$c = new pasl\Compiler();
$ir  = $c->toIr($src);
$asm = $c->toX86($src);      // NASM
$pasm = $c->toPasmAsm($src); // PASM bytecode assembly
```
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# PASL — PHP-like language → native binary / Windows EXE

**One require. Compile. Run like an `.exe`.**

```php
require 'pasl/pasl.php';
use pasl\Package;
echo Package::toC($source);   // auto-picks numeric vs full surface
```

```bash
php pasl/pasl-run.php --c -o app.c examples/arrays.pasl
gcc -O2 -o app app.c && ./app; echo $?

# HTTPS / live need OpenSSL:
gcc -O2 -o app app.c -lssl -lcrypto
```

## What you get

| Capability | Example | Target |
|------------|---------|--------|
| Integers & control | `$x++; while ($i) {…}` | C / x86 / ARM / PASM |
| Complex | `complex $z = 3+4i;` | numeric core |
| Strings | `string $s = "hi";` | C / EXE |
| Arrays | `array $a = [1,2,3];` | C / EXE |
| Bags | `object $o = {}; $o.x = 1;` | C / EXE |
| `fetch` (HTTP + HTTPS/TLS) | `fetch("https://…")` | C / EXE + OpenSSL |
| Live pages | `live_file` / `live_dom` / `live_run` | C / EXE |

Silent by default. **Exit status = result**.

## Package layout (integrated)

```
pasl.php              ← single entry
pasl-package.php      ← pasl\Package auto-route
pasl-front + pasl-back ← numeric O(n)
pasl-strnet.php       ← strings, arrays, bags, fetch, live, TLS
pasl-run.php          ← CLI
examples/
```

## Live updates — refresh vs smooth

**Does it act like pressing the browser Refresh button?**

| Mode | Like F5? | What you can lose |
|------|----------|-------------------|
| **`live_file` + meta refresh** | **Yes** — whole document | Scroll, focus, typing in that page |
| **`/plain` meta refresh** | **Yes** — whole page | Same as F5 |
| **`live_dom` + iframe `/stream`** | **Only the slot** | Slot content; **outer shell stays** |

Meta-refresh / backing-file modes **are** full document reloads (like F5). That can drop scroll, focus, and in-progress form fields in the reloaded document.

**Smoother without a SPA:** `live_dom` + multipart `/stream` — outer chrome is not torn down; only the connected slot is replaced.

```pasl
// Stable outer shell:
live_dom("pasl-root", "slot content");
live_run(8765, 2);

// Full-document refresh (like F5):
live_file("/tmp/pasl-live.html");
live_set("content");
live_run(8765, 2);
```

## CLI

```text
php pasl/pasl-run.php [--c|--strnet|--x86|--arm|--pasm] [--bin] [-o out] [-c 'src'|file]
```

## Examples

`arrays` · `bags` · `strings` · `fetch` · `fetch_https` · `live_file` · `live_dom`

## Benchmarks

| Case | µs | compiles/s |
|------|---:|-----------:|
| num_tiny | ~12 | ~85k |
| arr_sum | ~46 | ~22k |
| mixed | ~58 | ~17k |

```bash
php pasl/bench/bench-all.php 500
```

## Docs

- [PASL_Programming_Guide.pdf](PASL_Programming_Guide.pdf)
- [PASL_Programming_Guide.md](PASL_Programming_Guide.md)

*PASL integrated · github.com/dompipe/pasm-v2*
````

</details>

### `pasl/build-native.md`

- Current lines: 30
- Original reachable commit: `d5e962f` 2026-08-22T13:26:10-04:00 dompipe: PASL portable C backend: Linux binaries + Windows EXE path
- Latest Markdown-touching commit: `98d39bb` 2026-08-22T13:32:44-04:00 dompipe: PASL major release docs: benchmarks table, PHP-to-EXE synopsis, tooling
- Markdown-touching commits for this path: 2
- Latest blame by author: dompipe 30
- Latest blame by commit:
  - `d5e962f` 25 lines dompipe: PASL portable C backend: Linux binaries + Windows EXE path
  - `98d39bb` 5 lines dompipe: PASL major release docs: benchmarks table, PHP-to-EXE synopsis, tooling

<details>
<summary>Latest line blame</summary>

````markdown
d5e962fc (dompipe 2026-08-22  1) # Building native binaries and Windows EXEs from PASL
d5e962fc (dompipe 2026-08-22  2) 
d5e962fc (dompipe 2026-08-22  3) PASL emits **portable C** (default) so one source can become a Linux binary, macOS binary, or **Windows `.exe`**.
d5e962fc (dompipe 2026-08-22  4) 
98d39bb1 (dompipe 2026-08-22  5) ## Fast path
d5e962fc (dompipe 2026-08-22  6) 
d5e962fc (dompipe 2026-08-22  7) ```bash
d5e962fc (dompipe 2026-08-22  8) php pasl/pasl-run.php --c -o sum.c file.pasl
d5e962fc (dompipe 2026-08-22  9) gcc -O2 -o sum sum.c && ./sum; echo $?
d5e962fc (dompipe 2026-08-22 10) 
98d39bb1 (dompipe 2026-08-22 11) # Windows EXE
d5e962fc (dompipe 2026-08-22 12) x86_64-w64-mingw32-gcc -O2 -o sum.exe sum.c
98d39bb1 (dompipe 2026-08-22 13) cl /O2 sum.c /Fe:sum.exe
98d39bb1 (dompipe 2026-08-22 14) build-windows.bat sum.c
d5e962fc (dompipe 2026-08-22 15) ```
d5e962fc (dompipe 2026-08-22 16) 
d5e962fc (dompipe 2026-08-22 17) ## One-shot host binary
d5e962fc (dompipe 2026-08-22 18) 
d5e962fc (dompipe 2026-08-22 19) ```bash
d5e962fc (dompipe 2026-08-22 20) php pasl/pasl-run.php --c --bin -o /tmp/sum.c -c '$sum=0; $i=5; while($i){ $sum=$sum+$i; $i--; }'
d5e962fc (dompipe 2026-08-22 21) /tmp/sum; echo $?   # 15
d5e962fc (dompipe 2026-08-22 22) ```
d5e962fc (dompipe 2026-08-22 23) 
d5e962fc (dompipe 2026-08-22 24) | Artifact | Runs on |
d5e962fc (dompipe 2026-08-22 25) |----------|--------|
d5e962fc (dompipe 2026-08-22 26) | `.c` + host gcc/clang | Same OS/arch |
d5e962fc (dompipe 2026-08-22 27) | `.c` + mingw/cl → `.exe` | Windows x64 |
d5e962fc (dompipe 2026-08-22 28) | static Linux ELF | Many Linux x86-64 hosts |
d5e962fc (dompipe 2026-08-22 29) | `--x86` / `--arm` | Linux freestanding |
98d39bb1 (dompipe 2026-08-22 30) | PASM | PHP + PASM VM |
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# Building native binaries and Windows EXEs from PASL

PASL emits **portable C** (default) so one source can become a Linux binary, macOS binary, or **Windows `.exe`**.

## Fast path: C → binary / EXE

```bash
php pasl/pasl-run.php --c -o sum.c file.pasl

# Linux / macOS
gcc -O2 -o sum sum.c && ./sum; echo $?

# Windows EXE — MSVC
cl /O2 sum.c /Fe:sum.exe

# Windows EXE — MinGW / MSYS2
gcc -O2 -o sum.exe sum.c

# Windows EXE — cross from Linux
x86_64-w64-mingw32-gcc -O2 -o sum.exe sum.c
```

## One-shot host binary

```bash
php pasl/pasl-run.php --c --bin -o /tmp/sum.c -c '$sum=0; $i=5; while($i){ $sum=$sum+$i; $i--; }'
/tmp/sum; echo $?   # 15
```

## Portability

| Artifact | Runs on |
|----------|--------|
| `.c` + host gcc/clang | Same OS/arch |
| `.c` + mingw/cl → `.exe` | Windows x64 |
| static Linux ELF | Many Linux x86-64 hosts |
| `--x86` / `--arm` | Linux freestanding |
| PASM / `.pbc` | Any machine with PHP + PASM VM |

Recommended for shipping: **emit C**, compile on the target OS (or cross-compile with mingw for Windows).
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# Building native binaries and Windows EXEs from PASL

PASL emits **portable C** (default) so one source can become a Linux binary, macOS binary, or **Windows `.exe`**.

## Fast path

```bash
php pasl/pasl-run.php --c -o sum.c file.pasl
gcc -O2 -o sum sum.c && ./sum; echo $?

# Windows EXE
x86_64-w64-mingw32-gcc -O2 -o sum.exe sum.c
cl /O2 sum.c /Fe:sum.exe
build-windows.bat sum.c
```

## One-shot host binary

```bash
php pasl/pasl-run.php --c --bin -o /tmp/sum.c -c '$sum=0; $i=5; while($i){ $sum=$sum+$i; $i--; }'
/tmp/sum; echo $?   # 15
```

| Artifact | Runs on |
|----------|--------|
| `.c` + host gcc/clang | Same OS/arch |
| `.c` + mingw/cl → `.exe` | Windows x64 |
| static Linux ELF | Many Linux x86-64 hosts |
| `--x86` / `--arm` | Linux freestanding |
| PASM | PHP + PASM VM |
````

</details>

### `pasl/xi/README.md`

- Current lines: 41
- Original reachable commit: `8093de0` 2026-08-25T18:09:29-04:00 dompipe: Add xi XIP book server (core engine, CLI, Makefile, Docker)
- Latest Markdown-touching commit: `e599163` 2026-08-25T18:28:02-04:00 dompipe: xi: README note on XipEngine assembly
- Markdown-touching commits for this path: 2
- Latest blame by author: dompipe 41
- Latest blame by commit:
  - `8093de0` 23 lines dompipe: Add xi XIP book server (core engine, CLI, Makefile, Docker)
  - `e599163` 18 lines dompipe: xi: README note on XipEngine assembly

<details>
<summary>Latest line blame</summary>

````markdown
8093de0c (dompipe 2026-08-25  1) # xi — XIP Book Server
8093de0c (dompipe 2026-08-25  2) 
8093de0c (dompipe 2026-08-25  3) Institutional **book**-shaped website sections with embedded PHP, bags/channels, and **no JS courier**.
8093de0c (dompipe 2026-08-25  4) 
8093de0c (dompipe 2026-08-25  5) ```bash
e5991633 (dompipe 2026-08-25  6) cd pasl/xi
e5991633 (dompipe 2026-08-25  7) make test
8093de0c (dompipe 2026-08-25  8) make foreground          # http://127.0.0.1:8765/
8093de0c (dompipe 2026-08-25  9) make start / stop / status
8093de0c (dompipe 2026-08-25 10) make drop                # JSON into cover inbox
8093de0c (dompipe 2026-08-25 11) make docker-build && make docker-run
8093de0c (dompipe 2026-08-25 12) ```
8093de0c (dompipe 2026-08-25 13) 
8093de0c (dompipe 2026-08-25 14) ```bash
8093de0c (dompipe 2026-08-25 15) php xi.php localhost:8765 start config.json
8093de0c (dompipe 2026-08-25 16) php xi.php localhost:8765 stop
8093de0c (dompipe 2026-08-25 17) ```
8093de0c (dompipe 2026-08-25 18) 
e5991633 (dompipe 2026-08-25 19) ## Layout
e5991633 (dompipe 2026-08-25 20) 
8093de0c (dompipe 2026-08-25 21) - **Books** = site sections (`books/cover`, `books/account`)
8093de0c (dompipe 2026-08-25 22) - **Leaves** = pages (state-ready or normalized)
8093de0c (dompipe 2026-08-25 23) - **Binding** = spine, cursor, history, channels
8093de0c (dompipe 2026-08-25 24) - **XIP** = form + protocol + page segments
8093de0c (dompipe 2026-08-25 25) - **Drops** = JSON inbox → channel (next interaction, no refresh loop)
8093de0c (dompipe 2026-08-25 26) - **Tables** = isolated Y-axis channels (iframe-like for devs)
8093de0c (dompipe 2026-08-25 27) 
e5991633 (dompipe 2026-08-25 28) ## XipEngine source
e5991633 (dompipe 2026-08-25 29) 
e5991633 (dompipe 2026-08-25 30) `XipEngine.php` assembles from `XipEngine.h1.php` + `XipEngine.h2.php` on first require (writes `XipEngine.assembled.php`). This keeps the class portable across tooling size limits while remaining pure PHP.
e5991633 (dompipe 2026-08-25 31) 
e5991633 (dompipe 2026-08-25 32) ## Docs
e5991633 (dompipe 2026-08-25 33) 
e5991633 (dompipe 2026-08-25 34) Generate the books PDF locally:
e5991633 (dompipe 2026-08-25 35) 
e5991633 (dompipe 2026-08-25 36) ```bash
e5991633 (dompipe 2026-08-25 37) pip install reportlab
e5991633 (dompipe 2026-08-25 38) python3 docs/build_pdf.py
e5991633 (dompipe 2026-08-25 39) ```
e5991633 (dompipe 2026-08-25 40) 
e5991633 (dompipe 2026-08-25 41) See also the design agreement in the conversation / local `docs/XIP_Books_Guide.pdf`.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# xi — XIP Book Server

Institutional **book**-shaped website sections with embedded PHP, bags/channels, and **no JS courier**.

```bash
make foreground          # http://127.0.0.1:8765/
make start / stop / status
make test
make drop                # JSON into cover inbox
make docker-build && make docker-run
```

```bash
php xi.php localhost:8765 start config.json
php xi.php localhost:8765 stop
```

- **Books** = site sections (`books/cover`, `books/account`)
- **Leaves** = pages (state-ready or normalized)
- **Binding** = spine, cursor, history, channels
- **XIP** = form + protocol + page segments
- **Drops** = JSON inbox → channel (next interaction, no refresh loop)
- **Tables** = isolated Y-axis channels (iframe-like for devs)

See **docs/XIP_Books_Guide.pdf** for the full design.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# xi — XIP Book Server

Institutional **book**-shaped website sections with embedded PHP, bags/channels, and **no JS courier**.

```bash
cd pasl/xi
make test
make foreground          # http://127.0.0.1:8765/
make start / stop / status
make drop                # JSON into cover inbox
make docker-build && make docker-run
```

```bash
php xi.php localhost:8765 start config.json
php xi.php localhost:8765 stop
```

## Layout

- **Books** = site sections (`books/cover`, `books/account`)
- **Leaves** = pages (state-ready or normalized)
- **Binding** = spine, cursor, history, channels
- **XIP** = form + protocol + page segments
- **Drops** = JSON inbox → channel (next interaction, no refresh loop)
- **Tables** = isolated Y-axis channels (iframe-like for devs)

## XipEngine source

`XipEngine.php` assembles from `XipEngine.h1.php` + `XipEngine.h2.php` on first require (writes `XipEngine.assembled.php`). This keeps the class portable across tooling size limits while remaining pure PHP.

## Docs

Generate the books PDF locally:

```bash
pip install reportlab
python3 docs/build_pdf.py
```

See also the design agreement in the conversation / local `docs/XIP_Books_Guide.pdf`.
````

</details>

### `pasl/xi/data/README.md`

- Current lines: 11
- Original reachable commit: `8093de0` 2026-08-25T18:09:29-04:00 dompipe: Add xi XIP book server (core engine, CLI, Makefile, Docker)
- Latest Markdown-touching commit: `8093de0` 2026-08-25T18:09:29-04:00 dompipe: Add xi XIP book server (core engine, CLI, Makefile, Docker)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 11
- Latest blame by commit:
  - `8093de0` 11 lines dompipe: Add xi XIP book server (core engine, CLI, Makefile, Docker)

<details>
<summary>Latest line blame</summary>

````markdown
8093de0c (dompipe 2026-08-25  1) # xi data (runtime)
8093de0c (dompipe 2026-08-25  2) 
8093de0c (dompipe 2026-08-25  3) Per-book channels, binding snapshots, and inbox drops live here.
8093de0c (dompipe 2026-08-25  4) 
8093de0c (dompipe 2026-08-25  5) ```
8093de0c (dompipe 2026-08-25  6) data/books/<bookId>/channels/*.json
8093de0c (dompipe 2026-08-25  7) data/books/<bookId>/binding.json
8093de0c (dompipe 2026-08-25  8) data/books/<bookId>/inbox/*.json
8093de0c (dompipe 2026-08-25  9) ```
8093de0c (dompipe 2026-08-25 10) 
8093de0c (dompipe 2026-08-25 11) Do not commit secrets. Mount this directory as a volume in Docker.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# xi data (runtime)

Per-book channels, binding snapshots, and inbox drops live here.

```
data/books/<bookId>/channels/*.json
data/books/<bookId>/binding.json
data/books/<bookId>/inbox/*.json
```

Do not commit secrets. Mount this directory as a volume in Docker.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# xi data (runtime)

Per-book channels, binding snapshots, and inbox drops live here.

```
data/books/<bookId>/channels/*.json
data/books/<bookId>/binding.json
data/books/<bookId>/inbox/*.json
```

Do not commit secrets. Mount this directory as a volume in Docker.
````

</details>

### `pasl/xi/docs/README.md`

- Current lines: 6
- Original reachable commit: `ef6f6b6` 2026-08-25T18:17:46-04:00 dompipe: xi: XipEngine (b64 parts + loader) — completes runnable book server
- Latest Markdown-touching commit: `ef6f6b6` 2026-08-25T18:17:46-04:00 dompipe: xi: XipEngine (b64 parts + loader) — completes runnable book server
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 6
- Latest blame by commit:
  - `ef6f6b6` 6 lines dompipe: xi: XipEngine (b64 parts + loader) — completes runnable book server

<details>
<summary>Latest line blame</summary>

```markdown
ef6f6b69 (dompipe 2026-08-25 1) # XIP Books documentation
ef6f6b69 (dompipe 2026-08-25 2) 
ef6f6b69 (dompipe 2026-08-25 3) - Build PDF: `python3 docs/build_pdf.py` (requires reportlab)
ef6f6b69 (dompipe 2026-08-25 4) - Guide covers books, binding, leaves, channels, drops, tables, security, Makefile
ef6f6b69 (dompipe 2026-08-25 5) 
ef6f6b69 (dompipe 2026-08-25 6) If `XIP_Books_Guide.pdf` is not in this folder, generate it locally from `build_pdf.py`.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# XIP Books documentation

- Build PDF: `python3 docs/build_pdf.py` (requires reportlab)
- Guide covers books, binding, leaves, channels, drops, tables, security, Makefile

If `XIP_Books_Guide.pdf` is not in this folder, generate it locally from `build_pdf.py`.
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# XIP Books documentation

- Build PDF: `python3 docs/build_pdf.py` (requires reportlab)
- Guide covers books, binding, leaves, channels, drops, tables, security, Makefile

If `XIP_Books_Guide.pdf` is not in this folder, generate it locally from `build_pdf.py`.
```

</details>

### `plugins/TARGETS.md`

- Current lines: 41
- Original reachable commit: `ca14210` 2026-08-25T20:36:37-04:00 dompipe: Plugin allow-gate: must target windows, mac, linux, and web (jx) before install
- Latest Markdown-touching commit: `e4de552` 2026-08-25T20:46:10-04:00 dompipe: Hard reject non-portable plugins; collect all errors into jxerr.log
- Markdown-touching commits for this path: 2
- Latest blame by author: dompipe 41
- Latest blame by commit:
  - `e4de552` 30 lines dompipe: Hard reject non-portable plugins; collect all errors into jxerr.log
  - `ca14210` 11 lines dompipe: Plugin allow-gate: must target windows, mac, linux, and web (jx) before install

<details>
<summary>Latest line blame</summary>

````markdown
e4de552e (dompipe 2026-08-25  1) # Plugin target policy — hard reject
ca142103 (dompipe 2026-08-25  2) 
e4de552e (dompipe 2026-08-25  3) A plugin is **allowed** only if it is portable to **all four**:
ca142103 (dompipe 2026-08-25  4) 
ca142103 (dompipe 2026-08-25  5) | Target | Meaning |
ca142103 (dompipe 2026-08-25  6) |--------|--------|
e4de552e (dompipe 2026-08-25  7) | **windows** | PHP host on Windows |
e4de552e (dompipe 2026-08-25  8) | **mac** | PHP host on macOS |
e4de552e (dompipe 2026-08-25  9) | **linux** | PHP host on Linux |
e4de552e (dompipe 2026-08-25 10) | **web** | jx web/hosting path |
ca142103 (dompipe 2026-08-25 11) 
e4de552e (dompipe 2026-08-25 12) ## Non-portable = not possible (this version)
ca142103 (dompipe 2026-08-25 13) 
e4de552e (dompipe 2026-08-25 14) If a plugin is not portable, it is **outside the requests of the current state of programming**. A later jx version might support it; **this one does not**. It is **not possible** to install or use it here.
e4de552e (dompipe 2026-08-25 15) 
e4de552e (dompipe 2026-08-25 16) Result: **HARD REJECT** — install aborted. No partial install.
e4de552e (dompipe 2026-08-25 17) 
e4de552e (dompipe 2026-08-25 18) ## Multi-error log: `jxerr.log`
e4de552e (dompipe 2026-08-25 19) 
e4de552e (dompipe 2026-08-25 20) The checker does **not** stop at the first problem. It walks the plugin and **collects every** target/portability error, then:
e4de552e (dompipe 2026-08-25 21) 
e4de552e (dompipe 2026-08-25 22) 1. Writes the full list to **`jxerr.log`** at the repo root (append, timestamped blocks)
e4de552e (dompipe 2026-08-25 23) 2. Prints a **condensed** multi-error summary to stderr
e4de552e (dompipe 2026-08-25 24) 
e4de552e (dompipe 2026-08-25 25) Example block in `jxerr.log`:
e4de552e (dompipe 2026-08-25 26) 
e4de552e (dompipe 2026-08-25 27) ```
e4de552e (dompipe 2026-08-25 28) ==== jxerr 2026-08-25T20:44:00+00:00 [install:badplug] ====
e4de552e (dompipe 2026-08-25 29) 1. Plugin 'badplug': missing required target 'mac'
e4de552e (dompipe 2026-08-25 30) 2. Plugin 'badplug' [x.php]: dl() is not portable — outside this version; cannot use
e4de552e (dompipe 2026-08-25 31) 3. Plugin 'badplug': HARD REJECT — non-portable or incomplete targets...
e4de552e (dompipe 2026-08-25 32) ==== end (3 errors) ====
e4de552e (dompipe 2026-08-25 33) ```
ca142103 (dompipe 2026-08-25 34) 
ca142103 (dompipe 2026-08-25 35) ## Commands
ca142103 (dompipe 2026-08-25 36) 
ca142103 (dompipe 2026-08-25 37) ```bash
e4de552e (dompipe 2026-08-25 38) php jx-install.php check-targets
e4de552e (dompipe 2026-08-25 39) php jx-install.php check-targets decimals
e4de552e (dompipe 2026-08-25 40) php jx-install.php install <id>    # hard reject + jxerr.log on failure
ca142103 (dompipe 2026-08-25 41) ```
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# Plugin target policy (allow gate)

A plugin is **allowed** only if it compiles (or is verified portable) for **all four** targets:

| Target | Meaning |
|--------|--------|
| **windows** | PHP CLI / host on Windows |
| **mac** | PHP CLI / host on macOS |
| **linux** | PHP CLI / host on Linux |
| **web** | jx web/hosting path (server-side Book under the hosting module; browser surfaces via jx protocol) |

## Rules

1. `plugins/catalog.json` lists `required_targets: ["windows","mac","linux","web"]`.
2. Each plugin must declare the same four in `targets` (catalog and/or `plugin.json`).
3. `jx-install.php` runs **check-targets** before any install. Missing or failing a target → **install denied**.
4. Checks are portable PHP (no OS-specific extensions required for core plugins). Platform-specific code must be gated and still provide a working path on every target.
5. **web (jx)** means the plugin must not assume a TTY-only environment: no hard dependency on CLI-only APIs for its core `provides` list.

## Commands

```bash
php jx-install.php check-targets           # all catalog plugins
php jx-install.php check-targets decimals  # one plugin
php jx-install.php install decimals        # runs check-targets first
```
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# Plugin target policy — hard reject

A plugin is **allowed** only if it is portable to **all four**:

| Target | Meaning |
|--------|--------|
| **windows** | PHP host on Windows |
| **mac** | PHP host on macOS |
| **linux** | PHP host on Linux |
| **web** | jx web/hosting path |

## Non-portable = not possible (this version)

If a plugin is not portable, it is **outside the requests of the current state of programming**. A later jx version might support it; **this one does not**. It is **not possible** to install or use it here.

Result: **HARD REJECT** — install aborted. No partial install.

## Multi-error log: `jxerr.log`

The checker does **not** stop at the first problem. It walks the plugin and **collects every** target/portability error, then:

1. Writes the full list to **`jxerr.log`** at the repo root (append, timestamped blocks)
2. Prints a **condensed** multi-error summary to stderr

Example block in `jxerr.log`:

```
==== jxerr 2026-08-25T20:44:00+00:00 [install:badplug] ====
1. Plugin 'badplug': missing required target 'mac'
2. Plugin 'badplug' [x.php]: dl() is not portable — outside this version; cannot use
3. Plugin 'badplug': HARD REJECT — non-portable or incomplete targets...
==== end (3 errors) ====
```

## Commands

```bash
php jx-install.php check-targets
php jx-install.php check-targets decimals
php jx-install.php install <id>    # hard reject + jxerr.log on failure
```
````

</details>

## jx-lang Files

### `README.md`

- Current lines: 79
- Original reachable commit: `0452fc2` 2026-08-25T20:12:19-04:00 dompipe: Initial commit
- Latest Markdown-touching commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Markdown-touching commits for this path: 2
- Latest blame by author: dompipe 79
- Latest blame by commit:
  - `8c52595` 79 lines dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests

<details>
<summary>Latest line blame</summary>

````markdown
8c52595a (dompipe 2026-08-25  1) # jx (jinx)
8c52595a (dompipe 2026-08-25  2) 
8c52595a (dompipe 2026-08-25  3) **jx** (pronounced *jinx*) is a PHP-derived server-side language that expands into Books, Bags, and Pages under a strict, Docker-like memory model. It targets native code through a smart table maker, with Resistant fallback when purity cannot be guaranteed.
8c52595a (dompipe 2026-08-25  4) 
8c52595a (dompipe 2026-08-25  5) ## Core Concepts
8c52595a (dompipe 2026-08-25  6) 
8c52595a (dompipe 2026-08-25  7) | Concept | Meaning |
8c52595a (dompipe 2026-08-25  8) |---------|---------|
8c52595a (dompipe 2026-08-25  9) | **Book** | The compiled unit. Contains Pages, Bags, libraries, and entry points. Loaded by the hosting module under isolation. |
8c52595a (dompipe 2026-08-25 10) | **Page** | A runnable surface living in an X11-style memory state. Scheduled by the TaskHandler. |
8c52595a (dompipe 2026-08-25 11) | **Bag** | The only mutable memory container. A Task *is* a special Bag. All writes require underwrite + sign + handshake. |
8c52595a (dompipe 2026-08-25 12) | **Delivery** | Deep path operator: `parent.child.subchild...` extracts or rebinds nested values. |
8c52595a (dompipe 2026-08-25 13) | **Resistant code** | Safe fallback emitted when the smart table cannot produce a pure native path. |
8c52595a (dompipe 2026-08-25 14) 
8c52595a (dompipe 2026-08-25 15) ## Design Pillars
8c52595a (dompipe 2026-08-25 16) 
8c52595a (dompipe 2026-08-25 17) - **A** — Platform staging treats Pages in an X11 state of memory.
8c52595a (dompipe 2026-08-25 18) - **B** — Language is about compiled **Books**, not single pages.
8c52595a (dompipe 2026-08-25 19) - **C** — Delivery (derivative apprehensives) for deep structure access.
8c52595a (dompipe 2026-08-25 20) - **D** — `const` is a keyword and is castable.
8c52595a (dompipe 2026-08-25 21) - **E** — Complex numbers are first-class.
8c52595a (dompipe 2026-08-25 22) - **F** — Built on PHP via a hosting module that embeds the original engine, expands with jx, and isolates Book libraries under memory constraints. Server-side can update browser-side through a coherent protocol.
8c52595a (dompipe 2026-08-25 23) 
8c52595a (dompipe 2026-08-25 24) ## Memory Model (non-negotiable)
8c52595a (dompipe 2026-08-25 25) 
8c52595a (dompipe 2026-08-25 26) Memory writes are forbidden by default. A write is legal only when:
8c52595a (dompipe 2026-08-25 27) 
8c52595a (dompipe 2026-08-25 28) 1. A **buffer of allowance** is supplied,
8c52595a (dompipe 2026-08-25 29) 2. It is handed to an **underwritten bag**,
8c52595a (dompipe 2026-08-25 30) 3. Mutation occurs through an **event handshake**.
8c52595a (dompipe 2026-08-25 31) 
8c52595a (dompipe 2026-08-25 32) Tasks are special Bags. They support `push` for property preassignments and inner scoped variables.
8c52595a (dompipe 2026-08-25 33) 
8c52595a (dompipe 2026-08-25 34) ## Quick Surface
8c52595a (dompipe 2026-08-25 35) 
8c52595a (dompipe 2026-08-25 36) ```jx
8c52595a (dompipe 2026-08-25 37) // Book / Page / Bag
8c52595a (dompipe 2026-08-25 38) book = Book.load("dashboard.jx")
8c52595a (dompipe 2026-08-25 39) page = Page.spawn(entry, bag)
8c52595a (dompipe 2026-08-25 40) bag  = Bag.underwrite(4096)
8c52595a (dompipe 2026-08-25 41) 
8c52595a (dompipe 2026-08-25 42) // Task (special Bag)
8c52595a (dompipe 2026-08-25 43) task = Task.underwrite(8192)
8c52595a (dompipe 2026-08-25 44) task.push("title", "Settings")
8c52595a (dompipe 2026-08-25 45) id = task.id()
8c52595a (dompipe 2026-08-25 46) 
8c52595a (dompipe 2026-08-25 47) // Sign + mutate
8c52595a (dompipe 2026-08-25 48) ref = bag.sign(node)
8c52595a (dompipe 2026-08-25 49) bag.set(data).commit(ref)          // tight
8c52595a (dompipe 2026-08-25 50) bag.tell(set, data).pass(ref)      // verbose → lowers to tight
8c52595a (dompipe 2026-08-25 51) 
8c52595a (dompipe 2026-08-25 52) // Oversight
8c52595a (dompipe 2026-08-25 53) remaining = bag.quotient()
8c52595a (dompipe 2026-08-25 54) 
8c52595a (dompipe 2026-08-25 55) // Delivery
8c52595a (dompipe 2026-08-25 56) port = config.server.ports.https.delivery()
8c52595a (dompipe 2026-08-25 57) newVar.delivery(config.server.ports.https)
8c52595a (dompipe 2026-08-25 58) 
8c52595a (dompipe 2026-08-25 59) // const + complex
8c52595a (dompipe 2026-08-25 60) const limit = 100
8c52595a (dompipe 2026-08-25 61) c = 3 + 4i
8c52595a (dompipe 2026-08-25 62) ```
8c52595a (dompipe 2026-08-25 63) 
8c52595a (dompipe 2026-08-25 64) ## Repository Layout
8c52595a (dompipe 2026-08-25 65) 
8c52595a (dompipe 2026-08-25 66) - `docs/smart-table.md` — Smart table maker schema
8c52595a (dompipe 2026-08-25 67) - `docs/delivery.md` — Delivery syntax and lowering
8c52595a (dompipe 2026-08-25 68) - `docs/complex.md` — Complex number surface and native representation
8c52595a (dompipe 2026-08-25 69) - `docs/hosting-api.md` — Book / Page / Bag API for the hosting module
8c52595a (dompipe 2026-08-25 70) - `tests/edge-cases.md` — Edge-case tests that stress Resistant code
8c52595a (dompipe 2026-08-25 71) - `SPEC.md` — Consolidated language specification
8c52595a (dompipe 2026-08-25 72) 
8c52595a (dompipe 2026-08-25 73) ## Status
8c52595a (dompipe 2026-08-25 74) 
8c52595a (dompipe 2026-08-25 75) Specification stage. Compiler, hosting module, and AI interpreter are future work. The goal is that any AI instance that knows every method can fasten high-level jx to assembly with ease, falling back to Resistant code only when necessary.
8c52595a (dompipe 2026-08-25 76) 
8c52595a (dompipe 2026-08-25 77) ---
8c52595a (dompipe 2026-08-25 78) 
8c52595a (dompipe 2026-08-25 79) jx — pronounced jinx.
````

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# jx-lang
jx (jinx) — PHP-derived server-side language with Books, Bags, Pages, strict memory model, smart table maker, Delivery, complex numbers, and X11-like page staging. Compiles toward native with Resistant fallback.
```

</details>

<details>
<summary>Latest content</summary>

````markdown
# jx (jinx)

**jx** (pronounced *jinx*) is a PHP-derived server-side language that expands into Books, Bags, and Pages under a strict, Docker-like memory model. It targets native code through a smart table maker, with Resistant fallback when purity cannot be guaranteed.

## Core Concepts

| Concept | Meaning |
|---------|---------|
| **Book** | The compiled unit. Contains Pages, Bags, libraries, and entry points. Loaded by the hosting module under isolation. |
| **Page** | A runnable surface living in an X11-style memory state. Scheduled by the TaskHandler. |
| **Bag** | The only mutable memory container. A Task *is* a special Bag. All writes require underwrite + sign + handshake. |
| **Delivery** | Deep path operator: `parent.child.subchild...` extracts or rebinds nested values. |
| **Resistant code** | Safe fallback emitted when the smart table cannot produce a pure native path. |

## Design Pillars

- **A** — Platform staging treats Pages in an X11 state of memory.
- **B** — Language is about compiled **Books**, not single pages.
- **C** — Delivery (derivative apprehensives) for deep structure access.
- **D** — `const` is a keyword and is castable.
- **E** — Complex numbers are first-class.
- **F** — Built on PHP via a hosting module that embeds the original engine, expands with jx, and isolates Book libraries under memory constraints. Server-side can update browser-side through a coherent protocol.

## Memory Model (non-negotiable)

Memory writes are forbidden by default. A write is legal only when:

1. A **buffer of allowance** is supplied,
2. It is handed to an **underwritten bag**,
3. Mutation occurs through an **event handshake**.

Tasks are special Bags. They support `push` for property preassignments and inner scoped variables.

## Quick Surface

```jx
// Book / Page / Bag
book = Book.load("dashboard.jx")
page = Page.spawn(entry, bag)
bag  = Bag.underwrite(4096)

// Task (special Bag)
task = Task.underwrite(8192)
task.push("title", "Settings")
id = task.id()

// Sign + mutate
ref = bag.sign(node)
bag.set(data).commit(ref)          // tight
bag.tell(set, data).pass(ref)      // verbose → lowers to tight

// Oversight
remaining = bag.quotient()

// Delivery
port = config.server.ports.https.delivery()
newVar.delivery(config.server.ports.https)

// const + complex
const limit = 100
c = 3 + 4i
```

## Repository Layout

- `docs/smart-table.md` — Smart table maker schema
- `docs/delivery.md` — Delivery syntax and lowering
- `docs/complex.md` — Complex number surface and native representation
- `docs/hosting-api.md` — Book / Page / Bag API for the hosting module
- `tests/edge-cases.md` — Edge-case tests that stress Resistant code
- `SPEC.md` — Consolidated language specification

## Status

Specification stage. Compiler, hosting module, and AI interpreter are future work. The goal is that any AI instance that knows every method can fasten high-level jx to assembly with ease, falling back to Resistant code only when necessary.

---

jx — pronounced jinx.
````

</details>

### `SPEC.md`

- Current lines: 51
- Original reachable commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Latest Markdown-touching commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 51
- Latest blame by commit:
  - `8c52595` 51 lines dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests

<details>
<summary>Latest line blame</summary>

```markdown
8c52595a (dompipe 2026-08-25  1) # jx Language Specification (v0.1)
8c52595a (dompipe 2026-08-25  2) 
8c52595a (dompipe 2026-08-25  3) ## 1. Identity
8c52595a (dompipe 2026-08-25  4) 
8c52595a (dompipe 2026-08-25  5) - Name: **jx**
8c52595a (dompipe 2026-08-25  6) - Pronunciation: *jinx*
8c52595a (dompipe 2026-08-25  7) - Foundation: PHP (hosting module embeds and expands the original engine)
8c52595a (dompipe 2026-08-25  8) - Compilation target: native (via smart table maker) with Resistant fallback
8c52595a (dompipe 2026-08-25  9) - Memory model: strict, Docker-like isolation; X11-style page staging
8c52595a (dompipe 2026-08-25 10) 
8c52595a (dompipe 2026-08-25 11) ## 2. Ontology
8c52595a (dompipe 2026-08-25 12) 
8c52595a (dompipe 2026-08-25 13) ### Book
8c52595a (dompipe 2026-08-25 14) Compiled unit. Contains Pages, Bags, libraries, entry points. Loaded under isolation by the hosting module.
8c52595a (dompipe 2026-08-25 15) 
8c52595a (dompipe 2026-08-25 16) ### Page
8c52595a (dompipe 2026-08-25 17) Runnable surface in an X11-like memory state. Scheduled by TaskHandler.
8c52595a (dompipe 2026-08-25 18) 
8c52595a (dompipe 2026-08-25 19) ### Bag
8c52595a (dompipe 2026-08-25 20) Only mutable memory container. Capacity is underwritten at creation. Writes require:
8c52595a (dompipe 2026-08-25 21) - buffer of allowance
8c52595a (dompipe 2026-08-25 22) - underwritten bag
8c52595a (dompipe 2026-08-25 23) - event handshake
8c52595a (dompipe 2026-08-25 24) 
8c52595a (dompipe 2026-08-25 25) ### Task
8c52595a (dompipe 2026-08-25 26) Special Bag. Adds:
8c52595a (dompipe 2026-08-25 27) - `push(key, value)` — property preassignment
8c52595a (dompipe 2026-08-25 28) - inner scoped variables
8c52595a (dompipe 2026-08-25 29) - `id()` — stable task identifier
8c52595a (dompipe 2026-08-25 30) 
8c52595a (dompipe 2026-08-25 31) ## 3. Keywords (selected)
8c52595a (dompipe 2026-08-25 32) 
8c52595a (dompipe 2026-08-25 33) - `const` — immutable binding; also castable `(const)expr`
8c52595a (dompipe 2026-08-25 34) - `delivery` — deep path extract / rebind
8c52595a (dompipe 2026-08-25 35) - Complex literals: `3 + 4i`, `complex(re, im)`
8c52595a (dompipe 2026-08-25 36) 
8c52595a (dompipe 2026-08-25 37) ## 4. Tight vs Verbose
8c52595a (dompipe 2026-08-25 38) 
8c52595a (dompipe 2026-08-25 39) Verbose (placebo) forms exist only for readability. They lower exclusively to tight methods before code generation. The assembler never sees the verbose surface.
8c52595a (dompipe 2026-08-25 40) 
8c52595a (dompipe 2026-08-25 41) ## 5. Smart Table Maker
8c52595a (dompipe 2026-08-25 42) 
8c52595a (dompipe 2026-08-25 43) See `docs/smart-table.md`.
8c52595a (dompipe 2026-08-25 44) 
8c52595a (dompipe 2026-08-25 45) ## 6. Resistant Code
8c52595a (dompipe 2026-08-25 46) 
8c52595a (dompipe 2026-08-25 47) When the smart table cannot emit a pure native path that still obeys memory and safety rules, it emits Resistant code: correct, tested, explicitly marked, lower purity.
8c52595a (dompipe 2026-08-25 48) 
8c52595a (dompipe 2026-08-25 49) ## 7. Hosting Module
8c52595a (dompipe 2026-08-25 50) 
8c52595a (dompipe 2026-08-25 51) Embeds PHP, expands with jx, isolates each Book under class + memory constraints. Provides the protocol by which server-side state can update browser-side surfaces coherently.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# jx Language Specification (v0.1)

## 1. Identity

- Name: **jx**
- Pronunciation: *jinx*
- Foundation: PHP (hosting module embeds and expands the original engine)
- Compilation target: native (via smart table maker) with Resistant fallback
- Memory model: strict, Docker-like isolation; X11-style page staging

## 2. Ontology

### Book
Compiled unit. Contains Pages, Bags, libraries, entry points. Loaded under isolation by the hosting module.

### Page
Runnable surface in an X11-like memory state. Scheduled by TaskHandler.

### Bag
Only mutable memory container. Capacity is underwritten at creation. Writes require:
- buffer of allowance
- underwritten bag
- event handshake

### Task
Special Bag. Adds:
- `push(key, value)` — property preassignment
- inner scoped variables
- `id()` — stable task identifier

## 3. Keywords (selected)

- `const` — immutable binding; also castable `(const)expr`
- `delivery` — deep path extract / rebind
- Complex literals: `3 + 4i`, `complex(re, im)`

## 4. Tight vs Verbose

Verbose (placebo) forms exist only for readability. They lower exclusively to tight methods before code generation. The assembler never sees the verbose surface.

## 5. Smart Table Maker

See `docs/smart-table.md`.

## 6. Resistant Code

When the smart table cannot emit a pure native path that still obeys memory and safety rules, it emits Resistant code: correct, tested, explicitly marked, lower purity.

## 7. Hosting Module

Embeds PHP, expands with jx, isolates each Book under class + memory constraints. Provides the protocol by which server-side state can update browser-side surfaces coherently.
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# jx Language Specification (v0.1)

## 1. Identity

- Name: **jx**
- Pronunciation: *jinx*
- Foundation: PHP (hosting module embeds and expands the original engine)
- Compilation target: native (via smart table maker) with Resistant fallback
- Memory model: strict, Docker-like isolation; X11-style page staging

## 2. Ontology

### Book
Compiled unit. Contains Pages, Bags, libraries, entry points. Loaded under isolation by the hosting module.

### Page
Runnable surface in an X11-like memory state. Scheduled by TaskHandler.

### Bag
Only mutable memory container. Capacity is underwritten at creation. Writes require:
- buffer of allowance
- underwritten bag
- event handshake

### Task
Special Bag. Adds:
- `push(key, value)` — property preassignment
- inner scoped variables
- `id()` — stable task identifier

## 3. Keywords (selected)

- `const` — immutable binding; also castable `(const)expr`
- `delivery` — deep path extract / rebind
- Complex literals: `3 + 4i`, `complex(re, im)`

## 4. Tight vs Verbose

Verbose (placebo) forms exist only for readability. They lower exclusively to tight methods before code generation. The assembler never sees the verbose surface.

## 5. Smart Table Maker

See `docs/smart-table.md`.

## 6. Resistant Code

When the smart table cannot emit a pure native path that still obeys memory and safety rules, it emits Resistant code: correct, tested, explicitly marked, lower purity.

## 7. Hosting Module

Embeds PHP, expands with jx, isolates each Book under class + memory constraints. Provides the protocol by which server-side state can update browser-side surfaces coherently.
```

</details>

### `docs/CONVERSATION_LOG.md`

- Current lines: 119
- Original reachable commit: `6dc1230` 2026-08-25T20:16:11-04:00 dompipe: Add full design conversation log and reflective gaps (perfection is amiss)
- Latest Markdown-touching commit: `6dc1230` 2026-08-25T20:16:11-04:00 dompipe: Add full design conversation log and reflective gaps (perfection is amiss)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 119
- Latest blame by commit:
  - `6dc1230` 119 lines dompipe: Add full design conversation log and reflective gaps (perfection is amiss)

<details>
<summary>Latest line blame</summary>

```markdown
6dc12304 (dompipe 2026-08-25   1) # jx Design Conversation Log
6dc12304 (dompipe 2026-08-25   2) 
6dc12304 (dompipe 2026-08-25   3) This document captures the design conversation that produced the jx (jinx) language specification, from the early Axis/yaxis discussion through Books, Bags, Pages, the smart table maker, Delivery, complex numbers, and the edge-case posture. It ends with a deliberate acknowledgement that perfection is amiss — things were almost certainly missed.
6dc12304 (dompipe 2026-08-25   4) 
6dc12304 (dompipe 2026-08-25   5) ---
6dc12304 (dompipe 2026-08-25   6) 
6dc12304 (dompipe 2026-08-25   7) ## 1. Origin and renaming
6dc12304 (dompipe 2026-08-25   8) 
6dc12304 (dompipe 2026-08-25   9) - Started from discussion of an Axis-like language and a VS Code extension.
6dc12304 (dompipe 2026-08-25  10) - "yaxis" / y-axis tables were examined for insert throughput impact (help / harm / confine).
6dc12304 (dompipe 2026-08-25  11) - Decision: replace manual y-tables with an adaptive ingest / write-buffer module.
6dc12304 (dompipe 2026-08-25  12) - Language renamed to **jx**, pronounced **jinx**, to avoid naming collisions.
6dc12304 (dompipe 2026-08-25  13) 
6dc12304 (dompipe 2026-08-25  14) ## 2. Native calls and assembler friendliness
6dc12304 (dompipe 2026-08-25  15) 
6dc12304 (dompipe 2026-08-25  16) - Major functions must be assembly-friendly (clear inputs/outputs).
6dc12304 (dompipe 2026-08-25  17) - Names should be rhetorical for common tasks.
6dc12304 (dompipe 2026-08-25  18) - Assembler surface needs symbolic constants with adamantly obvious names (`SYS_WRITE`, `STDOUT`, `O_CREAT`, `PROT_READ`, `MAP_PRIVATE`, etc.).
6dc12304 (dompipe 2026-08-25  19) - No magic numbers in source.
6dc12304 (dompipe 2026-08-25  20) 
6dc12304 (dompipe 2026-08-25  21) ## 3. Memory model (hard rule)
6dc12304 (dompipe 2026-08-25  22) 
6dc12304 (dompipe 2026-08-25  23) - **No memory writes by default.**
6dc12304 (dompipe 2026-08-25  24) - A write is legal only when:
6dc12304 (dompipe 2026-08-25  25)   1. A buffer of allowance is supplied,
6dc12304 (dompipe 2026-08-25  26)   2. It is handed to an underwritten bag,
6dc12304 (dompipe 2026-08-25  27)   3. Mutation occurs through an event handshake.
6dc12304 (dompipe 2026-08-25  28) - Docker-like isolation: memory must never leave the current jx process / container boundary.
6dc12304 (dompipe 2026-08-25  29) - Bags are the only mutable containers. Tasks are special Bags.
6dc12304 (dompipe 2026-08-25  30) 
6dc12304 (dompipe 2026-08-25  31) ## 4. Bag / Task surface evolution
6dc12304 (dompipe 2026-08-25  32) 
6dc12304 (dompipe 2026-08-25  33) - `Bag.underwrite(size)`
6dc12304 (dompipe 2026-08-25  34) - `bag.sign(node) → refSign`
6dc12304 (dompipe 2026-08-25  35) - `bag.unsign(refSign)` (later noted as potentially optional / automatic)
6dc12304 (dompipe 2026-08-25  36) - `bag.set(...).commit(ref)`, `bag.onchange(...).commit(ref)`, `bag.get(ref)`
6dc12304 (dompipe 2026-08-25  37) - Oversight: `bag.capacity()`, `bag.used()`, `bag.quotient()` (remaining space) to prevent overflows that could crash the server.
6dc12304 (dompipe 2026-08-25  38) - Task as special Bag: property preassignments + inner scoped variables.
6dc12304 (dompipe 2026-08-25  39) - `preassign` renamed to **`push`** (more agentic).
6dc12304 (dompipe 2026-08-25  40) - Tight methods are the real surface; verbose/placebo forms (`tell` / `pass`) lower exclusively to tight forms so the assembler only sees the clean calls.
6dc12304 (dompipe 2026-08-25  41) 
6dc12304 (dompipe 2026-08-25  42) ## 5. X11-like character and multitasking
6dc12304 (dompipe 2026-08-25  43) 
6dc12304 (dompipe 2026-08-25  44) - Design began to resemble X11: programs as pages, bags as displayable surfaces.
6dc12304 (dompipe 2026-08-25  45) - Multitasking required inside the server TaskHandler.
6dc12304 (dompipe 2026-08-25  46) - Task can report its own `task.id()`.
6dc12304 (dompipe 2026-08-25  47) - Task = special Bag unified allocation, signing, overflow protection, and execution context.
6dc12304 (dompipe 2026-08-25  48) 
6dc12304 (dompipe 2026-08-25  49) ## 6. Reflection on undeeded ref ceremony
6dc12304 (dompipe 2026-08-25  50) 
6dc12304 (dompipe 2026-08-25  51) Some ref-related operations felt heavy:
6dc12304 (dompipe 2026-08-25  52) - Explicit `unsign` on every path (could be scope-automatic).
6dc12304 (dompipe 2026-08-25  53) - Separate `.commit(ref)` step (could fold into the mutation call).
6dc12304 (dompipe 2026-08-25  54) - `get(ref)` when the ref already implies the region.
6dc12304 (dompipe 2026-08-25  55) - Two-step sign-then-write for one-shot cases.
6dc12304 (dompipe 2026-08-25  56) 
6dc12304 (dompipe 2026-08-25  57) These remain open for tightening.
6dc12304 (dompipe 2026-08-25  58) 
6dc12304 (dompipe 2026-08-25  59) ## 7. Rainbow / smart table and compilation posture
6dc12304 (dompipe 2026-08-25  60) 
6dc12304 (dompipe 2026-08-25  61) - Prefer a **smart table maker** over a static rainbow table.
6dc12304 (dompipe 2026-08-25  62) - Table knows every method, writing style, side-effect class, and preferred native lowering.
6dc12304 (dompipe 2026-08-25  63) - Extrudes fast native code when safe; otherwise emits **Resistant code** (correct, marked, lower purity).
6dc12304 (dompipe 2026-08-25  64) - Goal: an AI interpreter that knows every function can fasten jx to assembly with ease.
6dc12304 (dompipe 2026-08-25  65) - All code must be checked across compilers; edge cases must be protected.
6dc12304 (dompipe 2026-08-25  66) 
6dc12304 (dompipe 2026-08-25  67) ## 8. Full package steering (A–F)
6dc12304 (dompipe 2026-08-25  68) 
6dc12304 (dompipe 2026-08-25  69) - **A** — Platform staging treats Pages in an X11 state of memory.
6dc12304 (dompipe 2026-08-25  70) - **B** — Language is about compiled **Books** more than single pages. Ontology: Books, Bags, Pages.
6dc12304 (dompipe 2026-08-25  71) - **C** — Delivery (derivative apprehensives): `parent.child.subchild...` for deep extract/rebind.
6dc12304 (dompipe 2026-08-25  72) - **D** — `const` is a keyword and is castable.
6dc12304 (dompipe 2026-08-25  73) - **E** — Complex numbers are first-class (`3 + 4i`, `complex(re, im)`).
6dc12304 (dompipe 2026-08-25  74) - **F** — Built on PHP via a hosting module that embeds the original engine, expands with jx, isolates Book libraries under class + memory constraints. Server-side updates browser-side through a coherent protocol. Derivative strengths are the commodity; weaknesses are canonised and given controlled remediation at CLI and displayed surfaces.
6dc12304 (dompipe 2026-08-25  75) 
6dc12304 (dompipe 2026-08-25  76) ## 9. Edge-case morph (tests that stress Resistant code)
6dc12304 (dompipe 2026-08-25  77) 
6dc12304 (dompipe 2026-08-25  78) See also `tests/edge-cases.md`. Summary of the stress set:
6dc12304 (dompipe 2026-08-25  79) 
6dc12304 (dompipe 2026-08-25  80) 1. Deep Delivery into missing structure
6dc12304 (dompipe 2026-08-25  81) 2. Delivery into a `const` target
6dc12304 (dompipe 2026-08-25  82) 3. Quotient exhaustion on write
6dc12304 (dompipe 2026-08-25  83) 4. Sign / unsign races under concurrency
6dc12304 (dompipe 2026-08-25  84) 5. Complex edge values (overflow / inf)
6dc12304 (dompipe 2026-08-25  85) 6. Const-cast violations
6dc12304 (dompipe 2026-08-25  86) 7. Hostile dynamic shapes between path resolution and use
6dc12304 (dompipe 2026-08-25  87) 8. One-shot sign-and-write under low quotient
6dc12304 (dompipe 2026-08-25  88) 9. Task `push` / mutation from another Page without proper ref
6dc12304 (dompipe 2026-08-25  89) 10. Resistant regions must remain introspectable
6dc12304 (dompipe 2026-08-25  90) 
6dc12304 (dompipe 2026-08-25  91) ---
6dc12304 (dompipe 2026-08-25  92) 
6dc12304 (dompipe 2026-08-25  93) ## 10. Perfection is amiss — what we may have missed
6dc12304 (dompipe 2026-08-25  94) 
6dc12304 (dompipe 2026-08-25  95) Perfection is amiss. The conversation moved quickly across renaming, memory law, X11 resonance, multitasking, smart tables, PHP foundation, and edge cases. The following are known or likely gaps; they are recorded so they are not silently forgotten.
6dc12304 (dompipe 2026-08-25  96) 
6dc12304 (dompipe 2026-08-25  97) ### Likely missed or under-specified
6dc12304 (dompipe 2026-08-25  98) 
6dc12304 (dompipe 2026-08-25  99) - **Exact handshake protocol** — request/ack/commit wire shape, failure modes, and whether partial commits are ever visible.
6dc12304 (dompipe 2026-08-25 100) - **RefSign representation and forge resistance** — how a ref is implemented so it cannot be guessed or manufactured outside the TaskHandler.
6dc12304 (dompipe 2026-08-25 101) - **Automatic vs explicit unsign** — final rule for lifetime of refs (scope exit, bag drop, explicit only).
6dc12304 (dompipe 2026-08-25 102) - **One-shot sign-and-write sugar** — whether a single call may both sign and mutate.
6dc12304 (dompipe 2026-08-25 103) - **Scheduling policy** — cooperative only, preemptive slices, priorities, fairness across Pages.
6dc12304 (dompipe 2026-08-25 104) - **Book versioning and hot reload** — how a running Book is replaced without tearing Pages.
6dc12304 (dompipe 2026-08-25 105) - **Browser-side protocol** — concrete messages for server → browser surface updates.
6dc12304 (dompipe 2026-08-25 106) - **Error model** — structured errors vs exceptions vs status codes; interaction with Resistant code.
6dc12304 (dompipe 2026-08-25 107) - **Const propagation depth** — how far `const` and cast-const flow through Delivery and complex ops.
6dc12304 (dompipe 2026-08-25 108) - **Complex + Delivery + Bag interaction** — storing complex values inside signed regions, alignment, and quotient accounting.
6dc12304 (dompipe 2026-08-25 109) - **PHP interop boundary** — which PHP values may cross into jx Bags and under what copying / signing rules.
6dc12304 (dompipe 2026-08-25 110) - **AI interpreter state** — how an AI instance keeps the smart table and live Bag/Task state coherent across turns.
6dc12304 (dompipe 2026-08-25 111) - **Testing of the tester** — who verifies that Resistant markers are actually present and that edge-case tests fail closed.
6dc12304 (dompipe 2026-08-25 112) 
6dc12304 (dompipe 2026-08-25 113) ### Attitude
6dc12304 (dompipe 2026-08-25 114) 
6dc12304 (dompipe 2026-08-25 115) These gaps are not failures; they are the natural residue of a design that prioritised coherent direction over exhaustive closure in one pass. Future work should close them deliberately, one at a time, without weakening the memory law or the Book/Bag/Page ontology.
6dc12304 (dompipe 2026-08-25 116) 
6dc12304 (dompipe 2026-08-25 117) ---
6dc12304 (dompipe 2026-08-25 118) 
6dc12304 (dompipe 2026-08-25 119) *End of conversation log. Perfection is amiss; the work continues.*
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# jx Design Conversation Log

This document captures the design conversation that produced the jx (jinx) language specification, from the early Axis/yaxis discussion through Books, Bags, Pages, the smart table maker, Delivery, complex numbers, and the edge-case posture. It ends with a deliberate acknowledgement that perfection is amiss — things were almost certainly missed.

---

## 1. Origin and renaming

- Started from discussion of an Axis-like language and a VS Code extension.
- "yaxis" / y-axis tables were examined for insert throughput impact (help / harm / confine).
- Decision: replace manual y-tables with an adaptive ingest / write-buffer module.
- Language renamed to **jx**, pronounced **jinx**, to avoid naming collisions.

## 2. Native calls and assembler friendliness

- Major functions must be assembly-friendly (clear inputs/outputs).
- Names should be rhetorical for common tasks.
- Assembler surface needs symbolic constants with adamantly obvious names (`SYS_WRITE`, `STDOUT`, `O_CREAT`, `PROT_READ`, `MAP_PRIVATE`, etc.).
- No magic numbers in source.

## 3. Memory model (hard rule)

- **No memory writes by default.**
- A write is legal only when:
  1. A buffer of allowance is supplied,
  2. It is handed to an underwritten bag,
  3. Mutation occurs through an event handshake.
- Docker-like isolation: memory must never leave the current jx process / container boundary.
- Bags are the only mutable containers. Tasks are special Bags.

## 4. Bag / Task surface evolution

- `Bag.underwrite(size)`
- `bag.sign(node) → refSign`
- `bag.unsign(refSign)` (later noted as potentially optional / automatic)
- `bag.set(...).commit(ref)`, `bag.onchange(...).commit(ref)`, `bag.get(ref)`
- Oversight: `bag.capacity()`, `bag.used()`, `bag.quotient()` (remaining space) to prevent overflows that could crash the server.
- Task as special Bag: property preassignments + inner scoped variables.
- `preassign` renamed to **`push`** (more agentic).
- Tight methods are the real surface; verbose/placebo forms (`tell` / `pass`) lower exclusively to tight forms so the assembler only sees the clean calls.

## 5. X11-like character and multitasking

- Design began to resemble X11: programs as pages, bags as displayable surfaces.
- Multitasking required inside the server TaskHandler.
- Task can report its own `task.id()`.
- Task = special Bag unified allocation, signing, overflow protection, and execution context.

## 6. Reflection on undeeded ref ceremony

Some ref-related operations felt heavy:
- Explicit `unsign` on every path (could be scope-automatic).
- Separate `.commit(ref)` step (could fold into the mutation call).
- `get(ref)` when the ref already implies the region.
- Two-step sign-then-write for one-shot cases.

These remain open for tightening.

## 7. Rainbow / smart table and compilation posture

- Prefer a **smart table maker** over a static rainbow table.
- Table knows every method, writing style, side-effect class, and preferred native lowering.
- Extrudes fast native code when safe; otherwise emits **Resistant code** (correct, marked, lower purity).
- Goal: an AI interpreter that knows every function can fasten jx to assembly with ease.
- All code must be checked across compilers; edge cases must be protected.

## 8. Full package steering (A–F)

- **A** — Platform staging treats Pages in an X11 state of memory.
- **B** — Language is about compiled **Books** more than single pages. Ontology: Books, Bags, Pages.
- **C** — Delivery (derivative apprehensives): `parent.child.subchild...` for deep extract/rebind.
- **D** — `const` is a keyword and is castable.
- **E** — Complex numbers are first-class (`3 + 4i`, `complex(re, im)`).
- **F** — Built on PHP via a hosting module that embeds the original engine, expands with jx, isolates Book libraries under class + memory constraints. Server-side updates browser-side through a coherent protocol. Derivative strengths are the commodity; weaknesses are canonised and given controlled remediation at CLI and displayed surfaces.

## 9. Edge-case morph (tests that stress Resistant code)

See also `tests/edge-cases.md`. Summary of the stress set:

1. Deep Delivery into missing structure
2. Delivery into a `const` target
3. Quotient exhaustion on write
4. Sign / unsign races under concurrency
5. Complex edge values (overflow / inf)
6. Const-cast violations
7. Hostile dynamic shapes between path resolution and use
8. One-shot sign-and-write under low quotient
9. Task `push` / mutation from another Page without proper ref
10. Resistant regions must remain introspectable

---

## 10. Perfection is amiss — what we may have missed

Perfection is amiss. The conversation moved quickly across renaming, memory law, X11 resonance, multitasking, smart tables, PHP foundation, and edge cases. The following are known or likely gaps; they are recorded so they are not silently forgotten.

### Likely missed or under-specified

- **Exact handshake protocol** — request/ack/commit wire shape, failure modes, and whether partial commits are ever visible.
- **RefSign representation and forge resistance** — how a ref is implemented so it cannot be guessed or manufactured outside the TaskHandler.
- **Automatic vs explicit unsign** — final rule for lifetime of refs (scope exit, bag drop, explicit only).
- **One-shot sign-and-write sugar** — whether a single call may both sign and mutate.
- **Scheduling policy** — cooperative only, preemptive slices, priorities, fairness across Pages.
- **Book versioning and hot reload** — how a running Book is replaced without tearing Pages.
- **Browser-side protocol** — concrete messages for server → browser surface updates.
- **Error model** — structured errors vs exceptions vs status codes; interaction with Resistant code.
- **Const propagation depth** — how far `const` and cast-const flow through Delivery and complex ops.
- **Complex + Delivery + Bag interaction** — storing complex values inside signed regions, alignment, and quotient accounting.
- **PHP interop boundary** — which PHP values may cross into jx Bags and under what copying / signing rules.
- **AI interpreter state** — how an AI instance keeps the smart table and live Bag/Task state coherent across turns.
- **Testing of the tester** — who verifies that Resistant markers are actually present and that edge-case tests fail closed.

### Attitude

These gaps are not failures; they are the natural residue of a design that prioritised coherent direction over exhaustive closure in one pass. Future work should close them deliberately, one at a time, without weakening the memory law or the Book/Bag/Page ontology.

---

*End of conversation log. Perfection is amiss; the work continues.*
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# jx Design Conversation Log

This document captures the design conversation that produced the jx (jinx) language specification, from the early Axis/yaxis discussion through Books, Bags, Pages, the smart table maker, Delivery, complex numbers, and the edge-case posture. It ends with a deliberate acknowledgement that perfection is amiss — things were almost certainly missed.

---

## 1. Origin and renaming

- Started from discussion of an Axis-like language and a VS Code extension.
- "yaxis" / y-axis tables were examined for insert throughput impact (help / harm / confine).
- Decision: replace manual y-tables with an adaptive ingest / write-buffer module.
- Language renamed to **jx**, pronounced **jinx**, to avoid naming collisions.

## 2. Native calls and assembler friendliness

- Major functions must be assembly-friendly (clear inputs/outputs).
- Names should be rhetorical for common tasks.
- Assembler surface needs symbolic constants with adamantly obvious names (`SYS_WRITE`, `STDOUT`, `O_CREAT`, `PROT_READ`, `MAP_PRIVATE`, etc.).
- No magic numbers in source.

## 3. Memory model (hard rule)

- **No memory writes by default.**
- A write is legal only when:
  1. A buffer of allowance is supplied,
  2. It is handed to an underwritten bag,
  3. Mutation occurs through an event handshake.
- Docker-like isolation: memory must never leave the current jx process / container boundary.
- Bags are the only mutable containers. Tasks are special Bags.

## 4. Bag / Task surface evolution

- `Bag.underwrite(size)`
- `bag.sign(node) → refSign`
- `bag.unsign(refSign)` (later noted as potentially optional / automatic)
- `bag.set(...).commit(ref)`, `bag.onchange(...).commit(ref)`, `bag.get(ref)`
- Oversight: `bag.capacity()`, `bag.used()`, `bag.quotient()` (remaining space) to prevent overflows that could crash the server.
- Task as special Bag: property preassignments + inner scoped variables.
- `preassign` renamed to **`push`** (more agentic).
- Tight methods are the real surface; verbose/placebo forms (`tell` / `pass`) lower exclusively to tight forms so the assembler only sees the clean calls.

## 5. X11-like character and multitasking

- Design began to resemble X11: programs as pages, bags as displayable surfaces.
- Multitasking required inside the server TaskHandler.
- Task can report its own `task.id()`.
- Task = special Bag unified allocation, signing, overflow protection, and execution context.

## 6. Reflection on undeeded ref ceremony

Some ref-related operations felt heavy:
- Explicit `unsign` on every path (could be scope-automatic).
- Separate `.commit(ref)` step (could fold into the mutation call).
- `get(ref)` when the ref already implies the region.
- Two-step sign-then-write for one-shot cases.

These remain open for tightening.

## 7. Rainbow / smart table and compilation posture

- Prefer a **smart table maker** over a static rainbow table.
- Table knows every method, writing style, side-effect class, and preferred native lowering.
- Extrudes fast native code when safe; otherwise emits **Resistant code** (correct, marked, lower purity).
- Goal: an AI interpreter that knows every function can fasten jx to assembly with ease.
- All code must be checked across compilers; edge cases must be protected.

## 8. Full package steering (A–F)

- **A** — Platform staging treats Pages in an X11 state of memory.
- **B** — Language is about compiled **Books** more than single pages. Ontology: Books, Bags, Pages.
- **C** — Delivery (derivative apprehensives): `parent.child.subchild...` for deep extract/rebind.
- **D** — `const` is a keyword and is castable.
- **E** — Complex numbers are first-class (`3 + 4i`, `complex(re, im)`).
- **F** — Built on PHP via a hosting module that embeds the original engine, expands with jx, isolates Book libraries under class + memory constraints. Server-side updates browser-side through a coherent protocol. Derivative strengths are the commodity; weaknesses are canonised and given controlled remediation at CLI and displayed surfaces.

## 9. Edge-case morph (tests that stress Resistant code)

See also `tests/edge-cases.md`. Summary of the stress set:

1. Deep Delivery into missing structure
2. Delivery into a `const` target
3. Quotient exhaustion on write
4. Sign / unsign races under concurrency
5. Complex edge values (overflow / inf)
6. Const-cast violations
7. Hostile dynamic shapes between path resolution and use
8. One-shot sign-and-write under low quotient
9. Task `push` / mutation from another Page without proper ref
10. Resistant regions must remain introspectable

---

## 10. Perfection is amiss — what we may have missed

Perfection is amiss. The conversation moved quickly across renaming, memory law, X11 resonance, multitasking, smart tables, PHP foundation, and edge cases. The following are known or likely gaps; they are recorded so they are not silently forgotten.

### Likely missed or under-specified

- **Exact handshake protocol** — request/ack/commit wire shape, failure modes, and whether partial commits are ever visible.
- **RefSign representation and forge resistance** — how a ref is implemented so it cannot be guessed or manufactured outside the TaskHandler.
- **Automatic vs explicit unsign** — final rule for lifetime of refs (scope exit, bag drop, explicit only).
- **One-shot sign-and-write sugar** — whether a single call may both sign and mutate.
- **Scheduling policy** — cooperative only, preemptive slices, priorities, fairness across Pages.
- **Book versioning and hot reload** — how a running Book is replaced without tearing Pages.
- **Browser-side protocol** — concrete messages for server → browser surface updates.
- **Error model** — structured errors vs exceptions vs status codes; interaction with Resistant code.
- **Const propagation depth** — how far `const` and cast-const flow through Delivery and complex ops.
- **Complex + Delivery + Bag interaction** — storing complex values inside signed regions, alignment, and quotient accounting.
- **PHP interop boundary** — which PHP values may cross into jx Bags and under what copying / signing rules.
- **AI interpreter state** — how an AI instance keeps the smart table and live Bag/Task state coherent across turns.
- **Testing of the tester** — who verifies that Resistant markers are actually present and that edge-case tests fail closed.

### Attitude

These gaps are not failures; they are the natural residue of a design that prioritised coherent direction over exhaustive closure in one pass. Future work should close them deliberately, one at a time, without weakening the memory law or the Book/Bag/Page ontology.

---

*End of conversation log. Perfection is amiss; the work continues.*
```

</details>

### `docs/GAPS.md`

- Current lines: 33
- Original reachable commit: `6dc1230` 2026-08-25T20:16:11-04:00 dompipe: Add full design conversation log and reflective gaps (perfection is amiss)
- Latest Markdown-touching commit: `6dc1230` 2026-08-25T20:16:11-04:00 dompipe: Add full design conversation log and reflective gaps (perfection is amiss)
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 33
- Latest blame by commit:
  - `6dc1230` 33 lines dompipe: Add full design conversation log and reflective gaps (perfection is amiss)

<details>
<summary>Latest line blame</summary>

```markdown
6dc12304 (dompipe 2026-08-25  1) # Known Gaps — Perfection Is Amiss
6dc12304 (dompipe 2026-08-25  2) 
6dc12304 (dompipe 2026-08-25  3) This file is the living list of things the design conversation did not fully close. It exists so that "we almost certainly missed something" is an explicit, tracked fact rather than a vague worry.
6dc12304 (dompipe 2026-08-25  4) 
6dc12304 (dompipe 2026-08-25  5) ## High priority
6dc12304 (dompipe 2026-08-25  6) 
6dc12304 (dompipe 2026-08-25  7) 1. **Handshake protocol** — exact phases, error paths, visibility of partial mutation.
6dc12304 (dompipe 2026-08-25  8) 2. **RefSign security** — representation, unforgeability, cross-Task leakage prevention.
6dc12304 (dompipe 2026-08-25  9) 3. **Ref lifetime** — automatic unsign on scope/bag drop vs mandatory explicit unsign.
6dc12304 (dompipe 2026-08-25 10) 4. **Scheduling policy** — cooperative / preemptive / priorities for Pages.
6dc12304 (dompipe 2026-08-25 11) 5. **Server → browser protocol** — concrete messages and coherence guarantees.
6dc12304 (dompipe 2026-08-25 12) 
6dc12304 (dompipe 2026-08-25 13) ## Medium priority
6dc12304 (dompipe 2026-08-25 14) 
6dc12304 (dompipe 2026-08-25 15) 6. Book versioning and hot-reload semantics.
6dc12304 (dompipe 2026-08-25 16) 7. Error model (structured errors, interaction with Resistant code).
6dc12304 (dompipe 2026-08-25 17) 8. Const propagation rules through Delivery and complex operations.
6dc12304 (dompipe 2026-08-25 18) 9. PHP ↔ jx value crossing rules (copy, sign, quotient impact).
6dc12304 (dompipe 2026-08-25 19) 10. Complex values inside Bags (alignment, size accounting).
6dc12304 (dompipe 2026-08-25 20) 
6dc12304 (dompipe 2026-08-25 21) ## Lower priority / process
6dc12304 (dompipe 2026-08-25 22) 
6dc12304 (dompipe 2026-08-25 23) 11. One-shot sign-and-write convenience form.
6dc12304 (dompipe 2026-08-25 24) 12. AI interpreter coherence of smart table + live state.
6dc12304 (dompipe 2026-08-25 25) 13. Meta-testing: proof that Resistant markers are emitted and that edge tests fail closed.
6dc12304 (dompipe 2026-08-25 26) 
6dc12304 (dompipe 2026-08-25 27) ## Rule for closing a gap
6dc12304 (dompipe 2026-08-25 28) 
6dc12304 (dompipe 2026-08-25 29) - Write the decision into the relevant `docs/*.md` or `SPEC.md`.
6dc12304 (dompipe 2026-08-25 30) - Add or adjust an edge-case test if the gap touches safety or Resistant behaviour.
6dc12304 (dompipe 2026-08-25 31) - Remove or strike the item from this file only when the above is done.
6dc12304 (dompipe 2026-08-25 32) 
6dc12304 (dompipe 2026-08-25 33) Perfection is amiss. Tracking the amiss is how we stay honest.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# Known Gaps — Perfection Is Amiss

This file is the living list of things the design conversation did not fully close. It exists so that "we almost certainly missed something" is an explicit, tracked fact rather than a vague worry.

## High priority

1. **Handshake protocol** — exact phases, error paths, visibility of partial mutation.
2. **RefSign security** — representation, unforgeability, cross-Task leakage prevention.
3. **Ref lifetime** — automatic unsign on scope/bag drop vs mandatory explicit unsign.
4. **Scheduling policy** — cooperative / preemptive / priorities for Pages.
5. **Server → browser protocol** — concrete messages and coherence guarantees.

## Medium priority

6. Book versioning and hot-reload semantics.
7. Error model (structured errors, interaction with Resistant code).
8. Const propagation rules through Delivery and complex operations.
9. PHP ↔ jx value crossing rules (copy, sign, quotient impact).
10. Complex values inside Bags (alignment, size accounting).

## Lower priority / process

11. One-shot sign-and-write convenience form.
12. AI interpreter coherence of smart table + live state.
13. Meta-testing: proof that Resistant markers are emitted and that edge tests fail closed.

## Rule for closing a gap

- Write the decision into the relevant `docs/*.md` or `SPEC.md`.
- Add or adjust an edge-case test if the gap touches safety or Resistant behaviour.
- Remove or strike the item from this file only when the above is done.

Perfection is amiss. Tracking the amiss is how we stay honest.
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# Known Gaps — Perfection Is Amiss

This file is the living list of things the design conversation did not fully close. It exists so that "we almost certainly missed something" is an explicit, tracked fact rather than a vague worry.

## High priority

1. **Handshake protocol** — exact phases, error paths, visibility of partial mutation.
2. **RefSign security** — representation, unforgeability, cross-Task leakage prevention.
3. **Ref lifetime** — automatic unsign on scope/bag drop vs mandatory explicit unsign.
4. **Scheduling policy** — cooperative / preemptive / priorities for Pages.
5. **Server → browser protocol** — concrete messages and coherence guarantees.

## Medium priority

6. Book versioning and hot-reload semantics.
7. Error model (structured errors, interaction with Resistant code).
8. Const propagation rules through Delivery and complex operations.
9. PHP ↔ jx value crossing rules (copy, sign, quotient impact).
10. Complex values inside Bags (alignment, size accounting).

## Lower priority / process

11. One-shot sign-and-write convenience form.
12. AI interpreter coherence of smart table + live state.
13. Meta-testing: proof that Resistant markers are emitted and that edge tests fail closed.

## Rule for closing a gap

- Write the decision into the relevant `docs/*.md` or `SPEC.md`.
- Add or adjust an edge-case test if the gap touches safety or Resistant behaviour.
- Remove or strike the item from this file only when the above is done.

Perfection is amiss. Tracking the amiss is how we stay honest.
```

</details>

### `docs/complex.md`

- Current lines: 36
- Original reachable commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Latest Markdown-touching commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 36
- Latest blame by commit:
  - `8c52595` 36 lines dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests

<details>
<summary>Latest line blame</summary>

````markdown
8c52595a (dompipe 2026-08-25  1) # Complex Numbers
8c52595a (dompipe 2026-08-25  2) 
8c52595a (dompipe 2026-08-25  3) Complex numbers are first-class in jx.
8c52595a (dompipe 2026-08-25  4) 
8c52595a (dompipe 2026-08-25  5) ## Literals and Construction
8c52595a (dompipe 2026-08-25  6) 
8c52595a (dompipe 2026-08-25  7) ```jx
8c52595a (dompipe 2026-08-25  8) c1 = 3 + 4i
8c52595a (dompipe 2026-08-25  9) c2 = complex(3, 4)
8c52595a (dompipe 2026-08-25 10) c3 = 2.5 - 1.25i
8c52595a (dompipe 2026-08-25 11) ```
8c52595a (dompipe 2026-08-25 12) 
8c52595a (dompipe 2026-08-25 13) ## Operations
8c52595a (dompipe 2026-08-25 14) 
8c52595a (dompipe 2026-08-25 15) - Arithmetic: `+`, `-`, `*`, `/`
8c52595a (dompipe 2026-08-25 16) - Conjugate: `c.conj` or `conj(c)`
8c52595a (dompipe 2026-08-25 17) - Polar: `c.mag`, `c.arg`, `from_polar(r, theta)`
8c52595a (dompipe 2026-08-25 18) - Components: `c.re`, `c.im`
8c52595a (dompipe 2026-08-25 19) 
8c52595a (dompipe 2026-08-25 20) ## Native Representation
8c52595a (dompipe 2026-08-25 21) 
8c52595a (dompipe 2026-08-25 22) Preferred layout (platform-dependent, chosen by smart table):
8c52595a (dompipe 2026-08-25 23) 
8c52595a (dompipe 2026-08-25 24) - Two consecutive floats / doubles (re, im) in registers or stack slots when possible.
8c52595a (dompipe 2026-08-25 25) - Or a small struct `{ re: f64, im: f64 }` with known ABI.
8c52595a (dompipe 2026-08-25 26) 
8c52595a (dompipe 2026-08-25 27) The smart table entries for complex arithmetic carry both a pure native_template (SIMD or scalar) and a Resistant scalar fallback.
8c52595a (dompipe 2026-08-25 28) 
8c52595a (dompipe 2026-08-25 29) ## Const and Delivery
8c52595a (dompipe 2026-08-25 30) 
8c52595a (dompipe 2026-08-25 31) ```jx
8c52595a (dompipe 2026-08-25 32) const origin = 0 + 0i
8c52595a (dompipe 2026-08-25 33) val = transform.matrix.scale.delivery()   // may be complex
8c52595a (dompipe 2026-08-25 34) ```
8c52595a (dompipe 2026-08-25 35) 
8c52595a (dompipe 2026-08-25 36) Complex values obey the same `const` and memory rules as any other data.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# Complex Numbers

Complex numbers are first-class in jx.

## Literals and Construction

```jx
c1 = 3 + 4i
c2 = complex(3, 4)
c3 = 2.5 - 1.25i
```

## Operations

- Arithmetic: `+`, `-`, `*`, `/`
- Conjugate: `c.conj` or `conj(c)`
- Polar: `c.mag`, `c.arg`, `from_polar(r, theta)`
- Components: `c.re`, `c.im`

## Native Representation

Preferred layout (platform-dependent, chosen by smart table):

- Two consecutive floats / doubles (re, im) in registers or stack slots when possible.
- Or a small struct `{ re: f64, im: f64 }` with known ABI.

The smart table entries for complex arithmetic carry both a pure native_template (SIMD or scalar) and a Resistant scalar fallback.

## Const and Delivery

```jx
const origin = 0 + 0i
val = transform.matrix.scale.delivery()   // may be complex
```

Complex values obey the same `const` and memory rules as any other data.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# Complex Numbers

Complex numbers are first-class in jx.

## Literals and Construction

```jx
c1 = 3 + 4i
c2 = complex(3, 4)
c3 = 2.5 - 1.25i
```

## Operations

- Arithmetic: `+`, `-`, `*`, `/`
- Conjugate: `c.conj` or `conj(c)`
- Polar: `c.mag`, `c.arg`, `from_polar(r, theta)`
- Components: `c.re`, `c.im`

## Native Representation

Preferred layout (platform-dependent, chosen by smart table):

- Two consecutive floats / doubles (re, im) in registers or stack slots when possible.
- Or a small struct `{ re: f64, im: f64 }` with known ABI.

The smart table entries for complex arithmetic carry both a pure native_template (SIMD or scalar) and a Resistant scalar fallback.

## Const and Delivery

```jx
const origin = 0 + 0i
val = transform.matrix.scale.delivery()   // may be complex
```

Complex values obey the same `const` and memory rules as any other data.
````

</details>

### `docs/delivery.md`

- Current lines: 41
- Original reachable commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Latest Markdown-touching commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 41
- Latest blame by commit:
  - `8c52595` 41 lines dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests

<details>
<summary>Latest line blame</summary>

````markdown
8c52595a (dompipe 2026-08-25  1) # Delivery (Derivative Apprehensives)
8c52595a (dompipe 2026-08-25  2) 
8c52595a (dompipe 2026-08-25  3) Delivery is the deep-path operator that makes nested structure addressable without manual traversal boilerplate.
8c52595a (dompipe 2026-08-25  4) 
8c52595a (dompipe 2026-08-25  5) ## Syntax
8c52595a (dompipe 2026-08-25  6) 
8c52595a (dompipe 2026-08-25  7) ### Extract
8c52595a (dompipe 2026-08-25  8) ```jx
8c52595a (dompipe 2026-08-25  9) val = config.server.ports.https.delivery()
8c52595a (dompipe 2026-08-25 10) val = config.server.ports.https.delivery(default = 443)
8c52595a (dompipe 2026-08-25 11) ```
8c52595a (dompipe 2026-08-25 12) 
8c52595a (dompipe 2026-08-25 13) ### Rebind / assign into
8c52595a (dompipe 2026-08-25 14) ```jx
8c52595a (dompipe 2026-08-25 15) newVar.delivery(config.server.ports.https)
8c52595a (dompipe 2026-08-25 16) existing.delivery(config.server.ports.https)
8c52595a (dompipe 2026-08-25 17) ```
8c52595a (dompipe 2026-08-25 18) 
8c52595a (dompipe 2026-08-25 19) ### Path form (explicit)
8c52595a (dompipe 2026-08-25 20) ```jx
8c52595a (dompipe 2026-08-25 21) val = delivery(config, ["server", "ports", "https"])
8c52595a (dompipe 2026-08-25 22) ```
8c52595a (dompipe 2026-08-25 23) 
8c52595a (dompipe 2026-08-25 24) ## Semantics
8c52595a (dompipe 2026-08-25 25) 
8c52595a (dompipe 2026-08-25 26) - The path is checked statically when fully constant; otherwise runtime checks are inserted.
8c52595a (dompipe 2026-08-25 27) - Missing intermediate nodes produce a controlled error (or the provided default).
8c52595a (dompipe 2026-08-25 28) - Delivery never performs a free memory write. If the target is inside a Bag, a proper sign + handshake is still required for mutation.
8c52595a (dompipe 2026-08-25 29) - Delivery into a `const` target is rejected.
8c52595a (dompipe 2026-08-25 30) 
8c52595a (dompipe 2026-08-25 31) ## Lowering
8c52595a (dompipe 2026-08-25 32) 
8c52595a (dompipe 2026-08-25 33) - Constant paths → direct offset / field loads (native_template).
8c52595a (dompipe 2026-08-25 34) - Dynamic paths → bounds- and existence-checked loop or recursive helper (often Resistant).
8c52595a (dompipe 2026-08-25 35) - The smart table entry `delivery.extract` / `delivery.rebind` decides which template to extrude.
8c52595a (dompipe 2026-08-25 36) 
8c52595a (dompipe 2026-08-25 37) ## Verbose form
8c52595a (dompipe 2026-08-25 38) ```jx
8c52595a (dompipe 2026-08-25 39) val = tell(delivery, config.server.ports.https)
8c52595a (dompipe 2026-08-25 40) ```
8c52595a (dompipe 2026-08-25 41) Lowers to the tight form above.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# Delivery (Derivative Apprehensives)

Delivery is the deep-path operator that makes nested structure addressable without manual traversal boilerplate.

## Syntax

### Extract
```jx
val = config.server.ports.https.delivery()
val = config.server.ports.https.delivery(default = 443)
```

### Rebind / assign into
```jx
newVar.delivery(config.server.ports.https)
existing.delivery(config.server.ports.https)
```

### Path form (explicit)
```jx
val = delivery(config, ["server", "ports", "https"])
```

## Semantics

- The path is checked statically when fully constant; otherwise runtime checks are inserted.
- Missing intermediate nodes produce a controlled error (or the provided default).
- Delivery never performs a free memory write. If the target is inside a Bag, a proper sign + handshake is still required for mutation.
- Delivery into a `const` target is rejected.

## Lowering

- Constant paths → direct offset / field loads (native_template).
- Dynamic paths → bounds- and existence-checked loop or recursive helper (often Resistant).
- The smart table entry `delivery.extract` / `delivery.rebind` decides which template to extrude.

## Verbose form
```jx
val = tell(delivery, config.server.ports.https)
```
Lowers to the tight form above.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# Delivery (Derivative Apprehensives)

Delivery is the deep-path operator that makes nested structure addressable without manual traversal boilerplate.

## Syntax

### Extract
```jx
val = config.server.ports.https.delivery()
val = config.server.ports.https.delivery(default = 443)
```

### Rebind / assign into
```jx
newVar.delivery(config.server.ports.https)
existing.delivery(config.server.ports.https)
```

### Path form (explicit)
```jx
val = delivery(config, ["server", "ports", "https"])
```

## Semantics

- The path is checked statically when fully constant; otherwise runtime checks are inserted.
- Missing intermediate nodes produce a controlled error (or the provided default).
- Delivery never performs a free memory write. If the target is inside a Bag, a proper sign + handshake is still required for mutation.
- Delivery into a `const` target is rejected.

## Lowering

- Constant paths → direct offset / field loads (native_template).
- Dynamic paths → bounds- and existence-checked loop or recursive helper (often Resistant).
- The smart table entry `delivery.extract` / `delivery.rebind` decides which template to extrude.

## Verbose form
```jx
val = tell(delivery, config.server.ports.https)
```
Lowers to the tight form above.
````

</details>

### `docs/hosting-api.md`

- Current lines: 53
- Original reachable commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Latest Markdown-touching commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 53
- Latest blame by commit:
  - `8c52595` 53 lines dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests

<details>
<summary>Latest line blame</summary>

````markdown
8c52595a (dompipe 2026-08-25  1) # Hosting Module API — Book / Page / Bag
8c52595a (dompipe 2026-08-25  2) 
8c52595a (dompipe 2026-08-25  3) The hosting module embeds the original PHP engine, expands it with jx, and loads Books under isolation.
8c52595a (dompipe 2026-08-25  4) 
8c52595a (dompipe 2026-08-25  5) ## Book
8c52595a (dompipe 2026-08-25  6) 
8c52595a (dompipe 2026-08-25  7) ```jx
8c52595a (dompipe 2026-08-25  8) book = Book.load(path)              // load compiled Book
8c52595a (dompipe 2026-08-25  9) book = Book.compile(source)         // compile then load
8c52595a (dompipe 2026-08-25 10) Book.unload(book)
8c52595a (dompipe 2026-08-25 11) 
8c52595a (dompipe 2026-08-25 12) entries = book.entries()
8c52595a (dompipe 2026-08-25 13) page    = book.page(name)
8c52595a (dompipe 2026-08-25 14) ```
8c52595a (dompipe 2026-08-25 15) 
8c52595a (dompipe 2026-08-25 16) Each Book receives:
8c52595a (dompipe 2026-08-25 17) - its own class namespace projection
8c52595a (dompipe 2026-08-25 18) - a hard memory quota (Docker-like)
8c52595a (dompipe 2026-08-25 19) - isolated Bags / Pages
8c52595a (dompipe 2026-08-25 20) 
8c52595a (dompipe 2026-08-25 21) ## Page
8c52595a (dompipe 2026-08-25 22) 
8c52595a (dompipe 2026-08-25 23) ```jx
8c52595a (dompipe 2026-08-25 24) page = Page.spawn(entryFunc, initialBag?)
8c52595a (dompipe 2026-08-25 25) page = book.page("main")
8c52595a (dompipe 2026-08-25 26) 
8c52595a (dompipe 2026-08-25 27) Page.yield()
8c52595a (dompipe 2026-08-25 28) Page.sleep(ms)
8c52595a (dompipe 2026-08-25 29) id = page.id()                      // Page is also a Task/Bag
8c52595a (dompipe 2026-08-25 30) ```
8c52595a (dompipe 2026-08-25 31) 
8c52595a (dompipe 2026-08-25 32) Pages live in an X11-style memory state managed by the TaskHandler.
8c52595a (dompipe 2026-08-25 33) 
8c52595a (dompipe 2026-08-25 34) ## Bag / Task
8c52595a (dompipe 2026-08-25 35) 
8c52595a (dompipe 2026-08-25 36) ```jx
8c52595a (dompipe 2026-08-25 37) bag  = Bag.underwrite(size)
8c52595a (dompipe 2026-08-25 38) task = Task.underwrite(size)        // Task is a special Bag
8c52595a (dompipe 2026-08-25 39) 
8c52595a (dompipe 2026-08-25 40) task.push(key, value)               // preassignment
8c52595a (dompipe 2026-08-25 41) ref  = task.sign(node)
8c52595a (dompipe 2026-08-25 42) task.set(data).commit(ref)
8c52595a (dompipe 2026-08-25 43) task.unsign(ref)                    // optional; often automatic on scope exit
8c52595a (dompipe 2026-08-25 44) 
8c52595a (dompipe 2026-08-25 45) remaining = task.quotient()
8c52595a (dompipe 2026-08-25 46) used      = task.used()
8c52595a (dompipe 2026-08-25 47) cap       = task.capacity()
8c52595a (dompipe 2026-08-25 48) id        = task.id()
8c52595a (dompipe 2026-08-25 49) ```
8c52595a (dompipe 2026-08-25 50) 
8c52595a (dompipe 2026-08-25 51) ## Protocol Note
8c52595a (dompipe 2026-08-25 52) 
8c52595a (dompipe 2026-08-25 53) The hosting module owns the coherent channel by which server-side Book state can update browser-side surfaces. Exact wire format is future work; the invariant is that the same Book description can drive both sides without a divisive split.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# Hosting Module API — Book / Page / Bag

The hosting module embeds the original PHP engine, expands it with jx, and loads Books under isolation.

## Book

```jx
book = Book.load(path)              // load compiled Book
book = Book.compile(source)         // compile then load
Book.unload(book)

entries = book.entries()
page    = book.page(name)
```

Each Book receives:
- its own class namespace projection
- a hard memory quota (Docker-like)
- isolated Bags / Pages

## Page

```jx
page = Page.spawn(entryFunc, initialBag?)
page = book.page("main")

Page.yield()
Page.sleep(ms)
id = page.id()                      // Page is also a Task/Bag
```

Pages live in an X11-style memory state managed by the TaskHandler.

## Bag / Task

```jx
bag  = Bag.underwrite(size)
task = Task.underwrite(size)        // Task is a special Bag

task.push(key, value)               // preassignment
ref  = task.sign(node)
task.set(data).commit(ref)
task.unsign(ref)                    // optional; often automatic on scope exit

remaining = task.quotient()
used      = task.used()
cap       = task.capacity()
id        = task.id()
```

## Protocol Note

The hosting module owns the coherent channel by which server-side Book state can update browser-side surfaces. Exact wire format is future work; the invariant is that the same Book description can drive both sides without a divisive split.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# Hosting Module API — Book / Page / Bag

The hosting module embeds the original PHP engine, expands it with jx, and loads Books under isolation.

## Book

```jx
book = Book.load(path)              // load compiled Book
book = Book.compile(source)         // compile then load
Book.unload(book)

entries = book.entries()
page    = book.page(name)
```

Each Book receives:
- its own class namespace projection
- a hard memory quota (Docker-like)
- isolated Bags / Pages

## Page

```jx
page = Page.spawn(entryFunc, initialBag?)
page = book.page("main")

Page.yield()
Page.sleep(ms)
id = page.id()                      // Page is also a Task/Bag
```

Pages live in an X11-style memory state managed by the TaskHandler.

## Bag / Task

```jx
bag  = Bag.underwrite(size)
task = Task.underwrite(size)        // Task is a special Bag

task.push(key, value)               // preassignment
ref  = task.sign(node)
task.set(data).commit(ref)
task.unsign(ref)                    // optional; often automatic on scope exit

remaining = task.quotient()
used      = task.used()
cap       = task.capacity()
id        = task.id()
```

## Protocol Note

The hosting module owns the coherent channel by which server-side Book state can update browser-side surfaces. Exact wire format is future work; the invariant is that the same Book description can drive both sides without a divisive split.
````

</details>

### `docs/smart-table.md`

- Current lines: 42
- Original reachable commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Latest Markdown-touching commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 42
- Latest blame by commit:
  - `8c52595` 42 lines dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests

<details>
<summary>Latest line blame</summary>

```markdown
8c52595a (dompipe 2026-08-25  1) # Smart Table Maker
8c52595a (dompipe 2026-08-25  2) 
8c52595a (dompipe 2026-08-25  3) The smart table maker is the heart of jx compilation and AI interpretation.
8c52595a (dompipe 2026-08-25  4) 
8c52595a (dompipe 2026-08-25  5) ## Purpose
8c52595a (dompipe 2026-08-25  6) 
8c52595a (dompipe 2026-08-25  7) Maintain a living catalogue of every method so that both the batch compiler and any AI interpreter instance can **extrude** the highest-performance native sequence that still obeys the memory model. When a pure native path is impossible, emit **Resistant code**.
8c52595a (dompipe 2026-08-25  8) 
8c52595a (dompipe 2026-08-25  9) ## Schema (v0.1)
8c52595a (dompipe 2026-08-25 10) 
8c52595a (dompipe 2026-08-25 11) Each row describes one known method / operation.
8c52595a (dompipe 2026-08-25 12) 
8c52595a (dompipe 2026-08-25 13) | Column | Type | Description |
8c52595a (dompipe 2026-08-25 14) |--------|------|-------------|
8c52595a (dompipe 2026-08-25 15) | `id` | string | Unique stable identifier (`bag.set`, `task.push`, `delivery.extract`, …) |
8c52595a (dompipe 2026-08-25 16) | `name` | string | Surface name as written by the programmer |
8c52595a (dompipe 2026-08-25 17) | `module` | string | Owning module / type (`Bag`, `Task`, `Book`, `global`, …) |
8c52595a (dompipe 2026-08-25 18) | `arity` | int or range | Number of arguments |
8c52595a (dompipe 2026-08-25 19) | `arg_shapes` | list | Accepted type / shape patterns |
8c52595a (dompipe 2026-08-25 20) | `side_effect` | enum | `none`, `read`, `write-bag`, `schedule`, `io`, … |
8c52595a (dompipe 2026-08-25 21) | `requires_ref` | bool | Whether a live `refSign` is mandatory |
8c52595a (dompipe 2026-08-25 22) | `memory_class` | enum | `pure`, `underwritten-only`, `task-local`, … |
8c52595a (dompipe 2026-08-25 23) | `native_template` | string / IR | Preferred native lowering (register mapping, instruction sequence skeleton) |
8c52595a (dompipe 2026-08-25 24) | `resistant_template` | string / IR | Fallback sequence when native_template cannot be applied safely |
8c52595a (dompipe 2026-08-25 25) | `purity_score` | float | 1.0 = fully pure native, lower = more Resistant |
8c52595a (dompipe 2026-08-25 26) | `notes` | string | Human / AI guidance |
8c52595a (dompipe 2026-08-25 27) 
8c52595a (dompipe 2026-08-25 28) ## Extrusion Process
8c52595a (dompipe 2026-08-25 29) 
8c52595a (dompipe 2026-08-25 30) 1. Resolve the call to a table row (or set of candidate rows).
8c52595a (dompipe 2026-08-25 31) 2. Match argument shapes and side-effect constraints.
8c52595a (dompipe 2026-08-25 32) 3. If a high-purity `native_template` applies under current memory / const / delivery facts → emit it.
8c52595a (dompipe 2026-08-25 33) 4. Otherwise instantiate `resistant_template`, mark the region as Resistant, and continue.
8c52595a (dompipe 2026-08-25 34) 5. Record the choice for later audit and for AI interpreters that may re-lower live.
8c52595a (dompipe 2026-08-25 35) 
8c52595a (dompipe 2026-08-25 36) ## AI Interpreter Contract
8c52595a (dompipe 2026-08-25 37) 
8c52595a (dompipe 2026-08-25 38) Any AI instance that “knows every function and remembers every method and their functional writing style” is expected to consult the same table (or an equivalent in-memory form). The goal is that fastening high-level jx to assembly becomes a table-driven, almost mechanical act rather than open-ended invention.
8c52595a (dompipe 2026-08-25 39) 
8c52595a (dompipe 2026-08-25 40) ## Extensibility
8c52595a (dompipe 2026-08-25 41) 
8c52595a (dompipe 2026-08-25 42) New methods are added by inserting rows. The compiler and AI interpreters pick them up without further hard-coding. Deprecated methods are marked but retained for Resistant compatibility.
```

</details>

<details>
<summary>Original reachable content</summary>

```markdown
# Smart Table Maker

The smart table maker is the heart of jx compilation and AI interpretation.

## Purpose

Maintain a living catalogue of every method so that both the batch compiler and any AI interpreter instance can **extrude** the highest-performance native sequence that still obeys the memory model. When a pure native path is impossible, emit **Resistant code**.

## Schema (v0.1)

Each row describes one known method / operation.

| Column | Type | Description |
|--------|------|-------------|
| `id` | string | Unique stable identifier (`bag.set`, `task.push`, `delivery.extract`, …) |
| `name` | string | Surface name as written by the programmer |
| `module` | string | Owning module / type (`Bag`, `Task`, `Book`, `global`, …) |
| `arity` | int or range | Number of arguments |
| `arg_shapes` | list | Accepted type / shape patterns |
| `side_effect` | enum | `none`, `read`, `write-bag`, `schedule`, `io`, … |
| `requires_ref` | bool | Whether a live `refSign` is mandatory |
| `memory_class` | enum | `pure`, `underwritten-only`, `task-local`, … |
| `native_template` | string / IR | Preferred native lowering (register mapping, instruction sequence skeleton) |
| `resistant_template` | string / IR | Fallback sequence when native_template cannot be applied safely |
| `purity_score` | float | 1.0 = fully pure native, lower = more Resistant |
| `notes` | string | Human / AI guidance |

## Extrusion Process

1. Resolve the call to a table row (or set of candidate rows).
2. Match argument shapes and side-effect constraints.
3. If a high-purity `native_template` applies under current memory / const / delivery facts → emit it.
4. Otherwise instantiate `resistant_template`, mark the region as Resistant, and continue.
5. Record the choice for later audit and for AI interpreters that may re-lower live.

## AI Interpreter Contract

Any AI instance that “knows every function and remembers every method and their functional writing style” is expected to consult the same table (or an equivalent in-memory form). The goal is that fastening high-level jx to assembly becomes a table-driven, almost mechanical act rather than open-ended invention.

## Extensibility

New methods are added by inserting rows. The compiler and AI interpreters pick them up without further hard-coding. Deprecated methods are marked but retained for Resistant compatibility.
```

</details>

<details>
<summary>Latest content</summary>

```markdown
# Smart Table Maker

The smart table maker is the heart of jx compilation and AI interpretation.

## Purpose

Maintain a living catalogue of every method so that both the batch compiler and any AI interpreter instance can **extrude** the highest-performance native sequence that still obeys the memory model. When a pure native path is impossible, emit **Resistant code**.

## Schema (v0.1)

Each row describes one known method / operation.

| Column | Type | Description |
|--------|------|-------------|
| `id` | string | Unique stable identifier (`bag.set`, `task.push`, `delivery.extract`, …) |
| `name` | string | Surface name as written by the programmer |
| `module` | string | Owning module / type (`Bag`, `Task`, `Book`, `global`, …) |
| `arity` | int or range | Number of arguments |
| `arg_shapes` | list | Accepted type / shape patterns |
| `side_effect` | enum | `none`, `read`, `write-bag`, `schedule`, `io`, … |
| `requires_ref` | bool | Whether a live `refSign` is mandatory |
| `memory_class` | enum | `pure`, `underwritten-only`, `task-local`, … |
| `native_template` | string / IR | Preferred native lowering (register mapping, instruction sequence skeleton) |
| `resistant_template` | string / IR | Fallback sequence when native_template cannot be applied safely |
| `purity_score` | float | 1.0 = fully pure native, lower = more Resistant |
| `notes` | string | Human / AI guidance |

## Extrusion Process

1. Resolve the call to a table row (or set of candidate rows).
2. Match argument shapes and side-effect constraints.
3. If a high-purity `native_template` applies under current memory / const / delivery facts → emit it.
4. Otherwise instantiate `resistant_template`, mark the region as Resistant, and continue.
5. Record the choice for later audit and for AI interpreters that may re-lower live.

## AI Interpreter Contract

Any AI instance that “knows every function and remembers every method and their functional writing style” is expected to consult the same table (or an equivalent in-memory form). The goal is that fastening high-level jx to assembly becomes a table-driven, almost mechanical act rather than open-ended invention.

## Extensibility

New methods are added by inserting rows. The compiler and AI interpreters pick them up without further hard-coding. Deprecated methods are marked but retained for Resistant compatibility.
```

</details>

### `tests/edge-cases.md`

- Current lines: 64
- Original reachable commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Latest Markdown-touching commit: `8c52595` 2026-08-25T20:13:29-04:00 dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
- Markdown-touching commits for this path: 1
- Latest blame by author: dompipe 64
- Latest blame by commit:
  - `8c52595` 64 lines dompipe: Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests

<details>
<summary>Latest line blame</summary>

````markdown
8c52595a (dompipe 2026-08-25  1) # Edge-Case Tests — Stressing Resistant Code
8c52595a (dompipe 2026-08-25  2) 
8c52595a (dompipe 2026-08-25  3) These cases deliberately push the compiler and runtime into regions where pure native extrusion is difficult or unsafe. The expected outcome is either a clean static rejection or emission of marked Resistant code that still preserves the memory model.
8c52595a (dompipe 2026-08-25  4) 
8c52595a (dompipe 2026-08-25  5) ## 1. Deep Delivery into missing structure
8c52595a (dompipe 2026-08-25  6) ```jx
8c52595a (dompipe 2026-08-25  7) val = config.server.ports.https.delivery()
8c52595a (dompipe 2026-08-25  8) // when config.server is null / undefined
8c52595a (dompipe 2026-08-25  9) ```
8c52595a (dompipe 2026-08-25 10) Expect: controlled error or default; never a raw crash.
8c52595a (dompipe 2026-08-25 11) 
8c52595a (dompipe 2026-08-25 12) ## 2. Delivery into const target
8c52595a (dompipe 2026-08-25 13) ```jx
8c52595a (dompipe 2026-08-25 14) const c = 0
8c52595a (dompipe 2026-08-25 15) c.delivery(some.path)   // must be rejected
8c52595a (dompipe 2026-08-25 16) ```
8c52595a (dompipe 2026-08-25 17) 
8c52595a (dompipe 2026-08-25 18) ## 3. Quotient exhaustion
8c52595a (dompipe 2026-08-25 19) ```jx
8c52595a (dompipe 2026-08-25 20) bag = Bag.underwrite(16)
8c52595a (dompipe 2026-08-25 21) ref = bag.sign(node)
8c52595a (dompipe 2026-08-25 22) bag.set(largeBuffer).commit(ref)   // larger than quotient
8c52595a (dompipe 2026-08-25 23) ```
8c52595a (dompipe 2026-08-25 24) Expect: rejection before any store; server stays alive.
8c52595a (dompipe 2026-08-25 25) 
8c52595a (dompipe 2026-08-25 26) ## 4. Sign / unsign races under concurrency
8c52595a (dompipe 2026-08-25 27) Multiple Pages signing and unsigning the same Bag region.
8c52595a (dompipe 2026-08-25 28) Expect: TaskHandler serialises or isolates; no use-after-unsign.
8c52595a (dompipe 2026-08-25 29) 
8c52595a (dompipe 2026-08-25 30) ## 5. Complex edge values
8c52595a (dompipe 2026-08-25 31) ```jx
8c52595a (dompipe 2026-08-25 32) c = 1e308 + 1e308i
8c52595a (dompipe 2026-08-25 33) d = c * c
8c52595a (dompipe 2026-08-25 34) // overflow / inf handling
8c52595a (dompipe 2026-08-25 35) ```
8c52595a (dompipe 2026-08-25 36) Expect: defined behaviour (inf / error), not UB.
8c52595a (dompipe 2026-08-25 37) 
8c52595a (dompipe 2026-08-25 38) ## 6. Const cast violations
8c52595a (dompipe 2026-08-25 39) ```jx
8c52595a (dompipe 2026-08-25 40) x = (const) mutableBag
8c52595a (dompipe 2026-08-25 41) // later attempt to mutate through x
8c52595a (dompipe 2026-08-25 42) ```
8c52595a (dompipe 2026-08-25 43) Expect: static or dynamic rejection.
8c52595a (dompipe 2026-08-25 44) 
8c52595a (dompipe 2026-08-25 45) ## 7. Hostile dynamic shapes
8c52595a (dompipe 2026-08-25 46) Objects that change shape between Delivery path resolution and use.
8c52595a (dompipe 2026-08-25 47) Expect: Resistant checks or rejection; no assembler explosion.
8c52595a (dompipe 2026-08-25 48) 
8c52595a (dompipe 2026-08-25 49) ## 8. One-shot sign-and-write under low quotient
8c52595a (dompipe 2026-08-25 50) ```jx
8c52595a (dompipe 2026-08-25 51) bag.set(data).commit(bag.sign(node))  // data larger than remaining
8c52595a (dompipe 2026-08-25 52) ```
8c52595a (dompipe 2026-08-25 53) Expect: atomic failure, no partial write.
8c52595a (dompipe 2026-08-25 54) 
8c52595a (dompipe 2026-08-25 55) ## 9. Task push after Task has been scheduled
8c52595a (dompipe 2026-08-25 56) Mutation of preassignments from another Page.
8c52595a (dompipe 2026-08-25 57) Expect: only legal through proper ref + handshake; otherwise rejected.
8c52595a (dompipe 2026-08-25 58) 
8c52595a (dompipe 2026-08-25 59) ## 10. Resistant marker visibility
8c52595a (dompipe 2026-08-25 60) Any emitted Resistant region must be introspectable (debug / audit) so developers know purity was traded.
8c52595a (dompipe 2026-08-25 61) 
8c52595a (dompipe 2026-08-25 62) ---
8c52595a (dompipe 2026-08-25 63) 
8c52595a (dompipe 2026-08-25 64) All of the above must pass on every supported compiler backend. The collective steering goal remains the five pillars A–E while keeping the language coherent and non-divisive.
````

</details>

<details>
<summary>Original reachable content</summary>

````markdown
# Edge-Case Tests — Stressing Resistant Code

These cases deliberately push the compiler and runtime into regions where pure native extrusion is difficult or unsafe. The expected outcome is either a clean static rejection or emission of marked Resistant code that still preserves the memory model.

## 1. Deep Delivery into missing structure
```jx
val = config.server.ports.https.delivery()
// when config.server is null / undefined
```
Expect: controlled error or default; never a raw crash.

## 2. Delivery into const target
```jx
const c = 0
c.delivery(some.path)   // must be rejected
```

## 3. Quotient exhaustion
```jx
bag = Bag.underwrite(16)
ref = bag.sign(node)
bag.set(largeBuffer).commit(ref)   // larger than quotient
```
Expect: rejection before any store; server stays alive.

## 4. Sign / unsign races under concurrency
Multiple Pages signing and unsigning the same Bag region.
Expect: TaskHandler serialises or isolates; no use-after-unsign.

## 5. Complex edge values
```jx
c = 1e308 + 1e308i
d = c * c
// overflow / inf handling
```
Expect: defined behaviour (inf / error), not UB.

## 6. Const cast violations
```jx
x = (const) mutableBag
// later attempt to mutate through x
```
Expect: static or dynamic rejection.

## 7. Hostile dynamic shapes
Objects that change shape between Delivery path resolution and use.
Expect: Resistant checks or rejection; no assembler explosion.

## 8. One-shot sign-and-write under low quotient
```jx
bag.set(data).commit(bag.sign(node))  // data larger than remaining
```
Expect: atomic failure, no partial write.

## 9. Task push after Task has been scheduled
Mutation of preassignments from another Page.
Expect: only legal through proper ref + handshake; otherwise rejected.

## 10. Resistant marker visibility
Any emitted Resistant region must be introspectable (debug / audit) so developers know purity was traded.

---

All of the above must pass on every supported compiler backend. The collective steering goal remains the five pillars A–E while keeping the language coherent and non-divisive.
````

</details>

<details>
<summary>Latest content</summary>

````markdown
# Edge-Case Tests — Stressing Resistant Code

These cases deliberately push the compiler and runtime into regions where pure native extrusion is difficult or unsafe. The expected outcome is either a clean static rejection or emission of marked Resistant code that still preserves the memory model.

## 1. Deep Delivery into missing structure
```jx
val = config.server.ports.https.delivery()
// when config.server is null / undefined
```
Expect: controlled error or default; never a raw crash.

## 2. Delivery into const target
```jx
const c = 0
c.delivery(some.path)   // must be rejected
```

## 3. Quotient exhaustion
```jx
bag = Bag.underwrite(16)
ref = bag.sign(node)
bag.set(largeBuffer).commit(ref)   // larger than quotient
```
Expect: rejection before any store; server stays alive.

## 4. Sign / unsign races under concurrency
Multiple Pages signing and unsigning the same Bag region.
Expect: TaskHandler serialises or isolates; no use-after-unsign.

## 5. Complex edge values
```jx
c = 1e308 + 1e308i
d = c * c
// overflow / inf handling
```
Expect: defined behaviour (inf / error), not UB.

## 6. Const cast violations
```jx
x = (const) mutableBag
// later attempt to mutate through x
```
Expect: static or dynamic rejection.

## 7. Hostile dynamic shapes
Objects that change shape between Delivery path resolution and use.
Expect: Resistant checks or rejection; no assembler explosion.

## 8. One-shot sign-and-write under low quotient
```jx
bag.set(data).commit(bag.sign(node))  // data larger than remaining
```
Expect: atomic failure, no partial write.

## 9. Task push after Task has been scheduled
Mutation of preassignments from another Page.
Expect: only legal through proper ref + handshake; otherwise rejected.

## 10. Resistant marker visibility
Any emitted Resistant region must be introspectable (debug / audit) so developers know purity was traded.

---

All of the above must pass on every supported compiler backend. The collective steering goal remains the five pillars A–E while keeping the language coherent and non-divisive.
````

</details>

