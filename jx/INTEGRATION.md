# jx realized construct

## Entry

```php
require_once __DIR__ . '/jx.php';  // from repo root: require 'jx.php';

use jx\Jx;
```

## What `jx.php` is

One file mass that implements:

- `Bag` — underwrite, sign, unsign, quotient, set→commit(ref) handshake, push, tell/pass
- `Task` extends `Bag` — named task identity
- `Page` — spawn + run around a Task
- `Book` — quota-isolated registry of bags/pages
- `Delivery` — deep extract/rebind
- `Complex` — first-class complex
- `SmartTable` — method catalogue + extrude(native|resistant)
- `Sym` — symbolic OS/asm constants
- `Jx` facade

PASM files in this repo remain the engine (bytecode, frames, master table). jx is the improved product surface renamed onto that trail.

## Run smoke

```bash
php examples/jx-smoke.php
```

## Rename

GitHub repo may still be `pasm-v2`; product name is **jx**. Rename the repo in Settings when ready.
