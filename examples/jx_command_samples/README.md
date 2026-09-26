# JX command samples

This folder contains ten small PHP declaration files that demonstrate how the current `jx_*` commands build native page state and how that state can be emitted into C.

## Commands demonstrated

| Command | Purpose |
|---|---|
| `jx_page_title(string $title)` | Sets the native page/window title. |
| `jx_page_badge(string $badge)` | Sets the small status/category badge. |
| `jx_page_body(string $body)` | Sets the main body text. |
| `jx_modal_title(string $title)` | Sets the modal overlay title. |
| `jx_modal_body(string $body)` | Sets the modal overlay body. |
| `jx_iframe_title(string $title)` | Sets the attached HTML/iframe panel title. |
| `jx_iframe_html(string $html)` | Sets the attached HTML payload. |
| `jx_page_json(string $json)` | Attaches the structured machine-readable page object/state JSON. |
| `jx_local_api(string $path, array $params)` | Declares a localhost Island/API route and the query params it accepts. |

## Samples

| File | Focus |
|---|---|
| `01_title_badge_body.php` | Minimal title, badge, body, and JSON attachment. |
| `02_modal_help.php` | Modal title/body layered above page state. |
| `03_iframe_attachment.php` | Attached HTML payload. |
| `04_page_json_state.php` | Page object model expressed in JSON. |
| `05_local_api_json.php` | Read-only `/json` local API route. |
| `06_update_api.php` | `/update?title=...&badge=...&body=...`. |
| `07_modal_api.php` | `/modal?title=...&body=...`. |
| `08_iframe_api.php` | `/iframe?title=...&html=...`. |
| `09_dashboard_combined.php` | All declaration families working together. |
| `10_island_protocol_demo.php` | Island protocol route map and state contract. |

## Emit one sample to C

From the repo root:

```sh
mkdir -p build/jx-command-samples
./jx -o build/jx-command-samples/sample01.c examples/jx_command_samples/01_title_badge_body.php
```

Then compile the generated C on Windows/MSYS2:

```sh
gcc -O2 -Wall -Wextra -mwindows -I . \
  -o build/jx-command-samples/sample01.exe \
  build/jx-command-samples/sample01.c \
  -lws2_32 -lgdi32 -luser32
```

On Linux/WSL with MinGW-w64 cross compile:

```sh
x86_64-w64-mingw32-gcc -O2 -Wall -Wextra -mwindows -I . \
  -o build/jx-command-samples/sample01.exe \
  build/jx-command-samples/sample01.c \
  -lws2_32 -lgdi32 -luser32
```

## Emit all ten samples

```sh
mkdir -p build/jx-command-samples
for sample in examples/jx_command_samples/[0-9][0-9]_*.php; do
  base=$(basename "$sample" .php)
  ./jx -o "build/jx-command-samples/${base}.c" "$sample"
done
```

Compile all generated C files with MinGW-w64:

```sh
for cfile in build/jx-command-samples/*.c; do
  exe="${cfile%.c}.exe"
  x86_64-w64-mingw32-gcc -O2 -Wall -Wextra -mwindows -I . \
    -o "$exe" "$cfile" \
    -lws2_32 -lgdi32 -luser32
done
```

## Island/API smoke tests

After launching a compiled sample that declares local API routes, test the routes from another terminal:

```sh
curl http://127.0.0.1:8765/json
curl 'http://127.0.0.1:8765/update?title=Updated&badge=LIVE&body=Changed'
curl 'http://127.0.0.1:8765/modal?title=Help&body=Opened'
curl 'http://127.0.0.1:8765/iframe?title=Panel&html=%3Ch2%3EHi%3C%2Fh2%3E'
```

The exact port is the port used by the emitted local API runtime. If the runtime prints a different port, use that port in the commands above.

## Related doc

See `docs/JX_COMMAND_SAMPLES_AND_ISLAND.md` for the full explanation of the declarations, page JSON, C emission, and Island protocol.
