# WSL native launcher

This builds a Linux ELF launcher for JX under WSL. The launcher is compiled C,
but it intentionally keeps the current PHP runtime as the foreground engine.
That gives WSL a native `jx` command now while leaving the future PASM/NASM
self-hosted executable path open.

Build from the repo root:

```sh
wsl -d Ubuntu -- sh tools/wsl/build-wsl.sh
```

Run:

```sh
wsl -d Ubuntu -- build/wsl/jx --print examples/hello.jx
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
- `jx xi ...` to `pasl/xi/xi.php`
- `jx book open ...` to the XI foreground server for a Book URL
