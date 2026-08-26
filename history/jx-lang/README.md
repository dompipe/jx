# jx (jinx)

**jx** (pronounced *jinx*) is a PHP-derived server-side language that expands into Books, Bags, and Pages under a strict, Docker-like memory model. It targets native code through a smart table maker, with Resistant fallback when purity cannot be guaranteed.

## Core Concepts

| Concept | Meaning |
|---------|---------|
| **Book** | The compiled unit. Contains Pages, Bags, libraries, and entry points. Loaded by the hosting module under isolation. |
| **Page** | A runnable surface living in an X11-style memory state. Scheduled by the TaskHandler. |
| **Bag** | The only mutable memory container. A Task *is* a special Bag. All writes require underwrite + sign + handshake. |
| **Delivery** | Deep path operator: `parent.child.subchild...` extracts or rebinds nested values. |
| **Resistant code** | Safe fallback emitted when the smart table cannot produce a pure native path. |

## Design Pillars

- **A** — Platform staging treats Pages in an X11 state of memory.
- **B** — Language is about compiled **Books**, not single pages.
- **C** — Delivery (derivative apprehensives) for deep structure access.
- **D** — `const` is a keyword and is castable.
- **E** — Complex numbers are first-class.
- **F** — Built on PHP via a hosting module that embeds the original engine, expands with jx, and isolates Book libraries under memory constraints. Server-side can update browser-side through a coherent protocol.

## Memory Model (non-negotiable)

Memory writes are forbidden by default. A write is legal only when:

1. A **buffer of allowance** is supplied,
2. It is handed to an **underwritten bag**,
3. Mutation occurs through an **event handshake**.

Tasks are special Bags. They support `push` for property preassignments and inner scoped variables.

## Quick Surface

```jx
// Book / Page / Bag
book = Book.load("dashboard.jx")
page = Page.spawn(entry, bag)
bag  = Bag.underwrite(4096)

// Task (special Bag)
task = Task.underwrite(8192)
task.push("title", "Settings")
id = task.id()

// Sign + mutate
ref = bag.sign(node)
bag.set(data).commit(ref)          // tight
bag.tell(set, data).pass(ref)      // verbose → lowers to tight

// Oversight
remaining = bag.quotient()

// Delivery
port = config.server.ports.https.delivery()
newVar.delivery(config.server.ports.https)

// const + complex
const limit = 100
c = 3 + 4i
```

## Repository Layout

- `docs/smart-table.md` — Smart table maker schema
- `docs/delivery.md` — Delivery syntax and lowering
- `docs/complex.md` — Complex number surface and native representation
- `docs/hosting-api.md` — Book / Page / Bag API for the hosting module
- `tests/edge-cases.md` — Edge-case tests that stress Resistant code
- `SPEC.md` — Consolidated language specification

## Status

Specification stage. Compiler, hosting module, and AI interpreter are future work. The goal is that any AI instance that knows every method can fasten high-level jx to assembly with ease, falling back to Resistant code only when necessary.

---

jx — pronounced jinx.
