# JX Island Protocol & Native Page Emission Contract (v1)

## 1. Purpose

A compiled JX native page is a self-contained “island”:

- owns its page state
- owns its native renderer (currently Win32 GDI, later X11 / Cocoa / WebView)
- exposes a tiny localhost HTTP API for inspection and mutation
- redraws after any successful mutation

The authoring surface remains PHP-style declarations + optional structured JSON.  
The emitted C is the permanent artifact.  
The final executable is the island.

## 2. State model

Every native page carries exactly one live state object of this shape:

```json
{
  "page": {
    "type": "page",
    "id": "…",
    "attrs": { "title": "…", "renderer": "win32-native", "webview": false },
    "children": [ /* Page Object Model tree */ ]
  },
  "badge": "…",
  "body": "…",
  "modal": {
    "title": "…",
    "body": "…",
    "visible": false
  },
  "iframe": {
    "title": "…",
    "html": "…"
  },
  "api": [
    { "path": "/json",   "method": "GET", "params": [] },
    { "path": "/update", "method": "GET", "params": ["title","badge","body"] },
    { "path": "/modal",  "method": "GET", "params": ["title","body"] },
    { "path": "/iframe", "method": "GET", "params": ["title","html"] }
  ]
}
```

Rules:
- The `page` object **must** obey the Native Page Object Model (required `type` + unique `id` on every node).
- Convenience fields (`badge`, `body`, `modal`, `iframe`) are the fast path used by the current Win32 renderer and by the simple `jx_*` helpers.
- `jx_page_json()` is the authoritative structured source; the convenience fields must be kept in sync with it by the emitter or by the runtime.

## 3. Emission contract

When JX emits C for a native-page input it **must**:

1. Embed the initial state as static data (or a constructor that builds it once).
2. Emit a single global (or process-local) live state pointer that the Island routes and the renderer both use.
3. Emit the Win32 (or target) window creation + message loop.
4. Emit a minimal HTTP listener bound only to `127.0.0.1` (default port 8765, overridable).
5. Map each declared `jx_local_api` route to a handler that:
   - mutates the live state
   - returns the new state (or a success indicator)
   - posts a redraw request to the native window
6. Never expose the listener on any non-loopback interface.

The generated C must be compilable with the documented flags:

```bash
gcc -O2 -Wall -Wextra -mwindows -I . -o out.exe out.c -lws2_32 -lgdi32 -luser32
```

## 4. Island route semantics (normative)

| Route | Method | Required params | Effect | Response |
|---|---|---|---|---|
| `/json` | GET | none | none | current full state JSON |
| `/update` | GET | title, badge, body (all optional) | replace convenience fields + matching nodes in `page` | new state JSON |
| `/modal` | GET | title, body (optional) | update modal fields; set `visible=true` if either present | new state JSON |
| `/iframe` | GET | title, html (optional) | update iframe fields | new state JSON |

All routes are intentionally GET + query-string for easy curl testing.  
Future versions may add POST with JSON body; the GET surface remains the baseline.

## 5. Redraw contract

After any successful mutation the runtime **must**:

1. Update the live state.
2. Invalidate / post a paint message to the native window.
3. On paint, read only from the live state (never from the original static data).

Silent mutation without redraw is a bug.

## 6. Compatibility with current samples

The ten samples under `examples/jx_command_samples/` are the conformance suite for this contract.  
A new emitter or runtime is considered correct when all ten:

- emit clean C
- compile with the documented flags
- open a native window
- answer `/json`
- accept the mutation routes and visibly update

## 7. Relationship to first-wave runtime units

- `jx_value` / `jx_string` / `jx_array` become the in-memory representation of the live state.
- `jx_output` is used only for diagnostics / CLI fallback; the primary output path is the native window paint.
- Once the five core units exist, the Island listener and the Win32 paint path can be rewritten to stop depending on the PHP process bridge entirely for pure-declaration pages.

## 8. Immediate implementation order (updated)

1. First-wave runtime units (`jx_value` … `jx_frame`) — previous section.
2. **This contract** — Island state shape + emission + route semantics (current).
3. Emitter changes so pure-declaration samples no longer embed a PHP payload or spawn a PHP process.
4. Win32 paint path that reads only the live Island state.
5. Conformance: all ten samples pass the Island smoke tests without a PHP runtime present.
