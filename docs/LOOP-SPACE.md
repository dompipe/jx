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

## Active loop body extraction

`pasm-lang.php` now loads `pasm-lang-compiler-loop.php` as the active PASL compiler. `for` and `while` bodies are compiled once into out-of-line blocks instead of being emitted inline in the controller.

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

The current PASM ISA does not need a new runtime `CALL` instruction for this shape. Because each loop block has a fixed continuation, canonical `LCALL` lowers to a direct branch to the out-of-line block, and that block branches to its known continuation. Native ELF/EXE targets may later emit a machine `call` or inline the block when profitable.

The main program jumps over all deferred loop blocks before its final `RET`, so blocks cannot execute by fall-through.

## For loop lowering

A `for` loop is lowered into:

```text
compile init once
check_label:
  LCHECK condition
  branch body_block or exit

body_block:
  compiled body
  branch step_block (or check_label)

step_block:
  compiled step mutation
  branch check_label
```

`continue` targets the compiled step block. `break` targets the controller exit label.

## While loop lowering

A `while` loop becomes:

```text
check_label:
  LCHECK condition
  branch body_block or exit

body_block:
  compiled body
  branch check_label
```

`continue` targets the condition check and `break` targets the exit label.

## Loop kinds

The bounded loop-space semantic model covers:

- `for`
- `while`
- `do-while`
- `foreach`
- `repeat`

The active compiler currently connects `for` and `while` to out-of-line block emission. `do-while`, `foreach`, and `repeat` share the same loop-space descriptors but still need their surface-specific collection/entry semantics connected before they are accepted by the PASL front end.

## Bounded nesting

Default maximum nesting depth is 8.

Nested loops receive slots in order:

```text
outer        slot 0
  inner      slot 1
    inner    slot 2
      ...
```

Exceeding the configured maximum is a compile-time error. Nested bodies are compiled while their parent still owns its slot, so this cap is enforced by the active compiler rather than being descriptive metadata only.

When a loop scope exits its slot is reusable, so a program may contain many sequential loops without growing runtime loop-controller state.

This is the meaning of invoking a series of loops "in space": runtime loop state occupies a small bounded slot array rather than recursively allocating arbitrary controller objects.

## Canonical Shadow Machine

Canonical loop source remains permanent and readable. Extracted loop bodies and controllers are disposable execution shadows. Each block retains a stable canonical relationship through its loop-space descriptor and generated block symbol.

The earlier `pasm-lang-compiler.php` remains in the repository for lineage/reference. It is not the compiler loaded by `pasm-lang.php` on this branch.

## Regression

Run:

```bash
php test-pasm-loop-space.php
php test-pasm-loop-compiler.php
```

The second test checks out-of-line body/step labels, canonical variable-op annotations, nested slot allocation, and hard nesting-limit failure.
