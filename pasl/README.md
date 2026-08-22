# PASL — PHP-like language → native binary / Windows EXE

**One require. Compile. Run like an `.exe`.**

```php
require 'pasl/pasl.php';
use pasl\Package;
echo Package::toC($source);   // auto-picks numeric vs full surface
```

```bash
php pasl/pasl-run.php --c -o app.c examples/arrays.pasl
gcc -O2 -o app app.c && ./app; echo $?

# HTTPS / live need OpenSSL:
gcc -O2 -o app app.c -lssl -lcrypto
```

## What you get

| Capability | Example | Target |
|------------|---------|--------|
| Integers & control | `$x++; while ($i) {…}` | C / x86 / ARM / PASM |
| Complex | `complex $z = 3+4i;` | numeric core |
| Strings | `string $s = "hi";` | C / EXE |
| Arrays | `array $a = [1,2,3];` | C / EXE |
| Bags | `object $o = {}; $o.x = 1;` | C / EXE |
| `fetch` (HTTP + HTTPS/TLS) | `fetch("https://…")` | C / EXE + OpenSSL |
| Live pages | `live_file` / `live_dom` / `live_run` | C / EXE |

Silent by default. **Exit status = result**.

## Package layout (integrated)

```
pasl.php              ← single entry
pasl-package.php      ← pasl\Package auto-route
pasl-front + pasl-back ← numeric O(n)
pasl-strnet.php       ← strings, arrays, bags, fetch, live, TLS
pasl-run.php          ← CLI
examples/
```

## Live updates — refresh vs smooth

**Does it act like pressing the browser Refresh button?**

| Mode | Like F5? | What you can lose |
|------|----------|-------------------|
| **`live_file` + meta refresh** | **Yes** — whole document | Scroll, focus, typing in that page |
| **`/plain` meta refresh** | **Yes** — whole page | Same as F5 |
| **`live_dom` + iframe `/stream`** | **Only the slot** | Slot content; **outer shell stays** |

Meta-refresh / backing-file modes **are** full document reloads (like F5). That can drop scroll, focus, and in-progress form fields in the reloaded document.

**Smoother without a SPA:** `live_dom` + multipart `/stream` — outer chrome is not torn down; only the connected slot is replaced.

```pasl
// Stable outer shell:
live_dom("pasl-root", "slot content");
live_run(8765, 2);

// Full-document refresh (like F5):
live_file("/tmp/pasl-live.html");
live_set("content");
live_run(8765, 2);
```

## CLI

```text
php pasl/pasl-run.php [--c|--strnet|--x86|--arm|--pasm] [--bin] [-o out] [-c 'src'|file]
```

## Examples

`arrays` · `bags` · `strings` · `fetch` · `fetch_https` · `live_file` · `live_dom`

## Benchmarks

| Case | µs | compiles/s |
|------|---:|-----------:|
| num_tiny | ~12 | ~85k |
| arr_sum | ~46 | ~22k |
| mixed | ~58 | ~17k |

```bash
php pasl/bench/bench-all.php 500
```

## Docs

- [PASL_Programming_Guide.pdf](PASL_Programming_Guide.pdf)
- [PASL_Programming_Guide.md](PASL_Programming_Guide.md)

*PASL integrated · github.com/dompipe/pasm-v2*
