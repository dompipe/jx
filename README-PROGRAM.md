# PASM Program Builder

`pasm-program.php` adds a whole-program layer on top of the existing runtime.

## What it is

| Piece | Role |
|-------|------|
| **Canonical blocks** | Immutable instruction arrays (code identity) |
| **OOP containers** | Vector, Stack, Queue, Deque, Map, Set — hot PHP until `finalize()` |
| **ASM frame** | Free-form PASM assembly → binary bytecode via the existing assembler |
| **PHP frame** | Arbitrary PHP callables — **run as PHP**, not compiled to bytecode |
| **Kernels** | Named extra assembly routines |

## What it is not

There is **no** general PHP-to-bytecode compiler. Arbitrary PHP is registered with `php($name, callable)` and executed with `runPhp($name)`. Only the ASM frame and named kernels become binary bytecode.

## Quick start

```php
require_once __DIR__ . '/pasm-program.php';
use pasm\PASMProgram;

$prog = new PASMProgram();

$prog->block('add-two', [
    ['ADD', 'R2', 'R0', 'R1'],
    ['RET', 'R2'],
]);

$v = $prog->vector([1, 2, 3]);
$v->add(4);

$prog->php('setup', function (PASMProgram $p) {
    // arbitrary PHP: prepare memory, call APIs, mutate containers, etc.
});

$prog->asm(<<<'ASM'
        MOVI ecx 40
        MOVI ah  2
        ADD  adx ecx ah
        RET  adx
ASM);

$prog->runPhp('setup');
echo $prog->runAsm();           // 42

$package = $prog->finalize();   // flush containers, compile ASM/kernels
$package->runAsm();
$package->runPhp('setup');
```

## Example

```bash
php examples/program-php-asm-oop.php
```

## Finalize boundary

`finalize()` materializes every tracked container (`flush` + `loadRegister` into `P0`…), compiles the ASM frame and kernels to bytecode, and returns a `PASMProgramPackage` that can:

- `invoke($block)` — run a canonical block
- `runAsm()` — run main bytecode
- `runKernel($name)` — run a named kernel
- `runPhp($name)` — run a PHP stage
- `describe()` — human summary
