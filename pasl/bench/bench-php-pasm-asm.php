#!/usr/bin/env php
<?php declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/pasm-expr.php';
require_once dirname(__DIR__, 2) . '/pasm-jxl.php';

use pasm\PASMAssembler;
use pasm\PASMBytecodeVM;
use pasm\PASMExprCompiler;
use pasm\PASMJxlCompiler;
use pasm\PASMOptimizedBytecodeVM;
use pasm\PASMOptimizingAssembler;
use pasm\PASMRuntime;

$options = [
    'compile_iters' => 300,
    'run_iters' => 120,
    'php_iters' => 20000,
    'rounds' => 7,
    'optimize' => true,
    'json' => false,
];

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if ($arg === '--json') $options['json'] = true;
    elseif ($arg === '--no-optimize') $options['optimize'] = false;
    elseif ($arg === '--compile-iters' && isset($argv[$i + 1])) $options['compile_iters'] = max(1, (int)$argv[++$i]);
    elseif ($arg === '--run-iters' && isset($argv[$i + 1])) $options['run_iters'] = max(1, (int)$argv[++$i]);
    elseif ($arg === '--php-iters' && isset($argv[$i + 1])) $options['php_iters'] = max(1, (int)$argv[++$i]);
    elseif ($arg === '--rounds' && isset($argv[$i + 1])) $options['rounds'] = max(1, (int)$argv[++$i]);
    else {
        fwrite(STDERR, "Usage: php pasl/bench/bench-php-pasm-asm.php [--compile-iters N] [--run-iters N] [--php-iters N] [--rounds N] [--no-optimize] [--json]\n");
        exit(2);
    }
}

$cases = [
    'scalar' => [
        'src' => '$a=1;$b=2;$c=$a+$b;$d=$c*3;$result=$d-1;',
        'php' => static function (): int { $a=1; $b=2; $c=$a+$b; $d=$c*3; return $d-1; },
    ],
    'while64' => [
        'src' => '$sum=0;$i=64;while($i){$sum+=$i;$i--;}$result=$sum;',
        'php' => static function (): int { $sum=0; $i=64; while ($i) { $sum += $i; $i--; } return $sum; },
    ],
    'for128' => [
        'src' => '$sum=0;$i=0;for($i=0;$i<128;$i++){$sum+=$i;}$result=$sum;',
        'php' => static function (): int { $sum=0; for ($i=0; $i<128; $i++) $sum += $i; return $sum; },
    ],
    'nested16' => [
        'src' => '$sum=0;$i=0;$j=0;for($i=0;$i<16;$i++){for($j=0;$j<16;$j++){$sum+=$i+$j;}}$result=$sum;',
        'php' => static function (): int { $sum=0; for ($i=0; $i<16; $i++) for ($j=0; $j<16; $j++) $sum += $i + $j; return $sum; },
    ],
    'signed128' => [
        'src' => '$sum=0;$i=-64;while($i<64){$sum+=$i;$i++;}$result=$sum;',
        'php' => static function (): int { $sum=0; $i=-64; while ($i<64) { $sum += $i; $i++; } return $sum; },
    ],
    'bitmix' => [
        'src' => '$a=305419896;$b=252645135;$c=$a^$b;$d=$c&16711935;$result=$d|65536;',
        'php' => static function (): int { $a=305419896; $b=252645135; $c=$a^$b; $d=$c&16711935; return $d|65536; },
    ],
];

function median(array $values): float {
    sort($values, SORT_NUMERIC);
    $n = count($values);
    if ($n === 0) return 0.0;
    $m = intdiv($n, 2);
    return ($n % 2) ? (float)$values[$m] : ((float)$values[$m-1] + (float)$values[$m]) / 2.0;
}

function measureUs(callable $fn, int $iters, int $rounds): float {
    for ($i=0, $n=min(8,$iters); $i<$n; $i++) $fn();
    $samples=[];
    for ($r=0; $r<$rounds; $r++) {
        $t0=hrtime(true);
        for ($i=0; $i<$iters; $i++) $fn();
        $samples[] = ((hrtime(true)-$t0)/$iters)/1000.0;
    }
    return median($samples);
}

function ratio(float $a, float $b): float { return $b > 0.0 ? $a/$b : 0.0; }
function geometricMean(array $values): float {
    $values=array_values(array_filter($values, static fn($v)=>is_numeric($v)&&(float)$v>0.0));
    if ($values===[]) return 0.0;
    $s=0.0; foreach($values as $v) $s+=log((float)$v);
    return exp($s/count($values));
}

function makeVm(bool $optimize): object {
    $rt = new PASMRuntime();
    return $optimize ? new PASMOptimizedBytecodeVM($rt) : new PASMBytecodeVM($rt);
}

$optimize=(bool)$options['optimize'];
$jxlCompiler=new PASMJxlCompiler();
$results=[];

foreach ($cases as $name=>$case) {
    $src=$case['src'];
    $php=$case['php'];

    $expr=new PASMExprCompiler();
    $asm=$expr->compile($src);
    $assembler=$optimize ? new PASMOptimizingAssembler(true) : new PASMAssembler();
    $pbc=$assembler->compile($asm);
    $jxl=$jxlCompiler->compile($asm);
    if (!PASMJxlCompiler::isJxl($jxl)) throw new RuntimeException("{$name}: invalid JXL emitted from PASM ASM");

    $admittedAsm=$jxlCompiler->toPasmAssembly($jxl);
    $admittedPbc=$assembler->compile($admittedAsm);

    $expected=$php();
    $pbcVm=makeVm($optimize);
    $jxlVm=makeVm($optimize);
    $pbcResult=$pbcVm->run($pbc);
    $jxlResult=$jxlVm->run($admittedPbc);
    if ($pbcResult !== $expected || $jxlResult !== $expected) {
        throw new RuntimeException("{$name}: PHP/PASM/JXL mismatch PHP={$expected} PBC=".var_export($pbcResult,true)." JXL=".var_export($jxlResult,true));
    }

    $phpUs=measureUs($php,$options['php_iters'],$options['rounds']);
    $rewriteUs=measureUs(static fn()=>(new PASMExprCompiler())->compile($src),$options['compile_iters'],$options['rounds']);
    $asmToPbcUs=measureUs(static function() use($asm,$optimize): void {
        $a=$optimize ? new PASMOptimizingAssembler(true) : new PASMAssembler();
        $a->compile($asm);
    },$options['compile_iters'],$options['rounds']);
    $asmToJxlUs=measureUs(static function() use($asm): void {
        (new PASMJxlCompiler())->compile($asm);
    },$options['compile_iters'],$options['rounds']);
    $jxlAdmitUs=measureUs(static function() use($jxl,$optimize): void {
        $bridge=new PASMJxlCompiler();
        $a=$optimize ? new PASMOptimizingAssembler(true) : new PASMAssembler();
        $a->compile($bridge->toPasmAssembly($jxl));
    },$options['compile_iters'],$options['rounds']);

    $warmPbcVm=makeVm($optimize);
    $pbcWarmUs=measureUs(static fn()=>$warmPbcVm->run($pbc),$options['run_iters'],$options['rounds']);
    $warmJxlVm=makeVm($optimize);
    $jxlWarmUs=measureUs(static fn()=>$warmJxlVm->run($admittedPbc),$options['run_iters'],$options['rounds']);
    $pbcColdUs=measureUs(static function() use($pbc,$optimize) { return makeVm($optimize)->run($pbc); },$options['run_iters'],$options['rounds']);
    $jxlE2eUs=measureUs(static function() use($jxl,$optimize) {
        $bridge=new PASMJxlCompiler();
        $a=$optimize ? new PASMOptimizingAssembler(true) : new PASMAssembler();
        $code=$a->compile($bridge->toPasmAssembly($jxl));
        return makeVm($optimize)->run($code);
    },$options['run_iters'],$options['rounds']);

    $results[]=[
        'case'=>$name,
        'result'=>$expected,
        'source_bytes'=>strlen($src),
        'pasm_asm_bytes'=>strlen($asm),
        'pbc_bytes'=>strlen($pbc),
        'jxl_bytes'=>strlen($jxl),
        'php_direct_us'=>$phpUs,
        'php_to_pasm_asm_us'=>$rewriteUs,
        'asm_to_pbc_us'=>$asmToPbcUs,
        'asm_to_jxl_us'=>$asmToJxlUs,
        'jxl_compat_admit_us'=>$jxlAdmitUs,
        'pbc_warm_run_us'=>$pbcWarmUs,
        'jxl_admitted_warm_run_us'=>$jxlWarmUs,
        'pbc_cold_run_us'=>$pbcColdUs,
        'jxl_compat_e2e_us'=>$jxlE2eUs,
        'pbc_warm_over_php'=>ratio($pbcWarmUs,$phpUs),
        'jxl_warm_over_php'=>ratio($jxlWarmUs,$phpUs),
        'jxl_e2e_over_php'=>ratio($jxlE2eUs,$phpUs),
    ];
}

$summary=[
    'pbc_warm_over_php_gmean'=>geometricMean(array_column($results,'pbc_warm_over_php')),
    'jxl_warm_over_php_gmean'=>geometricMean(array_column($results,'jxl_warm_over_php')),
    'jxl_e2e_over_php_gmean'=>geometricMean(array_column($results,'jxl_e2e_over_php')),
    'jxl_over_pbc_size_gmean'=>geometricMean(array_map(static fn($r)=>ratio((float)$r['jxl_bytes'],(float)$r['pbc_bytes']),$results)),
];

$payload=[
    'benchmark'=>'php-pasm-asm-jxl',
    'php'=>PHP_VERSION,
    'platform'=>PHP_OS_FAMILY.' '.php_uname('m'),
    'optimize'=>$optimize,
    'compile_iters'=>$options['compile_iters'],
    'run_iters'=>$options['run_iters'],
    'php_iters'=>$options['php_iters'],
    'rounds'=>$options['rounds'],
    'execution_note'=>'PHP-like restricted source is rewritten by PASMExprCompiler into PASM assembly. Current JXL execution numbers still include compatibility JXL->PASM ASM->PBC admission; they are not direct native JXL.',
    'results'=>$results,
    'summary'=>$summary,
];

if ($options['json']) {
    echo json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    exit(0);
}

echo "PHP -> PASM ASM -> JXL benchmark\n";
echo "PHP {$payload['php']} | {$payload['platform']} | optimize=".($optimize?'yes':'no')." | compile={$options['compile_iters']} run={$options['run_iters']} php={$options['php_iters']} rounds={$options['rounds']}\n";
echo "JXL e2e is compatibility admission today; PASM ASM rewrite and admitted warm execution are measured separately.\n\n";
printf("%-10s %6s %7s %6s %6s %10s %10s %10s %10s\n",'case','srcB','asmB','pbcB','jxlB','PHP us','PHP->ASM','ASM->PBC','ASM->JXL');
foreach($results as $r) printf("%-10s %6d %7d %6d %6d %10.4f %10.2f %10.2f %10.2f\n",$r['case'],$r['source_bytes'],$r['pasm_asm_bytes'],$r['pbc_bytes'],$r['jxl_bytes'],$r['php_direct_us'],$r['php_to_pasm_asm_us'],$r['asm_to_pbc_us'],$r['asm_to_jxl_us']);

echo "\n";
printf("%-10s %10s %10s %10s %10s %9s\n",'case','PBC warm','JXL warm','JXL admit','JXL e2e','PBC/PHP');
foreach($results as $r) printf("%-10s %10.2f %10.2f %10.2f %10.2f %8.1fx\n",$r['case'],$r['pbc_warm_run_us'],$r['jxl_admitted_warm_run_us'],$r['jxl_compat_admit_us'],$r['jxl_compat_e2e_us'],$r['pbc_warm_over_php']);

echo "\nGeometric means:\n";
printf("  PBC warm / direct PHP : %.2fx\n",$summary['pbc_warm_over_php_gmean']);
printf("  JXL warm / direct PHP : %.2fx\n",$summary['jxl_warm_over_php_gmean']);
printf("  JXL e2e / direct PHP  : %.2fx\n",$summary['jxl_e2e_over_php_gmean']);
printf("  JXL / PBC size        : %.3fx\n",$summary['jxl_over_pbc_size_gmean']);
