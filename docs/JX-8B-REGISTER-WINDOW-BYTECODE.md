# JX `.8B` Register-Window Prepared Format

> **Compatibility/internal format.** `.8B` preserves the older prepared register-window execution design. Public `.jxl` now means a native JXNI executable image and must not contain this six-byte/register-window stream.

## Purpose

`.8B` is a compact prepared representation for compiler experiments, benchmarks, compatibility execution, and register-window research. It is useful precisely because it can preserve already-resolved compiler decisions without pretending to be the native executable file boundary.

Canonical public formats are:

```text
.jx   Jinx source
.jxl  native Jinx executable image
.jll  native Jinx Loadable Library
.jxb  indexed compressed resources
.8B   historical/internal prepared register-window stream
.pbc  PASM bytecode/prepared compatibility representation
```

## Register-window idea

A prepared block can select an eight-entry window into a larger logical register file:

```text
prepared block
    |
    +-- register window
           |
           +-- local selector 0..7
                   |
                   +-- logical register 0..255
```

A window is eight bytes:

```c
uint8_t register_id[8];
```

With 32 windows:

```text
32 * 8 = 256 logical register ids
```

The register id is compact; the register value itself remains native width.

## Performance rule

A hot prepared operation must not search for a register on every execution.

Required model:

```text
cold admission
 -> validate block/window
 -> resolve local selectors
 -> prelink register references

hot execution
 -> compact operation
 -> already-resolved registers
```

The window is therefore primarily admission metadata.

## Blocks

A historical block descriptor can remain conceptually:

```c
typedef struct {
    uint32_t code_offset;
    uint32_t code_bytes;
    uint8_t register_window;
    uint8_t flags;
    uint16_t reserved;
} jx8b_block;
```

Changing block changes the local working set without adding a runtime page-search instruction.

## Prepared bindings

A prepared binding may retain both compact selectors and resolved logical register ids:

```c
typedef struct {
    uint8_t opcode;
    uint8_t block;
    uint8_t local0;
    uint8_t local1;
    uint8_t register0;
    uint8_t register1;
    uint8_t native_operation;
    uint8_t flags;
} jx8b_prepared;
```

Admission can verify:

```text
window[block.window][local0] == register0
window[block.window][local1] == register1
```

and then bind direct register storage.

## Prepared byte law

The historical prepared stream uses the separately documented prepared-execution grammar. It must not be confused with either the current JXNI native-image ABI or the global JX Hot-Call ABI.

The important migration rule is:

```text
old six-byte/prepared stream -> .8B or internal in-memory representation
native executable output     -> .jxl
native library output        -> .jll
```

Files such as `pasm-jxl.php`, `jx-jxl-compiler.php`, and the native container benchmark sources retain historical `JXL` names because they are proven subsystems. Those names are internal compatibility terminology, not the public extension definition.

## Canonical source rule

Programmers do not manually manage register windows in ordinary `.jx` code.

Compiler work can still use this sequence for the prepared compatibility path:

```text
canonical variables
 -> liveness analysis
 -> allocate logical registers
 -> group hot sets into windows of eight
 -> form prepared blocks
 -> prelink bindings
 -> emit internal/.8B prepared stream
```

The normal native path instead continues from resolved JX IR/PASM into the direct native encoder and wraps resulting CODE in JXNI:

```text
.jx
 -> JX IR
 -> PASM
 -> native encoder
 -> JXNI
      entrypoint    -> .jxl
      no entrypoint -> .jll
```

## Relationship to Bags

The register-window concept remains useful independently of the public file extension:

```text
registers -> immediate hot working state
Bags      -> durable/structured semantic state
```

A hot Bag field may still be promoted/cached into a logical register during compiler preparation.

## Historical Book packaging

Older `.64B`/compiled-Book packages could carry `.8B` or historically named JXL prepared sections. Those packages remain compatibility artifacts. New `.jxb` means indexed compressed resources.

If an old Book is admitted, its internal prepared section should be identified by its bytes/legacy manifest, not reclassified as a modern `.jxl` executable.

## Admission checks

A compatibility `.8B` loader should continue validating:

- format/version,
- window and block ranges,
- local selectors 0..7,
- logical register ids 0..255,
- branch targets,
- prepared binding consistency,
- attachment boundaries,
- malformed/truncated instructions.

## Why retain it

This prepared format remains useful for:

- benchmark reproducibility,
- testing compiler decisions,
- comparing prepared-dispatch and direct-native paths,
- bootstrap execution,
- compatibility with existing JX experiments.

It simply no longer owns the `.jxl` name.

For the current public binary contracts see [`JX-FILE-FORMATS.md`](JX-FILE-FORMATS.md).