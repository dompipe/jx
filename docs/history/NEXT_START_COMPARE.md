# Next Start Compare: jx-lang + pasm-v2 -> jx

Generated on 2026-08-25 after converging `dompipe/jx-lang` and `dompipe/pasm-v2` into `dompipe/jx`.

## Current Heads

- Current repo: `dompipe/jx`
- Current branch: `fix/plugin-runtime-root`
- Current commit: `51ba67073319e4fd3e02eea385e4a79a587d30f5` (`Fix book back navigation history`)
- `jx-lang/main`: `6dc123045ad553282ff4b66102e45d01db43b6aa` (`Add full design conversation log and reflective gaps (perfection is amiss)`)
- `pasm-v2/main`: `6662e9e0c8e4133c49a2d6d653c8ae973612bc2b` (`jxerr: include source line numbers on every error`)

Both source heads are ancestors of the current branch:

```sh
git merge-base HEAD refs/remotes/jx-lang/main
# 6dc123045ad553282ff4b66102e45d01db43b6aa

git merge-base HEAD refs/remotes/pasm-v2/main
# 6662e9e0c8e4133c49a2d6d653c8ae973612bc2b
```

## Reproduce The Compare

```sh
git fetch jx-lang main
git fetch pasm-v2 main
git fetch origin main fix/plugin-runtime-root

git log --oneline refs/remotes/jx-lang/main..HEAD
git log --oneline refs/remotes/pasm-v2/main..HEAD

git diff --stat refs/remotes/jx-lang/main..HEAD
git diff --stat refs/remotes/pasm-v2/main..HEAD

git diff --name-status refs/remotes/jx-lang/main..HEAD
git diff --name-status refs/remotes/pasm-v2/main..HEAD
```

## Size Of The Movement

Compared with `jx-lang/main`:

```text
179 files changed, 23715 insertions(+), 54 deletions(-)
```

This is the large import/convergence view. It adds the PASM/PASL runtime tree, installer, plugins, executable, XIP Book server, and archives the original `jx-lang` specification tree under `history/jx-lang`.

Compared with `pasm-v2/main`:

```text
67 files changed, 8241 insertions(+), 78 deletions(-)
```

This is the tighter view for continuing from PASM/PASL. It adds the `jx-lang` history archive, the final JX installer/runtime-root work, the browser/window host protocol, and the Back navigation repair.

## Lineage Shape

The useful commit path from `pasm-v2/main` to now is:

```text
51ba670 Fix book back navigation history
2f0ad78 Unify JX install and host runtime
2256b10 Converge JX documentation and preserve source lineage
1599d9c Merge jx-lang lineage into jx
ca5c7d5 Archive jx-lang tree under history namespace
6dc1230 Add full design conversation log and reflective gaps (perfection is amiss)
8c52595 Initial jx language specification: smart table, Delivery, complex, Book/Page/Bag API, edge-case tests
0452fc2 Initial commit
```

The useful commit path from `jx-lang/main` to now is:

```text
51ba670 Fix book back navigation history
2f0ad78 Unify JX install and host runtime
2256b10 Converge JX documentation and preserve source lineage
1599d9c Merge jx-lang lineage into jx
ca5c7d5 Archive jx-lang tree under history namespace
6662e9e jxerr: include source line numbers on every error
4fa23a3 Refine jxerr message formatting: codes, structured log blocks, condensed stderr
e4de552 Hard reject non-portable plugins; collect all errors into jxerr.log
cebe689 Declare targets windows/mac/linux/web on every plugin.json
ca14210 Plugin allow-gate: must target windows, mac, linux, and web (jx) before install
20daf6b Complete jx install: plugin source dir, sequential installs, dual backups, decimals, intro, smart compiler modules
be8fd24 jx executable compiler/interpreter: jx-run.php, JxEngine, .jx source path through PASL bytecode when possible
a30639b Realize jx as one code construct on PASM: Bag/Task/Page/Book, memory law, Delivery, smart table bridge
4b802d6 Integrate jx language design into pasm-v2; project identity becomes jx (jinx)
8093de0..e599163 XIP Book server build-out
f50d3d7..542b349 no-JS live page and refresh/smooth semantics
d92a8d1..a2d591c PASM/PASL compiler/runtime lineage
```

## What Came From jx-lang

The last `jx-lang` content is preserved, not flattened away:

- `history/jx-lang/SPEC.md`
- `history/jx-lang/docs/CONVERSATION_LOG.md`
- `history/jx-lang/docs/GAPS.md`
- `history/jx-lang/docs/complex.md`
- `history/jx-lang/docs/delivery.md`
- `history/jx-lang/docs/hosting-api.md`
- `history/jx-lang/docs/smart-table.md`
- `history/jx-lang/tests/edge-cases.md`
- `history/jx-lang/README.md`

The active JX docs were also lifted into:

- `jx/SPEC.md`
- `jx/INTRO.md`
- `jx/INTEGRATION.md`
- `jx/PASM_MAP.md`
- `jx/COMPILER.md`
- `jx/INSTALL.md`

## What Came From pasm-v2

The active PASM/PASL base remains the runtime floor:

- `pasm-*.php` root runtime/compiler files
- `pasl/pasl.php`
- `pasl/pasl-front.php`, `pasl/pasl-back.php`, and payload part files
- `pasl/pasl-package.php`
- `pasl/pasl-run.php`
- `pasl/bench/*`
- `examples/pasl/*`
- `test-pasm-oop-fast.php`

Important current PASL caveat: `pasl/pasl.php` now tolerates the optional damaged strnet payload so numeric PASL can still compile and run.

## Convergence Additions

### JX executable and installer

- `jx.php` is the PHP executable/runtime surface.
- `jx-run.php` runs `.jx` programs through the JX engine and PASL/PASM path where possible.
- `jx-install.php` installs the executable and plugins.
- Linux install target is `/etc/bin` by policy in this branch.
- macOS and Windows install into a PATH-visible location.
- Plugins install into a shared package folder and can be linked into a Book or Library context.
- Plugins are context-free packages. A plugin manifest with `"depends"` is a hard reject.
- Per-plugin uninstall and full uninstall are supported.

Blame anchor for context-free package rejection:

```sh
git blame -L 310,360 -- jx-install.php
```

Key lines currently belong to `2f0ad78`:

- `E-CONTEXT`
- `"plugin packages must not depend on sibling packages"`
- forcing every required target false when context-free validation fails

### Plugin runtime-root bridge

Each plugin now has:

```text
plugins/<id>/runtime-root.php
```

Bootstrap files use that helper instead of assuming `host/jx.php`, which fixes the original crash:

```text
Failed opening required '/home/g0d77/jx/host/jx.php'
```

### XIP Book/window layer

The XIP Book server remains named `xi` and is pronounced `Z`.

Primary files:

- `pasl/xi/xi.php`
- `pasl/xi/src/XipEngine.php`
- `pasl/xi/src/XipEngine.h1.php`
- `pasl/xi/src/XipEngine.h2.php`
- `pasl/xi/src/Book.php`
- `pasl/xi/src/Binding.php`
- `pasl/xi/src/Server.php`
- `pasl/xi/src/HostProtocol.php`

Run it with no Node dependency:

```sh
cd pasl/xi
php xi.php localhost:8766 start config.json --foreground
```

Open:

```text
http://127.0.0.1:8766/?book=cover
```

### Book inside a Library

The library-style folder is:

```text
pasl/xi/books/
```

Example Books:

- `pasl/xi/books/cover`
- `pasl/xi/books/account`

The cover Book declares a portable window and a PASL program:

- `pasl/xi/books/cover/book.json`
- `pasl/xi/books/cover/pages/home.php`
- `pasl/xi/books/cover/programs/home.pasl`

### Host protocol

`JxHostProtocol` defines an OS-neutral drop/window envelope:

- version: `jx.host/1`
- hosts: `browser`, `win32`, `x11`
- drop route: `/jx/drop`
- schema: `pasl/host/drop.schema.json`
- native C ABI: `pasl/host/jx_host.h`
- native adapters:
  - `pasl/host/jx_host_win32.c`
  - `pasl/host/jx_host_x11.c`

Blame anchor:

```sh
git blame -L 1,90 -- pasl/xi/src/HostProtocol.php
```

All current host protocol lines were introduced by `2f0ad78`.

## Back Button Fix

The last active fix is `51ba670`.

Problem: `Binding::forward()` recorded history even when already at the final leaf. Repeated Forward clicks stacked the current page, so Back could pop the same leaf and appear broken.

Fix:

- `forward()` records history only when the cursor advances.
- `open()` records history only when the target leaf differs from the current leaf.
- `back()` skips stale same-page history entries so old persisted windows recover.

Blame anchor:

```sh
git blame -L 50,88 -- pasl/xi/src/Binding.php
```

No-JS verification used PHP/HTTP form posts:

```text
start=end
f1=end
f2=end
f_end=end
b1=about
b2=home
```

## Verification Commands

Use these before continuing:

```sh
php -l jx-install.php
php -l pasl/xi/src/Binding.php
php examples/jx-smoke.php
php jx-run.php --print examples/hello.jx
php test-pasm-oop-fast.php
php pasl/xi/scripts/smoke.php
git diff --check
```

Expected important outputs:

```text
jx smoke
overflow protected: ok
all smoke checks passed
248
PASS pasm oop fast
smoke OK GET
smoke OK BROWSER PASL
smoke OK POST
smoke OK TURN
smoke OK HOST DROP
```

## Runtime State To Ignore

The live demo creates untracked state under:

```text
pasl/xi/data/books/cover/binding.json
pasl/xi/data/books/cover/channels/*.json
work/*.png
```

Do not commit those unless the task explicitly asks for captured runtime state.

Generated cache files are ignored by `.gitignore`:

```text
pasl/.pasl-*.body.php
pasl/xi/src/XipEngine.assembled.php
```

## Next Work Pointers

1. Keep `xi` named `xi`; it is pronounced `Z`.
2. Do not prove core Book navigation with Node/JS. Use PHP engine calls or HTTP form posts.
3. Browser execution may exist as a host adapter, but the base Book/window flow must remain usable without JavaScript.
4. Native host compilation still needs a real C toolchain and Win32/X11 headers installed on the target OS.
5. The next architectural step is making the JX executable drive the `xi` Book/window host directly, so `jx` can open a Book in the chosen OS host without requiring the user to manually start `xi.php`.
