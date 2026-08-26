# Windows native launcher

This builds `jx.exe`, a native Windows launcher for the current JX runtime.
The launcher is compiled C, while the foreground runtime remains PHP until the
PASM/NASM self-hosted executable is ready.

Build from the repo root:

```powershell
tools\windows\build-windows.ps1
```

or:

```bat
tools\windows\build-windows.bat
```

The build script accepts any of these compilers on `PATH`:

- Visual Studio Build Tools: `cl.exe`
- MinGW-w64: `gcc.exe`
- LLVM: `clang.exe`
- .NET SDK / Framework: `csc.exe`

Run:

```powershell
build\windows\jx.exe --print examples\hello.jx
build\windows\jx.exe xi localhost:8766 status
build\windows\jx.exe book open language localhost:8766
```

Install into the Windows PATH when ready:

```powershell
php jx-install.php install-system
Copy-Item build\windows\jx.exe "$env:LOCALAPPDATA\jx\bin\jx.exe" -Force
```

The launcher resolves the repo from `JX_ROOT`, the compiled-in build root, or a
relative install beside the repo. It dispatches:

- ordinary arguments to `jx-run.php`
- `jx.exe xi ...` to `pasl\xi\xi.php`
- `jx.exe book open ...` to the XI foreground server for a Book URL
