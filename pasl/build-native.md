# Building native binaries and Windows EXEs from PASL

PASL emits **portable C** (default) so one source can become a Linux binary, macOS binary, or **Windows `.exe`**.

## Fast path

```bash
php pasl/pasl-run.php --c -o sum.c file.pasl
gcc -O2 -o sum sum.c && ./sum; echo $?

# Windows EXE
x86_64-w64-mingw32-gcc -O2 -o sum.exe sum.c
cl /O2 sum.c /Fe:sum.exe
build-windows.bat sum.c
```

## One-shot host binary

```bash
php pasl/pasl-run.php --c --bin -o /tmp/sum.c -c '$sum=0; $i=5; while($i){ $sum=$sum+$i; $i--; }'
/tmp/sum; echo $?   # 15
```

| Artifact | Runs on |
|----------|--------|
| `.c` + host gcc/clang | Same OS/arch |
| `.c` + mingw/cl → `.exe` | Windows x64 |
| static Linux ELF | Many Linux x86-64 hosts |
| `--x86` / `--arm` | Linux freestanding |
| PASM | PHP + PASM VM |
