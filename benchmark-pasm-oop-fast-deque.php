<?php declare(strict_types=1);

/**
 * Opposite-end deque regression.
 *
 * This intentionally isolates the workload that exposed the legacy asymptotic
 * problem. SplDoublyLinkedList is the structural PHP baseline because both end
 * operations are O(1). Keep this benchmark separate from the normal deque row.
 *
 * Usage:
 *   php benchmark-pasm-oop-fast-deque.php
 *   php benchmark-pasm-oop-fast-deque.php 10000,20000 7 1
 */

if(($argv[1]??'')==='--child'){
    $mode=$argv[2]??'new';
    $total=max(2,(int)($argv[3]??10000));
    $n=intdiv($total,2);
    if($mode==='old')require_once __DIR__.'/pasm-oop-containers-legacy.php';
    elseif($mode==='new')require_once __DIR__.'/pasm-oop-containers.php';

    gc_collect_cycles();$x=0;$t=hrtime(true);
    if($mode==='native'){
        $d=new SplDoublyLinkedList();
        for($i=0;$i<$n;$i++)$d->unshift($i);
        for($i=0;$i<$n;$i++)$x^=(int)$d->pop();
    }else{
        $d=new \pasm\Deque();
        for($i=0;$i<$n;$i++)$d->pushFront($i);
        for($i=0;$i<$n;$i++)$x^=(int)$d->popBack();
    }
    $ms=(hrtime(true)-$t)/1e6;
    echo json_encode([
        'mode'=>$mode,'ops'=>$total,'ms'=>$ms,
        'checksum'=>$x,'peak_mb'=>memory_get_peak_usage(true)/1048576,
    ],JSON_THROW_ON_ERROR),"\n";
    exit;
}

$sizes=array_values(array_filter(array_map('intval',explode(',',$argv[1]??'10000,20000')),static fn(int $x):bool=>$x>=2));
$reps=max(1,(int)($argv[2]??7));
$warmups=max(0,(int)($argv[3]??1));

$summary=static function(array $xs):array{
    sort($xs,SORT_NUMERIC);$n=count($xs);
    $median=$n%2?$xs[intdiv($n,2)]:($xs[$n/2-1]+$xs[$n/2])/2;
    return ['median_ms'=>$median,'min_ms'=>$xs[0],'p95_ms'=>$xs[max(0,min($n-1,(int)ceil($n*0.95)-1))]];
};

$out=[];
foreach($sizes as $ops){
    foreach(['old','new','native'] as $mode){
        $run=static function()use($mode,$ops):array{
            $cmd=escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=1 '.escapeshellarg(__FILE__)
                .' --child '.escapeshellarg($mode).' '.escapeshellarg((string)$ops);
            $raw=shell_exec($cmd);if(!$raw)throw new RuntimeException("Deque child failed: {$mode}/{$ops}");
            return json_decode(trim($raw),true,512,JSON_THROW_ON_ERROR);
        };
        for($i=0;$i<$warmups;$i++)$run();
        $times=[];$checksum=null;$peak=0.0;
        for($i=0;$i<$reps;$i++){
            $r=$run();$times[]=$r['ms'];$peak=max($peak,(float)$r['peak_mb']);
            if($checksum===null)$checksum=$r['checksum'];elseif($checksum!==$r['checksum'])throw new RuntimeException("Deque checksum changed: {$mode}");
        }
        $s=$summary($times);$seconds=$s['median_ms']/1000.0;
        $out[(string)$ops][$mode]=$s+[
            'mops_s'=>$seconds>0?($ops/$seconds)/1e6:INF,
            'ns_op'=>($s['median_ms']*1e6)/$ops,
            'checksum'=>$checksum,'peak_mb'=>$peak,
        ];
    }
    $checks=array_column($out[(string)$ops],'checksum');
    if(count(array_unique($checks,SORT_REGULAR))!==1)throw new RuntimeException("Cross-implementation deque checksum mismatch at {$ops} ops");
}

foreach($out as $ops=>$modes){
    echo "\nOpposite-end deque operations: {$ops}; reps={$reps}; warmups={$warmups}\n";
    foreach($modes as $mode=>$r){
        printf("%-7s median=%10.3f ms  p95=%10.3f ms  %10.2f Mops/s\n",$mode,$r['median_ms'],$r['p95_ms'],$r['mops_s']);
    }
    printf("legacy -> canonical: %.2fx\n",$modes['old']['median_ms']/$modes['new']['median_ms']);
}

file_put_contents(__DIR__.'/benchmark-pasm-oop-fast-deque-results.json',json_encode([
    'suite'=>'pasm-opposite-end-deque/1','reps'=>$reps,'warmups'=>$warmups,'results'=>$out,
],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
