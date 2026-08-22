# PASL Language Manual

**PASM Language — PHP-like Compiler for PASM Bytecode**  
Version 1.0 · 2026 · dompipe/pasm-v2

Silent by default · Optimized by default · Portable `.pbc` bytecode

## 1. Overview

PASL is a restricted, PHP-like language that compiles to the existing PASM binary ISA. It is **not** a full PHP compiler. Every construct has a precise lowering to registers, jumps, and memory ops the bytecode VM can execute.

Goals:

1. Feel like small PHP numeric scripts  
2. Emit optimizable bytecode  
3. Write portable `.pbc` files  
4. First-class complex numbers  
5. Stay silent unless asked to print  

| Is | Is not |
|----|--------|
| Integer & complex arithmetic | Full PHP objects/strings/arrays |
| while / for / if / select | foreach over iterators |
| Optimized bytecode + .pbc | Native x86/ARM binaries |
| Runs on PASM VM (PHP host) | OS executable without PHP |

## 2. Invocation

```bash
php pasm-run.php -o out.pbc examples/pasl/arith.pasl   # compile (silent)
php pasm-run.php out.pbc                           # run (silent)
php pasm-run.php --print out.pbc                   # print return value
php pasm-run.php --print -c '$x=1; $x++;'
php pasm-run.php -O0 --print -c '$x=2*3;'
```

## 3. Types

### Integer

Mapped to `ecx, ah, adx, bdx, cdx, ddx, edx, rdx`.

```pasl
$addedto = 0;
$addedto = $addedto + 1;
$addedto++;
$addedto += 1;
$addedto = $addedto * 2;
```

### Complex

Pair `(re, im)` in two registers. Literals: `3+4i`, `1-2i`, `i`, `-i`.

| Op | Expansion |
|----|-----------|
| z+w | re+re, im+im |
| z-w | re-re, im-im |
| z*w | (ac−bd)+(ad+bc)i |

```pasl
complex $z = 3+4i;
complex $w = 1-2i;
complex $p = $z * $w;   // 11+2i
```

## 4. Control flow

ZF-only: `==`, `!=`, nonzero. No `< > <= >=`.

```pasl
while ($i) { $sum = $sum + $i; $i--; }
for ($k = 0; $k != 4; $k++) { $j = $j + 1; }
if ($mode == 2) { ... } else { ... }
select ($mode) {
  case 1: ...
  case 2: ...
  default: ...
}
```

## 5. Optimizations (-O1 default)

- Drop `x=x`, `x+=0`, `x*=1`
- `y*2` → `ADD y,y`
- Constant folding
- Backend peephole (`PASMOptimizingAssembler`)

## 6. `.pbc` format

```
Magic PBC1 | ver | flags | len | entry | crc32 | code | symbols…
```

Portable VM image: any computer with PHP + PASM runtime.

## 7. Embed API

```php
use pasm\lang\Engine;
$eng = new Engine(optimize: true, verbose: false);
$eng->compileFile($src, 'app.pbc');
$result = $eng->runFile('app.pbc');
```

## 8. Limits

8 registers; no relational compares; no strings/arrays/objects; no complex÷; single entry; not native machine code.
