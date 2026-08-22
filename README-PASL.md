# PASL — PASM Language

PHP-like language → **PASM bytecode** or **x86-64 NASM**.

## Bytecode VM

```bash
php pasm-run.php -o out.pbc examples/pasl/arith.pasl
php pasm-run.php --print out.pbc
```

## Native x86-64 (Linux)

```bash
php pasm-run.php --x86 -o sum.s examples/pasl/x86-sum.pasl
nasm -f elf64 sum.s -o sum.o
ld sum.o -o sum
./sum; echo $?   # 15
```

Freestanding: prologue, GPRs, `cmp`/`jmp` loops, complex as register pairs.
**No printf/write** — only `sys_exit` with the result in `rdi`.

See `pasm-lang-x86.php` and sample `examples/pasl/x86-sum.s`.
