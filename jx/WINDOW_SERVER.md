# JX Window Server

JX should be a window server, not only a program runner or webserver.

The current implementation keeps XI as the Book rendering engine, but introduces
`jx-window-server.php` as the JX-owned control surface. That makes the layering
explicit:

- Windows, Linux, or WSL supplies the host OS.
- JX supplies the user-space desktop/window server contract.
- XI renders Book surfaces for now.
- Books behave like application sections inside the JX window world.

On Windows, the long direction is a self-made Windows-like environment running
on a normal Windows-installed desktop PC. Windows remains the installed OS with
drivers, filesystem, and process support. JX becomes a shell/window world above
it, closer in role to `explorer.exe`.

On Linux/WSL, the long direction is an X11-relevant display host: a JX process
that owns Book windows and can eventually route native X11/Wayland windows,
browser-hosted windows, and compiled PASL/JX application windows through one
drop/window protocol.

Current commands:

```sh
jx window-server start localhost:8766
jx window-server status localhost:8766
jx window-server stop localhost:8766
jx window-server open language localhost:8766
jx book open language localhost:8766
```

`jx book open` should become the friendly application-launch verb. It delegates
to the window server. The window server decides whether to start the Book host,
reuse an existing display, or route the Book to a native host.
