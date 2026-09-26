# JX

JX is the new executable path for PHP-to-executable-code work.

The command name is:

```bash
./jx
```

## Current direction

JX now has two layers:

1. A bootstrap PHP shebang command checked into the repo as `jx`.
2. A native GCC-compilable implementation at `src/native/jx.c`.

Run the native build to replace the bootstrap `jx` with a real compiled executable in your working tree:

```bash
sh scripts/build-native-jx.sh
```

After that, `./jx` is a GCC-built native program.

## PHP to C

The native `jx` collects the current PHP execution oracle into one final C file. It reads a PHP source file and emits a standalone `.c` file.

```bash
./jx emit-c examples/hello.php -o build/hello.c
```

The emitted C file contains:

- the original PHP payload as embedded bytes
- JX Oracle metadata
- a runtime bridge that writes the payload to a temporary PHP file
- a process executor that calls the local PHP runtime
- argument and exit-code preservation

Compile the emitted C file with GCC/CC:

```bash
cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/hello build/hello.c
./build/hello world
```

Expected output:

```text
Hello from JX, world
```

## PHP plus CSS asset to C

JX can also collect a CSS file into the emitted C source:

```bash
./jx emit-c examples/page.php --asset examples/style.css -o build/page.c
cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/page build/page.c
./build/page world > build/page.html
```

The generated executable materializes the CSS asset at runtime and exposes it to PHP as:

```text
JX_CSS_FILE=/tmp/jx_css_asset_XXXXXX
JX_CSS_NAME=style.css
```

The PHP page can then inline or read it:

```php
$css = file_get_contents(getenv('JX_CSS_FILE'));
```

That gives this flow:

```text
PHP + CSS
↓
native ./jx
↓
one generated .c file
↓
gcc
↓
one executable that recreates the PHP/CSS bundle
```

## JX native page declarations, samples, and Island protocol

The repo includes a practical guide and ten sample declaration files for the native page/window workflow:

```text
docs/JX_COMMAND_SAMPLES_AND_ISLAND.md
examples/jx_command_samples/
```

These examples cover:

- `jx_page_title()`
- `jx_page_badge()`
- `jx_page_body()`
- `jx_modal_title()`
- `jx_modal_body()`
- `jx_iframe_title()`
- `jx_iframe_html()`
- `jx_page_json()`
- `jx_local_api()`
- how page JSON mirrors declaration state
- how local Island/API routes like `/json`, `/update`, `/modal`, and `/iframe` work
- how to emit each sample to C and compile it

Start here:

```bash
cat docs/JX_COMMAND_SAMPLES_AND_ISLAND.md
ls examples/jx_command_samples
```

Emit all ten samples to C:

```bash
mkdir -p build/jx-command-samples
for sample in examples/jx_command_samples/[0-9][0-9]_*.php; do
  base=$(basename "$sample" .php)
  ./jx -o "build/jx-command-samples/${base}.c" "$sample"
done
```

## Commands

```bash
git pull origin main
sh scripts/build-native-jx.sh
./jx --version
mkdir -p build
./jx emit-c examples/hello.php -o build/hello.c
cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/hello build/hello.c
./build/hello world
./jx emit-c examples/page.php --asset examples/style.css -o build/page.c
cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/page build/page.c
./build/page world > build/page.html
./tests/native-run.sh
```

## Important status

This is now a native executable compiler front-end that turns PHP files, and optionally a CSS asset, into GCC-compilable `.c` files.

The generated executable still delegates PHP execution to the installed PHP runtime. That keeps behavior aligned while the native PHP compiler is built underneath it.

The next step is to replace the bridge pieces with native lowered PHP operations one group at a time.
