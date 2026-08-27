<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-runtime.php';
require_once __DIR__ . '/pasm-bytecode.php';
require_once __DIR__ . '/pasm-optimizer.php';
require_once __DIR__ . '/pasm-lang-core.php';
require_once __DIR__ . '/pasm-lang-compiler.php';

use pasm\lang\Compiler;
use pasm\PASMRuntime;
use pasm\PASMOptimizedBytecodeVM;

$n = max(1, (int)($argv[1] ?? 10000));
$reps = max(3, (int)($argv[2] ?? 9));

function median(array $xs): float {
    sort($xs, SORT_NUMERIC);
    $c = count($xs); $m = intdiv($c, 2);
    return $c % 2 ? (float)$xs[$m] : ((float)$xs[$m-1] + (float)$xs[$m]) / 2.0;
}
function benchCompileLegacy(string $src, int $reps): array {
    $times=[]; $asm=''; $bc='';
    for ($i=0; $i<$reps; $i++) {
        $c = new Compiler(true, false);
        $t=hrtime(true); $asm=$c->compile($src); $times[]=(hrtime(true)-$t)/1e6;
    }
    $c = new Compiler(true, false); $bc=$c->compileToBytecode($src);
    return ['compile_ms'=>median($times),'asm_bytes'=>strlen($asm),'bytecode_bytes'=>strlen($bc),'bytecode'=>$bc];
}
function benchRunLegacy(string $bc, int $reps): float {
    $times=[];
    for ($i=0; $i<$reps; $i++) {
        $rt = new PASMRuntime(); $vm = new PASMOptimizedBytecodeVM($rt);
        $t=hrtime(true); $vm->run($bc); $times[]=(hrtime(true)-$t)/1e6;
    }
    return median($times);
}

$cases = [
    'for' => '$i=0; $sum=0; for($i=0; $i!='.$n.'; $i++){ $sum += $i; }',
    'while' => '$i=0; $sum=0; while($i!='.$n.'){ $sum += $i; $i++; }',
];
$out=['compiler'=>'legacy-inline','n'=>$n,'reps'=>$reps,'cases'=>[]];
foreach ($cases as $name=>$src) {
    $b=benchCompileLegacy($src,$reps); $run=benchRunLegacy($b['bytecode'],$reps);
    unset($b['bytecode']); $b['run_ms']=$run; $out['cases'][$name]=$b;
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
