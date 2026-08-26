# Plugin target policy — hard reject

A plugin is **allowed** only if it is portable to **all four**:

| Target | Meaning |
|--------|--------|
| **windows** | PHP host on Windows |
| **mac** | PHP host on macOS |
| **linux** | PHP host on Linux |
| **web** | jx web/hosting path |

## Non-portable = not possible (this version)

If a plugin is not portable, it is **outside the requests of the current state of programming**. A later jx version might support it; **this one does not**. It is **not possible** to install or use it here.

Result: **HARD REJECT** — install aborted. No partial install.

## Multi-error log: `jxerr.log`

The checker does **not** stop at the first problem. It walks the plugin and **collects every** target/portability error, then:

1. Writes the full list to **`jxerr.log`** at the repo root (append, timestamped blocks)
2. Prints a **condensed** multi-error summary to stderr

Example block in `jxerr.log`:

```
==== jxerr 2026-08-25T20:44:00+00:00 [install:badplug] ====
1. Plugin 'badplug': missing required target 'mac'
2. Plugin 'badplug' [x.php]: dl() is not portable — outside this version; cannot use
3. Plugin 'badplug': HARD REJECT — non-portable or incomplete targets...
==== end (3 errors) ====
```

## Commands

```bash
php jx-install.php check-targets
php jx-install.php check-targets decimals
php jx-install.php install <id>    # hard reject + jxerr.log on failure
```
