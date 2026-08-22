# PASL Language & Compiler Manual

**Version 2.0 — O(n) multi-target compiler**  
Targets: **x86-64**, **AArch64 (ARM64)**, **PASM bytecode assembly**

## Pipeline

```
source  --scan O(n)--> tokens
tokens  --parse O(n)--> IR
IR      --emit  O(m)--> x86-64 NASM | AArch64 GAS | PASM assembly
```

## CLI

```bash
php pasl/pasl-run.php --x86 -o sum.s  file.pasl
nasm -f elf64 sum.s -o sum.o && ld sum.o -o sum && ./sum; echo $?

php pasl/pasl-run.php --arm -o sum.s  file.pasl
as -o sum.o sum.s && ld -o sum sum.o && ./sum; echo $?

php pasl/pasl-run.php --pasm -o sum.asm file.pasl
php pasl/pasl-run.php --print --arm -c '$x=1; $x++;'
```

Silent on success unless `--print`.

## Language

- Integers: `=`, `++`, `+=`, `+ - * / % & | ^ << >>`
- Complex: `complex $z = 3+4i;` and `+ - *` on complex vars
- Control: `while`, `for`, `if/else`, `select`/`switch`, `break`, `continue`
- Conditions: `==`, `!=`, nonzero only

## Backends

| Target | Tooling | Exit |
|--------|---------|------|
| x86-64 | nasm + ld | sys_exit 60, rdi |
| AArch64 | as + ld | svc #0, x8=93, x0 |
| PASM | PASM assembler/VM | RET reg |

Native backends are **freestanding** (no printf/write).

## API

```php
$c = new pasl\Compiler();
$c->toIr($src);
$c->toX86($src);
$c->toArm($src);
$c->toPasmAsm($src);
```

## Complexity

Scan O(n) · Parse O(n) · Emit O(m)=O(n) · Runtime loops O(iterations)

## Files

`pasl-front.php` (IR/scan/parse) · `pasl-back.php` (x86/ARM/PASM backends) · `pasl-run.php` · `PASL_Manual.pdf`
