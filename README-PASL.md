# PASL — PASM Language

PHP-like restricted language → optimized PASM bytecode → portable `.pbc` files.

## Quick start

```bash
php pasm-run.php -o out.pbc examples/pasl/arith.pasl
php pasm-run.php --print out.pbc
```

Silent by default. Use `--print` for the return value.

## Features

- Integers and **complex numbers** (`3+4i`)
- `while` / `for` / `if` / `select`
- Optimizations (`-O1` default)
- Manuals: `PASL_Language_Manual.md` / `PASL_Language_Manual.pdf`

See the manuals for operator maps, `.pbc` layout, and limits.
