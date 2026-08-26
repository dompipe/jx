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
  modules/                 ← per-plugin links into shared package storage
  state.json
  links.json               ← Book/library link registry
  backups/pre/<ts>/
  backups/full/<ts>/
  backups/uninstall/<ts>/
```

Each plugin directory is a complete package with its own `plugin.json`, entry
file, runtime resolver, and root marker. Plugins share only the JX host
contract; they do not depend on another plugin package or its install order.

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
sudo php jx-install.php install-system   # Linux/macOS
php jx-install.php install-system        # Windows
php jx-install.php install-system --dry-run

php jx-install.php install-required
php jx-install.php install intro
php jx-install.php uninstall intro

# Select packages at Book or library scope
php jx-install.php link decimals book /path/to/book
php jx-install.php link delivery library /path/to/library
php jx-install.php unlink decimals book /path/to/book

php jx-install.php backup-full
php jx-install.php restore-full <timestamp>

php jx-install.php uninstall-system
php jx-install.php uninstall-system --keep-plugins
php jx-install.php uninstall-system --dry-run
```

## System locations

| Platform | Command directory | Shared active plugins |
|----------|-------------------|-----------------------|
| Linux | `/etc/bin` | `/etc/jx/plugins` |
| macOS | `/usr/local/bin` | `/usr/local/share/jx/plugins` |
| Windows | `%LOCALAPPDATA%\jx\bin` | `%ProgramData%\jx\plugins` |

The command directory is added to PATH. `host/modules/<plugin>` links each
installed package independently; the entire modules directory is never bound
as one opaque installation.

Book and library links use `<context>/.jx/plugins/<plugin>`, with selections
recorded in `<context>/.jx/plugins.json`. Packages contain no Book, library, or
sibling-package paths, so the same package can be linked into any context.

`uninstall <plugin>` takes a pre-uninstall backup and removes that plugin's
Book/library links. `uninstall-system` validates ownership, backs up the shared
store under `host/backups/uninstall/`, removes JX-owned launchers and PATH
entries, and restores local module mode. `--keep-plugins` retains the shared
package store.
