<?php declare(strict_types=1);

/**
 * JXL native container benchmark provider for benchmark-container-suite.php.
 *
 * This does not estimate JXL performance. On x86-64 Linux with NASM + cc + ld,
 * it builds the actual assembly runtime and executes the C benchmark harness.
 * On hosts without that native toolchain it exits successfully with empty JXL
 * result sets so portable PHP CI can still run the rest of the benchmark suite.
 *
 * Usage:
 *   php benchmark-jxl-containers.php [even_total_ops] [reps] [warmups]
 *   php benchmark-jxl-containers.php 1000000 9 2 --json
 */

$ops=max(2,(int)($argv[1]??100000));
$reps=max(1,(int)($argv[2]??9));
$warmups=max(0,(int)($argv[3]??2));
$jsonOnly=in_array('--json',$argv,true);
if($ops%2!==0)$ops--;

function jxl_bench_command_exists(string $name): bool
{
    $out=[];$status=0;
    exec('command -v '.escapeshellarg($name).' 2>/dev/null',$out,$status);
    return $status===0 && $out!==[];
}

function jxl_bench_unavailable(string $reason,bool $jsonOnly,int $ops,int $reps,int $warmups): never
{
    $result=[
        'suite'=>'jxl-native-containers/1',
        'status'=>'unavailable',
        'reason'=>$reason,
        'ops'=>$ops,'reps'=>$reps,'warmups'=>$warmups,
        'path'=>'prepared-6-byte-executor',
        'native'=>[],
        'vm'=>[],
    ];
    if($jsonOnly)echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
    else echo "JXL native container benchmark: SKIP ({$reason})\n";
    exit(0);
}

$machine=strtolower(trim((string)php_uname('m')));
if(PHP_OS_FAMILY!=='Linux' || !in_array($machine,['x86_64','amd64'],true)){
    jxl_bench_unavailable('requires Linux x86-64 native runtime',$jsonOnly,$ops,$reps,$warmups);
}
foreach(['nasm','cc','ld'] as $tool){
    if(!jxl_bench_command_exists($tool))jxl_bench_unavailable("missing native tool {$tool}",$jsonOnly,$ops,$reps,$warmups);
}

$root=__DIR__;
$outDir=$root.'/build/native/x86_64';
if(!is_dir($outDir) && !mkdir($outDir,0777,true) && !is_dir($outDir)){
    throw new RuntimeException("Cannot create {$outDir}");
}

$run=function(string $cmd,string $label): string {
    $lines=[];$status=0;exec($cmd.' 2>&1',$lines,$status);
    $text=implode("\n",$lines);
    if($status!==0)throw new RuntimeException("{$label} failed (exit {$status})\n{$text}");
    return $text;
};

$run('bash '.escapeshellarg($root.'/native/x86_64/build-jxl-containers.sh').' >/dev/null','native JXL container build');

$harness=$outDir.'/benchmark_jxl_containers';
$compile=implode(' ',[
    'cc','-std=c11','-O3','-Wall','-Wextra','-Werror','-no-pie',
    '-I'.escapeshellarg($root.'/native/x86_64'),
    escapeshellarg($root.'/native/x86_64/benchmark_jxl_containers.c'),
    escapeshellarg($outDir.'/jxl_container_runtime.o'),
    '-o',escapeshellarg($harness),
]);
$run($compile,'native JXL benchmark compile');

$raw=$run(
    escapeshellarg($harness).' '.escapeshellarg((string)$ops).' '.escapeshellarg((string)$reps).' '.escapeshellarg((string)$warmups),
    'native JXL benchmark execution'
);
$result=json_decode(trim($raw),true,512,JSON_THROW_ON_ERROR);
$result['status']='measured';
$result['toolchain']=['cc'=>'cc -O3','assembler'=>'nasm elf64','linker'=>'ld -r'];

if($jsonOnly){
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
    exit;
}

echo "JXL native containers; path=prepared 6-byte executor; total_ops={$ops}; reps={$reps}; warmups={$warmups}\n";
printf("%-9s %10s %10s %10s %10s %10s\n",'container','median','min','p95','Mops/s','ns/op');
foreach(($result['native']??[]) as $name=>$m){
    printf("%-9s %9.3f %9.3f %9.3f %10.2f %10.2f\n",ucfirst((string)$name),$m['median_ms'],$m['min_ms'],$m['p95_ms'],$m['mops_s'],$m['ns_op']);
}
