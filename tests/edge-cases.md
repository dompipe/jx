# Edge-Case Tests — Stressing Resistant Code

These cases deliberately push the compiler and runtime into regions where pure native extrusion is difficult or unsafe. The expected outcome is either a clean static rejection or emission of marked Resistant code that still preserves the memory model.

## 1. Deep Delivery into missing structure
```jx
val = config.server.ports.https.delivery()
// when config.server is null / undefined
```
Expect: controlled error or default; never a raw crash.

## 2. Delivery into const target
```jx
const c = 0
c.delivery(some.path)   // must be rejected
```

## 3. Quotient exhaustion
```jx
bag = Bag.underwrite(16)
ref = bag.sign(node)
bag.set(largeBuffer).commit(ref)   // larger than quotient
```
Expect: rejection before any store; server stays alive.

## 4. Sign / unsign races under concurrency
Multiple Pages signing and unsigning the same Bag region.
Expect: TaskHandler serialises or isolates; no use-after-unsign.

## 5. Complex edge values
```jx
c = 1e308 + 1e308i
d = c * c
// overflow / inf handling
```
Expect: defined behaviour (inf / error), not UB.

## 6. Const cast violations
```jx
x = (const) mutableBag
// later attempt to mutate through x
```
Expect: static or dynamic rejection.

## 7. Hostile dynamic shapes
Objects that change shape between Delivery path resolution and use.
Expect: Resistant checks or rejection; no assembler explosion.

## 8. One-shot sign-and-write under low quotient
```jx
bag.set(data).commit(bag.sign(node))  // data larger than remaining
```
Expect: atomic failure, no partial write.

## 9. Task push after Task has been scheduled
Mutation of preassignments from another Page.
Expect: only legal through proper ref + handshake; otherwise rejected.

## 10. Resistant marker visibility
Any emitted Resistant region must be introspectable (debug / audit) so developers know purity was traded.

---

All of the above must pass on every supported compiler backend. The collective steering goal remains the five pillars A–E while keeping the language coherent and non-divisive.
