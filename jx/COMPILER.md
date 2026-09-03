# JX executable compiler

## Principle

JX separates readable source, internal lowering, native code images, loadable libraries, and resource packaging.

```text
canonical .jx source
    -> PHP-backed JX front end
    -> canonical JX IR
    -> PASM lowering when appropriate
    -> direct target-native encoder
    -> shared native image
         entrypoint present  -> .jxl
         no entrypoint       -> .jll

resources / images / models / tables
    -> indexed ZIP/deflate resource archive
    -> .jxb
```

PHP is cold compiler/tooling infrastructure. It may parse unusual JX syntax, normalize it to JX IR, choose PASM lowering, construct native images, build JXB archives, and emit diagnostics. Native `.jxl` and `.jll` execution does not require PHP source.

The optimization law remains:

> **Resolve cold -> bind once -> execute hot.**

## Canonical file contract

```text
.jx    canonical Jinx source code
.jxl   native Jinx executable image; has an entrypoint
.jll   Jinx Loadable Library; same native image format, normally no entrypoint
.jxb   indexed compressed resource archive; ZIP-compatible Deflate payloads
.pasl  optional PASL source / compiler-development surface
.pbc   PASM bytecode / prepared compatibility and test representation
```

Historical `.64B`, compiled-Book JXB, and six-byte prepared-stream JXL readers may remain for compatibility, but new code must not assign those old meanings to the canonical public extensions.

## One native image, two code extensions

`.jxl` and `.jll` use the same native image structure and the same native encoder. The distinction is an image property rather than a second machine-code format.

```text
JXNI image
|- header
|- CODE
|- DATA              optional
|- IMPORTS           optional
|- EXPORTS           optional
|- SIGNATURES        optional
|- RELOCATIONS       optional
`- DEBUG             optional
```

The header declares architecture, flags, section directory, and entrypoint.

```text
entrypoint != none -> executable image -> .jxl
entrypoint == none -> loadable library -> .jll
```

A parameterized JLL may carry initialization metadata or a deliberately supplied entrypoint, but ordinary library loading does not require one.

Implementation: `../jx-native-image.php`.

## JLL public-call contract

A JLL normally ships the callable contract of the code compiled into it. Public functions are described by compact `EXPORTS` and `SIGNATURES` sections.

An export record identifies:

```text
function name
native CODE offset
signature id
function flags
```

A signature identifies:

```text
ordered parameter types
return type
```

This allows a loader to map a JLL into memory, resolve an export, validate/prepare arguments, and jump to native code without parsing the original `.jx` source.

Private functions do not need to appear in the public tables.

Current tooling:

```bash
php jll-native-compile.php library.jx library.jll exports.json
php jll-inspect.php library.jll
```

## JXL native executable contract

`.jxl` is not the six-byte prepared stream and is not PASM bytecode. It is the output boundary of the native encoder.

The existing direct encoder already performs deterministic PASM -> x86-64 machine-code emission without NASM/GAS/LLVM in the middle for admitted operations:

```text
JX
 -> JX IR
 -> PASM
 -> PASMNativeJxlEncoder
 -> native machine bytes
 -> JxNativeImage(entrypoint)
 -> program.jxl
```

Current command:

```bash
php jxl-native-compile.php program.jx program.jxl
```

The direct encoder is intentionally strict. Unsupported PASM operations must be implemented in the native encoder rather than silently reinterpreted as another public JXL format.

## PASM and PASL roles

PASM is the preferred low-level executable IR because it is short, resolved, and close to native instructions.

PASL remains useful for:

- readable compiler tests,
- arithmetic/control-flow lowering,
- bootstrap work,
- validation against a simpler intermediate form,
- compatibility tooling.

PASL is **not** a mandatory stage for every JX construct.

Canonical compiler law:

```text
common scalar JX:
    JX IR -> PASM -> native encoder

construct helped by PASL:
    JX IR -> PASL/PASM lowering -> native encoder

rich/rare construct:
    PHP front end -> normalized JX IR -> PASM or direct native preparation
```

The language is therefore not constrained to features PASL can express.

## Prepared six-byte stream compatibility

The repository contains a proven six-byte prepared decoder and native container benchmarks historically named JXL. That machinery remains useful and may continue to exist internally while migration occurs.

Its canonical role is now **internal prepared execution / PBC-compatible compiler machinery**, not the meaning of a newly emitted `.jxl` file.

Do not remove benchmark code merely to rename the public file contract. Rename or qualify prepared-stream terminology as files are touched, and keep its byte law documented as an internal ABI where required for benchmark reproducibility.

## JXB resource contract

`.jxb` is no longer the executable compiled Book boundary. It is the resource package.

Canonical JXB v1 is ZIP-compatible and uses per-entry Deflate compression so the runtime can read the central directory once and retrieve only requested resources.

```text
assets.jxb
|- jx-manifest.json
|- images/logo.png
|- models/skull.mesh
|- tables/taxonomy.bin
|- dictionaries/words.bin
`- shaders/main.bin
```

Runtime law:

```text
open archive
 -> read member index
 -> locate requested member
 -> decompress/stream that member only
 -> leave unrelated members compressed
```

Implementation: `../jx-jxb-archive.php`.

The `.jxb` extension does not mean that every member is inherently binary; it means the package is the binary/resource distribution boundary.

## Current implementation map

```text
jx-forif-lowering.php   rich PHP/JX normalization, including rare row-forif form
pasm-*.php              PASM/PASL lowering and compatibility execution
pasm-native-jxl.php     current direct x86-64 native encoder (historical class name)
jx-native-image.php     shared .jxl/.jll image container
jxl-native-compile.php  native executable image writer
jll-native-compile.php  native library image writer
jll-inspect.php         JLL export/signature inspection
jx-jxb-archive.php      indexed JXB resources
```

## Compatibility policy

Old artifacts should be readable where practical, but the canonical meanings are fixed:

> **`.jx` is source. `.jxl` is a native executable. `.jll` is a native loadable library. `.jxb` is an indexed compressed resource archive. PASM is the low-level compiler language.**

When documentation, CLI help, tests, or comments are updated, those meanings are authoritative.