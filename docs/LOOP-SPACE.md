# Compiled Loop Space

JX/PASL treats variable mutation and loop control as compiler-lowered primitives rather than repeated high-level interpretation.

## Variable mutation

Canonical variable operations are:

- `VSET`
- `VINC`
- `VDEC`
- `VADD`
- `VSUB`
- `VMUL`
- `VDIV`
- `VMOD`
- `VAND`
- `VOR`
- `VXOR`
- `VSHL`
- `VSHR`
- `VALG`

`VALG` represents one mutation whose right-hand side is an ordered algebra tree. The tree is compiled/fused before execution. Increment, decrement, and simple compound operations may lower to one native instruction.

## Loop body extraction

Loop bodies are compiled once into callable blocks. The controller does not reinterpret the body on each iteration.

Conceptually:

```text
loop slot N
  condition state
  body target
  optional step target
  iteration state

LCHECK condition
LCALL  body
LCALL  step      # for loops where needed
LREPEAT slot
```

The desired optimized native form for the common case is a guard/check followed by a direct call or inlined block target. The final backend may replace `LCALL` with a direct branch or inline the block entirely when profitable.

## Loop kinds

The loop-space model applies to:

- `for`
- `while`
- `do-while`
- `foreach`
- `repeat`

These are surface/canonical differences. Once their initialization, condition, step, and iteration-source semantics are known, their executable shadows share the same bounded loop-space machinery.

## Bounded nesting

Default maximum nesting depth is 8.

Nested loops receive slots in order:

```text
outer        slot 0
  inner      slot 1
    inner    slot 2
      ...
```

Exceeding the configured maximum is a compile-time error. When a loop scope exits its slot is reusable, so a program may contain many sequential loops without growing runtime loop state.

This is the meaning of invoking a series of loops "in space": runtime loop state occupies a small bounded slot array rather than recursively allocating arbitrary loop-controller objects.

## Canonical Shadow Machine

Canonical loop source remains permanent and readable. Extracted loop bodies and controllers are disposable execution shadows. Each block must retain provenance back to the canonical loop and body nodes.

The current `pasm-loop-space.php` module defines the canonical mutation parser, loop descriptors, nesting allocator, controller form, and tests. The existing PASL compiler still has its legacy inline loop emitter while this pass is connected to final code emission.
