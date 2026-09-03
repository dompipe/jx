# JX Programming Book

## Latest revision — 2026.09.03.2

The current language-book revision is:

**[JX Programming Tutelage — 2026.09.03.2](../JX-PROGRAMMING-TUTELAGE-2026-09-03.2.md)**

This revision promotes the collection-loop family into the active language documentation:

```text
foreach   forward collection traversal
reveach   reverse collection traversal
forif     forward traversal with inline condition
revif     reverse traversal with inline condition
```

For `forif` and `revif`, the standalone `_` operator is the filtered frame's **value zero/current value**. It is valid in the predicate and body, and when written first in a callback it becomes callback argument zero.

Example:

```jx
forif ($value in $values if _ > 10) {
    callback(_, $value);
}
```

The current value is passed first.

The longer canonical manuscript remains available at:

**[JX Programming Tutelage](../JX-PROGRAMMING-TUTELAGE.md)**

The 2026.09.03.2 revision supersedes that manuscript's older `foreach` status text wherever the two disagree.
