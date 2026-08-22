# PASM Program Builder

## Will `$addedto++` become bytecode automatically?

**No.** Ordinary PHP in a `.php` file still runs as PHP.

To lower integer assignments and operators into PASM bytecode, pass them through **`expr()`** (or `PASMExprCompiler`):

```php
$prog->expr(<<<'SRC'
    $addedto = 0;
    $addedto = $addedto + 1;
    $addedto++;
    $addedto += 1;
SRC);

$package = $prog->finalize();
echo $package->runExpr();        // 2
echo bin2hex($package->toBytecode()); // unified stream includes expr
```

### Operator → bytecode mapping

| Source | PASM |
|--------|------|
| `$x = 42` | `MOVI reg 42` |
| `$x = $y` | `MOVR x y` |
| `$x = $a + $b` | `ADD x a b` |
| `-` `*` `/` `%` | `SUB` `MUL` `DIV` `MOD` |
| `&` \| `^` `<<` `>>` | `AND` `OR` `XOR` `SHL` `SHR` |
| `$x += 1` | `ADD x x immReg` |
| `$x++` / `++$x` | `INC x` |
| `$x--` / `--$x` | `DEC x` |
| unary `-` | `NEG` |

Variables are allocated onto the 8 bytecode registers: `ecx, ah, adx, bdx, cdx, ddx, edx, rdx`.

### Not supported in `expr()`

- Strings, arrays, objects, method calls
- Control flow (`if`, `while`, `for`) — use ASM frame or canonical blocks
- More than 8 live variables

Use the **ASM frame** or **canonical blocks** for loops/branches; use **`php()`** for arbitrary PHP that must stay PHP.

## Unified bytecode

`finalize()` builds:

1. Container integer prelude → arena + `ecx`/`ah`/`adx`
2. All `expr()` chunks → ASM → bytecode
3. User `asm()` frame

```php
$bytes = $package->toBytecode();
$package->runUnified();
```

## Errors

`PASMProgramException` → `PASMAssembleException` | `PASMFinalizeException` | `PASMExecuteException`  
`PASMExprException` for bad expressions.

```bash
php examples/expr-to-bytecode.php
php examples/program-php-asm-oop.php
```
