<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-runtime.php';
require_once __DIR__ . '/pasm-bytecode.php';
require_once __DIR__ . '/pasm-register-command.php';
require_once __DIR__ . '/pasm-register-fast-vm.php';

use pasm\PASMBC;
use pasm\PASMRegisterCommand;
use pasm\PASMRegisterCommandVM;
use pasm\PASMRegisterFastVM;

$n = max(1, (int)($argv[1] ?? 200000));
$reps = max(3, (int)($argv[2] ?? 9));

function median(array $xs): float {
    sort($xs, SORT_NUMERIC);
    $m = intdiv(count($xs), 2);
    return count($xs) % 2 ? (float)$xs[$m] : ((float)$xs[$m-1] + (float)$xs[$m]) / 2.0;
}

/** Reference executor for the pre-packed 4-byte ADD / 2-byte RET format. */
function runLegacyRegisterStream(string $code): int {
    $r = [0,1,0,0,0,0,0,0];
    $pc = 0; $n = strlen($code);
    while ($pc < $n) {
        $op = ord($code[$pc++]);
        if ($op === PASMBC::ADD) {
            $d=ord($code[$pc++]); $a=ord($code[$pc++]); $b=ord($code[$pc++]);
            $r[$d]=$r[$a]+$r[$b];
            continue;
        }
        if ($op === PASMBC::RET) return $r[ord($code[$pc++])];
        throw new RuntimeException('Unexpected legacy benchmark opcode');
    }
    return $r[0];
}

$legacyAdd = chr(PASMBC::ADD).chr(0).chr(0).chr(1);
$legacyRet = chr(PASMBC::RET).chr(0);
$legacyCode = str_repeat($legacyAdd, $n).$legacyRet;

$packedAdd = PASMRegisterCommand::encode(PASMBC::ADD, [0,0,1]);
$packedRet = PASMRegisterCommand::encode(PASMBC::RET, [0]);
$packedCode = str_repeat($packedAdd, $n).$packedRet;

$legacyTimes=[]; $genericTimes=[]; $fastTimes=[];
$legacyResult=$genericResult=$fastResult=null;
for($i=0;$i<$reps;$i++){
    $t=hrtime(true); $legacyResult=runLegacyRegisterStream($legacyCode); $legacyTimes[]=(hrtime(true)-$t)/1e6;

    $gvm=new PASMRegisterCommandVM([0=>0,1=>1]);
    $t=hrtime(true); $genericResult=$gvm->run($packedCode); $genericTimes[]=(hrtime(true)-$t)/1e6;

    $fvm=new PASMRegisterFastVM([0=>0,1=>1]);
    $t=hrtime(true); $fastResult=$fvm->run($packedCode); $fastTimes[]=(hrtime(true)-$t)/1e6;
}

if($legacyResult!==$n||$genericResult!==$n||$fastResult!==$n){
    fwrite(STDERR,"FAIL result mismatch legacy={$legacyResult} generic={$genericResult} fast={$fastResult} expected={$n}\n");
    exit(1);
}

$legacyMs=median($legacyTimes); $genericMs=median($genericTimes); $fastMs=median($fastTimes);
$out=[
    'ops'=>$n,'reps'=>$reps,
    'legacy'=>['bytes'=>strlen($legacyCode),'bytes_per_add'=>strlen($legacyAdd),'run_ms'=>$legacyMs],
    'packed_generic'=>['bytes'=>strlen($packedCode),'bytes_per_add'=>strlen($packedAdd),'run_ms'=>$genericMs],
    'packed_fast'=>['bytes'=>strlen($packedCode),'bytes_per_add'=>strlen($packedAdd),'run_ms'=>$fastMs],
    'size_ratio'=>strlen($legacyCode)/strlen($packedCode),
    'generic_speed_ratio'=>$legacyMs/$genericMs,
    'fast_speed_ratio'=>$legacyMs/$fastMs,
    'fast_vs_generic'=>$genericMs/$fastMs,
];
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
