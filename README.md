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
| `jx-lang.php` / `jx-run.php` | Language engine + executable compiler |
| `plugins/` | **Single source directory** for all module plugins |
| `host/modules/` | Per-plugin links to shared active packages |
| `host/backups/pre/` | Snapshot before each new plugin install |
| `host/backups/full/` | Full install snapshot (restore / redirect) |
| `jx/INTRO.md` | Introduction materials |
| `jx/INSTALL.md` | Install & plugin policy |
| `jx/COMPILER.md` | Compiler pipeline |
| `docs/history/` | Original-to-latest Markdown and blame conveyance |
| `history/jx-lang/` | History-preserving snapshot of the earlier `jx-lang` tree |

## Books and OS hosts

Books and Bindings are runtime state, not browser objects. The same Book can be
presented by three operating-system hosts:

- **browser**: PASL compiles to PASM and executes in the page VM
- **win32**: the stable C ABI maps windows and events to Win32
- **x11**: the same ABI maps windows and events to Xlib

All hosts exchange versioned `jx.host/1` JSON drops, so replacing a browser or
native window system does not replace the Book, leaf history, channels, or PASL
program. See `pasl/host/README.md` and the runnable XIP Cover Book.

### Current browser media status

JX now has host-neutral plugin contracts for Charts, Media, AudioFX, and Audio
Analysis.

The **base Media player is intentionally small**. MP3/MP4 Controls carry only the
media source, Bag binding when used, normal playback options, Style, playback
events, and an extension list. Equalizers, compressors, spatial processing,
sound enhancement, visualization, or future audio processing do **not** belong
inside the base player.

A plain MP3 therefore serializes with an empty extension list. An optional
plugin can extend Media without changing the MP3 contract:

```php
$player = MediaPlugin::mp3('theme', '/assets/theme.mp3', [
    'controls' => true,
    'volume' => 0.75,
]);

$enhanced = $player->extend('audio-fx', [
    'gain_db' => 1.5,
    'bass_db' => 2.0,
    'stereo_width' => 1.1,
    'compressor' => true,
]);
```

`AudioFX` is the first proof of the plugin-for-plugin contract. It extends
`media`, owns and validates its own sound-processing vocabulary, and deliberately
preserves safe unknown fields so later versions can add processing ideas without
expanding `MediaControl`.

The Audio Analysis plugin can independently turn a media stream into evenly
divided frequency buckets, optionally constrained by an explicit frequency
range, and publish those rows into a Bag for Charts or algebra.

The browser playback renderer itself is **not yet connected**. In other words,
the current branch can serialize an MP3 player Control, bind its source, attach
optional processing descriptors, and describe its analysis pipeline, but it
does not yet instantiate the final browser `<audio>` element from that
descriptor. That renderer is a host task, not a Media-object change.

The intended live path is:

```text
MP3 / MP4
   -> Media plugin
      -> optional Media-extension plugins
         -> browser/native media renderer
            -> Audio Analysis plugin (optional)
               -> Bag
                  -> Chart / algebra / Page
```

## Plugins

- Sourced **only** from `plugins/`
- Installed **one at a time**; new installs append **last** after need is assessed
- Dual backup: **pre** (per install) + **full** (total install)

Bundled host-neutral JX contracts also include:

```text
Charts
  pie
  candles
  bar
  line

Media
  MP3 / audio
  MP4 / video
  extension slots

AudioFX (extends Media)
  gain
  EQ fields
  compressor
  stereo width
  open future fields

Audio Analysis
  bucket-only spectrum
  ranged spectrum
```

These controls consume Bags rather than database handles. A Bag may itself be
bound to SQL, NoSQL, channels, or later data-source plugins.

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

## OOP benchmark — first stop

The first benchmark checkpoint is the current canonical PASM OOP container
implementation against the legacy implementation and direct native PHP. The
harness runs each mode in a fresh PHP process and reports the median of three
runs for each workload. `N` is half writes/inserts and half reads/removals.

Run it locally with:

```bash
php benchmark-pasm-oop-fast.php
php benchmark-pasm-oop-fast-sync.php
php benchmark-pasm-oop-fast-deque.php
```

A reproducible GitHub Actions definition is also stored at:

```text
.github/workflows/oop-benchmark.yml
```

The table below is the baseline currently checked into
`benchmark-pasm-oop-fast-results.json`. It is the **first stop**, not a claim
that the OOP layer already beats direct native PHP.

### 100,000 total operations

| Workload | Legacy ms | Canonical OOP ms | Native PHP ms | Legacy / new | Improvement vs legacy |
|---|---:|---:|---:|---:|---:|
| Vector add/get | 5.753 | 3.994 | 0.562 | 1.44x | 30.6% |
| Stack push/pop | 9.834 | 4.388 | 1.264 | 2.24x | 55.4% |
| Queue enq/deq | 8.523 | 6.997 | 0.724 | 1.22x | 17.9% |
| Deque back/front | 10.494 | 8.707 | 0.645 | 1.21x | 17.0% |
| Map put/get | 4.715 | 4.314 | 0.630 | 1.09x | 8.5% |
| Set add/has | 24.989 | 13.779 | 0.706 | 1.81x | 44.9% |

Peak memory:

```text
legacy    8.5 MB
canonical 8.5 MB
native    2.0 MB
```

### 1,000,000 total operations

| Workload | Legacy ms | Canonical OOP ms | Native PHP ms | Legacy / new | Improvement vs legacy |
|---|---:|---:|---:|---:|---:|
| Vector add/get | 53.924 | 42.449 | 8.189 | 1.27x | 21.3% |
| Stack push/pop | 80.414 | 46.253 | 14.645 | 1.74x | 42.5% |
| Queue enq/deq | 88.356 | 67.362 | 8.815 | 1.31x | 23.8% |
| Deque back/front | 96.006 | 83.465 | 9.306 | 1.15x | 13.1% |
| Map put/get | 48.917 | 45.220 | 9.232 | 1.08x | 7.6% |
| Set add/has | 240.272 | 152.258 | 10.117 | 1.58x | 36.6% |

Peak memory:

```text
legacy    56.00 MB
canonical 52.00 MB
native    14.01 MB
```

### First-stop reading

The canonical OOP rewrite is faster than the legacy implementation in all six
measured workloads. At one million operations, Stack is about **42.5% faster**
than legacy and Set about **36.6% faster**. Map currently improves the least,
about **7.6%**.

Direct native PHP remains materially faster. At one million operations the
canonical layer is approximately 3.16x native for Stack, 4.90x for Map, 5.18x
for Vector, 7.64x for Queue, 8.97x for Deque, and 15.05x for Set. That gap is the
optimization target; the benchmark must remain visible rather than being hidden
by abstraction claims.

The canonical design deliberately keeps ordinary operations in frame-local hot
state and pays the segmented/canonical checkpoint cost only at explicit
boundaries. The companion sync benchmark exists to keep that checkpoint cost
visible separately from the hot-operation benchmark.

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
