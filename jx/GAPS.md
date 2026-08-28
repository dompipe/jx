# JX gaps — active roadmap

This file is the implementation gap ledger. A gap is closed only when code, tests, and the relevant specification/tutorial agree.

Use the project documentation status words consistently:

```text
ACTIVE      accepted/tested today
PHP-BACKED  usable through the current PHP host/runtime API
JXL         prepared executable representation
PLANNED     intended surface/direction, not claimed as implemented
```

The OS product name is **Osaura**. Lowercase implementation symbols such as `osaura_*` remain correct.

## Recently closed or materially advanced

- **Semantic compiler spine:** `../jx-semantic.php` now provides a typed canonical parser/IR and PHP-backed semantic executor, plus a compact JXL emitter/VM subset.
- **Language families now parsed by the semantic spine:** functions/return, typed declarations, `elseif`, `foreach`, `do-while`, `repeat`, class/method/property declarations, namespace/import records, and try/catch/finally/throw. Native lowering breadth still varies by construct.
- **Canonical numeric `.jx` -> JXL -> deterministic `.64B`:** `../jx-jxl-book64.php` packages prepared code and validates it by bytes/hashes rather than filename extension.
- **Prepared metadata:** `../jx-prepared-metadata.php` defines stable ABI-facing type IDs and deterministic source maps; compiled Books now contain `META/prepared.json`.
- **Book trust:** `../jx-book-trust.php` defines normalized capabilities and a detached Ed25519 trust envelope bound to both canonical content hash and exact Book file hash.
- **Bag write law:** `../jx-bag-transaction.php` implements authenticated generation-bound RefSigns and the sign -> authorize -> reserve -> write -> commit state machine.
- **Service-turn law:** `../jx-service-turn.php` implements foreground-first but fair listener service, Bag publication only after real mutations, and one coalesced present after a bounded turn.

These advances do **not** mean every construct is native JXL/Osaura-ready yet. The remaining work below is primarily backend breadth, host integration, security enforcement, live generations, and ecosystem hardening.

## 1. Canonical language surface

### High

1. **Functions and `return` — ADVANCED.** Semantic parser/executor support is ACTIVE. Expand JXL/native lowering to the full function type/call surface and migrate legacy `JxEngine` paths onto the same IR.
2. **Canonical class/OOP declarations — ADVANCED.** Semantic class/method/property parsing exists. Finish inheritance/interface/trait/enum law, `this`/receiver semantics, visibility enforcement, constructors, and prepared native offsets.
3. **`foreach` collection lowering — ADVANCED.** Semantic execution exists. Add specialized JXL/native lowering for record/vector/stack/queue/deque/map/set without forcing all shapes through a slow generic iterator.
4. **`do-while` and `repeat` — ADVANCED.** Semantic support exists. Complete broad JXL/native backend coverage and documentation examples.
5. **Explicit typed declarations — ADVANCED.** Semantic types plus stable prepared type IDs now exist. Osaura/WSJX64 admission must consume and enforce the same metadata.
6. **Exception syntax — ADVANCED.** Semantic try/catch/finally/throw exists. Native error frames/unwinding and cross-host exception law remain.
7. **Module/import namespace law — ADVANCED.** Semantic records exist. Resolve imports through Book/Library/plugin identity rather than PHP filesystem inclusion.

### Medium

8. `elseif` direct parser convenience — semantic spine ACTIVE; migrate legacy path and examples.
9. Value-producing `match` surface and deterministic lowering.
10. Closures/arrow functions with explicit capture and Bag ownership rules.
11. `yield`/generator lowering into resumable Task/frame state.
12. `async`/`await` lowering into scheduler-visible event state rather than polling.
13. String/template/interpolation grammar beyond current runtime/binding string handling.
14. Null/optional typing and explicit missing-vs-null semantics in canonical source.
15. Generic/container type annotations once native shapes benefit from them.

## 2. JX -> JXL/native compiler integration

### High

16. **One semantic IR — ADVANCED.** A real typed semantic spine now exists in `../jx-semantic.php`; migrate remaining legacy `JxEngine`/PASL-special-case paths onto it so feature meaning is defined once.
17. **Direct canonical `.jx` -> JXL — ADVANCED.** Numeric/control-flow subset is implemented and tested. Broaden JXL lowering across Bags, collections, OOP, exceptions, calls, handles, and host services.
18. **Direct `.jx` -> `.64B` — ADVANCED.** Deterministic compiled Books carrying JXL are implemented. Add target-native executable sections and make this the ordinary production path.
19. **JXL admission/decoder parity.** Port/admit the same JXL byte law under PHP host, WSJX64, and Osaura64; bind mode once and keep `1xxxxxxx` attachment bytes non-opcodes.
20. **Typed register metadata — ADVANCED.** Stable prepared type IDs exist and are embedded in `.64B`; wire them into JXL/native register admission and reject representation mismatches before execution.
21. **Prepared Bag/method offsets.** Move receiver/field/method resolution from repeat execution into compiler/prelink tables.

### Medium

22. Const propagation through Delivery, Bags, complex values, and JXL preparation.
23. Liveness/register allocation across larger JX methods, including spill/promotion policy to Bags.
24. **Source/debug mapping — ADVANCED.** Deterministic semantic node/source-line metadata is embedded in Books. Add exact JXL/native byte offsets and debugger consumption.
25. Deterministic explanation output: canonical statement -> semantic op -> JXL/native lowering -> hot target.
26. Broader mixed-workload benchmark suite comparing PHP-hosted, PASL/PBC, JXL, native ELF/PE, and direct-native baselines.

## 3. Bag / memory law

### High

27. **Handshake/write-state machine — REFERENCE ACTIVE.** `../jx-bag-transaction.php` enforces sign -> authorize -> reserve -> write -> commit -> generation+1. Integrate the same law into production Bag stores and native hosts.
28. **RefSign unforgeability — REFERENCE ACTIVE.** RefSigns are opaque HMAC-SHA256 authenticated bag/subject/generation/nonce tokens. Define key ownership/rotation across process boundaries and native hosts.
29. **Ref lifetime — ADVANCED.** RefSigns are generation-bound and stale references fail. Add explicit revocation/unsign and borrowed-pointer lifetime enforcement in native memory.
30. Complex/typed-value capacity accounting in Bags.
31. Canonical copy-on-write / borrow rules beyond UI views, including lifetime proof for borrowed pointed data.

### Medium

32. One-shot sign-and-write sugar implemented only as composition of the secure state machine, never as a bypass.
33. Formal Bag schema/type evolution across `.64B` generations.
34. Efficient checkpoint/diff representation for very large Bags and dirty field/page publication.

## 4. Controls, Pages, and bindings

### High

35. Persist Bag-backed Control identity across every browser/native host path; moving/reparenting a view must never recreate semantic Control state.
36. Source-binding revision/stale-update law across SQL/NoSQL/channels/browser/native hosts.
37. **Canonical service turn — REFERENCE ACTIVE.** `../jx-service-turn.php` defines listener -> execute -> Bag publish -> dirty -> coalesced present with bounded foreground preference. Wire it to real JX11/JXL execution in Osaura/WSJX64.
38. Page/Control serialization into `.64B` with host-neutral layout/style semantics.

### Medium

39. Native font/text shaping parity.
40. Accessibility/semantic UI metadata independent of browser HTML.
41. Cursor, drag/drop, clipboard, IME, and richer host-neutral input contracts.
42. Explicit animation/timing model that does not require busy loops.

## 5. Tasks, bus, and scheduling

### High

43. Canonical scheduler policy shared in meaning across native Osaura64 and WSJX64.
44. Program/listener identity contract connecting Book Task IDs, JX11 listener PIDs, security subjects, and host process identities without conflating them.
45. Full processor-bus integration in the JX language repo: Bag generation descriptors, foreground listener bookmark, PID-ordered traversal, CHECK/RETURN semantics, and borrowed response lifetime.
46. Event/service-turn latency benchmarks from host input to Bag mutation to visible present.

### Medium

47. **Fairness/backpressure — ADVANCED.** Reference service turn gives the primary listener a bounded quantum then services every remaining PID in stable order. Add queue depth/backpressure/drop/coalescing policies for sustained producers.
48. Multi-core scheduling/prepared-queue semantics without losing deterministic Bag generations.
49. Cross-process/channel transport that preserves the same logical bus contract.

## 6. Security and host boundaries

### High

50. Formal PHP <-> JX crossing rules: ownership, secrets, handles, pointers, exceptions, and serialization.
51. **Capability proof — ADVANCED.** Compiled Books can carry a signed detached capability claim through `BookTrust`; runtime admission must bind those capabilities to prepared calls and enforce them without repeated string/policy lookup.
52. Browser/server protocol for Pages, Bags, sources, and events without leaking SQL credentials or host-private handles.
53. **`.64B` executable signing/trust — ADVANCED.** Detached Ed25519 envelopes are bound to validated canonical content and exact Book bytes. Define issuer/key distribution, revocation, policy roots, and native Osaura/WSJX64 enforcement.

### Medium

54. Sandboxed plugin capability declarations and native extension loading policy.
55. Cross-host security conformance fixtures.
56. Audit/provenance format for AI/compiler-generated code and prepared bindings.

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

The root `test-jx-language-doc-contract.php` gates architectural statements so future documentation/AI changes cannot silently reverse them. Every root `test-*.php` is automatically owned by `test-all.php`, so the new semantic, Book, trust, Bag transaction, prepared metadata, and service-turn contracts are part of the full runnable gate.

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

That criterion remains intentionally strict. JX is accumulating language, UI, data, kernel, plugin, security, and native execution information quickly; the product must absorb that flood by adding prepared structure rather than contradictory meanings.
