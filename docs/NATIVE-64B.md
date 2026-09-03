# Legacy `.64B` Book Name

`.64B` is no longer the canonical public filename for a compiled JX Book.

Use:

- **`.jxl`** for a prepared executable instruction stream.
- **`.jxb`** for a compiled JX Binary Book/container.

The current native Book specification is in [`NATIVE-JXB.md`](NATIVE-JXB.md).

This legacy document path is retained temporarily so existing links do not break.

The internal v1 package identifiers remain deliberately unchanged for binary compatibility:

```text
JX64B001
jx.64B/1
JX64/header.bin
JX64/manifest.json
```

Existing files whose names end in `.64B` remain readable by package identity. New tooling should not emit new `.64B` filenames.