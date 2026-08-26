# jx Language Specification (v0.1)

## 1. Identity

- Name: **jx**
- Pronunciation: *jinx*
- Foundation: PHP (hosting module embeds and expands the original engine)
- Compilation target: native (via smart table maker) with Resistant fallback
- Memory model: strict, Docker-like isolation; X11-style page staging

## 2. Ontology

### Book
Compiled unit. Contains Pages, Bags, libraries, entry points. Loaded under isolation by the hosting module.

### Page
Runnable surface in an X11-like memory state. Scheduled by TaskHandler.

### Bag
Only mutable memory container. Capacity is underwritten at creation. Writes require:
- buffer of allowance
- underwritten bag
- event handshake

### Task
Special Bag. Adds:
- `push(key, value)` — property preassignment
- inner scoped variables
- `id()` — stable task identifier

## 3. Keywords (selected)

- `const` — immutable binding; also castable `(const)expr`
- `delivery` — deep path extract / rebind
- Complex literals: `3 + 4i`, `complex(re, im)`

## 4. Tight vs Verbose

Verbose (placebo) forms exist only for readability. They lower exclusively to tight methods before code generation. The assembler never sees the verbose surface.

## 5. Smart Table Maker

See `docs/smart-table.md`.

## 6. Resistant Code

When the smart table cannot emit a pure native path that still obeys memory and safety rules, it emits Resistant code: correct, tested, explicitly marked, lower purity.

## 7. Hosting Module

Embeds PHP, expands with jx, isolates each Book under class + memory constraints. Provides the protocol by which server-side state can update browser-side surfaces coherently.
