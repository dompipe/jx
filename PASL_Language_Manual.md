# PASL Language Manual

**PASM Language — PHP-like source language with prepared JXL output**  
Version 1.1 · 2026 · dompipe/jx

Silent by default · Optimized by default · Canonical `.jxl` prepared stream

## 1. Overview

PASL is a restricted, PHP-like language whose front-end lowers source into PASM semantics. The normal prepared backend now encodes those semantics as fixed-width JXL rather than making `.pbc` the primary executable representation.

```text
PASL
  ↓
parser / variables / loop-space / foreach lowering
  ↓
PASM semantic assembly
  ↓
optimizer + loop fuser
  ↓
JXL six-byte cells
```

Goals:

1. Keep source compact and readable.
2. Preserve PASM's explicit register and operation semantics.
3. Give every supported PASM operation a canonical JXL representation.
4. Make `.jxl` the prepared executable stream used by new code.
5. Keep `.pbc` readable and explicitly producible for compatibility.
6. Allow `.jxl` to be packaged inside `.jxb` compiled Books.

## 2. File roles

| Extension | Role |
|---|---|
| `.pasl` | PASL source |
| `.jxl` | Canonical prepared executable stream |
| `.jxb` | Compiled Book/container; may carry JXL, metadata, bindings and native sections |
| `.pbc` | Legacy PASM bytecode container |
| `.64B` | Legacy JXB filename only; never the preferred new output name |

`.b64` files elsewhere in the repository are Base64 shards and are unrelated to the old `.64B` Book suffix.

## 3. JXL semantic profile

The PASM-profile JXL band is reserved directly above the native Bag/container band:

```text
0x20..0x37  prepared JXL arithmetic/control core
0x40..0x50  native Bag/container operations
0x51..0x76  PASM semantic operations
0x77        MOVI 64-bit continuation cell
```

PASM's current `0x00..0x25` operation set maps one-for-one to JXL `0x51..0x76`.

The profile covers all current PASM semantic possibilities:

- `HALT`
- `MOVI`, `MOVR`
- `ADD`, `SUB`, `MUL`, `DIV`, `MOD`
- `AND`, `OR`, `XOR`, `SHL`, `SHR`
- `CMP`
- `JMP`, `JZ`, `JNZ`, `JL`, `JLE`, `JG`, `JGE`
- `PUSH`, `POP`
- `LOAD32`, `STORE32`
- `INC`, `DEC`, `NEG`
- `RET`
- `ITERF`, `ITERR`, `IRESET`
- `NLOAD`, `NSTORE`
- `MCALL0`, `MCALL1`, `MCALL2`, `MCALL3`

Each normal instruction occupies exactly six bytes. The first byte is the opcode and the remaining five bytes are JXL attachment bytes with the high bit set. `MOVI` uses a second six-byte continuation cell so signed 64-bit immediates are not truncated.

## 4. Invocation and embedding

### New prepared path

```php
use pasm\lang\Engine;

$engine = new Engine(optimize: true, verbose: false);

$source = '$sum=0;$i=0;for($i=0;$i!=4;$i++){$sum+=$i;}$result=$sum;';
$jxl = $engine->compile($source);
$result = $engine->runCode($jxl); // 6

$engine->compileFile($source, 'program.jxl');
$result = $engine->runFile('program.jxl');
```

`Engine::compile()` returns JXL. `Engine::compileFile()` writes JXL unless the requested filename explicitly ends in `.pbc`.

### Explicit PBC compatibility

```php
$pbc = $engine->compilePbc($source);
$engine->compileFile($source, 'program.pbc');
```

PBC is no longer the default new prepared format.

## 5. Types and registers

### Integer

The PASM register window remains:

```text
ecx, ah, adx, bdx, cdx, ddx, edx, rdx
```

Example:

```pasl
$addedto = 0;
$addedto = $addedto + 1;
$addedto++;
$addedto += 1;
$addedto = $addedto * 2;
```

### Complex

Complex values use paired registers in the existing PASL lowering. Literals include `3+4i`, `1-2i`, `i`, and `-i` where supported by the source compiler.

## 6. Control flow

PASL lowers structured source into explicit PASM comparisons and branches, then relocates those branch targets to JXL byte offsets.

```pasl
while ($i) {
    $sum += $i;
    $i--;
}

for ($k = 0; $k != 4; $k++) {
    $sum += $k;
}

if ($mode == 2) {
    $result = 20;
} else {
    $result = 10;
}
```

Relational PASM branch operations `JL`, `JLE`, `JG`, and `JGE` also have dedicated JXL cells, so the prepared format no longer needs a separate bytecode vocabulary for them.

## 7. Iteration and named operations

The prepared profile retains the PASM operations used by the richer PASL lowering:

- `ITERF` — forward iterator step
- `ITERR` — reverse iterator step
- `IRESET` — iterator reset
- `NLOAD` / `NSTORE` — named memory access
- `MCALL0..3` — fast method-call forms

These are encoded as JXL rather than being hidden inside an opaque PBC payload.

## 8. Optimization

The existing optimizer remains before JXL emission. Current transformations include constant simplification, simple identity removal, register-level lowering, iterator rewrites and loop-block fusion where valid.

The important distinction is that optimization now feeds one canonical prepared representation:

```text
optimized PASM semantics -> JXL
```

rather than requiring a separate PASM bytecode file format for normal execution.

## 9. Execution status

The JXL representation is canonical and is the default PASL prepared output. The current PASL host can execute it by admitting the JXL profile and reconstructing the existing PASM runtime semantics.

The native x86-64 JXL dispatcher already executes the existing arithmetic/control and native Bag bands. Direct native x86-64 handlers for the PASM-specific `0x51..0x77` profile are the next optimization layer; until those handlers are added, PASL-profile JXL uses the compatibility PASM runtime admission path.

This distinction is intentional: **the file format migration is complete without falsely claiming every PASM-specific operation is already a native assembly instruction.**

## 10. JXB packaging

A compiled Book uses `.jxb`:

```text
program.jxb
  ├─ JX64/header.bin      stable v1 package identity
  ├─ JX64/manifest.json
  ├─ CODE/program.jxl
  └─ metadata / bindings / optional native sections
```

The internal `JX64B001` magic and `jx.64B/1` manifest identifier remain stable for binary compatibility. They are internal version identifiers, not the public filename extension.

New tools emit `.jxb`. Existing `.64B` files remain readable by their internal package identity.