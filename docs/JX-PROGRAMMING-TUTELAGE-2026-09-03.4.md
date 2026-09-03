# JX Programming Tutelage — 2026.09.03.4

## Native file-model revision

This revision supersedes older manuscript text wherever `.jxl`, `.jll`, `.jxb`, PASL, PASM, or the compilation pipeline are described differently.

Canonical meanings:

```text
.jx   Jinx source code
.jxl  native Jinx executable image
.jll  Jinx Loadable Library
.jxb  indexed compressed resource archive
```

PASM is the ordinary low-level compiler language. PASL is useful and retained, but it is not a mandatory stage for every JX construct.

## Compiler path

```text
.jx source
 -> PHP-backed front end
 -> canonical JX IR
 -> PASM directly where appropriate
 -> optional PASL-assisted lowering where useful
 -> direct native encoder
 -> native CODE
 -> shared JXNI image
      entrypoint    -> .jxl
      no entrypoint -> .jll
```

Rare syntax can be normalized by PHP before low-level encoding instead of making PASM understand every source-language edge case.

The tuple-returning filtered loop remains the current example:

```jx
_, no1, no2, no3 = forif ($value in $values if no1 < _)
```

Its order is:

```text
callback/iterator produces row
 -> _   = row[0]
 -> no1 = row[1]
 -> no2 = row[2]
 -> no3 = row[3]
 -> evaluate no1 < _
 -> run accepted body
```

`_` is value zero. `revif` reverses outer traversal but not tuple positions.

## JXL and JLL share one native ABI

A JXL and JLL are the same native image type. The image declares architecture, sections, flags, and entrypoint state.

```text
JXNI
|- fixed 40-byte header
|- fixed 32-byte section directory rows
|- CODE
|- DATA             optional
|- STRINGS          public names/type names
|- SIGNATURES       public parameter and return layouts
|- EXPORTS          public function names + CODE offsets
|- IMPORTS          reserved/native-link work
`- RELOCATIONS      reserved/native-link work
```

### JXL

A `.jxl` has a CODE-relative entrypoint and is the result of the native encoder.

It is not the old six-byte prepared execution stream.

### JLL

A normal `.jll` has no executable entrypoint. It ships its public callable contract with the native code.

An export identifies:

```text
function name
CODE-relative function offset
signature id
flags
```

A signature identifies:

```text
ordered parameter types
return type
```

A native host can therefore map a JLL once, look up a function name, inspect its parameters, and call the resolved native address without reparsing `.jx` source.

Linux and Windows native loader implementations now exist under `host/linux` and `host/windows`, using the shared binary parser in `host/common`.

## JXB resources

`.jxb` is resource packaging, not the executable Book identity.

Canonical v1 is ZIP-compatible and Deflate-compressed per member:

```text
assets.jxb
|- jx-manifest.json
|- images/
|- models/
|- fonts/
|- tables/
|- dictionaries/
`- arbitrary data
```

The runtime reads the member index and decompresses only what is requested.

This is deliberately archive-like rather than one monolithic gzip stream, because independent member access is the important behavior.

## Prepared-stream compatibility

The repository has a proven fixed-width six-byte prepared execution system that was historically called JXL. It remains valuable for testing, benchmarks, and compatibility.

It no longer defines the public `.jxl` extension.

Prepared/PASM persisted development output belongs under `.pbc` or clearly internal historical names.

## Current implementation files

```text
jx-native-image.php
pasm-native-encoder.php
pasm-native-jxl.php          historical raw native encoder implementation name
jxl-native-compile.php
jll-native-compile.php
jll-inspect.php
jx-jxb-archive.php
jxb-resource-pack.php
jxb-resource-get.php
host/common/jx-native-image.[ch]
host/linux/jx-jll-loader.[ch]
host/windows/jx-jll-loader.[ch]
jx-forif-lowering.php
```

For the complete binary layout, see `docs/JX-FILE-FORMATS.md`.

## Fixed rule

> **JX is source. JXL is native executable code. JLL is native loadable functional code. JXB is indexed compressed resources. PASM is the low-level compiler language.**
