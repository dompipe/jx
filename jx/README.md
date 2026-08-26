# JX v0.1 design and implementation docs

This directory is the canonical language-level documentation for **JX v0.1**. PASM remains the engine underneath; PASL is the compiler path; JX is the programming and application surface.

| File | Content |
|---|---|
| [SPEC.md](SPEC.md) | JX v0.1 language specification |
| [RHETORIC.md](RHETORIC.md) | Rhetorical parameter order and paragraph flow |
| [Flow.php](Flow.php) | Executable rhetorical adapter used by real examples |
| [COMPILER.md](COMPILER.md) | Compiler direction and lowering |
| [smart-table.md](smart-table.md) | Smart Table maker and native/Resistant selection |
| [delivery.md](delivery.md) | Delivery deep paths |
| [complex.md](complex.md) | Complex numbers |
| [hosting-api.md](hosting-api.md) | Book / Page / Bag hosting API |
| [CONTROL.md](CONTROL.md) | Host-neutral Controls, Images, movement, and Themes |
| [STYLE.md](STYLE.md) | Page Style, collectors, anchors, gap, hex colors, backgrounds, and transparency |
| [PASM_MAP.md](PASM_MAP.md) | Concrete PASM ↔ JX implementation map |
| [WINDOW_SERVER.md](WINDOW_SERVER.md) | JX window-server direction |
| [INSTALL.md](INSTALL.md) | Installation and plugin policy |
| [edge-cases.md](edge-cases.md) | Resistant-code stress cases |
| [GAPS.md](GAPS.md) | Remaining gaps |
| [CONVERSATION_LOG.md](CONVERSATION_LOG.md) | Design conversation record |

The standalone `dompipe/jx-lang` repository was an earlier design tree. The integrated runtime in this repository is the current target.

## Reading order

For someone learning the project, use this path:

```text
SPEC
  -> RHETORIC
  -> hosting-api
  -> CONTROL
  -> STYLE
  -> COMPILER
  -> PASM_MAP
  -> smart-table
  -> edge-cases / GAPS
```

The documentation should be readable from the outside inward: first what a JX programmer writes, then how a Page is composed and styled, then how PASL lowers it, then how PASM executes it.
