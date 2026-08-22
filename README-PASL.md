# PASL — PASM Language

PHP-like restricted language → PASM bytecode **or** native **x86-64 NASM**.

## Bytecode (VM)

```bash
php pasm-run.php -o out.pbc examples/pasl/arith.pasl
php pasm-run.php --print out.pbc
```

## x86-64 assembly (real machine code path)

```bash
# Emit NASM (Linux SysV, freestanding — no printf)
php pasm-run.php --x86 -o sum.s examples/pasl/x86-sum.pasl

# Assemble & link
nasm -f elf64 sum.s -o sum.o
ld sum.o -o sum
./sum; echo $?    # exit status = computed result (e.g. 15)
```

No write/print syscalls — only `sys_exit` with the result in `rdi`.

Complex numbers, while/for/if/select lower to real `mov`/`add`/`imul`/`cmp`/`jmp`.

See `PASL_Language_Manual.md` and `pasm-lang-x86.php`.
