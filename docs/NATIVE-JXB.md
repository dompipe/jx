# JX Native `.jxb` Compiled Books

## Rule

Native JX distribution does not execute PHP source.

PHP may participate in authoring and compilation, especially for current build tooling, but the native boundary is:

```text
canonical JX / PASL source
        -> semantic lowering
        -> prepared JXL and/or native target code
        -> deterministic .jxb compiled Book
        -> native runtime
```

The canonical public extension is **`.jxb`**: JX Binary Book.

The old `.64B` suffix is a legacy filename convention only. New writers do not emit it. Historical Books remain readable because package identity comes from the bytes, not the suffix.

## JXL versus JXB

The two formats have separate jobs:

```text
.jxl = prepared executable instruction stream
.jxb = compiled Book/container
```

A JXB may carry one or more JXL streams together with metadata, Bag/container bindings, prepared tables, assets, or native ELF/PE sections.

## Stable v1 package identity

The public filename changed without invalidating the proven v1 package ABI. These internal identifiers remain stable:

```text
magic:           JX64B001
manifest format: jx.64B/1
header entry:    JX64/header.bin
manifest entry:  JX64/manifest.json
```

`JX64B001` and `jx.64B/1` are versioned internal identifiers. They are not the preferred filename extension.

A historical file such as `desktop.64B`, a canonical `desktop.jxb`, or even a renamed `payload.bin` is the same Book if the package bytes validate.

## Container

JXB v1 is a deterministic ZIP-compatible container. Entries are stored rather than deflated, sorted by stable path, assigned normalized metadata, and written with a fixed timestamp. Identical compiler input is therefore intended to produce byte-identical package output.

The first entry is mandatory:

```text
JX64/header.bin
```

It is exactly 48 bytes:

```text
offset  size  meaning
0       8     magic: JX64B001
8       2     major version, little-endian
10      2     minor version, little-endian
12      4     compiled section count
16      32    SHA-256 of JX64/manifest.json
```

Because this entry is first and stored without compression, a native launcher can recognize the Book using the ordinary ZIP local-file header plus these 48 bytes. It does not need PHP, a ZIP library, or a filename-extension test for identity probing.

The C probe ABI lives in:

```text
host/common/jx64-probe.h
host/common/jx64-probe.c
```

## Manifest and compiled sections

The second reserved entry is:

```text
JX64/manifest.json
```

It records at least:

- package format (`jx.64B/1`)
- kind (`compiled-book`)
- architecture
- native/prepared target
- Book name
- compiler identity
- canonical content SHA-256
- ordered compiled-section table
- byte length and SHA-256 for every compiled section

Typical compiled sections include:

```text
CODE/program.jxl
CODE/program.elf
CODE/program.pe
CODE/native.bin
META/prepared.json
BOOK/pages.bin
BOOK/pages.json
HOT/registers.bin
HOT/reactions.bin
BAG/schema.bin
ASSET/index.bin
```

Source-language files are not native runtime dependencies. Source may be carried intentionally as a debug/asset section, but a compiled Book must not require source merely to wake or execute.

## Checksums

JX uses two distinct hashes.

### Canonical content hash

`content_sha256` is computed from the sorted compiled-section names, sizes, and section SHA-256 values. It identifies the executable semantic payload without creating a recursive self-hash problem inside the manifest.

Useful for:

- compiled Book identity
- cache validation
- dependency comparison
- deduplication
- rebuild detection
- cross-machine equality

### Whole-file hash

`file_sha256` hashes the final deterministic package bytes and is useful for downloads, mirrors, installation verification, and exact artifact identity.

## Native recognition

The native loader follows bytes, not names:

```text
open candidate file
    -> read ZIP local header
    -> require first entry JX64/header.bin
    -> require STORE encoding and 48-byte header
    -> require magic JX64B001
    -> validate format version
    -> validate manifest SHA-256
    -> validate per-section SHA-256
    -> validate canonical content SHA-256
    -> admit prepared/native sections
```

`jx\NativeBook64` is retained as the internal v1 packer class name for compatibility. Its default public extension is now `.jxb`.

`jx\semantic\JxbBook` is the public compiled-Book contract used by the semantic/JXL toolchain.

## PASL/JXL packaging

PASL now lowers its complete current PASM semantic operation set to prepared JXL. The intended composition is:

```text
PASL
  -> PASM semantic lowering
  -> .jxl prepared stream
  -> optional .jxb Book
```

JXL keeps execution semantics compact. JXB adds package identity, metadata, bindings, capabilities, signatures, assets, and native sections around those streams.

## Hot registers and Bags

JXB is the persistence boundary for precompiled awake-state metadata such as:

```text
HOT/registers.bin
HOT/reactions.bin
BAG/schema.bin
```

The Book remembers enough compiled structure to wake registers and bind Bags without reparsing an authoring language on every launch.

> Bags remember. Registers react. Compiled Books know how to wake.

## Compatibility rule

New code and documentation should say `.jxb` for a compiled Book and `.jxl` for a prepared executable stream.

Compatibility readers may still accept historical `.64B` filenames. The stable internal v1 strings `JX64B001` and `jx.64B/1` remain unchanged until a deliberate package-ABI version change is made.