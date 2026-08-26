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
build\windows\jx.exe window-server status localhost:8766
build\windows\jx.exe xi localhost:8766 status
build\windows\jx.exe book open language localhost:8766
build\windows\jx.exe window-server open language localhost:8766 --browser
```

## Compiled spec contract

`jx-spec-contract.exe` is a small compiled Windows executable generated from
the current JX language/control specs. It does not require PHP to print its
contract; it proves the Book/Page/Bag host ontology, Control/Image family
taxonomy, image pins, paint points, `IMG_DOTTED`, and `IMG_BLUR` can be lowered
into executable code.

Build and verify:

```powershell
tools\windows\build-spec-contract.ps1
build\windows\jx-spec-contract.exe --smoke
build\windows\jx-spec-contract.exe --contract
```

## Themed executable window

`jx-themed-window.exe` is a native Windows Forms demo for the current Control,
Image, and Theme contracts. It draws a moving image-like dial, mashes
`spinClicks` with `zoom`, and shows `IMG_BLUR` / `IMG_DOTTED` style trails.

Build and run:

```powershell
tools\windows\build-themed-window.ps1
build\windows\jx-themed-window.exe
```

Controls:

- `Space`: toggle the mash motion speed
- `B`: blur trail
- `D`: dotted trail
- `Esc`: close

Install into the Windows PATH when ready:

```powershell
php jx-install.php install-system
Copy-Item build\windows\jx.exe "$env:LOCALAPPDATA\jx\bin\jx.exe" -Force
```

The launcher resolves the repo from `JX_ROOT`, the compiled-in build root, or a
relative install beside the repo. It dispatches:

- ordinary arguments to `jx-run.php`
- `jx.exe window-server ...` to `jx-window-server.php`
- `jx.exe xi ...` to `pasl\xi\xi.php`
- `jx.exe book open ...` to the native JX Book window host
