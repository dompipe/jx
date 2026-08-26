# Plugin target policy (allow gate)

A plugin is **allowed** only if it compiles (or is verified portable) for **all four** targets:

| Target | Meaning |
|--------|--------|
| **windows** | PHP CLI / host on Windows |
| **mac** | PHP CLI / host on macOS |
| **linux** | PHP CLI / host on Linux |
| **web** | jx web/hosting path (server-side Book under the hosting module; browser surfaces via jx protocol) |

## Rules

1. `plugins/catalog.json` lists `required_targets: ["windows","mac","linux","web"]`.
2. Each plugin must declare the same four in `targets` (catalog and/or `plugin.json`).
3. `jx-install.php` runs **check-targets** before any install. Missing or failing a target → **install denied**.
4. Checks are portable PHP (no OS-specific extensions required for core plugins). Platform-specific code must be gated and still provide a working path on every target.
5. **web (jx)** means the plugin must not assume a TTY-only environment: no hard dependency on CLI-only APIs for its core `provides` list.

## Commands

```bash
php jx-install.php check-targets           # all catalog plugins
php jx-install.php check-targets decimals  # one plugin
php jx-install.php install decimals        # runs check-targets first
```
