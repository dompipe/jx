# jx install & plugin policy

## Single source directory

```
plugins/                 ← only source of module plugins
  catalog.json
  TARGETS.md             ← windows / mac / linux / web allow gate
  core/ decimals/ complex/ delivery/ smart-compiler/ const/ lang/ intro/
```

```
host/
  modules/
  state.json
  backups/pre/<ts>/
  backups/full/<ts>/
```

## Allow gate (mandatory)

A plugin is **allowed to install only if** it declares and passes compile/verify for:

- **windows**
- **mac**
- **linux**
- **web** (jx hosting / server–browser path)

```bash
php jx-install.php check-targets
php jx-install.php check-targets decimals
```

`install` and `install-required` run this gate first. Failure → install denied.

Details: `plugins/TARGETS.md`.

## Other rules

1. Plugins only from `plugins/`.
2. Install one at a time; new plugins append last.
3. Pre-backup before each install; full backup of total install for restore/redirect.

```bash
php jx-install.php install-required
php jx-install.php install intro
php jx-install.php backup-full
php jx-install.php restore-full <timestamp>
```
