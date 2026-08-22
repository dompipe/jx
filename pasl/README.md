# PASL — O(n) multi-target compiler

**Targets:** x86-64 · **AArch64 (ARM64)** · PASM bytecode assembly

```bash
php pasl/pasl-run.php --x86 -o out.s file.pasl   # NASM
php pasl/pasl-run.php --arm -o out.s file.pasl   # GAS AArch64
php pasl/pasl-run.php --pasm -o out.asm file.pasl
```

Full documentation: [PASL_Manual.md](PASL_Manual.md) · [PASL_Manual.pdf](PASL_Manual.pdf)

```php
$c = new pasl\Compiler();
echo $c->toArm('$sum=0; $i=5; while($i){ $sum=$sum+$i; $i--; }');
```
