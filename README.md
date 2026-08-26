# jx (jinx)

**Formerly pasm-v2.** This repository is the home of **jx** (pronounced *jinx*) — the PHP-derived server-side language and runtime that grows out of PASM.

PASM remains the low-level frame, segment, register, and bytecode engine. **jx** is the language and product name that sits on top of it: Books, Bags, Pages, strict memory law, Delivery, complex numbers, smart table extrusion, and Resistant fallback.

> **Repo rename:** GitHub does not allow automated rename via API from this flow. In GitHub → Settings → General → Repository name, rename `pasm-v2` → `jx` (or `jx-lang`) when you are ready. Until then this tree is the canonical jx source; the old name is legacy.

## What jx is

| Layer | Role |
|-------|------|
| **PASM** (this codebase) | Frames, canonical registers, segmented pages, hot-path containers, bytecode VM, master table, x86 lowering sketches |
| **jx language** | Books, Bags, Pages, Task-as-Bag, `push`, sign/handshake writes, Delivery, `const`, complex, rhetorical natives, smart table maker |
| **Hosting module** | Embeds PHP, loads Books under isolation, server→browser coherent protocol |

## Ontology (jx)

- **Book** — compiled unit (pages, bags, libraries, entry points)
- **Page** — runnable surface in an X11-style memory state (maps onto PASM frames / page segments)
- **Bag** — only mutable container; underwritten capacity; writes only via allowance + sign + handshake
- **Task** — special Bag (execution context + `push` preassignments + `id()`)
- **Delivery** — deep path `parent.child.subchild...` extract/rebind
- **Resistant code** — safe fallback when pure native extrusion is not possible

## Memory law (non-negotiable)

No free memory writes. A write is legal only when:

1. A buffer of allowance is supplied  
2. It is handed to an underwritten bag  
3. Mutation goes through an event handshake  

PASM’s frame-local hot path + explicit canonical boundary (`dirtySegments` / `flush` / `defrag`) is the concrete substrate for this law.

## Map: PASM → jx

| PASM concept | jx name / role |
|--------------|----------------|
| Register frame | Page / Task memory state |
| Segment / page-aligned storage | Bag backing (underwritten region) |
| Hot-path container (Vector, Stack, …) | Bag contents on the fast path |
| `dirtySegments` / `flush` / boundary | Handshake / commit boundary |
| Master table / bytecode ops | Smart table maker rows |
| Program / entry | Book entry / Page spawn |
| Network packet / runtime | Hosting module transport (future protocol) |

## Quick pointers

- Runtime & containers: `pasm-runtime.php`, `pasm-oop-containers.php`, `pasm-canonical*.php`
- Bytecode & assembler: `pasm-bytecode.php`, `pasm-bytecode-optimized.php`
- Master table: `pasm-master-table.php` → evolves into jx smart table
- Language sketches: `pasm-lang*.php`, `pasl/`, `PASL_Language_Manual.md`
- jx design docs: [`jx/`](jx/)

## Status

PASM hot-path work is real and benchmarked. jx language rules, Book/Bag/Page API, Delivery, complex, and edge-case posture are specified under `jx/` and are being integrated into this tree. Perfection is amiss; gaps are tracked in `jx/GAPS.md`.

---

jx — pronounced jinx.
