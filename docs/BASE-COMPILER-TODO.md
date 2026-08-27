# JX Base Compiler TODO

This file tracks compiler-level work that should apply across hosts, not just JX11.

## Core law: Bags remember; registers react

The canonical JX model must distinguish durable/observable semantic state from awake-state execution acceleration.

- **Bags remember.** Bags remain the canonical, checkpointable, inspectable representation of program state.
- **Registers react.** Registers are live native memory/cache structures used only while a compiled program is awake.
- **Reactions are prepared while waking.** Anything knowable before the interactive path should be prelinked into the awake cache.
- Register layout is never semantic. A program must be reconstructible from canonical state without preserving the prior process memory layout.
- On startup/resume, canonical state is resolved/prelinked into registers.
- During execution, hot paths use numeric register/slot/shadow identities instead of strings, object lookup, or repeated graph traversal.
- At required boundaries/checkpoints, dirty register state is synchronized back into canonical Bags.
- On process exit/crash/restart, registers may disappear; canonical Bag state remains authoritative.

## Native compiled-Book boundary

Native JX must not require PHP source after compilation. PHP remains useful as an authoring/compiler host and as a web target, but the native distribution boundary is a deterministic compiled Book package.

The default native package extension is `.64B`. The extension is descriptive only; package bytes are authoritative. A native launcher recognizes the mandatory `JX64/header.bin` entry and `JX64B001` magic even if the file is renamed.

```text
canonical JX / compiler input
    -> semantic IR
    -> target lowering
    -> native code + compiled tables
    -> deterministic .64B Book
    -> native ELF/PE/runtime
```

The package carries enough compiled information to wake without reparsing the authoring language:

```text
JX64/header.bin
JX64/manifest.json
BOOK/...
CODE/...
HOT/registers.bin
HOT/reactions.bin
BAG/...
ASSET/...
```

Every compiled section has a SHA-256. The manifest records a canonical content SHA-256, while the final deterministic archive also receives a whole-file SHA-256 for exact distribution identity. See `docs/NATIVE-64B.md`.

## Universal hot-event address

The WindowBag proof generalized into the base JX reactive address:

```text
W:slot:shadow
W3:[12:1]
```

The hot address is exactly 24 bits:

```text
8-bit register | 8-bit slot | 8-bit shadow

W3:[12:1] -> 0x03 0x0c 0x01 -> 0x030c01
```

The three routing bytes are universal across Controls, pointer, keyboard, windows, media, timers, Bag reactions, and future native hosts. Canonical names remain available in compiler/debug provenance tables; the interactive path carries only the numeric address and payload.

### Delivery policy is compile-time state

Each reactive shadow receives a delivery policy when the program wakes:

- `LATEST`: retain only the newest value in an event quantum (pointer movement, resize, slider motion, orientation).
- `QUEUE`: preserve every event in order (key/button down/up, submit, toggle, selection, transaction/commit, close).
- `ONCE`: one occurrence per quantum (enter/leave style transitions).
- `COUNT`: count repeated equivalent occurrences (click/double-click).
- `ACCUMULATE`: combine numeric/delta payloads (wheel/scroll and similar deltas).

Hosts MUST NOT silently invent a different loss/coalescing policy. Canonical JX may override a compiler default explicitly.

### Binary hot datagram ABI

`jx.hot-event/1` defines an 8-byte fixed header followed by an optional payload:

```text
byte 0    version
byte 1    register
byte 2    slot
byte 3    shadow
byte 4    delivery policy code
byte 5    flags
byte 6-7  payload length, network byte order
byte 8..  payload
```

The routing address is always bytes `1..3`. The packet may ride any suitable datagram transport. Local native hosts should prefer a nonblocking local datagram transport; UDP is appropriate when the producer/consumer boundary is genuinely networked. Transport failure must not redefine canonical program semantics.

## WindowBag proof case

A WindowBag register maps one canonical Bag identity once during startup:

```text
W0 = desktop-windows
```

A window-specific reference retains its compact two-byte form inside the register:

```text
[slot:shadow]
[17:3] -> 0x1103
```

Combined with the register byte this is the universal address:

```text
W0:[17:3] -> 0x001103
```

## Required base-compiler work

- [x] Generalize the register-cache concept beyond JX11 into a base runtime primitive (`HotRegisterBank`, `HotRef`).
- [x] Define universal 24-bit `W:slot:shadow` hot addresses in PHP and native C.
- [x] Define cross-host binary hot-event datagram framing.
- [x] Define compile-time delivery policies and defaults for common input families.
- [x] Define deterministic native `.64B` compiled-Book packaging with internal magic and checksums.
- [x] Make native package recognition independent of filename extension.
- [x] Prove Linux ELF can compile, package, rename and natively recognize a `.64B` Book.
- [ ] Prove the same full native `.64B` path for Windows PE in CI.
- [ ] Replace placeholder HOT tables in example `.64B` packages with compiler-emitted register/reaction tables.
- [ ] Add register classes/namespaces so host-specific caches such as `W0` do not collide with arithmetic/value registers.
- [ ] Add compiler-IR assignment of canonical Bag/target -> register identity.
- [ ] Add compiler-IR assignment of object/member/window/control -> slot identity.
- [ ] Add compiler-IR assignment of reactive handler/event -> shadow identity.
- [ ] Preserve canonical provenance for every register, slot and shadow for diagnostics/debugging.
- [x] Emit/parse packed `[slot:shadow]` references in the base runtime.
- [ ] Define shadow `0` as canonical state unless a stronger cross-domain rule is adopted.
- [ ] Reserve domain shadow maps for prelinked reactions such as focus, title, geometry, controls, media, chart and application handlers.
- [ ] Compile dirty-source -> affected-shadow dispatch lists so runtime does not discover dependencies dynamically.
- [ ] Union duplicate shadow invalidations within one event quantum and execute each affected shadow once.
- [ ] Define checkpoint instructions that synchronize only dirty register state back into the owning Bag.
- [ ] Define wake/rebuild instructions that restore register caches from canonical Bag state.
- [ ] Define overflow/refusal behavior; a register cache must never silently spill into another object's bounded memory.
- [ ] Ensure interpreter, PASM, native ELF, native PE/EXE and future hosts preserve identical canonical semantics even when their register layouts differ.
- [ ] Add compiler reports showing canonical object -> register -> slot -> shadow mappings and delivery policy.
- [x] Add benchmarks comparing canonical/runtime lookup with prelinked register dispatch for JX11.
- [ ] Extend the mixed-bag benchmark model to generic Controls/input once the first native Control host consumes `jx.hot-event/1`.

## Compiler pipeline target

```text
canonical JX
    -> canonical/semantic IR
    -> dependency analysis
    -> register + slot + shadow + delivery allocation
    -> prelinked execution shadow
    -> native target code + compiled tables
    -> deterministic .64B compiled Book
    -> native awake-state register cache
    -> nonblocking hot datagrams / direct in-process dispatch
    -> dirty/checkpoint synchronization
    -> canonical Bag output/state
```

## Non-negotiable invariants

> Canonical richness must not require runtime richness.

> Native installation consumes compiled Books, not PHP source.

Anything knowable at compile/startup time should be removed from the interactive hot path when observable behavior can remain identical.

The WindowBag register was the first proof case. The same base mechanism now applies to Controls, OOP objects, Bag containers, Delivery paths, SQL/NoSQL handles, media graphs, chart bindings, reactive dependencies and other bounded runtime relationships.
