# JX File Formats

## Canonical extensions

The public file contract is fixed:

| Extension | Meaning |
|---|---|
| `.jx` | canonical Jinx source code |
| `.jxl` | native Jinx executable image; contains a CODE-relative entrypoint |
| `.jll` | Jinx Loadable Library; same native image format, normally no entrypoint |
| `.jxb` | indexed compressed resource archive; ZIP-compatible per-member Deflate |
| `.pasl` | optional PASL source used by compiler/development paths |
| `.pbc` | PASM bytecode / prepared compatibility representation |

Historical `.64B`, `.8B`, compiled-Book JXB, and six-byte prepared-stream JXL artifacts are compatibility formats. They do not redefine the canonical public extensions.

---

# `.jx` — source

`.jx` is the human- and AI-readable authority.

The compiler pipeline is not required to translate every construct through PASL.

```text
.jx
 -> PHP-backed front end
 -> canonical JX IR
 -> PASM directly where appropriate
 -> optional PASL-assisted lowering where useful
 -> native encoder
```

Rich or rare syntax can be normalized by PHP before low-level lowering. For example, row-returning `forif` is normalized into a canonical row plan before PASM/native work.

---

# `.jxl` and `.jll` — one native image

JXL and JLL use the same `JXNI` native-image ABI.

```text
native image + entrypoint    = .jxl
native image + no entrypoint = .jll
```

The extension describes intended use. The bytes themselves also declare executable/library flags and entrypoint presence so loaders do not have to trust a suffix.

## Current magic

```text
bytes 0..7 = 4A 58 4E 49 01 00 00 00
             J  X  N  I  v1
```

## Header — 40 bytes

All integers are little-endian.

| Offset | Size | Meaning |
|---:|---:|---|
| 0 | 8 | `JXNI\x01\0\0\0` magic |
| 8 | 4 | image version |
| 12 | 4 | architecture id |
| 16 | 4 | flags |
| 20 | 4 | section count |
| 24 | 8 | CODE-relative entrypoint; all-ones means none |
| 32 | 4 | section-directory byte size |
| 36 | 4 | reserved |

Current architecture ids:

```text
1 = x86_64 SysV
2 = x86_64 Win64
3 = AArch64
```

Current flags:

```text
0x0001 executable
0x0002 library
0x0004 exports present
0x0008 imports present
0x0010 relocatable
```

## Section directory

The directory follows the header immediately. Each entry is exactly 32 bytes:

| Offset in row | Size | Meaning |
|---:|---:|---|
| 0 | 16 | uppercase section name, NUL padded when shorter |
| 16 | 8 | payload-relative section offset |
| 24 | 8 | section size |

The payload begins immediately after the directory.

Common sections:

```text
CODE
DATA
STRINGS
SIGNATURES
EXPORTS
IMPORTS        future/native-link use
RELOCATIONS    future/native-link use
DEBUG          optional
```

Loaders validate every section range before admitting the image.

## Executable JXL

A `.jxl` has an entrypoint into `CODE`.

```text
.jx
 -> JX IR / PASM
 -> direct native encoder
 -> native CODE bytes
 -> JXNI image(entrypoint)
 -> program.jxl
```

Current direct x86-64 emission is implemented beneath `PASMNativeEncoder` by the proven historical direct encoder in `pasm-native-jxl.php`.

`jxl-native-compile.php` is the canonical native executable CLI.

## Loadable JLL

A normal `.jll` uses the same image layout but has no executable entrypoint. Its public callable surface ships with it.

```text
library.jll
|- CODE
|- STRINGS
|- SIGNATURES
`- EXPORTS
```

The library may be mapped once and retained in memory. Export lookup then resolves a name to a CODE offset and signature id.

### `STRINGS`

One NUL-terminated byte string table. Offset zero is the empty string.

Function names and type names are interned once and referenced by offsets.

### `SIGNATURES`

```text
u32 signature_count

repeated signature records:
    u32 return_type_string_offset
    u16 parameter_count
    u16 reserved
    u32 parameter_type_string_offset[parameter_count]
```

Identical parameter/return layouts share one signature id.

### `EXPORTS`

```text
u32 export_count

repeated 24-byte records:
    u32 name_string_offset
    u32 signature_id
    u64 CODE-relative function offset
    u32 flags
    u32 reserved
```

Private functions do not need export records.

### Loading

Shared parsing API:

```text
host/common/jx-native-image.h
host/common/jx-native-image.c
```

Linux JLL mapping:

```text
host/linux/jx-jll-loader.h
host/linux/jx-jll-loader.c
```

The Linux loader:

```text
mmap file read-only
 -> validate JXNI
 -> require JLL/x86_64-SysV
 -> locate CODE
 -> mmap anonymous RW
 -> copy CODE once
 -> mprotect RX
 -> resolve exports as code_base + export_offset
```

Windows JLL mapping:

```text
host/windows/jx-jll-loader.h
host/windows/jx-jll-loader.c
```

It uses `MapViewOfFile`, `VirtualAlloc`, then `VirtualProtect(PAGE_EXECUTE_READ)`.

Both loaders currently reject non-empty IMPORTS or RELOCATIONS sections. Native linking must be implemented deliberately rather than silently executing unresolved code.

---

# `.jxb` — resources

JXB is an archive of independently addressable resources.

```text
assets.jxb
|- jx-manifest.json
|- images/logo.png
|- models/skull.mesh
|- tables/taxonomy.bin
|- dictionaries/words.bin
`- shaders/main.bin
```

Canonical v1 uses ZIP-compatible central-directory indexing and per-member Deflate compression.

This intentionally differs from one monolithic gzip stream: a monolithic stream is poor at random member access. JXB keeps gzip-family Deflate compression while retaining ZIP-like seekability.

Runtime law:

```text
open archive
 -> read index
 -> find requested member
 -> decompress or stream only that member
```

Implementation:

```text
jx-jxb-archive.php
jxb-resource-pack.php
jxb-resource-get.php
```

A JXB may contain a `.jxl` or `.jll` as an ordinary member when bundling is useful. The contained native image remains a JXL/JLL; the surrounding JXB remains a resource archive.

---

# PASM and the old prepared stream

PASM is the low-level compiler language. It is short enough to be the usual bridge from resolved JX IR to native encoding.

PASL remains optional and useful for compiler readability, testing, arithmetic/control flow, and bootstrap work.

The repository also contains a proven fixed-width six-byte prepared execution format historically named JXL. That byte format is retained for benchmark and compatibility use, but **new `.jxl` files must not contain it**.

Persisted prepared/PASM compatibility output should use `.pbc` or an explicitly internal historical format name, never the public `.jxl` extension.

---

# Compatibility

Do not destroy old readers simply because meanings changed. Migration rule:

```text
old compiled Book / .64B reader   -> compatibility namespace/tooling
old six-byte prepared stream      -> internal benchmark/compatibility tooling
new .jxl                           -> native JXNI executable
new .jll                           -> native JXNI library
new .jxb                           -> indexed compressed resources
```

The canonical meanings above take precedence over older prose wherever documentation conflicts.