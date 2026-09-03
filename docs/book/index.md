# JX Programming Book

## Latest revision — 2026.09.03.4

The current language-book revision is:

**[JX Programming Tutelage — 2026.09.03.4](../JX-PROGRAMMING-TUTELAGE-2026-09-03.4.md)**

This revision keeps the active collection-loop family:

```text
foreach   forward collection traversal
reveach   reverse collection traversal
forif     forward traversal with inline condition
revif     reverse traversal with inline condition
```

and the tuple-return form:

```jx
_, no1, no2, no3 = forif ($value in $values if no1 < _)
```

The callback/iterator row is exploded before the predicate:

```text
_   = row[0]
no1 = row[1]
no2 = row[2]
no3 = row[3]
```

`_` remains position zero. `revif` reverses outer traversal, never the returned row positions.

## Canonical file model

```text
.jx   Jinx source
.jxl  native Jinx executable image
.jll  Jinx Loadable Library
.jxb  indexed compressed resource archive
```

JXL and JLL use one shared `JXNI` native-image ABI and one native encoder path. The presence of an entrypoint distinguishes the ordinary executable from the ordinary library image.

JLL public function names, parameter lists, return types, and CODE offsets ship inside binary `EXPORTS`, `SIGNATURES`, and `STRINGS` sections so a native loader can map and call the library without reading the original source.

JXB uses ZIP-compatible indexed members and per-member Deflate compression. It is designed to pull or stream only the requested asset rather than decompressing the whole package.

Full binary/file specification:

**[JX File Formats](../JX-FILE-FORMATS.md)**

## Compiler pipeline

```text
JX source
 -> PHP normalization / canonical JX IR
 -> PASM directly where appropriate
 -> optional PASL-assisted lowering
 -> direct native encoder
 -> .jxl or .jll

resources
 -> indexed per-member compression
 -> .jxb
```

The proven six-byte prepared execution machinery remains as an internal/compatibility and benchmark representation. It no longer defines the public `.jxl` extension.

The longer historical manuscript remains available at:

**[JX Programming Tutelage](../JX-PROGRAMMING-TUTELAGE.md)**

Revision 2026.09.03.4 supersedes older manuscript statements wherever file-format or compiler-pipeline meanings disagree.