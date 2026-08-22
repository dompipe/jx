# PASL O(n) Compiler

Single-pass pipeline (each stage linear in input size):

```
source  --scan O(n)--> tokens
tokens  --parse O(n)--> IR ops
IR      --emit  O(m)--> x86-64 NASM | PASM assembly   (m = |IR| ≤ O(n))
```

No O(n²) global optimization passes. Register allocation is greedy first-fit over a fixed bank.

## CLI

```bash
# Native x86-64 (Linux SysV, freestanding — no printf)
php pasl/pasl-run.php --x86 -o sum.s examples/pasl/x86-sum.pasl
nasm -f elf64 sum.s -o sum.o && ld sum.o -o sum && ./sum; echo $?

# PASM assembly text
php pasl/pasl-run.php --pasm -o sum.asm examples/pasl/x86-sum.pasl
```

Silent by default; use `--print` to write assembly to stdout.

## Complexity

| Stage | Bound |
|-------|--------|
| Comment strip + scan | O(n) |
| Parse → IR | O(n) |
| Backend emit | O(m) = O(n) |
| Generated loops | O(iterations) at run time |

## API

```php
require 'pasl/pasl.php';
$c = new pasl\Compiler();
$ir  = $c->toIr($src);
$asm = $c->toX86($src);      // NASM
$pasm = $c->toPasmAsm($src); // PASM bytecode assembly
```
