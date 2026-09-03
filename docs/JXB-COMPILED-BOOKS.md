# Legacy compiled-Book JXB compatibility

> **Historical document.** New `.jxb` files are indexed compressed resource archives. Native executables are `.jxl`; native loadable libraries are `.jll`.

An earlier JX phase used `.jxb` as the public filename for a deterministic compiled Book built on the older `.64B` package ABI. That implementation remains valuable compatibility code, but it no longer defines the canonical extension meanings.

## Historical package identity

Legacy compiled Books may contain:

```text
magic:           JX64B001
manifest format: jx.64B/1
header entry:    JX64/header.bin
manifest entry:  JX64/manifest.json
```

Old readers may continue recognizing those bytes regardless of whether a historical artifact was named `.64B`, `.jxb`, or something else. Byte identity remains more trustworthy than a suffix.

## Historical execution layout

The former pipeline was approximately:

```text
.jx source
 -> prepared six-byte stream
 -> JX64B001 compiled Book
 -> host admission/runtime
```

Typical sections included:

```text
CODE/program.jxl
META/prepared.json
META/semantic.json
```

In that historical context `program.jxl` meant the old prepared stream. New `.jxl` output means a **native executable image** and must not be confused with that section.

## Canonical replacement

Current public contracts are:

| Form | Meaning |
|---|---|
| `.jx` | canonical Jinx source |
| `.jxl` | native executable image with entrypoint |
| `.jll` | native Jinx Loadable Library, same image format and normally no entrypoint |
| `.jxb` | indexed ZIP-compatible Deflate resource archive |
| `.pbc` | PASM bytecode/prepared compatibility representation |
| `.64B` | historical compiled-Book filename/ABI family |

The current native code pipeline is:

```text
.jx
 -> JX IR
 -> PASM when appropriate
 -> direct native encoder
 -> JxNativeImage
      entrypoint    -> .jxl
      no entrypoint -> .jll
```

Resource packaging is independent:

```text
images / models / fonts / tables / dictionaries / other assets
 -> per-member indexed compression
 -> .jxb
```

## Compatibility commands

Older tools such as `jxb-compile.php`, `jxb-run.php`, `jx64-compile.php`, `jx-jxb.php`, and `NativeBook64` may continue to operate as explicit compatibility paths until migrated or retired. They should be documented and surfaced as **legacy compiled-Book tooling**, not as the canonical JXB resource writer.

New resource work should use `jx-jxb-archive.php`.

New executable/library work should use `jxl-native-compile.php`, `jll-native-compile.php`, and `jx-native-image.php`.

## Rule for future edits

Do not delete proven legacy package readers solely because the extension contract changed. Keep them isolated behind compatibility names while preventing them from redefining the public meanings of `.jxl`, `.jll`, and `.jxb`.