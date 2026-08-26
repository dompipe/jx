# jx Language Specification (v0.1)

Integrated target: this repository (pasm-v2 → jx).

## Identity

- Name: **jx** · Pronunciation: *jinx*
- Foundation: PHP + PASM engine in this tree
- Compilation: smart table → native preferred, Resistant fallback
- Memory: strict; Docker-like isolation; X11-style page staging

## Ontology

- **Book** — compiled unit
- **Page** — runnable X11-like surface (PASM frame)
- **Bag** — only mutable container (segment-backed, hot path frame-local)
- **Task** — special Bag (`push`, inner scope, `id()`)
- **Delivery** — deep path extract/rebind
- **Resistant** — marked safe fallback code

## Keywords (selected)

`const` (castable), complex literals (`3+4i`), delivery paths, rhetorical natives (`put`/`take` direction), symbolic asm constants (`SYS_*`, `STDOUT`, …).

## Memory law

Writes only with allowance + underwritten bag + event handshake. Quotient oversight prevents server-crashing overflow.

## Tight vs verbose

Verbose (`tell`/`pass`) lowers only to tight methods before codegen/assembler.

See sibling files in this directory for smart table, Delivery, complex, hosting API, edge cases, conversation log, and gaps.
