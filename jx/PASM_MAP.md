# PASM ↔ jx implementation map

Use this when changing code in this repo so the engine and the language stay aligned.

## Frames and Pages

- `PASMRegisterFrame` / frame pool → **Page** (and Task) memory state
- Frame-local hot arrays in OOP containers → Bag hot path (no segment tax until boundary)
- `M_FRAME` and frame id → `Task.id()` / Page identity

## Segments and Bags

- Page-aligned segments (`SEGNEW` / segmented storage) → **Bag** underwritten capacity
- `dirtySegments()` / `clearDirty()` / `flush()` / `defrag()` → **handshake / commit** boundary
- Write only at boundary or through signed path → jx memory law

## Containers

- `PASMList` / Vector, Stack, Queue, Deque, Map, Set → Bag-resident structures
- Hot-path mutation stays frame-local; canonical image is write-back → matches “no free write; commit at boundary”

## Tables and compilation

- `pasm-master-table.php` → seed for **smart table maker** (`jx/smart-table.md`)
- `pasm-bytecode*.php` / assembler → native / Resistant extrusion targets
- `pasm-lang-compiler.php` / PASL → front door for jx surface syntax over time

## Program and Book

- `pasm-program.php` / run entry → **Book** load + Page spawn
- Isolation today is frame-level; Book-level quota is the jx hosting-module target

## Rename discipline

- User-facing docs and new APIs: prefer **jx** names (Book, Bag, Page, Delivery, quotient, push, Resistant)
- Internal PHP class names may stay `PASM*` until a deliberate rename pass
- New files for language surface: prefer `jx-*.php` or under `jx/` / `pasl/` as appropriate

## Resistant code

When a lowering cannot satisfy memory law + pure native template, emit the safe path and mark it. Edge cases: `jx/edge-cases.md`.
