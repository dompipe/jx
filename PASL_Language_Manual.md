# PASL Language Manual

**PASM Language — readable low-level/compiler surface for JX**  
Version 1.2 · 2026 · dompipe/jx

PASL remains a compact PHP-like language for expressing arithmetic, control flow, registers, loops, and other operations that lower naturally into PASM. It is a compiler-development and bootstrap surface; it is **not** required between every `.jx` construct and native code.

## 1. Position in JX

Canonical compilation is:

```text
.jx source
  -> PHP-backed JX parser/canonicalizer
  -> JX IR
  -> PASM directly where appropriate
  -> optional PASL-assisted lowering where useful
  -> direct native encoder
  -> JXNI native image
       entrypoint    -> .jxl
       no entrypoint -> .jll
```

PASL can also be compiled into the historical prepared/PASM compatibility representation for tests and bootstrap execution.

The key rule is:

> **PASL helps lower JX; PASL does not define what JX is allowed to express.**

## 2. File roles

| Extension | Role |
|---|---|
| `.pasl` | PASL source |
| `.jx` | canonical Jinx source |
| `.jxl` | native Jinx executable image with entrypoint |
| `.jll` | native Jinx Loadable Library, same image format and normally no entrypoint |
| `.jxb` | indexed ZIP-compatible Deflate resource archive |
| `.pbc` | PASM bytecode/prepared compatibility container |
| `.8B` | historical/internal six-byte prepared stream when persisted explicitly |
| `.64B` | historical compiled-Book/package generation |

The six-byte prepared format was historically called JXL in code and benchmark names. It remains available internally, but new `.jxl` files contain JXNI native executable images.

## 3. PASL to PASM

PASL lowers readable statements into explicit PASM semantics:

```text
PASL
  -> parser / variables / loop lowering
  -> PASM semantic assembly
  -> optimization / loop fusion / iterator preparation
```

PASM is intentionally short and resolved. For admitted operations it can feed the direct native encoder without a second general-purpose assembler.

## 4. Registers

The current PASM logical register window is:

```text
ecx, ah, adx, bdx, cdx, ddx, edx, rdx
```

These names are PASM logical registers, not a promise that the native target uses the identically named CPU registers.

The direct native encoder maps them deterministically to target registers for the selected ABI.

## 5. Basic statements

### Assignment

```pasl
$x = 10;
$y = $x;
```

### Arithmetic

```pasl
$x = $a + $b;
$x = $a - $b;
$x = $a * $b;
$x = $a / $b;
$x = $a % $b;
```

Support in a specific backend depends on whether the relevant PASM operation is admitted by that backend. The direct native x86-64 encoder is deliberately fail-closed for operations not yet implemented.

### Mutation

```pasl
$x++;
$x--;
$x += 4;
$x -= 4;
$x *= 2;
$x ^= 3;
```

### Bit operations

```pasl
$x = $a & $b;
$x = $a | $b;
$x = $a ^ $b;
$x = $a << $n;
$x = $a >> $n;
```

## 6. Conditions

```pasl
if ($x > $y) {
    $result = $x;
} else {
    $result = $y;
}
```

PASM comparisons lower into `CMP` plus the applicable conditional branch.

Current branch family includes:

```text
JZ
JNZ
JL
JLE
JG
JGE
```

## 7. Loops

### While

```pasl
while ($x > 0) {
    $x--;
}
```

### For

```pasl
for ($i = 0; $i < 10; $i++) {
    $sum += $i;
}
```

Loop lowering can move bounded bodies into compiled loop blocks and fuse unnecessary transfers before low-level emission.

### Break and continue

```pasl
for ($i = 0; $i < 100; $i++) {
    if ($i == 4) continue;
    if ($i == 20) break;
    $sum += $i;
}
```

## 8. Collection loops

The JX/PASL collection-loop family includes:

```text
foreach
reveach
forif
revif
```

The compact PASM iterator controller uses forward/reverse iterator operations and a prelinked iterator descriptor.

Rich row destructuring such as:

```jx
_, no1, no2, no3 = forif ($value in $values if no1 < _)
```

is **not** an array feature of PASM. The PHP/JX front end normalizes that rare source form before PASM/native lowering.

## 9. Selection

PASL supports structured selection through the active compiler surface:

```pasl
select ($x) {
    case 1:
        $result = 10;
    case 2:
        $result = 20;
    default:
        $result = 0;
}
```

`switch` may be accepted as a surface spelling where the compiler aliases it to the same canonical selection semantics.

## 10. Complex values

The PASL compiler retains complex-value support using paired logical registers where applicable:

```pasl
complex $z = 3+4i;
complex $w = 1-2i;
complex $p;
$p = $z * $w;
```

Complex values consume two logical register positions and therefore affect register pressure differently from scalar values.

## 11. Prepared compatibility execution

The repository retains a fixed-width prepared execution representation historically named JXL. It is useful for:

- regression tests,
- benchmark reproducibility,
- bootstrap execution,
- prepared-dispatch versus direct-native comparisons,
- container-operation benchmarking.

Persisted output from this compatibility subsystem should use `.pbc`, `.8B`, or another explicitly internal filename—not public `.jxl`.

The PASL development engine therefore uses `.pbc` for ordinary persisted prepared output:

```bash
php pasm-run.php -o program.pbc program.pasl
php pasm-run.php --print program.pbc
```

In-memory APIs may retain historical method/class names such as `compileJxl()` or `PASMJxlCompiler` while migration proceeds. Those identifiers do not define the public file extension.

## 12. Native executable output

For the current admitted direct-native subset:

```bash
php jxl-native-compile.php program.jx program.jxl
```

Pipeline:

```text
source
 -> PASM
 -> PASMNativeEncoder
 -> x86-64 CODE bytes
 -> JxNativeImage(entrypoint=0)
 -> program.jxl
```

The historical implementation class `PASMNativeJxlEncoder` remains underneath the canonical `PASMNativeEncoder` facade because it already contains tested direct x86-64 templates.

## 13. Native library output

A library uses the same code generator and native-image ABI:

```bash
php jll-native-compile.php math.jx math.jll exports.json
php jll-inspect.php math.jll
```

A JLL normally has no entrypoint and may ship:

```text
CODE
STRINGS
SIGNATURES
EXPORTS
```

so a native loader can map it, resolve a public function, inspect the parameter/return contract, and jump directly to its CODE offset.

## 14. JXNI image relationship

JXL and JLL are one image family:

```text
JXNI image
  + entrypoint -> .jxl
  - entrypoint -> .jll
```

The current binary ABI is defined in `docs/JX-FILE-FORMATS.md` and implemented in:

```text
jx-native-image.php
host/common/jx-native-image.h
host/common/jx-native-image.c
```

Native JLL loaders exist for Linux and Windows. Minimal native JXL launchers also exist under the corresponding host directories.

## 15. JXB relationship

JXB is independent of PASL execution. It is the resource boundary:

```text
images / models / fonts / tables / dictionaries / other resources
 -> indexed per-member Deflate archive
 -> assets.jxb
```

A JXB may contain a `.jxl` or `.jll` as a resource, but the archive itself does not become executable code.

## 16. Direct-native admission rule

The native encoder must not fake support for a PASM opcode by silently routing through a hidden interpreter.

Current policy:

```text
supported PASM op
 -> deterministic native byte template

unsupported PASM op
 -> compiler error
```

This keeps native `.jxl/.jll` output accurate and makes missing encoder work visible.

## 17. Development command summary

```bash
# Run PASL through the compatibility engine
php pasm-run.php --print program.pasl

# Persist PASM/prepared compatibility output
php pasm-run.php -o program.pbc program.pasl

# Generate diagnostic x86 assembly through the separate NASM-oriented backend
php pasm-run.php --x86 -o program.s program.pasl

# Generate a native JXNI executable using the direct encoder
php jxl-native-compile.php program.jx program.jxl

# Generate a JXNI library with public signatures
php jll-native-compile.php library.jx library.jll exports.json
php jll-inspect.php library.jll
```

## 18. Compiler law

The permanent architecture is:

> **JX IR is universal. PASM is the usual low-level language. PASL is optional. Native JXL/JLL are generated by the direct encoder.**

That prevents uncommon source features from forcing complexity into PASL or PASM while preserving a small, fast low-level execution model.