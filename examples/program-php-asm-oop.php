<?php declare(strict_types=1);
/**
 * Example: whole program with
 *   - canonical blocks
 *   - OOP containers
 *   - ASM frame
 *   - arbitrary PHP stages
 *
 * Run from repo root:
 *   php examples/program-php-asm-oop.php
 */

require_once __DIR__ . '/../pasm-program.php';

use pasm\PASMProgram;

$prog = new PASMProgram();

// --- canonical algorithmic core ---
$prog->block('add-two', [
    ['ADD', 'R2', 'R0', 'R1'],
    ['RET', 'R2'],
]);

// --- OOP data ---
$v = $prog->vector([10, 20, 30]);
$v->add(40);

$s = $prog->stack();
$s->push(100);
$s->push(200);

// --- arbitrary PHP stage (runs as PHP, not compiled to bytecode) ---
$prog->php('prepare-arena', function (PASMProgram $p): void {
    $rt = $p->runtime();
    $base = $rt->alloc(16);
    $rt->store32($base + 0, 10);
    $rt->store32($base + 4, 20);
    $rt->store32($base + 8, 30);
    $rt->store32($base + 12, 40);
    \pasm\PASM::$ecx = $base;
    \pasm\PASM::$ah = 4;
    echo "[php] arena prepared at ecx={$base}\n";
});

$prog->php('report', function ($ctx): void {
    // works with PASMProgram or PASMProgramPackage
    echo "[php] report stage ran\n";
    if (method_exists($ctx, 'describe')) {
        echo $ctx->describe(), "\n";
    }
});

// --- ASM frame: free-form assembly ---
$prog->asm(<<<'ASM'
; sum four u32 values at ecx (count in ah)
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

// Run PHP setup before finalize
$prog->runPhp('prepare-arena');

// Optional: run ASM before finalize (uses prepared arena)
echo "ASM result (pre-finalize): ", $prog->runAsm(), "\n";

// Complete the program
$package = $prog->finalize();

echo "\n", $package->describe(), "\n\n";

// Canonical block
$package->frame->set('R0', 40);
$package->frame->set('R1', 2);
$r = $package->invoke('add-two');
echo "add-two => {$r['result']}\n";

// Re-run ASM from package (ah was consumed; re-prepare if needed)
$package->runPhp('prepare-arena');
echo "ASM result (package): ", $package->runAsm(), "\n";

$package->runPhp('report');
