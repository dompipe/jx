# jx (jinx)

**Formerly pasm-v2.** Product name **jx**; PASM is the engine.

## OSAura operating system

JX is the language/compiler/runtime layer. The standalone operating system built around it is **OSAura**, maintained separately at `dompipe/OSAura`.

```text
canonical JX / .64B
        ↓
JX runtime ABI
        ↓
OSAura kernel
        ↓
drivers / hardware
```

OSAura consumes the host-neutral JX pieces: Bags/containers, `.64B`, applied bytecodes, tasks, channels, hot generations/rollback, multiplexed buses, and JX Security. Windows/Linux-specific wake and host mechanisms remain adapters/reference implementations rather than becoming part of the OSAura kernel.

The portability and synchronization contract is documented in `docs/OSAURA.md`.

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
| `pasm-lang-compiler-loop.php` | Active PASL compiler with out-of-line compiled `for`/`while` blocks |
| `jx-lang.php` / `jx-run.php` | Language engine + executable compiler |
| `jx/plugins/` | Host-neutral JX plugin contracts (Charts, Media, AudioFX, Audio Analysis) |
| `plugins/` | **Single source directory** for installable module plugins |
| `host/modules/` | Per-plugin links to shared active packages |
| `host/backups/pre/` | Snapshot before each new plugin install |
| `host/backups/full/` | Full install snapshot (restore / redirect) |
| `jx/INTRO.md` | Introduction materials |
| `jx/INSTALL.md` | Install & plugin policy |
| `jx/COMPILER.md` | Compiler pipeline |
| `docs/BAG-CONTAINERS.md` | Containers as Bag disciplines + native-shadow strategy |
| `docs/JX-ALIASES.md` | Language-wide alias rules and canonicalization |
| `docs/LOOP-SPACE.md` | Single-op mutations + bounded out-of-line loop compilation |
| `docs/OSAURA.md` | JX ↔ OSAura OS portability and synchronization contract |
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

`pasm-lang.php` now loads `pasm-lang-compiler-loop.php` as the active PASL compiler. `for` and `while` bodies are compiled once into out-of-line blocks rather than emitted inline in the loop controller.

Canonical loop control is:

```text
LCHECK condition
LCALL  compiled_body
[LCALL compiled_step]
LREPEAT loop_slot
```

On the current PASM ISA, `LCALL` lowers to a direct branch because each loop block has a fixed continuation. This avoids adding a runtime call-stack mechanism just to obtain the desired shape. Native ELF/EXE emitters remain free to turn that same canonical operation into a machine `call`, tail branch, or inline body.

For a `for` loop, init executes once, the controller performs the condition check, the compiled body branches to a compiled step block, and the step returns to the condition check. `continue` targets the step block and `break` targets the loop exit. A `while` loop uses the same structure without a step block.

Loop state is allocated in bounded slots. Default nesting depth is 8; exceeding the configured limit is a compile-time error. Nested bodies are compiled while the parent still occupies its slot, so the limit is enforced in the active compiler. Sequential loops reuse slots after leaving scope.

The semantic loop-space model also defines `do-while`, `foreach`, and `repeat`. Their surface-specific entry/collection semantics are not yet accepted by the PASL front end; `for` and `while` are the active out-of-line compiled forms now.

See `docs/LOOP-SPACE.md`.

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
php test-pasm-loop-compiler.php
php benchmark-jx-bag-containers.php 1000000 7
```

The repository also contains `.github/workflows/jx-compiler-ci.yml` to lint the active compiler and run these regressions on GitHub Actions.

## Books and OS hosts

Books and Bindings are runtime state, not browser objects. The same Book can be presented by three operating-system hosts:

- **browser**: PASL compiles to PASM and executes in the page VM
- **win32**: the stable C ABI maps windows and events to Win32
- **x11**: the same ABI maps windows and events to Xlib

All hosts exchange versioned `jx.host/1` JSON drops, so replacing a browser or native window system does not replace the Book, leaf history, channels, or PASL program. See `pasl/host/README.md` and the runnable XIP Cover Book.

## Host-neutral charts and media

JX includes host-neutral plugin contracts for Charts, Media, AudioFX, and Audio Analysis. The base Media player remains intentionally small: source, Bag binding, playback options, style, events, and extension slots. Processing belongs in extension plugins rather than in the base player.

Canonical chart types are:

```text
pie
candle
bar
line
vectormap
```

Media and analysis flow through Bags so charting and algebra do not depend on database handles:

```text
MP3 / MP4
   -> Media plugin
      -> optional Media-extension plugins
         -> browser/native media renderer
            -> Audio Analysis plugin (optional)
               -> Bag
                  -> Chart / algebra / Page
```

The browser media host contract lives in `host/browser/jx-media-host.js`; plugin contracts live under `jx/plugins/`.

## Plugins

- Sourced **only** from `plugins/`
- Installed **one at a time**; new installs append **last** after need is assessed
- Dual backup: **pre** (per install) + **full** (total install)
- Plugins may register aliases only in a collision-safe domain/context

Bundled host-neutral contracts include Charts, Media, AudioFX, and Audio Analysis. These consume Bags rather than SQL/NoSQL handles; data-source bindings remain separate from presentation.

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
- Language-wide canonical aliases with zero runtime alias lookup
- Single-op variable lowering and bounded compiled loop space
- Host-neutral chart/media contracts and native-shadow entry points

See `jx/INTRO.md` for the guided introduction.

## OOP benchmark — first stop

The canonical PASM OOP container implementation is benchmarked against the legacy implementation and direct native PHP. The checked-in harness runs each mode in a fresh process and reports repeated measurements; the direct-native gap remains an optimization target rather than being hidden.

Run:

```bash
php benchmark-pasm-oop-fast.php
php benchmark-pasm-oop-fast-sync.php
php benchmark-pasm-oop-fast-deque.php
```

GitHub Actions definition:

```text
.github/workflows/oop-benchmark.yml
```

### 100,000 total operations

| Workload | Legacy ms | Canonical OOP ms | Native PHP ms | Legacy / new | Improvement vs legacy |
|---|---:|---:|---:|---:|---:|
| Vector add/get | 5.753 | 3.994 | 0.562 | 1.44x | 30.6% |
| Stack push/pop | 9.834 | 4.388 | 1.264 | 2.24x | 55.4% |
| Queue enq/deq | 8.523 | 6.997 | 0.724 | 1.22x | 17.9% |
| Deque back/front | 10.494 | 8.707 | 0.645 | 1.21x | 17.0% |
| Map put/get | 4.715 | 4.314 | 0.630 | 1.09x | 8.5% |
| Set add/has | 24.989 | 13.779 | 0.706 | 1.81x | 44.9% |

### 1,000,000 total operations

| Workload | Legacy ms | Canonical OOP ms | Native PHP ms | Legacy / new | Improvement vs legacy |
|---|---:|---:|---:|---:|---:|
| Vector add/get | 53.924 | 42.449 | 8.189 | 1.27x | 21.3% |
| Stack push/pop | 80.414 | 46.253 | 14.645 | 1.74x | 42.5% |
| Queue enq/deq | 88.356 | 67.362 | 8.815 | 1.31x | 23.8% |
| Deque back/front | 96.006 | 83.465 | 9.306 | 1.15x | 13.1% |
| Map put/get | 48.917 | 45.220 | 9.232 | 1.08x | 7.6% |
| Set add/has | 240.272 | 152.258 | 10.117 | 1.58x | 36.6% |

At one million operations the canonical rewrite is faster than the legacy implementation across all six measured workloads. Direct native PHP remains faster; native shadows, fixed offsets, register identities, and compile-time resolution are the path for closing that gap while keeping canonical source readable.

## System layout

| Platform | Commands | Shared active plugins |
|----------|----------|-----------------------|
| Linux | `/etc/bin/jx`, `/etc/bin/jx-install` | `/etc/jx/plugins` |
| macOS | `/usr/local/bin/jx`, `/usr/local/bin/jx-install` | `/usr/local/share/jx/plugins` |
| Windows | `%LOCALAPPDATA%\jx\bin` (User PATH) | `%ProgramData%\jx\plugins` |

Plugins are independent packages. A Book or library links only the packages it uses:

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

System uninstall backs up shared plugins before removing them. Add `--keep-plugins` to retain the independent package store.

## Lineage

This repository converges `dompipe/pasm-v2` and `dompipe/jx-lang` without flattening either Git history. The current implementation and canonical docs come from the later `pasm-v2` integration. The earlier standalone language design remains under `history/jx-lang/`, where `git log --follow` and `git blame --follow` can continue through its original commits.

See [docs/history/README.md](docs/history/README.md) for source revisions, navigation, and provenance commands.

---

jx — pronounced jinx.
