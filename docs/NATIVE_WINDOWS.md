# Native Windows for JX Output

JX now has native OS window runner targets.

These are separate from the browser/open-file fallback. They are real OS-window programs that run a compiled JX artifact, capture its output, and draw that output into a platform window.

## Current window targets

```text
src/window/jx-window-x11.c      X11 native window runner
src/window/jx-window-win32.c    Win32 native window runner
```

## Build the native JX compiler

```bash
sh scripts/build-native-jx.sh
```

## Build an example PHP/CSS executable

```bash
mkdir -p build
./jx emit-c examples/page.php --asset examples/style.css -o build/page.c
cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/page build/page.c
```

## X11 native window

Install X11 development headers first if needed.

Debian/Ubuntu/WSL:

```bash
sudo apt-get install -y libx11-dev pkg-config
```

Build:

```bash
cc -O2 -std=c11 -Wall -Wextra -pedantic \
  -o build/jx-window-x11 \
  src/window/jx-window-x11.c \
  $(pkg-config --cflags --libs x11)
```

Run:

```bash
./build/jx-window-x11 ./build/page world
```

This opens a real X11 window titled `JX X11 Window`.

## Windows native window

Build with MinGW-w64 from Linux/WSL:

```bash
x86_64-w64-mingw32-gcc -O2 -Wall -Wextra \
  -o build/jx-window-win32.exe \
  src/window/jx-window-win32.c \
  -lgdi32 -luser32
```

Run on Windows:

```powershell
.\build\jx-window-win32.exe .\build\page.exe world
```

This opens a real Win32 window titled `JX Win32 Window`.

## Combined build helper

```bash
sh scripts/build-window-targets.sh
```

It builds whatever native window targets are supported by the tools installed on your machine.

## macOS / DMG direction

The macOS equivalent should be a Cocoa or WebKit app target:

```text
src/window/jx-window-macos.m
packaging/macos/JXWindow.app
packaging/macos/make-dmg.sh
```

A `.dmg` is not the window itself. The `.dmg` is the distributable disk image that contains a `.app`. The actual window should be implemented by the `.app`, then packaged into the `.dmg`.

## Current rendering status

The first native runners show the generated program output in a native window.

They do not yet render full HTML/CSS like a browser engine. For full CSS layout inside native windows, the next layer should use:

```text
Windows: WebView2
Linux: GTK WebKit or embedded browser view
macOS: WKWebView inside Cocoa
```

That is the next platform GUI layer after this native-window proof.
