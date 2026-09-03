# JX executable compiler

## Principle

JX separates readable source, prepared execution, and compiled packaging.

```text
canonical .jx / .pasl
    -> PHP-backed JX/PASL front end
    -> semantic JX / PASM lowering
    -> prepared .jxl and/or target-native code
    -> deterministic .jxb Book
    -> JX / WSJX64 / OSAura64 runtime
```

The PHP front end supplies mature authoring, parsing/tooling, package construction, server/web hosting, diagnostics, and compiler orchestration. Native installed Books do not need to execute PHP source.

The optimization law is:

> **Resolve cold -> bind once -> execute hot.**

Canonical source remains programmer- and AI-readable. Register windows, prepared operation IDs, JXL attachments, native targets, hot-bank placement, and Book layout are compiler/runtime concerns.

## File contract

```text
.jx    canonical JX source
.pasl  PASL source
.jxl   canonical prepared executable stream
.jxb   compiled JX Binary Book/container
.pbc   legacy PASM bytecode compatibility container
.64B   legacy JXB filename only
```

Internal v1 JXB package identifiers remain `JX64B001` and `jx.64B/1` for binary compatibility. They are not the public filename extension.

## Commands

```bash
# Interpret current canonical .jx surface
php jx-run.php --print examples/hello.jx

# PASL arithmetic/control flow executes through prepared JXL
php jx-run.php --print examples/arith.pasl
php jx-run.php -o out.jxl examples/arith.pasl
php jx-run.php --print out.jxl

# Explicit old PBC compatibility target
php jx-run.php -o out.pbc examples/arith.pasl
php jx-run.php --print out.pbc

# Typed semantic JX -> prepared JXL
php jx-run.php --jxl -o program.jxl program.jx

# Typed semantic JX -> compiled JXB Book
php jx-run.php --jxb -o program.jxb program.jx

# --64b is accepted as a legacy spelling of --jxb, but a requested .64B
# output suffix is normalized to .jxb.
```

## Current pipeline

1. **`jx-run.php`** — JX product CLI and executable compiler driver.
2. **`JxEngine` (`jx-lang.php`)** — current PHP-backed Bag/Task/Book/Delivery host surface.
3. **PASL Engine (`pasm-lang.php`)** — lowers PASL into PASM semantic assembly and now emits prepared JXL by default.
4. **Loop compiler / foreach pass / loop fuser** — lower structured source into explicit PASM semantics before JXL encoding.
5. **`pasm-jxl.php`** — canonical PASM-profile JXL backend covering all current PASM semantic opcodes.
6. **JXL core/native container backend** — existing arithmetic/control and native Bag prepared bands.
7. **`JxbBook` / internal v1 Book packer** — deterministic `.jxb` packaging, admission, checksums and trust metadata.

## PASL to JXL

PASL no longer needs PBC as its ordinary prepared representation.

```text
PASL source
  -> PASM semantic assembly
  -> optimization / iterator rewrite / loop fusion
  -> six-byte JXL cells
```

Current bands:

```text
0x20..0x37  prepared arithmetic/control core
0x40..0x50  native Bag/container operations
0x51..0x76  PASM semantic operations (PASM 0x00..0x25)
0x77        64-bit MOVI continuation
```

The PASM-profile band includes arithmetic, bitwise operations, comparison/branches, stack operations, `LOAD32`/`STORE32`, forward/reverse iterators, iterator reset, named memory, and `MCALL0..3`.

Every JXL cell is six bytes. The opcode byte keeps its high bit clear; the five following attachment bytes keep the high bit set. Full 64-bit immediates use the continuation cell rather than truncation.

## Execution status

The PASL compiler and file/runtime admission path now use JXL as the canonical prepared representation. The host currently executes PASM-profile JXL by reconstructing the already-tested PASM runtime semantics.

The native x86-64 JXL dispatcher already executes the existing arithmetic/control and Bag/container bands. Direct native handlers for PASM-profile `0x51..0x77` remain the next performance layer; they should be added without changing the `.jxl` file contract.

## Active PASL/JX-lowerable control forms

Current structured lowering includes the compiler-backed loop and branch families such as:

```text
while
for
if / else
select / switch
break
continue
foreach / reverse iterator lowering where collection bindings are supplied
```

Mutation includes assignment, increment/decrement, arithmetic/bitwise compound operations, and the available PASM comparison branches.

## JXL is separate from global Hot-Call ABI v4

Do not conflate these decoders.

Global JX Hot-Call ABI v4:

```text
1xxxxxxx                  -> HOT / exactly one byte
0xxxxxxx xxxxxxxx         -> EXTENDED / exactly two bytes
```

JXL prepared execution:

```text
0xxxxxxx -> executable JXL opcode
1xxxxxxx -> attached extension/data byte; never opcode
```

Admission selects the execution mode and matching decoder once.

## Relationship to JXB

`.jxb` is the compiled Book/container, not a synonym for one bytecode stream.

It may carry:

```text
Book metadata
Bag schemas/state descriptors
Page/Control descriptions
assets
permissions/generations
JXL executable sections
target-native ELF/PE sections
HOT/prepared tables
optional canonical/debug maps
```

Native recognition is byte/magic based rather than suffix-trust based. Existing `.64B` files remain readable because their package bytes carry the same v1 identity, but new writers use `.jxb`.

See `../docs/NATIVE-JXB.md` and `../docs/JXB-COMPILED-BOOKS.md`.

## PHP crossing rule

PHP may host current web/server JX, compiler/front-end tooling, Book construction/validation, and adapters. It must not become a runtime requirement for already prepared native Books merely because PHP was used to compile them.

## Relation to `pasm-run.php`

`pasm-run.php` remains the PASL-focused runner/compiler and may retain explicit legacy PBC controls. `jx-run.php` is the JX product entry. The canonical public pipeline is now JXL for prepared execution and JXB for compiled packaging.

## Documentation contract

When JXL bit law, opcode bands, package identity, or language status changes, implementation + specification + tests should move together. The PASL JXL CI gate currently verifies all 38 PASM semantic opcodes round-trip through JXL and executes a PASL loop through the new path.