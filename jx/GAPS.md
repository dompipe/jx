# JX gaps — active roadmap

This file is the implementation gap ledger. A gap is closed only when code, tests, and the relevant specification/tutorial agree.

Use the project documentation status words consistently:

```text
ACTIVE      accepted/tested today
PHP-BACKED  usable through the current PHP host/runtime API
JXL         prepared executable representation
PLANNED     intended surface/direction, not claimed as implemented
```

## 1. Canonical language surface

### High

1. **General canonical function declarations and `return`** — PHP-backed runtime code exists, but standalone `.jx` user-function parsing/lowering is not yet ACTIVE.
2. **Canonical class/OOP declarations** — runtime/compiler OOP machinery exists; source-level `class`, visibility, method declaration, constructor, interface/trait/enum semantics need one ratified parser/type contract.
3. **`foreach` collection lowering** — the PASL compiler recognizes and deliberately rejects it. Link iteration to record/vector/stack/queue/deque/map/set disciplines without forcing every container through one slow generic iterator.
4. **`do-while` and `repeat` surface lowering** — loop-space kinds exist semantically; parser/compiler forms need implementation and tests.
5. **Explicit typed scalar declarations** — integer/complex compiler storage exists and float/boolean/string values exist at JX/PHP boundaries, but a canonical typed declaration grammar and typed JXL/register metadata are not yet ratified.
6. **Exception syntax** — phase-aware runtime exceptions exist; canonical `try/catch/finally/throw` and native unwinding/error-frame semantics remain to be defined.
7. **Module/import namespace law** — Book/Library/plugin identity should define canonical imports rather than accidentally exposing PHP filesystem inclusion as universal JX semantics.

### Medium

8. `elseif` / `else if` direct parser convenience (nested `if` is usable today).
9. Value-producing `match` surface and deterministic lowering.
10. Closures/arrow functions with explicit capture and Bag ownership rules.
11. `yield`/generator lowering into resumable Task/frame state.
12. `async`/`await` lowering into scheduler-visible event state rather than polling.
13. String/template/interpolation grammar beyond current runtime/binding string handling.
14. Null/optional typing and explicit missing-vs-null semantics in canonical source.
15. Generic/container type annotations once the native shape system is stable enough to benefit.

## 2. JX -> JXL/native compiler integration

### High

16. **One semantic IR for canonical `.jx`** — current `JxEngine` directly recognizes core JX statements while pure arithmetic/control flow delegates to PASL. More JX constructs should lower through one typed semantic representation before choosing PHP/JXL/native backends.
17. **Direct canonical `.jx` -> JXL section emission** — the JXL contract is ratified in `../docs/JXL-PREPARED-EXECUTION.md`; general emitter/admission/runtime execution remains to be completed.
18. **Direct canonical `.jx` -> native `.64B` executable Book path** — deterministic `.64B` packaging exists and native Store examples build ELF/PE, but arbitrary canonical JX does not yet compile end-to-end to a native executable Book.
19. **JXL admission/decoder implementation and parity tests** — mode must bind once, high-bit bytes must be attachments only, and the same section must have identical semantics under JX host, WSJX64, and OSAura64.
20. **Typed register file metadata** — retain compact register IDs while proving integer/float/boolean/handle/Bag-reference/complex representations once at admission.
21. **Prepared Bag/method offsets** — move more receiver/field/method resolution out of repeat execution and into compiler/prelink tables.

### Medium

22. Const propagation through Delivery, Bags, complex values, and JXL preparation.
23. Liveness/register allocation across larger JX methods, including spill/promotion policy to Bags.
24. Canonical source/debug map from JXL/native offsets back to JX source without retaining source as a runtime dependency.
25. Deterministic explanation output: canonical statement -> semantic op -> JXL/native lowering -> hot target.
26. Broader mixed-workload benchmark suite comparing PHP-hosted, PASL/PBC, JXL, native ELF/PE, and direct-native baselines.

## 3. Bag / memory law

### High

27. Handshake protocol detail and formal write-state machine.
28. RefSign unforgeability across host/native boundaries.
29. Ref lifetime: automatic versus explicit revocation/unsign and generation boundaries.
30. Complex/typed-value capacity accounting in Bags.
31. Canonical copy-on-write / borrow rules for more than UI views, including explicit lifetime proof for borrowed pointed data.

### Medium

32. One-shot sign-and-write sugar that cannot bypass memory law.
33. More formal Bag schema/type evolution across `.64B` generations.
34. Efficient canonical checkpoint/diff representation for very large Bags.

## 4. Controls, Pages, and bindings

### High

35. Persist Bag-backed Control identity across every browser/native host path; moving/reparenting a view must never recreate semantic Control state.
36. Source-binding revision/stale-update law across SQL/NoSQL/channels/browser/native hosts.
37. One canonical event -> listener -> Task -> JXL reaction -> Bag mutation -> dirty-surface service turn.
38. Page/Control serialization into `.64B` with host-neutral layout/style semantics.

### Medium

39. Native font/text shaping parity.
40. Accessibility/semantic UI metadata independent of browser HTML.
41. Cursor, drag/drop, clipboard, IME, and richer input contracts that remain host-neutral.
42. Explicit animation/timing model that does not require busy loops.

## 5. Tasks, bus, and scheduling

### High

43. Canonical scheduler policy shared in meaning across native OSAura64 and WSJX64.
44. Program/listener identity contract connecting Book Task IDs, JX11 listener PIDs, security subjects, and host process identities without conflating them.
45. Full processor-bus integration in the JX language repo: Bag generation descriptors, foreground listener bookmark, PID-ordered traversal, CHECK/RETURN semantics, and borrowed response lifetime.
46. Event/service-turn latency benchmarks from host input to Bag mutation to visible present.

### Medium

47. Fairness/backpressure rules for high-rate bus/event producers.
48. Multi-core scheduling/prepared-queue semantics without losing deterministic Bag generations.
49. Cross-process/channel transport that preserves the same logical bus contract.

## 6. Security and host boundaries

### High

50. Formal PHP <-> JX crossing rules: ownership, secrets, handles, pointers, exceptions, and serialization.
51. Capability proof attached to prepared JXL/native targets rather than repeated name/policy lookup.
52. Browser/server protocol for Pages, Bags, sources, and events without leaking SQL credentials or host-private handles.
53. `.64B` executable signing/trust policy in addition to deterministic hashing/checksums.

### Medium

54. Sandboxed plugin capability declarations and native extension loading policy.
55. Cross-host security conformance fixtures.
56. Audit/provenance format for AI/compiler generated code and prepared bindings.

## 7. Books, versions, and live generations

### High

57. Book schema/version compatibility rules across hot reload.
58. Candidate-generation validation for JXL/native code + Bag continuity + source binding continuity.
59. Rollback semantics for executable generation plus durable state migrations.

### Medium

60. Partial Book updates/deduplication without weakening deterministic content identity.
61. Distribution/index format for large plugin/library/Book ecosystems.
62. Reproducible cross-target build metadata while keeping target-native sections separate.

## 8. Documentation / AI coherence

### High

63. Keep `../docs/JX-PROGRAMMING-TUTELAGE.md`, `SPEC.md`, compiler docs, and tests synchronized as syntax moves from PLANNED -> ACTIVE.
64. Keep JXL separate from global Hot-Call ABI v4. Never silently reuse one decoder's byte law in the other.
65. Preserve the rule that global `F0-FF` is protected/unassigned until explicit ABI ratification.
66. Add executable examples whenever a new syntax family becomes ACTIVE.

### Process rule

The root `test-jx-language-doc-contract.php` gates several architectural statements so future documentation/AI changes cannot silently reverse them.

## 9. Perfection criterion

A feature is not complete because a document describes it or because one host can fake it.

A JX feature is complete when, as applicable:

```text
canonical syntax is specified
parser accepts it
semantic meaning is host-neutral
ownership/security law is explicit
compiler lowers it deterministically
JXL/native representation is defined
runtime executes it
host parity is tested
errors are diagnosable back to canonical source
benchmarks measure the relevant repeat path
AI-facing tutorial marks it ACTIVE
```

That criterion is intentionally strict because JX is accumulating a large amount of language, UI, data, kernel, plugin, and native execution information. The project must grow by adding prepared structure, not by allowing contradictory meanings to pile up.
