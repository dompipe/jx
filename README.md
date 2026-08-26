# jx (jinx)

**Formerly pasm-v2.** Product name **jx**; PASM is the engine.

## Quick start

```bash
# Install required plugins (one-by-one, with pre + full backups)
php jx-install.php install-required

# Run
php jx-run.php --print examples/hello.jx
php examples/jx-smoke.php
```

## Layout

| Path | Role |
|------|------|
| `jx.php` | Core runtime (Bag, Task, Page, Book, Delivery, Complex, SmartTable, Sym) |
| `jx-lang.php` / `jx-run.php` | Language engine + executable compiler |
| `plugins/` | **Single source directory** for all module plugins |
| `host/modules/` | Active installed plugins |
| `host/backups/pre/` | Snapshot before each new plugin install |
| `host/backups/full/` | Full install snapshot (restore / redirect) |
| `jx/INTRO.md` | Introduction materials |
| `jx/INSTALL.md` | Install & plugin policy |
| `jx/COMPILER.md` | Compiler pipeline |
| `docs/history/` | Original-to-latest Markdown and blame conveyance |
| `history/jx-lang/` | History-preserving snapshot of the earlier `jx-lang` tree |

## Plugins

- Sourced **only** from `plugins/`
- Installed **one at a time**; new installs append **last** after need is assessed
- Dual backup: **pre** (per install) + **full** (total install)

```bash
php jx-install.php list
php jx-install.php install intro
php jx-install.php backup-full
php jx-install.php restore-full <timestamp>
```

## Includes

- Decimals (`plugins/decimals` → `jx\Decimal`)
- Complex, Delivery, const, smart compiler, lang bridge
- Memory law, Books/Bags/Pages, Resistant path

See `jx/INTRO.md` for the guided introduction.

## Lineage

This repository converges `dompipe/pasm-v2` and `dompipe/jx-lang` without
flattening either Git history. The current implementation and canonical docs
come from the later `pasm-v2` integration. The earlier standalone language
design remains under `history/jx-lang/`, where `git log --follow` and
`git blame --follow` can continue through its original commits.

See [docs/history/README.md](docs/history/README.md) for source revisions,
navigation, and provenance commands.

---

jx — pronounced jinx.
