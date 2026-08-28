# JXB compiled Books

`.jxb` is the canonical public filename extension for a compiled JX Book.

```text
canonical source        prepared execution        distributable Book
    app.jx        ->          JXL            ->        app.jxb
```

The filename is a tooling/user contract, not the trust boundary. A JX loader admits a Book from its validated package identity, hashes, manifest, prepared metadata, and target. Renaming a valid `app.jxb` does not change its identity, and naming arbitrary bytes `app.jxb` does not make them executable.

## Version-1 compatibility

The first `.jxb` generation deliberately keeps the existing internal compiled-Book ABI:

```text
magic:           JX64B001
manifest format: jx.64B/1
header entry:    JX64/header.bin
manifest entry:  JX64/manifest.json
```

These are versioned internal identifiers. They are **not** the public filename extension. Keeping them stable means the `.64B` -> `.jxb` naming correction does not invalidate existing deterministic package bytes, hashes, probes, or host admission code.

`.64B` should therefore be treated as a legacy filename convention. New documentation, generated files, examples, installers, and user-facing tools should use `.jxb`.

## Canonical commands

Compile and choose the output automatically:

```bash
php jxb-compile.php app.jx
# writes app.jxb
```

Compile to an explicit path:

```bash
php jxb-compile.php app.jx build/app.jxb
```

Execute a prepared JXL Book through the current host:

```bash
php jxb-run.php build/app.jxb
```

`jx64-compile.php` remains a compatibility CLI, but it now follows the `.jxb` public convention.

## Admission before execution

The public `JxbBook` path performs these stages:

```text
read bytes
  -> verify JX64B001 package/header
  -> verify manifest and section hashes
  -> require executable target = jxl for the current JXL host
  -> decode META/prepared.json
  -> require jx.prepared-metadata/1
  -> verify the ABI prepared type-ID table
  -> admit CODE/program.jxl
  -> execute JXL
```

The prepared type table is consumed at admission rather than rediscovered while executing. A mismatched representation ID is an admission failure.

## Source is not the executable payload

A normal compiled `.jxb` carries prepared executable content and metadata, not the original `.jx` source. The current JXL Book contains, among other validated sections:

```text
CODE/program.jxl
META/prepared.json
META/semantic.json
```

Canonical source remains the permanent human meaning; the compiled Book is a deterministic execution/distribution artifact.

## Naming rule

Use these names consistently:

| Form | Meaning |
|---|---|
| `.jx` | canonical human-readable JX source |
| JXL | prepared executable representation inside or alongside a compiled Book |
| `.jxb` | compiled/distributable JX Book |
| `JX64B001` / `jx.64B/1` | internal v1 package ABI identifiers |
| `.64B` | legacy public filename convention only |

Future internal Book ABI versions may change without requiring another public extension change.
