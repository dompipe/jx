# Legacy `.64B` Book Name

`.64B` belongs to the historical compiled-Book/package generation.

The old internal identifiers may remain readable for compatibility:

```text
JX64B001
jx.64B/1
JX64/header.bin
JX64/manifest.json
```

They no longer determine the meanings of the current public extensions.

Use the current contracts:

- **`.jx`** — Jinx source.
- **`.jxl`** — native Jinx executable image with an entrypoint.
- **`.jll`** — Jinx Loadable Library using the same native-image format, normally without an entrypoint.
- **`.jxb`** — indexed ZIP-compatible Deflate resource archive.
- **`.pbc`** — PASM/prepared compatibility representation.

Existing `.64B` or old compiled-Book artifacts may remain readable through explicit compatibility code. New tooling should not emit new `.64B` filenames, and new `.jxb` output must not reuse the compiled-Book meaning.

See [`NATIVE-JXB.md`](NATIVE-JXB.md) for the current JXB resource contract and `../jx/COMPILER.md` for the native JXL/JLL compiler contract.