# jx (jinx)

**Formerly pasm-v2.** Product name **jx**; PASM is the engine.

## Quick start

```bash
# Install commands, PATH integration, and shared plugin storage
sudo php jx-install.php install-system   # Linux/macOS
php jx-install.php install-system        # Windows

# Install required plugins (one-by-one, with pre + full backups)
jx-install install-required

# Run
jx --print examples/hello.jx
php examples/jx-smoke.php
```

## Layout

| Path | Role |
|------|------|
| `jx.php` | Core runtime (Bag, Task, Page, Book, Delivery, Complex, SmartTable, Sym) |
| `jx-bag-containers.php` | Bag-backed record/vector/stack/queue/deque/map/set disciplines |
| `jx-lang.php` / `jx-run.php` | Language engine + executable compiler |
| `plugins/` | **Single source directory** for all module plugins |
| `host/modules/` | Per-plugin links to shared active packages |
| `host/backups/pre/` | Snapshot before each new plugin install |
| `host/backups/full/` | Full install snapshot (restore / redirect) |
| `jx/INTRO.md` | Introduction materials |
| `jx/INSTALL.md` | Install & plugin policy |
| `jx/COMPILER.md` | Compiler pipeline |
| `docs/BAG-CONTAINERS.md` | Containers as Bag disciplines + native-shadow strategy |
| `docs/history/` | Original-to-latest Markdown and blame conveyance |
| `history/jx-lang/` | History-preserving snapshot of the earlier `jx-lang` tree |

## Bags and containers

Containers are now modeled as **Bag disciplines**, not as a second memory system. A Bag supplies ownership, capacity, identity and canonical checkpoint law; the discipline supplies the hot access strategy.

```text
Bag
|- record -> fixed dense slots / native field offsets
|- vector -> contiguous indexed storage
|- stack  -> contiguous + LIFO
|- queue  -> power-of-two ring + FIFO
|- deque  -> double-ended power-of-two ring
|- map    -> target-native hash
`- set    -> target-native hash set
```

Use the container layer explicitly:

```php
require_once __DIR__ . '/jx.php';
require_once __DIR__ . '/jx-bag-containers.php';

$state = jx\BagContainers::record(4096, [
    'health' => 'int',
    'phi' => ['type' => 'int', 'default' => 0],
]);
$state->put('health', 100);

$jobs = jx\BagContainers::queue(65536, 'Task');
$jobs->enqueue($task);

// Explicit canonical boundary; hot operations do not pay this cost.
$jobs->checkpoint();
```

The invariant is: **be native while working; become canonical at the Bag boundary**. `nativeLayout()` is a compiler/shadow hint; `canonical()` is the authoritative state image. See `docs/BAG-CONTAINERS.md`.

Regression and benchmark harnesses:

```bash
php test-jx-bag-containers.php
php benchmark-jx-bag-containers.php 1000000 7
```

## Books and OS hosts

Books and Bindings are runtime state, not browser objects. The same Book can be
presented by three operating-system hosts:

- **browser**: PASL compiles to PASM and executes in the page VM
- **win32**: the stable C ABI maps windows and events to Win32
- **x11**: the same ABI maps windows and events to Xlib

All hosts exchange versioned `jx.host/1` JSON drops, so replacing a browser or
native window system does not replace the Book, leaf history, channels, or PASL
program. See `pasl/host/README.md` and the runnable XIP Cover Book.

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
- Bag-backed record/vector/stack/queue/deque/map/set disciplines

See `jx/INTRO.md` for the guided introduction.

## System layout

| Platform | Commands | Shared active plugins |
|----------|----------|-----------------------|
| Linux | `/etc/bin/jx`, `/etc/bin/jx-install` | `/etc/jx/plugins` |
| macOS | `/usr/local/bin/jx`, `/usr/local/bin/jx-install` | `/usr/local/share/jx/plugins` |
| Windows | `%LOCALAPPDATA%\jx\bin` (User PATH) | `%ProgramData%\jx\plugins` |

Plugins are independent packages. A Book or library links only the packages it
uses:

```bash
jx-install link decimals book /path/to/book
jx-install link delivery library /path/to/library
jx-install unlink decimals book /path/to/book
jx-install uninstall decimals
```

Preview or remove system integration with:

```bash
php jx-install.php install-system --dry-run
jx-install uninstall-system
```

System uninstall backs up shared plugins before removing them. Add
`--keep-plugins` to retain the independent package store.

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
