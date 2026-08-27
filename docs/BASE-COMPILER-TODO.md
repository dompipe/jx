# JX Base Compiler TODO

This file tracks compiler-level work that should apply across hosts, not just JX11.

## Core law: Bags remember; registers react

The canonical JX model must distinguish durable/observable semantic state from awake-state execution acceleration.

- **Bags remember.** Bags remain the canonical, checkpointable, inspectable representation of program state.
- **Registers react.** Registers are live native memory/cache structures used only while a compiled program is awake.
- Register layout is never semantic. A program must be reconstructible from canonical state without preserving the prior process memory layout.
- On startup/resume, canonical state is resolved/prelinked into registers.
- During execution, hot paths use numeric register/slot/shadow identities instead of strings, object lookup, or repeated graph traversal.
- At required boundaries/checkpoints, dirty register state is synchronized back into canonical Bags.
- On process exit/crash/restart, registers may disappear; canonical Bag state remains authoritative.

### WindowBag register pattern

JX11 establishes the first concrete form of this compiler rule, but the mechanism belongs to the base compiler.

A WindowBag register maps one canonical Bag identity once during startup, for example:

```text
W0 = desktop-windows
```

A live window reaction is represented canonically as:

```text
[slot:shadow]
[17:3]
```

The hot representation packs the pair into one 16-bit value:

```text
high byte = slot
low byte  = shadow

[17:3] -> 0x1103
```

This provides 256 slots and 256 shadows per register with a two-byte hot identity. The compiler/runtime may retain `[17:3]` for diagnostics and programmer-facing output while generated/native code uses the packed value.

### Required base-compiler work

- [ ] Generalize the register-cache concept beyond JX11 into canonical compiler IR.
- [ ] Add register classes/namespaces so host-specific caches such as `W0` do not collide with arithmetic/value registers.
- [ ] Add compile-time assignment of Bag -> register identity.
- [ ] Add compile-time assignment of object/member/window -> slot identity.
- [ ] Add compile-time assignment of reactive handler -> shadow identity.
- [ ] Preserve canonical provenance for every register, slot and shadow for diagnostics/debugging.
- [ ] Emit packed `[slot:shadow]` references where a target supports them.
- [ ] Define shadow `0` as canonical state unless a stronger cross-domain rule is adopted.
- [ ] Reserve additional shadows for prelinked reactions such as focus, title, geometry, taskbar/control refresh, media, chart and application handlers.
- [ ] Compile dirty-source -> affected-shadow dispatch lists so runtime does not discover dependencies dynamically.
- [ ] Union duplicate shadow invalidations within one event/batch and execute each affected shadow once.
- [ ] Define checkpoint instructions that synchronize only dirty register state back into the owning Bag.
- [ ] Define wake/rebuild instructions that restore register caches from canonical Bag state.
- [ ] Define overflow/refusal behavior; a register cache must never silently spill into another object's bounded memory.
- [ ] Ensure interpreter, PASM, native ELF, native PE/EXE and future hosts preserve identical canonical semantics even when their register layouts differ.
- [ ] Add compiler reports showing canonical object -> register -> slot -> shadow mappings.
- [ ] Add benchmarks comparing canonical/runtime lookup with prelinked register dispatch.

### Compiler pipeline target

```text
canonical JX
    -> canonical/semantic IR
    -> dependency analysis
    -> register allocation + slot allocation + shadow allocation
    -> prelinked execution shadow
    -> native awake-state register cache
    -> dirty/checkpoint synchronization
    -> canonical Bag output/state
```

### Non-negotiable invariant

> Canonical richness must not require runtime richness.

Anything knowable at compile/startup time should be removed from the interactive hot path when observable behavior can remain identical.

The WindowBag register is the first proof case, not a special exception. The same model should be evaluated for Controls, OOP objects, Bag containers, Delivery paths, SQL/NoSQL handles, media graphs, chart bindings, reactive dependencies and other bounded runtime relationships.
