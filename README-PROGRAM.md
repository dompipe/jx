# PASM Program Builder

`pasm-program.php` builds a whole program from the existing runtime layers and, at `finalize()`, produces a **unified bytecode** blob when possible.

## Unified bytecode

On `finalize()` / `compileUnified()`:

1. Each tracked container is flushed and bound into `P0`, `P1`, …
2. **Integer** values from linear containers are written into the memory arena.
3. An auto-generated **prelude** sets `ecx` (base), `ah` (count), `adx` (containerId).
4. Your **ASM frame** is appended (or a default sum-over-buffer body).
5. The combined assembly is compiled with `PASMOptimizingAssembler` → one binary string.

```php
$package = $prog->finalize();
$bytes   = $package->toBytecode();      // inclusive binary
$hex     = $package->toBytecodeHex();
$result  = $package->runUnified();
```

### Still outside the binary ISA

| Artifact | Why |
|----------|-----|
| Canonical blocks | Run on `PASMCanonicalExecutor` (command arrays), not the binary bytecode VM |
| PHP stages | Arbitrary PHP callables — executed as PHP |
| Non-integer container values | Skipped in the prelude (not representable in the integer LOAD32 path) |

## Error handling

```text
PASMProgramException          base
  PASMAssembleException       assembler / invalid ASM
  PASMFinalizeException       flush, alloc, package build
  PASMExecuteException        VM / block / PHP stage run
```

Messages are tagged `[PASMProgram:<phase>]` and may include JSON context.

```php
try {
    $package = $prog->finalize();
    echo $package->runUnified();
} catch (PASMAssembleException $e) {
    // bad assembly
} catch (PASMFinalizeException $e) {
    // materialize / package failure
} catch (PASMExecuteException $e) {
    // run-time failure
}
```

## Quick start

```php
require_once __DIR__ . '/pasm-program.php';
use pasm\PASMProgram;

$prog = new PASMProgram();
$v = $prog->vector([10, 20, 30]);
$v->add(40);

// optional user ASM; omit for default sum body
$prog->asm(<<<'ASM'
        MOVI  rdx  0
        MOVI  bdx  0
loop:   LOAD32 cdx ecx bdx
        ADD    rdx rdx cdx
        ADD    bdx bdx 4
        DEC    ah
        CMP    ah  0
        JNZ    loop
        RET    rdx
ASM);

$package = $prog->finalize();
echo $package->runUnified(); // 100
echo bin2hex($package->toBytecode());
```

```bash
php examples/program-php-asm-oop.php
```
