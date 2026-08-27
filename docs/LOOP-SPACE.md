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

`pasm-lang.php` loads `pasm-lang-compiler-loop.php` as the active PASL compiler. `for` and `while` bodies are compiled once into out-of-line blocks instead of being emitted inline in the controller. `do ... while` and `repeat(n)` are surface forms lowered into that same bounded machinery before register allocation.

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

The current PASM ISA does not need a new runtime `CALL` instruction for this shape. Because each loop block has a fixed continuation, canonical `LCALL` lowers to a direct branch to the out-of-line block, and that block branches to its known continuation. Native ELF/EXE targets may emit a machine `call` or inline the block when profitable.

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
  branch fused_step/check

fused_step:
  compiled step mutation
  branch check_label
```

`continue` targets the fused step. `break` targets the controller exit label.

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

## Collection loops: foreach and reveach

The canonical source vocabulary is intentionally small:

```jx
foreach ($players as $player) {
    // forward traversal
}

reveach ($players as $player) {
    // reverse traversal
}
```

`reveach` is the one reverse-iteration keyword. JX does not use `rforeach` or `foreach reverse` as competing spellings.

Their PASM execution targets are:

```text
foreach -> ITERF <slot>
reveach -> ITERR <slot>
```

Both iterator commands are exactly two bytes in active PASM bytecode: one opcode byte plus one unsigned iterator-slot byte. The collection descriptor, cursor state, destination register and optional key destination are prelinked outside the repeated hot path, so each repeated iterator call carries one integer only.

The active iterator ABI and Bag-container binding layer are already runnable. High-level collection-source lowering into those prelinked descriptors is the remaining front-end connection.

## Loop kinds

The bounded loop-space semantic model covers:

- `for`
- `while`
- `do ... while`
- `repeat`
- `foreach`
- `reveach`

`for`, `while`, `do ... while`, and `repeat(n)` are active PASL surface forms. `foreach` and `reveach` are the canonical collection-loop vocabulary and map to the active `ITERF` / `ITERR` execution ABI while their final collection-source compiler bridge is completed.

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
php test-pasm-surface-loops.php
php test-pasm-iterator-abi.php
php test-pasm-foreach-surface.php
```

The regression set checks out-of-line body/step labels, canonical variable-op annotations, nested slot allocation, hard nesting-limit failure, runnable `do ... while` / `repeat`, exact two-byte iterator encoding, and the canonical `foreach` / `reveach` direction mapping.
