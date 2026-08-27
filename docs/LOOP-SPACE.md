# Compiled Loop Space

JX/PASL treats variable mutation and loop control as compiler-lowered primitives rather than repeated high-level interpretation.

## Variable mutation

Canonical variable operations are `VSET`, `VINC`, `VDEC`, `VADD`, `VSUB`, `VMUL`, `VDIV`, `VMOD`, `VAND`, `VOR`, `VXOR`, `VSHL`, `VSHR`, and `VALG`.

`VALG` represents one mutation whose right-hand side is an ordered algebra tree. The tree is compiled/fused before execution. Increment, decrement, and simple compound operations may lower to one native instruction.

## Active loop body extraction

`pasm-lang.php` loads `pasm-lang-compiler-loop.php` as the active PASL compiler. `for` and `while` bodies compile once into out-of-line blocks. `do ... while`, `repeat(n)`, `foreach`, and `reveach` enter the same bounded execution model through surface/compiler passes rather than separate interpreters.

Conceptually:

```text
loop slot N
  condition / iterator state
  body target
  optional step target

LCHECK or ITER
LCALL body
[LCALL step]
LREPEAT slot
```

On the current PASM ISA, canonical `LCALL` lowers to a direct branch because each block has a fixed continuation. Native targets may use a machine call, tail branch, or inline body when profitable.

## For and while

A `for` loop compiles init once, checks its condition, branches into one compiled body, executes its fused step, and returns to the check. `continue` targets the fused step; `break` targets the loop exit.

A `while` loop checks, branches into one compiled body, and returns to the check. `continue` targets the check and `break` targets the exit.

## Collection loops: foreach and reveach

The canonical source vocabulary is:

```jx
foreach ($players as $player) {
    $total += $player;
}

reveach ($players as $player) {
    $number = $number * 10 + $player;
}
```

Key/value traversal is also supported:

```jx
foreach ($scores as $key => $score) {
    $total += $key + $score;
}
```

`reveach` is the single reverse-iteration keyword. There is no competing `rforeach` or `foreach reverse` syntax.

The engine binds a source-visible collection once:

```php
$engine = new pasm\lang\Engine(true, false);
$engine->bindCollection('players', [10, 20, 30]);
$result = $engine->runSource($source);
```

Compilation prelinks the iterator slot and the actual 3-bit value/key destination registers. The repeated PASM operation therefore carries no collection identity and no destination register:

```text
foreach -> ITERF <slot>
reveach -> ITERR <slot>
```

Both are exactly two bytes:

```text
19 nn   ITERF nn
1A nn   ITERR nn
```

The only repeated integer is the unsigned one-byte slot.

Loop entry emits:

```text
21 nn   IRESET nn
```

`IRESET` is also two bytes, but it executes once when entering the collection loop, not on every item. It guarantees correct re-entry after `break` and correct nested-loop behavior without widening `ITERF` or `ITERR`.

The resulting controller is:

```text
IRESET slot
check:
    ITERF slot       # or ITERR
    JZ exit
    JMP body
body:
    compiled body
    JMP check
exit:
```

The iterator descriptor holds collection snapshot, cursor, value register, and optional key register. `ITERF/ITERR` write yielded scalar values directly into those prelinked registers and set the VM zero flag when traversal is exhausted.

## Loop kinds

The active bounded model now covers `for`, `while`, `do ... while`, `repeat`, `foreach`, and `reveach`.

Default nesting depth is 8. Nested loop bodies compile while their parents still own their slots, so exceeding the cap is a compile-time error. Sequential loops reuse bounded loop space rather than recursively allocating arbitrary controller objects.

## Canonical Shadow Machine

Canonical loop source remains permanent and readable. Extracted bodies, iterator bindings, controller labels, and fused blocks are disposable execution shadows. A richer source form never requires a second runtime loop interpreter.

## Regression

Run:

```bash
php test-pasm-loop-space.php
php test-pasm-loop-compiler.php
php test-pasm-surface-loops.php
php test-pasm-iterator-abi.php
php test-pasm-iterator-bytecode.php
php test-pasm-foreach-surface.php
php test-pasm-foreach-runtime.php
php examples/foreach-reveach.php
```

The collection runtime regression verifies forward traversal, reverse traversal, key/value binding, exact two-byte iterator/reset commands, nested collection-loop reset, early `break` followed by re-entry, and both optimized and unoptimized engines.
