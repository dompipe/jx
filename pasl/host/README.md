# JX host ABI

JX treats the browser, Win32, and X11 as operating-system window hosts around one Book/PASL runtime. A Book and its Binding are portable state. The host owns window creation, input, and presentation.

## Stable boundary

Every event crossing the boundary is a `jx.host/1` JSON drop:

```json
{
  "version": "jx.host/1",
  "type": "pasl.result",
  "host": "browser",
  "window": "cover-main",
  "book": "cover",
  "leaf": "home",
  "sequence": 1,
  "payload": { "result": "15" }
}
```

The host names are OS targets, not language targets. JX and PASL use the same protocol on every host.

## Browser

XIP compiles a leaf's `pasl` file to PASM assembly and embeds it as `application/jx-pasl`. `pasl/browser/pasl-vm.js` executes that program in the page. `jx-browser-host.js` reports results and events to `/jx/drop` using the same JSON envelope used by native hosts.

The JavaScript is host glue and a current PASM VM implementation; it is not the Book model. A later WASM VM can replace it without changing Books, Bindings, drops, or PASL programs.

## Native windows

`jx_host.h` is the C ABI available to compiler/runtime lowering:

```c
jx_host_window *jx_host_open(const jx_window_spec *spec);
int jx_host_poll(jx_host_window *window, jx_host_event *event);
int jx_host_set_title(jx_host_window *window, const char *title);
void jx_host_close(jx_host_window *window);
```

`jx_host_win32.c` maps it to the Win32 window/message APIs. `jx_host_x11.c` maps it to Xlib when compiled with `JX_HOST_X11`. These C functions are narrow compiler targets and may be called from generated assembly without exposing OS-specific calls to PASL code.

Example builds:

```bash
x86_64-w64-mingw32-gcc -c pasl/host/jx_host_win32.c
cc -DJX_HOST_X11 -c pasl/host/jx_host_x11.c $(pkg-config --cflags x11)
```

The next ABI additions should be drawing surfaces, controls, and normalized keyboard/pointer events. They should extend `jx.host/1` rather than leaking Win32 or X11 structures into a Book.
