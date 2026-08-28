# JX v0.1 design and implementation docs

This directory is the canonical language-level documentation for **JX v0.1**. PASM remains the engine underneath; PASL is the compiler path; JX is the programming and application surface.

For a programmer learning the language, start with the repository-wide **[JX Programming Tutelage](../docs/JX-PROGRAMMING-TUTELAGE.md)**. It is the broad status-aware guide to current syntax, loops, values/types, Bags, Books, Pages, Tasks, Controls, data, containers, compiler lowering, the PHP-backed front end, and the planned language families.

For executable preparation, read **[JXL Prepared Execution](../docs/JXL-PREPARED-EXECUTION.md)**. JXL is intentionally separate from the global Hot-Call ABI v4: JXL uses `0xxxxxxx` as executable opcodes and `1xxxxxxx` as attached data, while ABI v4 keeps its own one-byte HOT / two-byte EXTENDED law.

| File | Content |
|---|---|
| [../docs/JX-PROGRAMMING-TUTELAGE.md](../docs/JX-PROGRAMMING-TUTELAGE.md) | Large programming guide and language-status map |
| [../docs/JXL-PREPARED-EXECUTION.md](../docs/JXL-PREPARED-EXECUTION.md) | Authoritative JXL prepared execution contract |
| [../docs/HOT-CALL-ABI-V4.md](../docs/HOT-CALL-ABI-V4.md) | Global JX/OSAura hot-call ABI; distinct from JXL |
| [SPEC.md](SPEC.md) | JX v0.1 language specification |
| [RHETORIC.md](RHETORIC.md) | Rhetorical parameter order and paragraph flow |
| [Flow.php](Flow.php) | Executable rhetorical adapter used by real examples |
| [bootstrap.php](bootstrap.php) | Canonical one-time JX bootstrap for adapters/apps |
| [SQL.php](SQL.php) | First-class secure SQL object |
| [SQL.md](SQL.md) | SQL ownership, security, adapters, queries, transactions, and Bag synchronization |
| [COMPILER.md](COMPILER.md) | Compiler direction, current lowering, PHP/JXL/native boundaries |
| [smart-table.md](smart-table.md) | Smart Table maker and native/Resistant selection |
| [delivery.md](delivery.md) | Delivery deep paths |
| [complex.md](complex.md) | Complex numbers |
| [hosting-api.md](hosting-api.md) | Book / Page / Bag hosting API |
| [CONTROL.md](CONTROL.md) | Host-neutral Controls, Images, movement, and Themes |
| [STYLE.md](STYLE.md) | Page Style, collectors, anchors, gap, hex colors, backgrounds, and transparency |
| [APACHE.md](APACHE.md) | Apache reverse proxy, persistent JX host, PHP-FPM fallback, TLS, assets, and service deployment |
| [PASM_MAP.md](PASM_MAP.md) | Concrete PASM ↔ JX implementation map |
| [WINDOW_SERVER.md](WINDOW_SERVER.md) | JX window-server direction |
| [INSTALL.md](INSTALL.md) | Installation and plugin policy |
| [edge-cases.md](edge-cases.md) | Resistant-code stress cases |
| [GAPS.md](GAPS.md) | Remaining gaps, separated by language/runtime/preparation status |
| [CONVERSATION_LOG.md](CONVERSATION_LOG.md) | Design conversation record |

The standalone `dompipe/jx-lang` repository was an earlier design tree. The integrated runtime in this repository is the current target.

## Feature-status vocabulary

Current documentation uses four labels so a large and quickly growing project does not accidentally turn design direction into false implementation claims:

```text
ACTIVE      accepted/tested by the current implementation
PHP-BACKED  available through the PHP host/runtime API
JXL         compiler-produced prepared execution, not canonical source
PLANNED     deliberate direction, not claimed as accepted syntax
```

When a feature changes status, update the implementation, tests, specification, tutorial, and gap ledger together.

## Reading order

For someone learning the project, use this path:

```text
JX Programming Tutelage
  -> SPEC
  -> RHETORIC
  -> hosting-api
  -> CONTROL
  -> STYLE
  -> APACHE
  -> SQL
  -> COMPILER
  -> JXL Prepared Execution
  -> HOT-CALL-ABI-V4
  -> PASM_MAP
  -> smart-table
  -> edge-cases / GAPS
```

The documentation should be readable from the outside inward: first what a JX programmer writes, then what is active versus planned, then how a Page is composed and styled, then how the Book is hosted, then how durable storage crosses into SQL/NoSQL, then how PASL lowers it, how JXL remembers prepared decisions, and finally how PASM/native hosts execute it.

## Canonicality rule

Do not make application programmers manage register windows, JXL attachment bytes, hot-bank numbers, or native dispatch slots merely to obtain performance.

```text
readable canonical JX
    -> compiler/prelink preparation
    -> JXL / HOT / native form
```

**Canonical source is for coders and AI to read. Prepared execution is for the machine to repeat.**
