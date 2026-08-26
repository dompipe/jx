# Known Gaps — Perfection Is Amiss

This file is the living list of things the design conversation did not fully close. It exists so that "we almost certainly missed something" is an explicit, tracked fact rather than a vague worry.

## High priority

1. **Handshake protocol** — exact phases, error paths, visibility of partial mutation.
2. **RefSign security** — representation, unforgeability, cross-Task leakage prevention.
3. **Ref lifetime** — automatic unsign on scope/bag drop vs mandatory explicit unsign.
4. **Scheduling policy** — cooperative / preemptive / priorities for Pages.
5. **Server → browser protocol** — concrete messages and coherence guarantees.

## Medium priority

6. Book versioning and hot-reload semantics.
7. Error model (structured errors, interaction with Resistant code).
8. Const propagation rules through Delivery and complex operations.
9. PHP ↔ jx value crossing rules (copy, sign, quotient impact).
10. Complex values inside Bags (alignment, size accounting).

## Lower priority / process

11. One-shot sign-and-write convenience form.
12. AI interpreter coherence of smart table + live state.
13. Meta-testing: proof that Resistant markers are emitted and that edge tests fail closed.

## Rule for closing a gap

- Write the decision into the relevant `docs/*.md` or `SPEC.md`.
- Add or adjust an edge-case test if the gap touches safety or Resistant behaviour.
- Remove or strike the item from this file only when the above is done.

Perfection is amiss. Tracking the amiss is how we stay honest.
