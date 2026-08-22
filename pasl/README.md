# PASL — PHP-like → native binary / Windows EXE

**Write PHP-style code. Compile. Run like an `.exe`.**

## Package entry (unified)

```php
require 'pasl/pasl.php';   // core + strnet + pasl\Package

use pasl\Package;
echo Package::toC('$x=1;');                          // numeric
echo Package::toC('array $a=[1,2]; $x=$a[0];');      // auto full surface
echo Package::toX86('$x=1;');
Package::hasStrnet();  // true when strnet present
```

CLI uses the same facade (`pasl-run.php` → `Package::compile`).

## Types (complete)

| Type | Example | Path |
|------|---------|------|
| **int** | `$x = 1; $x++;` | all targets |
| **complex** | `complex $z = 3+4i;` | numeric core |
| **string** | `string $s = "hi"; $s = $s . "!";` | C / EXE |
| **array** | `array $a = [10,20,30]; $x = $a[0];` | C / EXE |
| **bag (object)** | `object $o = {}; $o.x = 1;` | C / EXE |
| **network** | `net_http_get("host", "/", 80)` | C / EXE |

## Quick start

```bash
php pasl/pasl-run.php --c -o app.c examples/arrays.pasl
gcc -O2 -o app app.c && ./app; echo $?

# Windows EXE
x86_64-w64-mingw32-gcc -O2 -o app.exe app.c
```

## Arrays

```pasl
array $a = [10, 20, 30];
$a[1] = 7;
$x = $a[0] + $a[1] + $a[2];  // 47
$n = count($a);               // 3
```

## Bags (non-classical)

```pasl
object $o = {};
$o.x = 10;
$o.y = 5;
$s = $o.x + $o.y;  // 15
```

## Benchmarks (toC via Package, 300 iters, PHP 8.3.6)

| Case | Bytes | µs | compiles/s |
|------|------:|---:|-----------:|
| num_tiny | 11 | 11.8 | ~85k |
| num_loop | 41 | 31.3 | ~32k |
| str_concat | 55 | 37.9 | ~26k |
| bag_fields | 44 | 39.0 | ~26k |
| arr_sum | 51 | 46.5 | ~22k |
| mixed | 82 | 58.0 | ~17k |

```bash
php pasl/bench/bench-all.php 500
```

## Docs

- [PASL_Programming_Guide.md](PASL_Programming_Guide.md)
- [PASL_Programming_Guide.pdf](PASL_Programming_Guide.pdf)
- [build-native.md](build-native.md)

## Layout

| File | Role |
|------|------|
| `pasl.php` | **Single require** — loads core + strnet + Package |
| `pasl-package.php` | `pasl\Package` auto-router |
| `pasl-front.php` / `pasl-back.php` | Numeric O(n) compiler |
| `pasl-strnet.php` | Full surface (strings/arrays/bags/net) |
| `pasl-run.php` | CLI via Package |

```
source → Package::toC → IR → C → gcc/mingw → ELF / .exe
```

Silent by default. Exit status = result.
