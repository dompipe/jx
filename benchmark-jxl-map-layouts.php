<?php declare(strict_types=1);

/**
 * Native A/B benchmark for the two retained JXL ordered Map layouts:
 *
 *   split  = keys[] + values[]
 *   vector = Vector<Entry>, Entry=[u64 key,u64 value]
 *
 * Both variants run through the same six-byte prepared JXL executor and the
 * same 80-byte admitted binding ABI. Allocation, pattern construction and
 * binding construction are outside the timed region.
 *
 * Usage:
 *   php benchmark-jxl-map-layouts.php [standard_ops] [shift_ops] [reps] [warmups]
 *   php benchmark-jxl-map-layouts.php 1000000 20000 7 1 --json
 */

$standardOps=max(2,(int)($argv[1]??1000000));
$shiftOps=max(2,(int)($argv[2]??20000));
$reps=max(1,(int)($argv[3]??7));
$warmups=max(0,(int)($argv[4]??1));
$jsonOnly=in_array('--json',$argv,true);
if($standardOps%2!==0)$standardOps--;
if($shiftOps%2!==0)$shiftOps--;

function map_layout_command_exists(string $name): bool
{
    $out=[];$status=0;
    exec('command -v '.escapeshellarg($name).' 2>/dev/null',$out,$status);
    return $status===0 && $out!==[];
}

function map_layout_unavailable(string $reason,bool $jsonOnly,int $standardOps,int $shiftOps,int $reps,int $warmups): never
{
    $result=[
        'suite'=>'jxl-map-layout-ab/1','status'=>'unavailable','reason'=>$reason,
        'path'=>'prepared-6-byte-executor','standard_ops'=>$standardOps,'shift_ops'=>$shiftOps,
        'reps'=>$reps,'warmups'=>$warmups,'workloads'=>[],
    ];
    if($jsonOnly)echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
    else echo "JXL Map layout A/B: SKIP ({$reason})\n";
    exit(0);
}

$machine=strtolower(trim((string)php_uname('m')));
if(PHP_OS_FAMILY!=='Linux' || !in_array($machine,['x86_64','amd64'],true)){
    map_layout_unavailable('requires Linux x86-64 native runtime',$jsonOnly,$standardOps,$shiftOps,$reps,$warmups);
}
foreach(['nasm','cc','ld'] as $tool){
    if(!map_layout_command_exists($tool))map_layout_unavailable("missing native tool {$tool}",$jsonOnly,$standardOps,$shiftOps,$reps,$warmups);
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

$harness=$outDir.'/benchmark_jxl_map_layouts';
$compile=implode(' ',[
    'cc','-std=c11','-O3','-Wall','-Wextra','-Werror','-no-pie',
    '-I'.escapeshellarg($root.'/native/x86_64'),
    escapeshellarg($root.'/native/x86_64/benchmark_jxl_map_layouts.c'),
    escapeshellarg($outDir.'/jxl_container_runtime.o'),
    '-o',escapeshellarg($harness),
]);
$run($compile,'native JXL Map layout benchmark compile');

$raw=$run(
    escapeshellarg($harness).' '.escapeshellarg((string)$standardOps).' '.escapeshellarg((string)$shiftOps)
    .' '.escapeshellarg((string)$reps).' '.escapeshellarg((string)$warmups),
    'native JXL Map layout benchmark execution'
);
$result=json_decode(trim($raw),true,512,JSON_THROW_ON_ERROR);
$result['status']='measured';
$result['toolchain']=['cc'=>'cc -O3','assembler'=>'nasm elf64','linker'=>'ld -r'];

if($jsonOnly){
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
    exit;
}

echo "JXL Map layout A/B; path=prepared 6-byte executor; standard_ops={$standardOps}; shift_ops={$shiftOps}; reps={$reps}; warmups={$warmups}\n";
printf("%-24s %11s %11s %11s %12s\n",'workload','split ms','vector ms','vec/split','winner');
foreach(($result['workloads']??[]) as $name=>$row){
    printf(
        "%-24s %11.3f %11.3f %11.3fx %12s\n",
        (string)$name,
        (float)$row['split']['median_ms'],
        (float)$row['vector']['median_ms'],
        (float)$row['vector_over_split'],
        (string)$row['faster']
    );
}
