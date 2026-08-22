# PASM Program Builder

## Control flow → bytecode

Pass **restricted** statement source through `expr()` / `PASMExprCompiler` (not raw PHP files).

### Supported

| Construct | Lowers to |
|-----------|-----------|
| `while ($i) { ... }` | label + `CMP`/`JNZ`/`JMP` |
| `for ($k=0; $k != 3; $k++) { ... }` | init + head/step/end labels |
| `if (cond) { ... } else { ... }` | `JZ`/`JNZ` + `JMP` |
| `select ($x) { case 1: ...; default: ...; }` | sequential `CMP`/`JNZ` (also `switch`) |
| `break` / `continue` | `JMP` to end / step of innermost loop |
| assignments, `++`, `+=`, arithmetic, bitwise | as before |

### Conditions

Because the binary ISA only exposes a **zero flag** (`CMP` + `JZ`/`JNZ`):

- Fully supported: `==`, `!=`, and nonzero truthiness (`while ($i)`).
- **Not** supported on this ISA: `<`, `>`, `<=`, `>=` (no sign flag). Structure counting loops with `++`/`--` and `!=` / nonzero tests instead.

### Not supported

| Feature | Alternative |
|---------|-------------|
| `foreach` over arrays/objects | `for ($i=0; $i != n; $i++)` or OOP containers + ASM |
| `do`/`while` | rewrite as `while` |
| `goto` | labels in ASM frame |
| exceptions, `match`, generators | `php()` stage (stays PHP) |

### Example

```php
$prog->expr(<<<'SRC'
    $sum = 0;
    $i = 5;
    while ($i) {
        $sum = $sum + $i;
        $i--;
    }
    for ($k = 0; $k != 3; $k++) {
        $sum = $sum + 1;
    }
    select ($k) {
        case 0: $sum = $sum + 100;
        default: $sum = $sum + 1;
    }
SRC);

echo $prog->finalize()->runExpr();
```

```bash
php examples/control-flow-bytecode.php
php examples/expr-to-bytecode.php
```

### Unified bytecode

`finalize()` still merges: container prelude + `expr()` chunks + user `asm()` into `toBytecode()`.
