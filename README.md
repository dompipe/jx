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
| `jx-alias.php` | Language-wide alias domains, collision rules, canonicalization + provenance |
| `jx-bag-containers.php` | Bag-backed record/vector/stack/queue/deque/map/set disciplines |
| `pasm-bag-hotops.php` | Canonical Bag hot ops (`BPUSH`, `BPOP`, `BEMPLACE`, etc.) + native lowering recipes |
| `pasm-loop-space.php` | Single-op variable mutation + bounded compiled loop-space descriptors |
| `jx-lang.php` / `jx-run.php` | Language engine + executable compiler |
| `plugins/` | **Single source directory** for all module plugins |
| `host/modules/` | Per-plugin links to shared active packages |
| `host/backups/pre/` | Snapshot before each new plugin install |
| `host/backups/full/` | Full install snapshot (restore / redirect) |
| `jx/INTRO.md` | Introduction materials |
| `jx/INSTALL.md` | Install & plugin policy |
| `jx/COMPILER.md` | Compiler pipeline |
| `docs/BAG-CONTAINERS.md` | Containers as Bag disciplines + native-shadow strategy |
| `docs/JX-ALIASES.md` | Language-wide alias rules and canonicalization |
| `docs/history/` | Original-to-latest Markdown and blame conveyance |
| `history/jx-lang/` | History-preserving snapshot of the earlier `jx-lang` tree |

## Language-wide aliases

Aliases are a standard compiler feature. Human-facing spellings resolve to one canonical operation before semantic lowering and do not survive into executable code.

```text
enqueue  ─┐
append   ─┼─> BPUSH ─> Bag discipline ─> native shadow
push     ─┘
```

The alias registry is domain-scoped, collision-safe, plugin-extensible, and provenance-aware. A diagnostic can retain `source_spelling=enqueue` while the compiler and native shadow only see `BPUSH`.

Examples:

```text
Book.load       -> Book.open
Bag.allocate    -> Bag.underwrite
deliver(...)    -> delivery(...)
SQL exec        -> EXECUTE
chart draw      -> RENDER
JNE             -> PASM JNZ
```

See `docs/JX-ALIASES.md`.

## Single-operation variable mutation

Variable changes canonicalize into one operation before target lowering:

```text
$x++            -> VINC x
$x--            -> VDEC x
$x += y         -> VADD x,y
$x -= y         -> VSUB x,y
$x *= y         -> VMUL x,y
$x = algebra    -> VALG x,<compiled algebra tree>
```

Where the target permits it, increment/decrement and simple compound assignments lower to a single native instruction (`inc`, `dec`, `add`, `sub`, etc.). Recursive algebra remains one canonical mutation; its expression tree is ordered and fused at compile time rather than rediscovered at runtime.

## Compiled loop space

Loops are moving toward **compiled callable bodies** instead of repeated inline interpretation. `pasm-loop-space.php` defines a bounded loop-space allocator and immutable loop block descriptors.

A loop iteration is modeled as:

```text
LCHECK condition
LCALL  compiled_body
[LCALL compiled_step]
LREPEAT loop_slot
```

The body and step are compiled blocks. The controller keeps only condition state, iteration state, direct block targets, and a bounded nesting slot. Default nesting depth is 8; exceeding the configured depth is a compile-time error. Sequential loops reuse slots after scope exit, while nested loops occupy ordered slots `0..depth-1`.

This applies to `for`, `while`, `do-while`, `foreach`, and `repeat` lowering. The existing PASL compiler still provides legacy inline loop lowering while this compiled-block loop-space pass is integrated into its final emission path; the new loop-space module is already loaded by `pasm-lang.php` and regression-tested independently.

## Bags and containers

Containers are modeled as **Bag disciplines**, not as a second memory system. A Bag supplies ownership, capacity, identity and canonical checkpoint law; the discipline supplies the hot access strategy.

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

The invariant is: **be native while working; become canonical at the Bag boundary**. `nativeLayout()` is a compiler/shadow hint; `canonical()` is the authoritative state image.

### Bag hot operations

The core canonical family is:

```text
BPUSH BPOP
BPUSHF BPUSHB BPOPF BPOPB
BEMPLACE
BPEEK BRESERVE BDIRTY BSYNC
```

Normal fixed-width push/pop forms target two machine instructions after register-residency lowering.

`BEMPLACE` computes an insertion location once:

- vector/stack: one address calculation + one overlap-safe bulk tail move + one store
- map: one probe to existing/insertion address + insert if absent
- set: one probe to existing/insertion address + insert key if absent

Aliases such as `insert`, `emplace`, `packin`, `putifabsent`, and `addifabsent` all resolve to `BEMPLACE`; the discipline decides the physical lowering.

Regression and benchmark harnesses:

```bash
php test-jx-bag-containers.php
php test-pasm-bag-hotops.php
php test-jx-alias.php
php test-jx-lang-alias.php
php test-pasm-loop-space.php
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
- Plugins may register aliases only in a collision-safe domain/context

```bash
php jx-install.php list
php jx-install.php install intro
php jx-install.php backup-full
php jx-install.php restore-full <timestamp>
```

## Includes

- Decimals (`plugins/decimals` → `jx\\Decimal`)
- Complex, Delivery, const, smart compiler, lang bridge
- Memory law, Books/Bags/Pages, Resistant path
- Bag-backed record/vector/stack/queue/deque/map/set disciplines
- Language-wide canonical aliases with zero runtime alias lookup
- Single-op variable lowering and bounded compiled loop space

See `jx/INTRO.md` for the guided introduction.

## System layout

| Platform | Commands | Shared active plugins |
|----------|----------|-----------------------|
| Linux | `/etc/bin/jx`, `/etc/bin/jx-install` | `/etc/jx/plugins` |
| macOS | `/usr/local/bin/jx`, `/usr/local/bin/jx-install` | `/usr/local/share/jx/plugins` |
| Windows | `%LOCALAPPDATA%\\jx\\bin` (User PATH) | `%ProgramData%\\jx\\plugins` |

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
