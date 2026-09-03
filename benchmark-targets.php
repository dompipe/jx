<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-lang.php';
require_once __DIR__ . '/pasm-lang-x86.php';

use pasm\lang\Engine;
use pasm\lang\Compiler;
use pasm\lang\X86Compiler;

const EXPECTED = 49995000;
const REPS = 9;

// $sum is deliberately the last persistent scalar allocated. The PASL compiler
// owns RET emission and returns the last allocated scalar register.
$source = <<<'PASL'
$i = 0;
$sum = 0;
while ($i < 10000) {
    $sum += $i;
    $i++;
}
PASL;

function median(array $values): float {
    sort($values, SORT_NUMERIC);
    return (float)$values[intdiv(count($values), 2)];
}

function bench(string $name, callable $fn, int $reps = REPS): array {
    for ($i=0;$i<3;$i++) {
        $v=$fn();
        if ((int)$v !== EXPECTED) throw new RuntimeException("{$name} warmup result mismatch {$v}");
    }
    $samples=[];$last=null;
    for ($i=0;$i<$reps;$i++) {
        $t0=hrtime(true);$last=$fn();$t1=hrtime(true);
        if ((int)$last !== EXPECTED) throw new RuntimeException("{$name} result mismatch {$last}");
        $samples[]=($t1-$t0)/1e6;
    }
    return ['target'=>$name,'median_ms'=>median($samples),'result'=>(int)$last,'reps'=>$reps];
}

$sourceResult = bench('jx-source-compile-run', fn() => (new Engine(true,false))->runSource($source));

$compileSamples=[];$jxl=null;
for($i=0;$i<REPS;$i++){
    $e=new Engine(true,false);$t0=hrtime(true);$code=$e->compile($source);$t1=hrtime(true);
    $compileSamples[]=($t1-$t0)/1e6;$jxl=$code;
}
if(!is_string($jxl) || $jxl==='') throw new RuntimeException('compiled JXL missing');
$compileResult=['target'=>'jx-compile-only','median_ms'=>median($compileSamples),'bytes'=>strlen($jxl),'reps'=>REPS];

// compile() now returns canonical PASM-profile JXL. The old harness sent these
// bytes into PASMBytecodeVM, which is a legacy .pbc decoder and therefore
// rejected the JXL opcode band. Use the public Engine admission path instead.
$preparedResult = bench('prepared-jxl-page', function() use ($jxl) {
    return (new Engine(true,false))->runCode($jxl);
});
$preparedResult['bytes']=strlen($jxl);

$compiler = new Compiler(true, false);
$assembly = $compiler->compile($source);
$tmpBase = sys_get_temp_dir() . '/jx-target-bench-' . getmypid();
$tmpAsm = $tmpBase . '.pasm';
file_put_contents($tmpAsm, $assembly);

$node = trim((string)shell_exec('command -v node 2>/dev/null'));
if ($node !== '') {
    $cmd = escapeshellarg($node).' '.escapeshellarg(__DIR__.'/benchmark-target-browser.js').' '.escapeshellarg($tmpAsm).' '.REPS.' '.EXPECTED;
    $lines=[];$status=0;exec($cmd.' 2>&1',$lines,$status);
    if($status!==0) throw new RuntimeException("browser benchmark failed: ".implode("\n",$lines));
    $browserResult=json_decode((string)end($lines),true,512,JSON_THROW_ON_ERROR);
} else {
    $browserResult=['target'=>'browser-js-pasm','skipped'=>true,'reason'=>'node unavailable'];
}

$cc = trim((string)shell_exec('command -v cc 2>/dev/null'));
if ($cc !== '') {
    $x86=(new X86Compiler(true))->compile($source);
    $tmpS=$tmpBase.'.s';$tmpC=$tmpBase.'.c';$tmpExe=$tmpBase.'.native';
    file_put_contents($tmpS,$x86);
    $c=<<<'C'
#include <stdio.h>
#include <stdint.h>
#include <time.h>
extern long pasl_main(void);
static uint64_t ns(void){ struct timespec t; clock_gettime(CLOCK_MONOTONIC,&t); return (uint64_t)t.tv_sec*1000000000ull+(uint64_t)t.tv_nsec; }
int main(void){
    const int warm=100, reps=5000;
    volatile long sink=0;
    for(int i=0;i<warm;i++) sink ^= pasl_main();
    uint64_t t0=ns();
    for(int i=0;i<reps;i++) sink ^= pasl_main();
    uint64_t t1=ns();
    long result=pasl_main();
    if(result!=49995000L) return 7;
    double ms=((double)(t1-t0)/1000000.0)/(double)reps;
    printf("{\"target\":\"native-x86-64\",\"median_ms\":%.9f,\"result\":%ld,\"reps\":%d,\"sink\":%ld}\n",ms,result,reps,(long)sink);
    return 0;
}
C;
    file_put_contents($tmpC,$c);
    $build=escapeshellarg($cc).' -O3 -no-pie -o '.escapeshellarg($tmpExe).' '.escapeshellarg($tmpC).' '.escapeshellarg($tmpS).' 2>&1';
    $lines=[];$status=0;exec($build,$lines,$status);
    if($status!==0) throw new RuntimeException("native build failed: ".implode("\n",$lines));
    $lines=[];$status=0;exec(escapeshellarg($tmpExe).' 2>&1',$lines,$status);
    if($status!==0) throw new RuntimeException("native benchmark failed: ".implode("\n",$lines));
    $nativeResult=json_decode((string)end($lines),true,512,JSON_THROW_ON_ERROR);
    @unlink($tmpS);@unlink($tmpC);@unlink($tmpExe);
} else {
    $nativeResult=['target'=>'native-x86-64','skipped'=>true,'reason'=>'cc unavailable'];
}
@unlink($tmpAsm);

$rows=[$sourceResult,$compileResult,$preparedResult,$browserResult,$nativeResult];
$baseline=(float)$sourceResult['median_ms'];
foreach($rows as &$row){
    if(isset($row['median_ms']) && $row['target']!=='jx-compile-only'){
        $row['speedup_vs_jx_source']=$baseline/(float)$row['median_ms'];
    }
}
unset($row);

$out=[
    'workload'=>'sum 0..9999 using PASL while ($i < 10000); expected 49,995,000',
    'note'=>'PHP server/CLI source compiles to canonical PASM-profile JXL; prepared execution uses Engine::runCode; browser comparison is the existing JS PASM VM; native uses x86-64 backend',
    'results'=>$rows,
];
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
