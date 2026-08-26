# JX lineage

This repository joins two source histories into one JX history:

- `dompipe/pasm-v2` at `6662e9e0c8e4133c49a2d6d653c8ae973612bc2b`
- `dompipe/jx-lang` at `6dc123045ad553282ff4b66102e45d01db43b6aa`

The later `pasm-v2` integration is the canonical runtime and documentation
tree. The earlier standalone `jx-lang` tree is retained at
`history/jx-lang/`; its original commits are parents of the convergence merge,
so its lineage remains queryable.

## Documents

- [MARKDOWN_CONVEYANCE.md](MARKDOWN_CONVEYANCE.md) records every reachable
  Markdown path from both source repositories, its first and latest content,
  commit timeline, and latest line-level blame.
- [`../../jx/`](../../jx/) contains the canonical current language docs.
- [`../../history/jx-lang/`](../../history/jx-lang/) contains the standalone
  design tree immediately before convergence.

## Follow the history

```bash
git log --all --graph --decorate --oneline
git log --follow -- history/jx-lang/SPEC.md
git blame --follow history/jx-lang/SPEC.md
git log --follow -- jx/SPEC.md
git blame jx/SPEC.md
```

The history copy is evidence, not a second canonical specification. New work
belongs in the runtime tree and `jx/` documentation.
