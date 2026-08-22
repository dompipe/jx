# PASL — PHP-like language → native binary / Windows EXE

**Write a tiny PHP-style program. Compile it. Run it like an `.exe`.**

Includes **integers**, **complex**, **strings**, and **network** (C/EXE path).

## Synopsis

```bash
php pasl/pasl-run.php --c -o sum.c -c '$sum=0; $i=5; while($i){ $sum=$sum+$i; $i--; }'
gcc -O2 -o sum sum.c && ./sum; echo $?    # 15

# Strings
php pasl/pasl-run.php --c -o s.c examples/strings.pasl
gcc -O2 -o s s.c && ./s; echo $?          # 3

# Network (HTTP GET)
php pasl/pasl-run.php --c -o net.c examples/http_get.pasl
gcc -O2 -o net net.c && ./net; echo $?

# Windows EXE
x86_64-w64-mingw32-gcc -O2 -o app.exe app.c -lws2_32
```

## Strings & network

```pasl
string $a = "hello";
$a = $a . " world";
$n = strlen($a);
$t = substr($a, 0, 5);
if ($a == "hello world") { }

$fd = net_connect("example.com", 80);
net_send($fd, "GET / HTTP/1.0\r\nHost: example.com\r\n\r\n");
string $resp = net_recv($fd, 4096);
net_close($fd);

string $body = net_http_get("example.com", "/", 80);
```

| Feature | C / EXE | x86/ARM freestanding |
|---------|---------|----------------------|
| Integers, loops | yes | yes |
| **Strings** | **yes** | numeric only |
| **Network** | **yes** (sockets/Winsock) | no |

## Benchmarks (numeric core, PHP 8.3.6, 1000 iters)

| Program | Bytes | IR µs | x86 µs | ARM µs | PASM µs | x86 compiles/s |
|---------|------:|------:|-------:|-------:|--------:|---------------:|
| tiny | 11 | 9.1 | 12.0 | 12.6 | 11.6 | ~84 000 |
| loop | 48 | 26.5 | 32.8 | 37.1 | 32.5 | ~30 000 |
| nested | 66 | 57.2 | 52.9 | 48.8 | 43.4 | ~19 000 |
| long40 | 325 | 257.5 | 271.1 | 283.3 | 270.3 | ~3 700 |

O(n) scaling: ~0.8–1.0 µs per source byte. Re-run: `php pasl/bench/bench.php 1000`

## Targets

| Artifact | How |
|----------|-----|
| Linux/macOS binary | `--c` → gcc/clang |
| **Windows EXE** | `--c` → mingw or `cl` |
| x86-64 ELF | `--x86` + nasm + ld |
| AArch64 ELF | `--arm` + as + ld |
| PASM | `--pasm` |

See [build-native.md](build-native.md), [PASL_Manual.md](PASL_Manual.md), `pasl-strnet.php`.
