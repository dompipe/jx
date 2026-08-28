# JX executable compiler

## Principle

JX deliberately separates the readable language from the prepared execution representation.

```text
canonical .jx
    -> PHP-backed JX front end
    -> semantic JX / PASL lowering
    -> prepared JXL and/or target-native code
    -> deterministic .64B Book
    -> JX / WSJX64 / OSAura64 runtime
```

The PHP front end is a strength of the current product: it supplies mature authoring, parsing/tooling, package construction, server/web hosting, diagnostics, and compiler orchestration. It is **not** a requirement that native installed Books execute PHP source.

The optimization law is:

> **Resolve cold -> bind once -> execute hot.**

Canonical source remains programmer- and AI-readable. Register windows, prepared operation IDs, native targets, JXL attachments, and hot-bank placement are compiler/runtime concerns.

## Commands

```bash
# Interpret current canonical .jx surface (Bags, Tasks, Delivery, …)
php jx-run.php --print examples/hello.jx

# PASL arithmetic/control flow -> bytecode VM
php jx-run.php --print examples/arith.pasl
php jx-run.php -o out.pbc examples/arith.pasl
php jx-run.php --print out.pbc

# Inline
php jx-run.php --print -c 'bag = Bag.underwrite(64); ref = bag.sign("a"); bag.set(1).commit(ref);'
php jx-run.php --print -c '$x = 1 + 2 * 3;'
```

## Current pipeline

1. **`jx-run.php`** — JX product CLI / executable compiler driver.
2. **`JxEngine` (`jx-lang.php`)** — strips comments, canonicalizes aliases/members, recognizes the active JX object surface, and executes Bag/Task/Book/Delivery semantics on the PHP-backed runtime in `jx.php`.
3. **PASL Engine (`pasm-lang.php`)** — lowers pure arithmetic/control-flow fragments to PASM assembly/bytecode and native targets where supported.
4. **Loop compiler (`pasm-lang-compiler-loop.php`)** — active out-of-line `for`/`while` loop blocks, `if/else`, `select`/`switch`, `break`, `continue`, integer/bitwise mutation, and complex working values.
5. **SmartTable / alias tables** — retain canonical method/extrusion decisions and source provenance while removing runtime alias lookup.
6. **`NativeBook64`** — deterministic `.64B` compiled-Book packaging and validation.
7. **JXL** — the newly ratified prepared executable layer; its authoritative byte law is documented in `../docs/JXL-PREPARED-EXECUTION.md`.

`.jx` programs that use the current Bag/Book/Task surface execute under the PHP-backed memory law today. Pure lowerable PASL fragments compile through the PASM compiler/VM/native machinery. The integration direction is to move more canonical JX semantics through semantic IR into JXL/native executable sections without changing the readable source language.

## Active PASL/JX-lowerable control forms

The current compiled control-flow set includes:

```text
while
for
if / else
select / switch
break
continue
```

Mutation forms include:

```text
=
++  --
+=  -=  *=  /=  %=
&=  |=  ^=  <<=  >>=
```

Comparison guards include:

```text
==  !=  <  <=  >  >=
```

Complex declarations/arithmetic are also compiler-backed within the current register limits.

`foreach` is intentionally recognized and rejected until collection lowering is linked. The semantic loop-space model also reserves `do-while` and `repeat`, but those surface forms are not advertised as ACTIVE yet. See `../docs/JX-PROGRAMMING-TUTELAGE.md` for the status-aware language map.

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

Admission selects the execution mode once and binds the matching decoder. A repeat loop must not keep asking which mode it is in.

The global eight-shadow discipline remains authoritative for JX/OSAura hot service banks. Protected `F0-FF` remains unassigned unless explicitly ratified in a future ABI change.

## Relationship to `.64B`

`.64B` is the compiled Book/container, not a synonym for one bytecode stream.

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

Native recognition is byte/magic based rather than filename-extension based. Native installation consumes compiled Books, not PHP source as a runtime dependency.

## PHP crossing rule

PHP may:

- host the current web/server JX runtime,
- implement compiler/front-end tooling,
- build and validate Books,
- expose PHP-backed library objects during the transition,
- provide adapters for SQL/NoSQL/plugins/hosts.

PHP must not become an excuse to skip JX ownership/security/canonicalization laws. External data still enters Bags through the JX boundary. Aliases still canonicalize before execution. Native Books must not require PHP source merely to execute prepared code.

## Relation to `pasm-run.php`

`pasm-run.php` remains the PASL-focused runner/compiler.

`jx-run.php` is the JX product entry: it understands the current `.jx` surface and delegates lowerable code to the PASL/PASM stack.

The long-term public path is one JX compiler experience whose target decides whether the product is web/PHP-hosted, JXL, target-native, or packaged as a `.64B` Book.

## Documentation contract

The repository now gates the critical language/preparation distinctions through `test-jx-language-doc-contract.php`. If the JXL bit law, ABI-v4 distinction, loop status, canonicality rule, or protected-bank invariant changes, implementation + specification + tutorial + tests must change together.
