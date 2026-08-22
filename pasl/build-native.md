# Building native binaries and Windows EXEs from PASL

PASL emits **portable C** (default) so one source can become a Linux binary, macOS binary, or **Windows `.exe`**.

## Fast path: C → binary / EXE

```bash
php pasl/pasl-run.php --c -o sum.c file.pasl

# Linux / macOS
gcc -O2 -o sum sum.c && ./sum; echo $?

# Windows EXE — MSVC
cl /O2 sum.c /Fe:sum.exe

# Windows EXE — MinGW / MSYS2
gcc -O2 -o sum.exe sum.c

# Windows EXE — cross from Linux
x86_64-w64-mingw32-gcc -O2 -o sum.exe sum.c
```

## One-shot host binary

```bash
php pasl/pasl-run.php --c --bin -o /tmp/sum.c -c '$sum=0; $i=5; while($i){ $sum=$sum+$i; $i--; }'
/tmp/sum; echo $?   # 15
```

## Portability

| Artifact | Runs on |
|----------|--------|
| `.c` + host gcc/clang | Same OS/arch |
| `.c` + mingw/cl → `.exe` | Windows x64 |
| static Linux ELF | Many Linux x86-64 hosts |
| `--x86` / `--arm` | Linux freestanding |
| PASM / `.pbc` | Any machine with PHP + PASM VM |

Recommended for shipping: **emit C**, compile on the target OS (or cross-compile with mingw for Windows).
