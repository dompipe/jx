# JX command samples, page JSON, C emission, and Island protocol

This document explains the current `jx_*` declaration workflow with ten practical samples. The short version is:

```text
PHP declaration file -> JX capture -> page state JSON -> generated C -> native executable -> local Island/API routes
```

The PHP file does not need to be a full web application. It can be a compact declaration file that calls `jx_page_title`, `jx_page_badge`, `jx_page_body`, `jx_modal_title`, `jx_modal_body`, `jx_iframe_title`, `jx_iframe_html`, `jx_page_json`, and `jx_local_api`. JX captures those declarations and emits a C program that carries the page state directly inside the native executable.

## 1. Current `jx_*` declaration surface

| Command | Input | Captured state |
|---|---|---|
| `jx_page_title($title)` | String | Core page/window title. |
| `jx_page_badge($badge)` | String | Small page status badge. |
| `jx_page_body($body)` | String | Main body text. |
| `jx_modal_title($title)` | String | Modal overlay title. |
| `jx_modal_body($body)` | String | Modal overlay body. |
| `jx_iframe_title($title)` | String | Attached HTML/iframe panel title. |
| `jx_iframe_html($html)` | String | Attached HTML payload. |
| `jx_page_json($json)` | JSON string | Machine-readable page state/object model. |
| `jx_local_api($path, $params)` | Route string and param list | Localhost Island/API route declaration. |

The declarations can be used independently, but they are strongest together: the title, badge, body, modal, iframe, and API declarations are the human-friendly layer, while `jx_page_json` is the machine-readable layer that tools, tests, diagnostics, and the Island protocol can inspect.

## 2. What page JSON is for

`jx_page_json()` attaches the structured page contract. It should describe what the compiled page contains, what object IDs exist, what routes are active, and what the renderer should expect.

A good shape is:

```php
jx_page_json(json_encode([
    'page' => [
        'type' => 'page',
        'id' => 'sample-page',
        'attrs' => [
            'title' => 'JX Sample',
            'renderer' => 'win32-native',
            'webview' => false,
        ],
        'children' => [
            ['type' => 'text', 'id' => 'hero-title', 'text' => 'Hello'],
            ['type' => 'text', 'id' => 'body-copy', 'text' => 'Native body'],
        ],
    ],
    'api' => [
        ['path' => '/json', 'method' => 'GET', 'params' => []],
        ['path' => '/update', 'method' => 'GET', 'params' => ['title', 'badge', 'body']],
    ],
], JSON_PRETTY_PRINT));
```

The JSON should not fight the `jx_*` declarations. It should mirror them and add structure:

- `jx_page_title()` gives the renderer a direct title field.
- `jx_page_badge()` gives the renderer a direct badge field.
- `jx_page_body()` gives the renderer direct body text.
- `jx_page_json()` gives the compiler and Island API a structured state object.
- `jx_local_api()` declares which routes are intended to exist.

## 3. Ten samples

The examples live in:

```text
examples/jx_command_samples/
```

| # | File | Demonstrates |
|---:|---|---|
| 1 | `01_title_badge_body.php` | Minimal page title, badge, body, and attached JSON. |
| 2 | `02_modal_help.php` | Modal title and modal body layered over the base page. |
| 3 | `03_iframe_attachment.php` | Attached HTML payload through iframe declarations. |
| 4 | `04_page_json_state.php` | JSON as the explicit page object model. |
| 5 | `05_local_api_json.php` | Read-only `/json` route declaration. |
| 6 | `06_update_api.php` | Runtime page mutation through `/update`. |
| 7 | `07_modal_api.php` | Runtime modal mutation through `/modal`. |
| 8 | `08_iframe_api.php` | Runtime HTML attachment mutation through `/iframe`. |
| 9 | `09_dashboard_combined.php` | Full page + modal + iframe + JSON + all API routes. |
| 10 | `10_island_protocol_demo.php` | Island protocol route map and state contract. |

## 4. Sample 1: page title, badge, and body

```php
jx_page_title('Sample 01 - Native Header');
jx_page_badge('BASIC');
jx_page_body('This sample declares the main page title, the small badge, and the body copy.');
```

This creates the basic visible state for the native window. When JX emits C, those strings become compiled data in the generated source file.

## 5. Sample 2: modal title and body

```php
jx_modal_title('How this native modal works');
jx_modal_body('The modal title and body are declared in PHP.');
```

The modal is a second state layer. A native renderer can draw it above the base page. The Island API can later replace the modal fields if `/modal` is declared.

## 6. Sample 3: iframe title and HTML

```php
jx_iframe_title('Attached status card');
jx_iframe_html('<section class="card"><h2>Attached HTML</h2><p>Payload.</p></section>');
```

The iframe declaration is an attached rich-content payload. It is called iframe-like because it is a separately named panel of HTML content, not because the native executable must run a browser engine.

## 7. Sample 4: explicit JSON state

```php
$page = [
    'type' => 'page',
    'id' => 'sample-04-page',
    'children' => [
        ['type' => 'text', 'id' => 'hero-title', 'text' => 'JSON is the contract'],
    ],
];

jx_page_json(json_encode(['page' => $page], JSON_PRETTY_PRINT));
```

Use this when the renderer, tests, or Island clients need a stable page object model. Every object should have a stable `id` so future updates can target it.

## 8. Samples 5-8: local API routes

The local API declarations tell the emitted program which localhost routes should exist.

```php
jx_local_api('/json', []);
jx_local_api('/update', ['title', 'badge', 'body']);
jx_local_api('/modal', ['title', 'body']);
jx_local_api('/iframe', ['title', 'html']);
```

The default route meanings are:

| Route | Meaning |
|---|---|
| `/json` | Return the current page JSON/state. |
| `/update?title=...&badge=...&body=...` | Replace the base page title, badge, and body. |
| `/modal?title=...&body=...` | Replace the modal title and body. |
| `/iframe?title=...&html=...` | Replace the iframe/attachment title and HTML. |

## 9. Sample 9: combined dashboard

The combined sample uses every declaration family:

```php
jx_page_title($state['page']['title']);
jx_page_badge($state['page']['badge']);
jx_page_body($state['page']['body']);
jx_modal_title($state['modal']['title']);
jx_modal_body($state['modal']['body']);
jx_iframe_title($state['iframe']['title']);
jx_iframe_html($state['iframe']['html']);
jx_page_json(json_encode($state, JSON_PRETTY_PRINT));
jx_local_api('/json', []);
jx_local_api('/update', ['title', 'badge', 'body']);
jx_local_api('/modal', ['title', 'body']);
jx_local_api('/iframe', ['title', 'html']);
```

This is the preferred pattern for a complete native page: direct declarations for renderer convenience, JSON for structure, and local API declarations for runtime control.

## 10. Sample 10: Island protocol

The Island protocol treats the compiled native program as a local island:

```text
compiled executable
  owns page state
  owns native renderer
  exposes localhost API routes
  accepts small state-change commands
  redraws after mutation
```

A protocol entry in JSON can look like:

```json
{
  "island": {
    "name": "jx-native-island",
    "transport": "localhost-http",
    "model": "page-state-plus-command-routes",
    "routes": [
      {"method": "GET", "path": "/json", "returns": "current page JSON"},
      {"method": "GET", "path": "/update", "params": ["title", "badge", "body"]},
      {"method": "GET", "path": "/modal", "params": ["title", "body"]},
      {"method": "GET", "path": "/iframe", "params": ["title", "html"]}
    ]
  }
}
```

The API is deliberately small. It is meant for local testing, automation, and native state mutation, not for exposing a public network service.

## 11. Emit a sample to C

From the repo root:

```sh
mkdir -p build/jx-command-samples
./jx -o build/jx-command-samples/sample01.c examples/jx_command_samples/01_title_badge_body.php
```

The generated C file should now contain static state corresponding to the declarations in the PHP file.

## 12. Compile the emitted C

Windows/MSYS2 GCC:

```sh
gcc -O2 -Wall -Wextra -mwindows -I . \
  -o build/jx-command-samples/sample01.exe \
  build/jx-command-samples/sample01.c \
  -lws2_32 -lgdi32 -luser32
```

Linux/WSL cross compile:

```sh
x86_64-w64-mingw32-gcc -O2 -Wall -Wextra -mwindows -I . \
  -o build/jx-command-samples/sample01.exe \
  build/jx-command-samples/sample01.c \
  -lws2_32 -lgdi32 -luser32
```

The Windows libraries must come after the C file in the GCC command. If you see undefined references such as `__imp_socket` or `__imp_WSAStartup`, the Winsock library is missing or ordered incorrectly. Use `-lws2_32` after the generated C file.

## 13. Emit all ten samples

```sh
mkdir -p build/jx-command-samples
for sample in examples/jx_command_samples/[0-9][0-9]_*.php; do
  base=$(basename "$sample" .php)
  ./jx -o "build/jx-command-samples/${base}.c" "$sample"
done
```

Compile all generated files:

```sh
for cfile in build/jx-command-samples/*.c; do
  exe="${cfile%.c}.exe"
  x86_64-w64-mingw32-gcc -O2 -Wall -Wextra -mwindows -I . \
    -o "$exe" "$cfile" \
    -lws2_32 -lgdi32 -luser32
done
```

## 14. Island/API smoke tests

Launch a compiled sample that declares local API routes, then from another terminal:

```sh
curl http://127.0.0.1:8765/json
curl 'http://127.0.0.1:8765/update?title=Updated&badge=LIVE&body=Changed'
curl 'http://127.0.0.1:8765/modal?title=Help&body=Opened'
curl 'http://127.0.0.1:8765/iframe?title=Panel&html=%3Ch2%3EHi%3C%2Fh2%3E'
```

Use the runtime's printed port if it is not `8765`.

## 15. What to test

A sample is working when:

1. `./jx -o out.c sample.php` creates a C file.
2. The C file compiles with the Win32/GDI/Winsock libraries.
3. The executable opens a native window.
4. `/json` returns page state when declared.
5. `/update`, `/modal`, and `/iframe` mutate their respective state when declared.
6. The visible native window redraws after mutation.

## 16. Development rule

When adding new `jx_*` declarations later, keep this shape:

```text
PHP declaration -> captured page state -> page JSON mirror -> generated C field -> optional Island/API route -> renderer redraw
```

That keeps the system inspectable and makes the generated C output easier to test.
