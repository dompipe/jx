# PASL — PHP-like language → native binary / Windows EXE

**Write a tiny PHP-style program. Compile it. Run it like an `.exe`.**

PASL is an **O(n)** multi-target compiler for a restricted PHP-like language (integers, complex numbers, loops, conditionals). One source becomes:

| Artifact | Command | Runs on |
|----------|---------|--------|
| **Linux / macOS binary** | `pasl → C → gcc/clang` | Host OS |
| **Windows `.exe`** | `pasl → C → mingw` or `cl` | Windows x64 |
| **x86-64 freestanding ELF** | `--x86` + `nasm` + `ld` | Linux x86-64 |
| **AArch64 freestanding ELF** | `--arm` + `as` + `ld` | Linux ARM64 |
| **PASM bytecode assembly** | `--pasm` | PHP + PASM VM |

```bash
# PHP-like source → portable C → native binary (exit status = result)
php pasl/pasl-run.php --c -o sum.c -c '$sum=0; $i=5; while($i){ $sum=$sum+$i; $i--; }'
gcc -O2 -o sum sum.c && ./sum; echo $?    # 15

# Windows EXE (cross-compile or on Windows)
x86_64-w64-mingw32-gcc -O2 -o sum.exe sum.c
# or:  cl /O2 sum.c /Fe:sum.exe
# or:  build-windows.bat sum.c
```

---

## Synopsis: code in PHP-like syntax, run like an EXE

```pasl
$sum = 0;
$i = 100;
while ($i) {
    $sum = $sum + $i;
    $i--;
}
```

That is **not** interpreted by PHP at runtime. PASL lowers it through a linear IR to **C** (or x86/ARM/PASM). The C is compiled by your normal toolchain into a **standalone process**. No PHP needed on the target machine.

| You write | Tool chain | You ship |
|-----------|------------|----------|
| `.pasl` or `-c '...'` | `pasl-run.php --c` | `.c` |
| `.c` | `gcc` / `clang` / `mingw` / `cl` | binary or **`.exe`** |

Silent by default. Program result is the **process exit code**.

---

## Quick start

```bash
git clone https://github.com/dompipe/pasm-v2.git
cd pasm-v2

php pasl/pasl-run.php --c -o hello.c -c '$x=1; $x++;'
gcc -O2 -o hello hello.c && ./hello; echo $?

php pasl/pasl-run.php --c --bin -o /tmp/hello.c -c '$x=42;'
/tmp/hello; echo $?

php pasl/pasl-run.php --x86  -o out.s  -c '$x=1;'
php pasl/pasl-run.php --arm  -o out.s  -c '$x=1;'
php pasl/pasl-run.php --pasm -o out.asm -c '$x=1;'
```

**API**

```php
require 'pasl/pasl.php';
$c = new pasl\Compiler();
$c->toC($src);       // portable → EXE/binary
$c->toX86($src);     // NASM Linux
$c->toArm($src);     // GAS AArch64
$c->toPasmAsm($src); // PASM text
$c->toIr($src);      // IR
```

---

## Benchmarks (PHP 8.3.6, in-process, 1000 iters, warmed)

Software: `pasl/bench/bench.php` + [hyperfine](https://github.com/sharkdp/hyperfine).

### Compile time by program × target

| Program | Bytes | IR µs | x86 µs | ARM µs | PASM µs | x86 compiles/s |
|---------|------:|------:|-------:|-------:|--------:|---------------:|
| tiny (`$x=1; $x++;`) | 11 | 9.1 | 12.0 | 12.6 | 11.6 | ~84 000 |
| arith | 41 | 28.2 | 37.4 | 37.7 | 34.9 | ~27 000 |
| loop (while 100) | 48 | 26.5 | 32.8 | 37.1 | 32.5 | ~30 000 |
| nested loops | 66 | 57.2 | 52.9 | 48.8 | 43.4 | ~19 000 |
| ifelse | 49 | 28.6 | 37.4 | 51.4 | 44.7 | ~27 000 |
| mixed | 67 | 43.1 | 48.4 | 45.1 | 43.7 | ~21 000 |
| long40 (40 stmts) | 325 | 257.5 | 271.1 | 283.3 | 270.3 | ~3 700 |

### O(n) scaling (x86, synthetic)

| Stmts | Src bytes | µs / compile | µs / byte |
|------:|----------:|-------------:|----------:|
| 10 | 85 | 72 | 0.84 |
| 20 | 165 | 140 | 0.85 |
| 40 | 325 | 286 | 0.88 |
| 80 | 645 | 661 | 1.03 |
| 160 | 1285 | 1038 | 0.81 |

µs/byte stays ~**0.8–1.0** → **linear** in source size.

### Generated code size (bytes of text)

| Program | x86 `.s` | ARM `.s` | PASM `.asm` |
|---------|---------:|---------:|------------:|
| tiny | 490 | 659 | 98 |
| loop | 710 | 876 | 361 |
| nested | 903 | 1071 | 553 |
| long40 | 3241 | 3485 | 3600 |

### Hyperfine (full `php -r` process, 50 runs)

Includes PHP startup (~15 ms dominates):

| Target | Mean ± σ |
|--------|----------|
| PASM | 15.1 ± 4.5 ms |
| x86 | 16.2 ± 5.2 ms |
| ARM | 17.8 ± 6.4 ms |

```bash
php pasl/bench/bench.php 1000
```

---

## Language (restricted PHP-like)

- **Integers:** `=`, `++`/`--`, `+=`, `+ - * / % & | ^ << >>`
- **Complex:** `complex $z = 3+4i;` and `+ - *`
- **Control:** `while`, `for`, `if`/`else`, `select`/`switch`, `break`, `continue`
- **Conditions:** `==`, `!=`, nonzero truthiness

Not full PHP (no strings, objects, arrays, `foreach`, or relational `<`/`>`).

---

## Pipeline (all O(n))

```
source ──scan──► tokens ──parse──► IR ──emit──► C | x86 | ARM | PASM
```

---

## Docs & helpers

| File | Purpose |
|------|--------|
| [PASL_Manual.md](PASL_Manual.md) | Full language & backend manual |
| [build-native.md](build-native.md) | Linux binary + **Windows EXE** recipes |
| [build-windows.bat](build-windows.bat) | One-click EXE on Windows (cl or MinGW) |
| [bench/bench.php](bench/bench.php) | Benchmark harness |
| `sum.c` | Example portable C output |

---

## Why programmers use this

1. **Familiar syntax** — PHP-like assignments and loops.
2. **Real binaries** — ship `.exe` / ELF without a PHP runtime on the target.
3. **Predictable compile cost** — O(n); tens of thousands of small compiles/sec.
4. **Multi-ISA** — same IR → C, x86-64, AArch64, or PASM.
5. **Freestanding option** — raw syscalls when you do not want a C runtime.
6. **Silent tooling** — fits scripts and CI; result is the exit code.

Part of **[dompipe/pasm-v2](https://github.com/dompipe/pasm-v2)** (PASM bytecode VM + assemblers + OOP containers).
