# JX `.jxb` Resource Archives

## Canonical rule

`.jxb` is the Jinx binary/resource package.

It is **not** the canonical executable format and it is **not** the loadable-library format.

```text
.jx   source code
.jxl  native executable
.jll  native loadable library
.jxb  compressed indexed resources
```

## Archive model

JXB is intentionally archive-like. Canonical v1 uses the ordinary ZIP container structure with per-member Deflate compression while keeping the `.jxb` extension.

That gives JX exactly the behavior wanted from a resource package:

```text
open package
 -> read central directory / member index
 -> seek directly to a named member
 -> decompress only that member
 -> return or stream it
```

The entire archive must not be decompressed merely to read one image, table, model, dictionary, font, shader, or other resource.

## Typical package

```text
assets.jxb
|- jx-manifest.json
|- images/
|  `- logo.png
|- models/
|  `- skull.mesh
|- fonts/
|- tables/
|  `- taxonomy.bin
|- dictionaries/
|- shaders/
`- data/
```

The member names are the resource namespace. Runtime lookup can therefore be as simple as:

```text
JXB.open("assets.jxb")
JXB.get("images/logo.png")
JXB.stream("tables/taxonomy.bin")
```

## Compression

The public rule is **individually compressed members**, not one monolithic gzip stream.

A single gzip stream would require walking/decompressing earlier data to reach arbitrary later content. ZIP-compatible Deflate provides the gzip-family compression behavior while retaining an index suitable for pull-what-you-want access.

Therefore:

```text
compression family = Deflate
archive behavior    = ZIP-compatible indexed members
public extension    = .jxb
```

Stored/uncompressed members may still be used deliberately for data that is already compressed or should be memory-mapped without another compression pass.

## Manifest

Canonical resource packages should include:

```text
jx-manifest.json
```

At minimum it identifies:

```json
{
  "format": "jx.jxb/1",
  "compression": "zip-deflate",
  "members": []
}
```

Future manifest fields may carry resource aliases, hashes, MIME/type hints, architecture-independent prepared data descriptions, or cache policy. They must not turn JXB back into the executable-code identity.

## Code in JXB

A JXB may contain a `.jxl` or `.jll` file as a resource when an application deliberately bundles one, just as a ZIP can contain an executable file. That does **not** change the contained artifact's identity.

```text
bundle.jxb
|- app.jxl
|- plugins/sqlite.jll
`- images/logo.png
```

The runtime extracts/maps the requested JXL/JLL member according to its own native-image rules.

JXB itself remains the archive.

## Current implementation

`jx-jxb-archive.php` provides the canonical PHP-side pack/read implementation:

- `JxbArchive::create()` builds a `.jxb` using ZIP + Deflate.
- `JxbArchive::open()` opens the index.
- `get()` returns exactly one named member.
- `stream()` opens exactly one member as a stream.
- `names()` lists indexed members.

Path traversal member names are rejected.

## Historical compiled-Book compatibility

The repository contains an older compiled-Book format using identifiers such as:

```text
JX64B001
jx.64B/1
```

and an earlier phase exposed those Books using `.jxb` filenames. Those bytes may remain readable through explicit compatibility code, but they are not the canonical meaning of new `.jxb` output.

Do not delete proven old readers merely because the public contract changed. Qualify them as legacy compiled-Book/64B readers and keep new JXB resource writers separate.

## Relationship to JXL and JLL

```text
JX source
  -> JX IR / PASM
  -> native encoder
  -> shared native image
       entrypoint     -> program.jxl
       no entrypoint  -> library.jll

resources
  -> indexed per-member Deflate archive
  -> assets.jxb
```

JXL and JLL are native code images. JXB is the resource/archive boundary.

## Compatibility rule

From this revision forward, new documentation and tooling should use these meanings:

> **`.jxl` native executable; `.jll` native loadable library; `.jxb` indexed compressed resource archive.**

Legacy `.64B`/compiled-Book support is compatibility work, not the definition of JXB.