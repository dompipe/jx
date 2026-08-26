# Introduction to jx (jinx)

**jx** is a PHP-derived server-side language and runtime built on the PASM engine.

## What you get after install

| Piece | Purpose |
|-------|---------|
| **Bag** | Only mutable memory; underwritten capacity; writes via sign + handshake |
| **Task / Page / Book** | Execution and packaging (X11-like pages) |
| **Decimal** | Fixed-scale decimal arithmetic |
| **Complex** | `3+4i` style complex numbers |
| **Delivery** | Deep path `a.b.c` extract/rebind |
| **const** | Immutable bindings |
| **Smart compiler** | Method table → native or Resistant code |
| **jx-run.php** | Executable compiler / interpreter |

## Memory law

No free writes. Only:

1. buffer of allowance  
2. underwritten bag  
3. event handshake (`set` → `commit(ref)`)

`quotient()` reports remaining capacity so overflows fail closed.

## First program

```jx
bag = Bag.underwrite(256);
ref = bag.sign("msg");
bag.set("hello").commit(ref);
```

```bash
php jx-run.php --print examples/hello.jx
```

## Plugins

All modules live under **`plugins/`** (one source directory).  
The host installs them **one at a time**. New plugins are added **last**, after you assess need.  
Each install is preceded by a **pre-install backup**; a **full backup** of the total install can be restored or redirected for uptime-friendly admin.

See `jx-install.php` and `jx/INSTALL.md`.
