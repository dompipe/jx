# jx Design Conversation Log

This document captures the design conversation that produced the jx (jinx) language specification, from the early Axis/yaxis discussion through Books, Bags, Pages, the smart table maker, Delivery, complex numbers, and the edge-case posture. It ends with a deliberate acknowledgement that perfection is amiss — things were almost certainly missed.

---

## 1. Origin and renaming

- Started from discussion of an Axis-like language and a VS Code extension.
- "yaxis" / y-axis tables were examined for insert throughput impact (help / harm / confine).
- Decision: replace manual y-tables with an adaptive ingest / write-buffer module.
- Language renamed to **jx**, pronounced **jinx**, to avoid naming collisions.

## 2. Native calls and assembler friendliness

- Major functions must be assembly-friendly (clear inputs/outputs).
- Names should be rhetorical for common tasks.
- Assembler surface needs symbolic constants with adamantly obvious names (`SYS_WRITE`, `STDOUT`, `O_CREAT`, `PROT_READ`, `MAP_PRIVATE`, etc.).
- No magic numbers in source.

## 3. Memory model (hard rule)

- **No memory writes by default.**
- A write is legal only when:
  1. A buffer of allowance is supplied,
  2. It is handed to an underwritten bag,
  3. Mutation occurs through an event handshake.
- Docker-like isolation: memory must never leave the current jx process / container boundary.
- Bags are the only mutable containers. Tasks are special Bags.

## 4. Bag / Task surface evolution

- `Bag.underwrite(size)`
- `bag.sign(node) → refSign`
- `bag.unsign(refSign)` (later noted as potentially optional / automatic)
- `bag.set(...).commit(ref)`, `bag.onchange(...).commit(ref)`, `bag.get(ref)`
- Oversight: `bag.capacity()`, `bag.used()`, `bag.quotient()` (remaining space) to prevent overflows that could crash the server.
- Task as special Bag: property preassignments + inner scoped variables.
- `preassign` renamed to **`push`** (more agentic).
- Tight methods are the real surface; verbose/placebo forms (`tell` / `pass`) lower exclusively to tight forms so the assembler only sees the clean calls.

## 5. X11-like character and multitasking

- Design began to resemble X11: programs as pages, bags as displayable surfaces.
- Multitasking required inside the server TaskHandler.
- Task can report its own `task.id()`.
- Task = special Bag unified allocation, signing, overflow protection, and execution context.

## 6. Reflection on undeeded ref ceremony

Some ref-related operations felt heavy:
- Explicit `unsign` on every path (could be scope-automatic).
- Separate `.commit(ref)` step (could fold into the mutation call).
- `get(ref)` when the ref already implies the region.
- Two-step sign-then-write for one-shot cases.

These remain open for tightening.

## 7. Rainbow / smart table and compilation posture

- Prefer a **smart table maker** over a static rainbow table.
- Table knows every method, writing style, side-effect class, and preferred native lowering.
- Extrudes fast native code when safe; otherwise emits **Resistant code** (correct, marked, lower purity).
- Goal: an AI interpreter that knows every function can fasten jx to assembly with ease.
- All code must be checked across compilers; edge cases must be protected.

## 8. Full package steering (A–F)

- **A** — Platform staging treats Pages in an X11 state of memory.
- **B** — Language is about compiled **Books** more than single pages. Ontology: Books, Bags, Pages.
- **C** — Delivery (derivative apprehensives): `parent.child.subchild...` for deep extract/rebind.
- **D** — `const` is a keyword and is castable.
- **E** — Complex numbers are first-class (`3 + 4i`, `complex(re, im)`).
- **F** — Built on PHP via a hosting module that embeds the original engine, expands with jx, isolates Book libraries under class + memory constraints. Server-side updates browser-side through a coherent protocol. Derivative strengths are the commodity; weaknesses are canonised and given controlled remediation at CLI and displayed surfaces.

## 9. Edge-case morph (tests that stress Resistant code)

See also `tests/edge-cases.md`. Summary of the stress set:

1. Deep Delivery into missing structure
2. Delivery into a `const` target
3. Quotient exhaustion on write
4. Sign / unsign races under concurrency
5. Complex edge values (overflow / inf)
6. Const-cast violations
7. Hostile dynamic shapes between path resolution and use
8. One-shot sign-and-write under low quotient
9. Task `push` / mutation from another Page without proper ref
10. Resistant regions must remain introspectable

---

## 10. Perfection is amiss — what we may have missed

Perfection is amiss. The conversation moved quickly across renaming, memory law, X11 resonance, multitasking, smart tables, PHP foundation, and edge cases. The following are known or likely gaps; they are recorded so they are not silently forgotten.

### Likely missed or under-specified

- **Exact handshake protocol** — request/ack/commit wire shape, failure modes, and whether partial commits are ever visible.
- **RefSign representation and forge resistance** — how a ref is implemented so it cannot be guessed or manufactured outside the TaskHandler.
- **Automatic vs explicit unsign** — final rule for lifetime of refs (scope exit, bag drop, explicit only).
- **One-shot sign-and-write sugar** — whether a single call may both sign and mutate.
- **Scheduling policy** — cooperative only, preemptive slices, priorities, fairness across Pages.
- **Book versioning and hot reload** — how a running Book is replaced without tearing Pages.
- **Browser-side protocol** — concrete messages for server → browser surface updates.
- **Error model** — structured errors vs exceptions vs status codes; interaction with Resistant code.
- **Const propagation depth** — how far `const` and cast-const flow through Delivery and complex ops.
- **Complex + Delivery + Bag interaction** — storing complex values inside signed regions, alignment, and quotient accounting.
- **PHP interop boundary** — which PHP values may cross into jx Bags and under what copying / signing rules.
- **AI interpreter state** — how an AI instance keeps the smart table and live Bag/Task state coherent across turns.
- **Testing of the tester** — who verifies that Resistant markers are actually present and that edge-case tests fail closed.

### Attitude

These gaps are not failures; they are the natural residue of a design that prioritised coherent direction over exhaustive closure in one pass. Future work should close them deliberately, one at a time, without weakening the memory law or the Book/Bag/Page ontology.

---

*End of conversation log. Perfection is amiss; the work continues.*
