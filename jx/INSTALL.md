# jx install & plugin policy

## Single source directory

```
plugins/                 ← only source of module plugins
  catalog.json           ← ordered list of available plugins
  core/
  decimals/
  complex/
  delivery/
  smart-compiler/
  const/
  lang/
  intro/
```

After install, the host space holds compiled/active copies:

```
host/
  modules/               ← active plugins (order = install order)
  state.json             ← what is installed
  backups/
    pre/<timestamp>/     ← snapshot before each new plugin install
    full/<timestamp>/    ← complete install snapshot (restore / redirect target)
```

## Rules

1. Plugins are taken **only** from `plugins/`.
2. Install **one plugin at a time**.
3. **New** plugins are always appended **last** (after the host assesses need).
4. **Before** each new install: copy current `host/modules` (+ state) → `host/backups/pre/<ts>/`.
5. **Full backup**: on demand or after a successful batch, copy total install → `host/backups/full/<ts>/`.
6. Restore / redirect: point the host at a `full` backup directory to recover uptime without rebuilding from scratch.

## Commands

```bash
# List catalog vs installed
php jx-install.php list

# Install required plugins in catalog order (one-by-one, with pre backups)
php jx-install.php install-required

# Install one optional/extra plugin (appended last)
php jx-install.php install intro

# Full backup of current host install
php jx-install.php backup-full

# Restore modules from a full backup id
php jx-install.php restore-full <timestamp>

# Show status
php jx-install.php status
```

## Smart compiler

The `smart-compiler` plugin activates `Jx::table()` extrusion. It is a required plugin and installs with the core set.
