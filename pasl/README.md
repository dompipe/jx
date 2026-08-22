# PASL — O(n) multi-target compiler

**Targets:** portable **C** (→ Linux binary / **Windows EXE**) · x86-64 · AArch64 · PASM

## Runnable binaries & Windows EXE

```bash
php pasl/pasl-run.php --c -o sum.c file.pasl
gcc -O2 -o sum sum.c && ./sum; echo $?

# Windows EXE
x86_64-w64-mingw32-gcc -O2 -o sum.exe sum.c
# or on Windows: build-windows.bat sum.c
```

See [build-native.md](build-native.md).
