# JX Programming Tutelage — 2026.09.03.3

## PHP-first `forif` / `revif` tuple lowering

This revision clarifies where rich collection-loop syntax belongs in the JX stack.

The source form remains readable:

```jx
_, no1, no2, no3 = forif ($value in $values if no1 < _)
```

If the current callback/iterator value is an array-like row:

```text
[a, b, c, d]
```

JX binds it positionally before evaluating the predicate:

```text
_   = a      // position 0, canonical current value
no1 = b      // position 1
no2 = c      // position 2
no3 = d      // position 3
```

Therefore:

```jx
_, no1, no2, no3 = forif ($value in $values if no1 < _)
```

means:

```text
read current row
explode positions
bind _ first
bind no1/no2/no3
check no1 < _
execute accepted body
advance forward
```

`revif` performs the same positional binding but traverses the outer collection in reverse. It does **not** reverse the returned row.

## Compiler ownership

Tuple/destructuring semantics belong to the PHP JX front end, not PASM.

The pipeline is:

```text
canonical JX source
    |
    v
PHP front end / lowering
    |
    | parse forif/revif
    | preserve _ as position zero
    | assign positional targets
    | normalize predicate/body
    v
canonical filtered-row plan
    |
    v
JXL preparation / redesign
    |
    | choose compact encoded form
    | allocate registers / row slots
    | prepare target-native execution
    v
ASM / native encoding
```

The PHP implementation lives in:

```text
jx-forif-lowering.php
```

It produces a cold canonical `FORIF_ROW` plan containing:

```text
direction
collection
targets
positions
current = _
source value name
optional key
predicate
```

Example normalized plan:

```text
op         = FORIF_ROW
direction  = forif
collection = values
targets    = [_, no1, no2, no3]
positions  = {_ : 0, no1 : 1, no2 : 2, no3 : 3}
predicate  = no1 < _
```

## PASM boundary

PASM iterator bytecode remains intentionally simple:

```text
ITERF slot
ITERR slot
IRESET slot
```

The iterator ABI does not learn PHP array/destructuring semantics. Those semantics must already have been normalized before PASM/native encoding.

This preserves the architectural rule:

> Rich source belongs in the compiler. Compact repetition belongs in JXL/native execution.

## Callback rule

`_` is still the first implicit callback/current value.

For example:

```jx
callback(_, no1, no2)
```

means callback argument order:

```text
arg0 = current row position 0
arg1 = row position 1
arg2 = row position 2
```

This remains true in both `forif` and `revif`.

## JXL redesign direction

The next encoding step should consume the normalized PHP plan rather than reparsing source.

A future JXL row operation can therefore be designed around already-known facts such as:

```text
row width
position -> destination register mapping
forward/reverse direction
predicate block
body block
```

That allows JXL to optimize for ASM/native encoding without carrying source-language parsing into the execution layer.

The rule is:

> PHP steals the readable lines and rewrites them into the best canonical form it can; JXL then redesigns that normalized meaning for compact ASM encoding.
