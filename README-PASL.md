# PASL — PASM Language

PHP-like language → **prepared JXL** by default, with PASM `.pbc` retained as a compatibility target and x86-64 NASM available as a separate native backend.

## Prepared JXL

PASL keeps its existing parser, register lowering, bounded loops, iterator lowering and loop fusion, then writes the resulting PASM semantics into fixed-width six-byte JXL cells.

```text
PASL source
  -> PASM semantic assembly
  -> loop / iterator optimization
  -> PASM-profile JXL (.jxl)
```

Every current PASM semantic opcode (`HALT` through `JGE`) has a canonical JXL representation. PASM opcodes `0x00..0x25` map into JXL `0x51..0x76`; `0x77` is the continuation cell used by full 64-bit `MOVI` values.

The normal embedded API now returns JXL:

```php
use pasm\lang\Engine;

$engine = new Engine(optimize: true, verbose: false);
$jxl = $engine->compile('$sum=0;$i=0;for($i=0;$i!=4;$i++){$sum+=$i;}$result=$sum;');
$result = $engine->runCode($jxl); // 6
```

For a file:

```php
$engine->compileFile($source, 'program.jxl');
$result = $engine->runFile('program.jxl');
```

## Legacy PBC

`.pbc` is still readable and can still be requested explicitly when compatibility with the older PASM bytecode container is required:

```php
$pbc = $engine->compilePbc($source);
$engine->compileFile($source, 'program.pbc');
```

The JX CLI follows the same rule: JXL is the prepared stream; only an explicit `.pbc` output asks for PBC.

## Native x86-64 (Linux)

The separate PASL→NASM path remains available:

```bash
php pasm-run.php --x86 -o sum.s examples/pasl/x86-sum.pasl
nasm -f elf64 sum.s -o sum.o
ld sum.o -o sum
./sum; echo $?
```

Freestanding output uses the PASM register model, comparisons/jumps and complex register pairs. See `pasm-lang-x86.php`.

## JXL and JXB

Do not use the old `.64B` suffix for new output:

- **`.jxl`** — prepared executable instruction stream.
- **`.jxb`** — compiled Book/container that may carry JXL plus metadata, bindings and native sections.
- **`.pbc`** — legacy PASM bytecode compatibility container.
- **`.64B`** — legacy JXB filename only; readers accept old files by package identity, but new writers use `.jxb`.