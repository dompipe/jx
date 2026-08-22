# PASL Complete Programming Guide

**Version 3.0** — PHP-like language → C → native binary / Windows EXE  
Integers · Complex · Strings · **Arrays** · Bags · Network

---

## 1. What PASL is

PASL is a restricted, PHP-like language compiled through a linear IR into portable **C** (→ ELF/EXE), x86-64 NASM, AArch64 GAS, or PASM text. Silent CLI. Result = **process exit status**.

### Two tiers

| Tier | Features | Flag |
|------|----------|------|
| Numeric core | int, complex, loops, if/select | `--c` / `--x86` / `--arm` / `--pasm` |
| Full surface | + strings, **arrays**, bags, network | `--c` (auto) |

---

## 2. First binary — every step

```bash
git clone https://github.com/dompipe/pasm-v2.git
cd pasm-v2
php pasl/pasl-run.php --c -o hello.c -c '$x=40; $x++; $x++;'
gcc -O2 -o hello hello.c
./hello; echo $?    # 42
```

### Windows EXE

```bash
x86_64-w64-mingw32-gcc -O2 -o hello.exe hello.c
# MSVC: cl /O2 hello.c /Fe:hello.exe
# Network MinGW: -lws2_32
```

---

## 3. All types

### Integers
```pasl
$x = 0; $x++; $x += 2; $y = $a * $b + $c;
```

### Complex
```pasl
complex $z = 3+4i; complex $p = $z * $w;
```

### Strings
```pasl
string $a = "hello"; $a = $a . " world"; $n = strlen($a);
$t = substr($a, 0, 5); if ($a == "hello world") { }
```

### Simple arrays
```pasl
array $a = [10, 20, 30];
$a[1] = 7;
$x = $a[0] + $a[1] + $a[2];  // 47
$n = count($a);               // 3
```

### Prototype bags (non-classical)
```pasl
object $o = {};
$o.x = 10; $o.y = 5;
$s = $o.x + $o.y;  // 15
```

### Network
```pasl
string $body = net_http_get("example.com", "/", 80);
$fd = net_connect("example.com", 80);
net_send($fd, "..."); string $resp = net_recv($fd, 4096); net_close($fd);
```

---

## 4. Control flow

`while`, `for`, `if`/`else`, `select`/`switch`, `break`, `continue`. Conditions: `==`, `!=`, nonzero.

---

## 5. Pipeline

```
source → scan O(n) → parse O(n) → IR → emit → C → gcc/mingw → EXE/ELF
```

---

## 6. CLI

```text
php pasl/pasl-run.php [--c|--x86|--arm|--pasm|--strnet] [--bin] [-o out] [-c src|file] [--print]
```

---

## 7. Benchmarks (toC, 500 iters, PHP 8.3.6)

| Case | Bytes | µs | compiles/s |
|------|------:|---:|-----------:|
| num_tiny | 11 | 11.7 | ~85k |
| num_loop | 41 | 30.6 | ~33k |
| str_concat | 55 | 37.4 | ~27k |
| bag_fields | 44 | 38.3 | ~26k |
| arr_sum | 51 | 46.0 | ~22k |
| mixed | 82 | 57.2 | ~17k |

```bash
php pasl/bench/bench-all.php 500
```

---

## 8. Limits

Not full PHP. Array elements int64. Bags have no methods. Strings/arrays/bags/network need **C path**. Exit status is 8-bit in shells.

---

## 9. Examples

`examples/arrays.pasl` · `examples/bags.pasl` · `examples/strings.pasl` · `examples/http_get.pasl`

*PASL Programming Guide v3 · github.com/dompipe/pasm-v2*
