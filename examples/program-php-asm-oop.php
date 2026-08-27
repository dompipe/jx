<?php declare(strict_types=1);
/**
 * Example: containers + ASM lowered into one unified bytecode blob.
 *
 *   php examples/program-php-asm-oop.php
 */

require_once __DIR__ . '/../pasm-program.php';

use pasm\{
    PASMProgram,
    PASMProgramException,
    PASMAssembleException,
    PASMExecuteException,
    PASMFinalizeException,
};

try {
    $prog = new PASMProgram();

    $prog->block('add-two', [
        ['ADD', 'R2', 'R0', 'R1'],
        ['RET', 'R2'],
    ]);

    $v = $prog->vector([10, 20, 30]);
    $v->add(40);

    $s = $prog->stack();
    $s->push(100);
    $s->push(200);

    // Sum the first container's integer prelude. PASM ALU/CMP operations are
    // register-only, so constants are loaded once into registers before loop.
    $prog->asm(<<<'ASM'
; ecx = prelude base, ah = count
        MOVI  rdx  0
        MOVI  bdx  0
        MOVI  edx  0
        MOVI  ddx  4
        CMP   ah   edx
        JZ    done
loop:
        LOAD32 cdx ecx bdx
        ADD    rdx rdx cdx
        ADD    bdx bdx ddx
        DEC    ah
        CMP    ah  edx
        JNZ    loop
done:
        RET    rdx
ASM);

    $prog->php('report', function ($pkg): void {
        echo "[php] unified bytes = ", strlen($pkg->toBytecode()), "\n";
    });

    $package = $prog->finalize();

    echo $package->describe(), "\n\n";

    echo "Unified bytecode result: ", $package->runUnified(), "\n";
    // 10+20+30+40 = 100 (first linear container)

    $package->frame->set('R0', 40);
    $package->frame->set('R1', 2);
    $r = $package->invoke('add-two');
    echo "add-two => {$r['result']}\n";

    $package->runPhp('report');

} catch (PASMAssembleException $e) {
    fwrite(STDERR, "ASSEMBLE ERROR: {$e->getMessage()}\n");
    exit(1);
} catch (PASMFinalizeException $e) {
    fwrite(STDERR, "FINALIZE ERROR: {$e->getMessage()}\n");
    exit(1);
} catch (PASMExecuteException $e) {
    fwrite(STDERR, "EXECUTE ERROR: {$e->getMessage()}\n");
    exit(1);
} catch (PASMProgramException $e) {
    fwrite(STDERR, "PROGRAM ERROR: {$e->getMessage()}\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: {$e->getMessage()}\n");
    exit(1);
}
