# JX Programming Book

## Latest revision — 2026.09.03.3

The current language-book revision is:

**[JX Programming Tutelage — 2026.09.03.3](../JX-PROGRAMMING-TUTELAGE-2026-09-03.3.md)**

This revision keeps the active collection-loop family:

```text
foreach   forward collection traversal
reveach   reverse collection traversal
forif     forward traversal with inline condition
revif     reverse traversal with inline condition
```

and clarifies the new tuple-return form:

```jx
_, no1, no2, no3 = forif ($value in $values if no1 < _)
```

When the current callback/iterator result is an array-like row, the PHP JX front end explodes it positionally **before the predicate**:

```text
_   = row[0]
no1 = row[1]
no2 = row[2]
no3 = row[3]
```

`_` is always position zero / the first implicit current value. `revif` reverses traversal of the outer collection but never reverses the positions inside the returned row.

### Compiler pipeline

```text
JX source
 -> PHP normalization / rewriting
 -> canonical FORIF_ROW plan
 -> JXL redesign / preparation
 -> ASM/native encoding
```

The rich destructuring rules belong to PHP (`jx-forif-lowering.php`), not the PASM iterator ABI. PASM remains concerned with compact scalar iteration while JXL can later encode the normalized row plan in the best native form.

The longer canonical manuscript remains available at:

**[JX Programming Tutelage](../JX-PROGRAMMING-TUTELAGE.md)**

Revision 2026.09.03.3 supersedes the older collection-loop notes wherever they disagree.
