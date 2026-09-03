# JX Programming Tutelage — 2026.09.03.2

## Collection-loop revision: `foreach`, `reveach`, `forif`, `revif`, and `_`

This revision updates the JX programming book to match the active collection-loop compiler path on `main`.

It supersedes the older statement that `foreach` was recognized but not active.

---

# 1. Active collection traversal

JX now has four related collection-loop forms:

```text
foreach   forward traversal
reveach   reverse traversal
forif     forward traversal + inline condition
revif     reverse traversal + inline condition
```

All four use the same prepared iterator machinery. JX does not create a second generic iterator engine for filtered loops.

The hot iterator operations remain:

```text
foreach / forif -> ITERF <slot>
reveach / revif -> ITERR <slot>
```

The iterator slot is prelinked before repetition. Collection identity, destination registers, and optional key destination are not rediscovered on every element.

The rule remains:

> **Resolve cold -> bind once -> execute hot.**

---

# 2. `foreach`

## Forward traversal

```jx
$total = 0;
foreach ($values as $value) {
    $total += $value;
}
```

Key/value form:

```jx
foreach ($weights as $key => $value) {
    $total += $key + $value;
}
```

A bound flat/list array is admitted once as a Vector-style traversal with numeric keys `0..n-1`.

A keyed array or keyed iterable is admitted once as Map-style traversal and keeps its explicit keys.

---

# 3. `reveach`

`reveach` is the reverse counterpart to `foreach`.

```jx
$number = 0;
reveach ($digits as $digit) {
    $number = $number * 10 + $digit;
}
```

For:

```text
[1, 2, 3, 4]
```

iteration order is:

```text
4, 3, 2, 1
```

The collection representation is not copied merely to reverse it. The same iterator descriptor walks in the opposite direction.

---

# 4. `forif`

`forif` is forward collection traversal with an inline predicate.

Python-like spelling:

```jx
forif ($value in $values if _ > 10) {
    $sum += _;
}
```

JX collection spelling:

```jx
forif ($values as $value if _ > 10) {
    $sum += _;
}
```

Key/value form:

```jx
forif ($weights as $key => $value if _ >= 20) {
    $sum += $key + _;
}
```

The predicate does not terminate the loop when false. A rejected value skips the body and advances to the next element.

Conceptually:

```text
ITERF
  |
  v
current value
  |
  v
predicate
 /     \
false  true
 |       |
next    body
  \     /
   ITERF
```

---

# 5. `revif`

`revif` applies the same inline filtering rule while traversing in reverse.

```jx
$result = 0;
revif ($digit in $digits if _ >= 3) {
    $result = $result * 10 + _;
}
```

Given:

```text
[1, 2, 3, 4, 5]
```

this visits the accepted values as:

```text
5, 4, 3
```

`revif` changes traversal direction only. It does not change the meaning of `_`.

---

# 6. `_` is value zero

Inside `forif` and `revif`, `_` is the first implicit value of the filtered-loop frame.

Formally:

```text
filtered-frame value[0] = current collection value
_                       = value[0]
```

This is stronger than treating `_` as a cosmetic alias in the predicate. It is available throughout the filtered loop body as the current-item operator.

Examples:

```jx
forif ($value in $values if _ != 0) {
    $total += _;
}
```

```jx
forif ($weights as $key => $value if _ >= 20) {
    $result += _ + $key;
}
```

The explicit variable still exists. `_` is the compact current-value operand.

---

# 7. Callback rule

Because `_` is value zero, it naturally occupies callback argument position zero when written first.

```jx
forif ($value in $values if _ > 0) {
    callback(_, $value);
}
```

Canonical lowering treats this as:

```text
callback(current_value, value)
         ^
         argument 0
```

Likewise:

```jx
forif ($weights as $key => $value if _ > 0) {
    callback(_, $key);
}
```

means:

```text
arg0 = current value
arg1 = current key
```

This gives callback-oriented code a stable leading value without requiring the programmer to rename or rebind the current item.

`revif` follows the identical callback rule. Only traversal order changes.

---

# 8. `_` is scoped

The bare `_` current-value operator belongs to a `forif` or `revif` filtered-iteration frame.

It is replaced during lowering before ordinary expression compilation.

A standalone underscore occurring inside a quoted string is not rewritten:

```jx
$message = "_";
```

Nor should underscore characters embedded in ordinary identifiers be treated as the operator:

```jx
$_scratch
$value_name
```

The operator is the standalone bare token:

```text
_
```

---

# 9. Vector and Map admission

Collection shape is decided once when the collection is bound to the execution engine.

## Flat/list collection

```text
[10, 20, 30]
```

becomes:

```text
kind   = vector
keys   = [0, 1, 2]
values = [10, 20, 30]
```

## Keyed collection

```text
[2 => 10, 5 => 20, 9 => 30]
```

becomes:

```text
kind   = map
keys   = [2, 5, 9]
values = [10, 20, 30]
```

Iteration therefore does not repeatedly ask whether the collection is list-like or keyed.

---

# 10. Prepared execution law

The four source forms are intentionally a surface family over two hot traversal operations.

```text
source           direction   predicate   hot iterator
-------------------------------------------------------
foreach          forward     no          ITERF
reveach          reverse     no          ITERR
forif            forward     yes         ITERF
revif            reverse     yes         ITERR
```

The filter is compiled into the body-entry path. It is not encoded by creating a new collection or by performing a second traversal.

This preserves JX's design principle:

> **Readable source may have several expressive forms; repeated execution should collapse to the smallest canonical machinery that preserves meaning.**

---

# 11. Regression coverage

The repository regression path now covers:

- forward `forif` filtering;
- Python-like `value in collection if condition` spelling;
- reverse `revif` filtering;
- keyed Map filtering;
- `_` in the predicate;
- `_` in the loop body;
- `_` lowering as callback argument zero/current value;
- Vector versus Map collection admission.

Relevant implementation files:

```text
pasm-foreach-surface.php
pasm-foreach-pass.php
pasm-lang-engine.php
test-pasm-forif-revif.php
test-pasm-foreach-canonical-collections.php
```

---

# 12. Current language promise

The collection-loop family can now be taught as active JX/PASL syntax:

```jx
foreach ($items as $item) {
    use($item);
}

reveach ($items as $item) {
    use($item);
}

forif ($item in $items if _ > 10) {
    use(_);
}

revif ($item in $items if _ > 10) {
    use(_);
}
```

For filtered loops, remember one rule first:

> **`_` is the current value, and it is value zero.**

That rule is intentionally useful for conditions, compact arithmetic, and callback pipelines.
