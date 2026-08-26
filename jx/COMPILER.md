# jx executable compiler

## Commands

```bash
# Interpret .jx (Bags, Tasks, Delivery, …)
php jx-run.php --print examples/hello.jx

# PASL arithmetic → bytecode VM (same stack as pasm-run.php)
php jx-run.php --print examples/arith.pasl
php jx-run.php -o out.pbc examples/arith.pasl
php jx-run.php --print out.pbc

# Inline
php jx-run.php --print -c 'bag = Bag.underwrite(64); ref = bag.sign("a"); bag.set(1).commit(ref);'
php jx-run.php --print -c '$x = 1 + 2 * 3;'
```

## Pipeline

1. **jx-run.php** — CLI entry (executable compiler driver)
2. **JxEngine** (`jx-lang.php`) — parses jx statements; executes Bag/Task/Book/Delivery on `jx.php`
3. **PASL Engine** (`pasm-lang-engine.php`) — pure arithmetic / complex / control flow → assembler → bytecode VM
4. **SmartTable** — records extrusion mode (native vs Resistant) for known methods

`.jx` programs that use bags are **interpreted** under the memory law.  
Pure PASL fragments are **compiled** to bytecode and run on the PASM VM — the same executable compiler path as `pasm-run.php`.

## Relation to pasm-run.php

`pasm-run.php` remains the PASL-only runner.  
`jx-run.php` is the jx product entry: it understands `.jx` and delegates lowerable code to the same compiler/VM.
