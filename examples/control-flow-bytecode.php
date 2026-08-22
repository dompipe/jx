<?php declare(strict_types=1);
/**
 * while / for / if / select → PASM bytecode
 *
 *   php examples/control-flow-bytecode.php
 */

require_once __DIR__ . '/../pasm-program.php';

use pasm\{PASMProgram, PASMExprCompiler, PASMProgramException};

try {
    $src = <<<'SRC'
        $sum = 0;
        $i = 5;
        while ($i) {
            $sum = $sum + $i;
            $i--;
        }

        $j = 0;
        for ($k = 0; $k != 3; $k++) {
            $j = $j + 10;
        }

        $mode = 2;
        select ($mode) {
            case 1:
                $j = $j + 1;
            case 2:
                $j = $j + 2;
            default:
                $j = $j + 99;
        }
SRC;

    $c = new PASMExprCompiler();
    $asm = $c->compile($src);
    echo "Assembly:\n{$asm}\n\n";
    echo "Vars: ", json_encode($c->vars()), "\n\n";

    $prog = new PASMProgram();
    $prog->expr($src);
    $pkg = $prog->finalize();
    echo "result => ", $pkg->runExpr(), "\n";
    // while: sum 5+4+3+2+1 = 15; for: j=30; select case 2: j=32; RET last var

    echo "bytecode bytes: ", strlen($pkg->toBytecode()), "\n";

} catch (PASMProgramException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
