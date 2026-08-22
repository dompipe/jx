<?php declare(strict_types=1);
/**
 * PHP-like integer ops → PASM bytecode
 *
 *   php examples/expr-to-bytecode.php
 */

require_once __DIR__ . '/../pasm-program.php';

use pasm\{PASMProgram, PASMExprCompiler, PASMProgramException};

try {
    // --- standalone expr compiler ---
    $c = new PASMExprCompiler();
    $src = <<<'SRC'
        $addedto = 0;
        $addedto = $addedto + 1;
        $addedto++;
        $addedto += 1;
        $addedto = $addedto * 2;
SRC;
    $asm = $c->compile($src);
    $bc  = $c->compileToBytecode($src);

    echo "Var map: ", json_encode($c->vars()), "\n\n";
    echo "Assembly:\n{$asm}\n\n";
    echo "Bytecode hex: ", bin2hex($bc), "\n\n";

    // --- via program builder ---
    $prog = new PASMProgram();
    $prog->expr($src);
    $package = $prog->finalize();

    echo $package->describe(), "\n\n";
    echo "runExpr() => ", $package->runExpr(), "\n";
    // 0+1 → 1; ++ → 2; +=1 → 3; *2 → 6

} catch (PASMProgramException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
