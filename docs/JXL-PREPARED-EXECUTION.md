# Historical JXL Prepared-Execution ABI

## Status

**Internal/compatibility ABI. Not the public `.jxl` file format.**

This document preserves the proven prepared-execution design that older JX code and benchmarks called **JXL**. The name remains in implementation files and benchmark labels for provenance, but the canonical public extension contract is now:

```text
.jx   Jinx source
.jxl  native JXNI executable image
.jll  native JXNI loadable library
.jxb  indexed compressed resource archive
.8B   historical/internal prepared stream when persisted explicitly
.pbc  PASM bytecode/prepared compatibility container
```

For current public binary formats, [`JX-FILE-FORMATS.md`](JX-FILE-FORMATS.md) is authoritative.

---

## Prepared byte law retained for compatibility

The historical prepared stream uses:

```text
0xxxxxxx = executable prepared opcode
1xxxxxxx = attached extension/data byte; never an independent opcode
```

This remains distinct from the global JX Hot-Call ABI:

```text
1xxxxxxx                  = HOT / one byte
0xxxxxxx xxxxxxxx         = EXTENDED / two bytes
```

A compatibility loader must select the proper decoder once at admission rather than test modes on every operation.

---

## Why the prepared representation remains useful

Canonical JX source can express readable operations while the compiler resolves details such as:

- register allocation,
- branch destinations,
- loop bodies,
- Bag/container operation ids,
- receiver shapes,
- native operation targets,
- constants,
- prepared bindings,
- register windows,
- hot/cold placement.

The prepared stream records those decisions so a compatibility executor or benchmark can repeat only the cheap work.

That purpose remains valid even though the public native executable boundary moved to JXNI.

---

## Relationship to the native path

Current preferred compilation is:

```text
.jx
 -> PHP/JX front end
 -> canonical JX IR
 -> PASM directly where appropriate
 -> direct native encoder
 -> machine CODE
 -> JXNI
      entrypoint    -> .jxl
      no entrypoint -> .jll
```

The older prepared path remains:

```text
JX/PASL
 -> PASM semantics
 -> fixed-width prepared encoding
 -> in-memory compatibility executor
    or explicit .8B/internal artifact
```

It is useful for regression testing, bootstrap work, and comparison against direct native emission.

---

## Six-byte prepared cells

The established container/native prepared operations use six-byte cells in relevant bands:

```text
+0 opcode
+1 attachment/binding low
+2 attachment/binding high
+3 source selector 0
+4 source selector 1
+5 destination selector
```

Attachment bytes use the high-bit convention required by the historical decoder.

Some PASM-profile operations use additional continuation cells for values that cannot fit in one cell, including full-width immediates.

The exact opcode tables remain implemented and tested in the historical prepared subsystem (`pasm-jxl.php`, semantic JXL compiler/runtime files, and native `jxl_*` benchmark sources). Those implementation tables remain the compatibility authority for byte-for-byte regression work.

---

## Prepared bindings

The central performance rule remains:

> Resolve identities, addresses, and operation targets once; do not perform string/name discovery in the repeated path.

A prepared binding may therefore hold:

```text
operation id
container/bag binding id
resolved register selectors
native target id
receiver/layout metadata
```

The exact shape depends on the prepared band being exercised.

---

## Register windows

The older `.8B` register-window design can be used with the prepared representation to expose a larger logical register file through small local working sets.

See [`JX-8B-REGISTER-WINDOW-BYTECODE.md`](JX-8B-REGISTER-WINDOW-BYTECODE.md), which is also explicitly classified as compatibility/internal material.

---

## Native container benchmarks

Files and benchmark labels such as:

```text
native/x86_64/jxl_container_executor.asm
native/x86_64/jxl_containers.asm
benchmark-jxl-containers.php
benchmark-jxl-map-layouts.php
```

retain `jxl` in their names because they measure the established prepared-dispatch subsystem. Renaming those files would obscure benchmark history and is not required to change the public extension contract.

When discussing those results, use wording such as **historical prepared JXL executor** or **six-byte prepared executor** rather than implying that benchmark input is a modern `.jxl` JXNI executable.

---

## Historical Book relationship

Older `.64B`/compiled-Book packages could contain prepared streams under names such as `CODE/program.jxl`. Those are legacy package members identified by the old manifest/byte format.

They are not modern JXNI `.jxl` images.

Compatibility readers may continue admitting them through the old package path. New `.jxb` archives are resources and new `.jxl` outputs are native JXNI executables.

---

## Admission requirements

A prepared compatibility loader should continue to reject malformed data, including:

- unsupported opcode bands,
- high-bit attachment bytes without a valid owning operation,
- truncated cells/continuations,
- invalid binding ids,
- invalid register selectors,
- invalid branch targets,
- mismatched prepared tables,
- incompatible mode/version metadata.

The native JXNI loader has a separate validation contract defined in [`JX-FILE-FORMATS.md`](JX-FILE-FORMATS.md).

---

## Fixed terminology

Use these phrases consistently:

```text
"prepared JXL" / "six-byte JXL"  -> historical/internal prepared ABI
".jxl executable"                -> native JXNI image with entrypoint
".jll library"                   -> native JXNI image without normal entrypoint
".jxb"                           -> indexed compressed resource archive
```

The prepared system remains important. It simply no longer owns the public `.jxl` extension.