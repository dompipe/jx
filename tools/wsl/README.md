# WSL native launcher

This builds a Linux ELF launcher for JX under WSL. The launcher is compiled C,
but it intentionally keeps the current PHP runtime as the execution engine.
For Books, WSL behaves like a window host: `jx book open` delegates to the JX
window server, which starts XI in the background when needed and opens the Book
URL through `wslview`, `xdg-open`, or the Windows PowerShell browser bridge.

Build from the repo root:

```sh
wsl -d Ubuntu -- sh tools/wsl/build-wsl.sh
```

Run:

```sh
wsl -d Ubuntu -- build/wsl/jx --print examples/hello.jx
wsl -d Ubuntu -- build/wsl/jx window-server status localhost:8766
wsl -d Ubuntu -- build/wsl/jx xi localhost:8766 status
wsl -d Ubuntu -- build/wsl/jx book open language localhost:8766
```

Install into the WSL PATH when ready:

```sh
wsl -d Ubuntu -- sudo install -m 0755 build/wsl/jx /etc/bin/jx
```

The launcher resolves the repo from `JX_ROOT`, the compiled-in build root, or a
relative install beside the repo. It dispatches:

- ordinary arguments to `jx-run.php`
- `jx window-server ...` to `jx-window-server.php`
- `jx xi ...` to `pasl/xi/xi.php`
- `jx book open ...` to a background XI server plus a WSL/Windows window opener
