# JX Native `.64B` Compiled Books

## Rule

Native JX distribution does not execute PHP source.

PHP may participate in authoring and compilation, especially for the web and
for current build tooling, but the native boundary is:

```text
canonical JX source / compiler inputs
        -> semantic lowering
        -> native target code + compiled tables
        -> deterministic JX compiled Book
        -> native runtime
```

The default extension is `.64B`, meaning a compiled 64-bit JX Book package.
The extension is descriptive only. Package bytes are authoritative.

A file renamed from:

```text
desktop.64B
```

to:

```text
desktop.book
payload.bin
main.data
```

must still be recognized as the same JX compiled Book.

## Container

`.64B` is a deterministic ZIP-compatible container. Entries are stored rather
than deflated, sorted by stable path, assigned normalized metadata, and written
with a fixed timestamp. This avoids compression-library variability and makes
identical compiler output byte-reproducible.

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

Because this entry is first and stored without compression, a native launcher
can recognize the Book using only the ordinary ZIP local-file header plus these
48 bytes. It does not require PHP, a ZIP library, or a filename-extension test.

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

- format (`jx.64B/1`)
- kind (`compiled-book`)
- architecture
- native target
- Book name
- compiler identity
- canonical content SHA-256
- ordered compiled-section table
- byte length and SHA-256 for every compiled section

Typical compiled sections are:

```text
BOOK/pages.bin or BOOK/pages.json
CODE/program.elf
CODE/program.pe
CODE/native.bin
HOT/registers.bin
HOT/reactions.bin
BAG/schema.bin
ASSET/index.bin
```

Source-language files are not native runtime dependencies. A native package may
carry source intentionally as an asset/debug section, but it must never be
required merely to wake or execute the compiled Book.

## Checksums

JX uses two distinct hashes.

### Canonical content hash

`content_sha256` is computed from the sorted compiled-section names, sizes, and
section SHA-256 values. It identifies the executable semantic payload without
creating a recursive self-hash problem inside the manifest.

This is appropriate for:

- compiled Book identity
- cache validation
- dependency comparison
- deduplication
- rebuild detection
- cross-machine equality

### Whole-file hash

`file_sha256` hashes the final deterministic ZIP bytes and is appropriate for:

- downloads
- mirrors
- installation verification
- exact artifact identity

Identical sections + identical metadata + identical JX compiler format are
expected to produce identical package bytes and therefore the same whole-file
SHA-256.

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
    -> map/load target-native sections
```

The small native probe performs the identity portion. `jx\NativeBook64` performs
full package and checksum validation in the current compiler/runtime tooling.

## Relationship to hot registers

A `.64B` Book is also the natural persistence point for precompiled awake-state
metadata:

```text
HOT/registers.bin
HOT/reactions.bin
```

For example:

```text
W0 = desktop-windows
W1 = pointer
W2 = keyboard
W3 = controls

W3:[12:1] -> reaction 47 -> COUNT
W1:[4:1]  -> reaction 18 -> LATEST
W2:[44:1] -> reaction 71 -> QUEUE
```

The `.64B` remembers enough compiled structure to wake those registers without
reparsing PHP or another authoring language. The registers themselves remain
awake-state acceleration and may be rebuilt each launch.

> Bags remember. Registers react. Compiled Books know how to wake.

## Web boundary

The web target remains separate. PHP can remain a useful server-side host for
web applications. Native JX has the stricter rule:

> Native installation consumes compiled Books, not PHP source.
