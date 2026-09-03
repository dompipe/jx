# PASL — PASM Language

PASL is an optional readable compiler/lowering surface above PASM. It is useful for arithmetic, control-flow, regression tests, and bootstrap work, but it is **not** the canonical executable file format and it is not a mandatory stage for all JX code.

## Current role

```text
JX source
  -> JX IR
  -> PASM directly where possible
  -> optional PASL-assisted lowering where useful
  -> direct target-native encoder
  -> .jxl executable or .jll library
```

The repository also contains a proven fixed-width six-byte prepared stream historically called JXL. That representation remains useful internally for compatibility and benchmark work, but new public `.jxl` files mean **native executable images**, not that prepared stream.

## PASM preparation

PASL keeps its parser, register lowering, bounded loops, iterator lowering, and loop fusion, producing explicit PASM semantics.

```text
PASL source
  -> PASM semantic assembly
  -> loop / iterator optimization
```

The PASM bytecode/prepared compatibility path remains available through `.pbc` and internal APIs. Existing six-byte prepared-stream tests may continue to use their historical class/function names until they are migrated; file-extension meaning is governed by the JX file contract, not those internal identifiers.

## Embedded API

```php
use pasm\lang\Engine;

$engine = new Engine(optimize: true, verbose: false);
$code = $engine->compile('$sum=0;$i=0;for($i=0;$i!=4;$i++){$sum+=$i;}$result=$sum;');
$result = $engine->runCode($code);
```

This API is a compiler/runtime development surface. Do not infer the public artifact extension from the in-memory representation returned by a compatibility method.

## Native x86-64

JX already contains a direct PASM -> x86-64 machine-code encoder in `pasm-native-jxl.php`. Despite the historical class name, its important architectural role is the **native encoder**.

The canonical native pipelines are:

```text
JX/PASL -> PASM -> direct native encoder -> JxNativeImage(entrypoint) -> .jxl
JX/PASL -> PASM -> direct native encoder -> JxNativeImage(no entry)   -> .jll
```

For a native executable:

```bash
php jxl-native-compile.php program.jx program.jxl
```

For a native library:

```bash
php jll-native-compile.php library.jx library.jll exports.json
php jll-inspect.php library.jll
```

The separate PASL -> NASM development path may also remain available for diagnostics and comparison.

## Canonical file meanings

```text
.jx   Jinx source
.jxl  native Jinx executable image
.jll  Jinx Loadable Library; same native image, normally no entrypoint
.jxb  indexed ZIP/Deflate resource archive
.pbc  PASM bytecode / prepared compatibility representation
.pasl PASL source
```

Historical `.64B` Books and old prepared-stream `.jxl` artifacts are compatibility concerns. New writers and documentation must use the canonical meanings above.

See `jx/COMPILER.md`, `docs/NATIVE-JXB.md`, and `jx-native-image.php`.