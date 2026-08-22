# PASL — PHP-like → native binary / Windows EXE

**Write PHP-style code. Compile. Run like an `.exe`.**

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

## Benchmarks (toC, 500 iters, PHP 8.3.6)

| Case | Bytes | µs | compiles/s |
|------|------:|---:|-----------:|
| num_tiny | 11 | 11.7 | ~85k |
| num_loop | 41 | 30.6 | ~33k |
| str_concat | 55 | 37.4 | ~27k |
| bag_fields | 44 | 38.3 | ~26k |
| **arr_sum** | 51 | 46.0 | ~22k |
| mixed | 82 | 57.2 | ~17k |

```bash
php pasl/bench/bench-all.php 500
```

## Docs

- **[PASL_Programming_Guide.pdf](PASL_Programming_Guide.pdf)** — full step-by-step manual
- [PASL_Programming_Guide.md](PASL_Programming_Guide.md) — same in Markdown
- [build-native.md](build-native.md) — Linux binary + Windows EXE

## Pipeline

```
source → IR (O(n)) → C → gcc/mingw → ELF / .exe
```

Silent by default. Exit status = result.
