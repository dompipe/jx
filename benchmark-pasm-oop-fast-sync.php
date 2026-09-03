<?php declare(strict_types=1);

require_once __DIR__.'/pasm-oop-containers.php';
use pasm\{Vector,Stack,Queue,Deque,Map,Set,PASMFramePool,PASMSegmentRegistry};

/**
 * Hot-work versus canonical dirty-page export benchmark.
 *
 * This intentionally keeps synchronization separate from hot operation timing.
 * Queue/Deque/Stack balanced workloads end empty, so zero dirty pages in those
 * cases are a property of this workload, not a claim that snapshots are free.
 *
 * Usage:
 *   php benchmark-pasm-oop-fast-sync.php
 *   php benchmark-pasm-oop-fast-sync.php 100000,1000000 5 1
 *   php benchmark-pasm-oop-fast-sync.php 1000000 7 2 --json
 */

$sizes=array_values(array_filter(array_map('intval',explode(',',$argv[1]??'100000,1000000')),static fn(int $x):bool=>$x>=2));
$reps=max(1,(int)($argv[2]??5));
$warmups=max(0,(int)($argv[3]??1));
$jsonOnly=in_array('--json',$argv,true);

function sync_summary(array $xs): array{
    sort($xs,SORT_NUMERIC);$n=count($xs);
    $median=$n%2?$xs[intdiv($n,2)]:($xs[$n/2-1]+$xs[$n/2])/2;
    $p95=$xs[max(0,min($n-1,(int)ceil($n*0.95)-1))];
    return ['median_ms'=>$median,'min_ms'=>$xs[0],'p95_ms'=>$p95];
}

function sync_once(string $class,int $ops): array{
    $n=intdiv($ops,2);
    $frames=new PASMFramePool();
    $segments=new PASMSegmentRegistry(pageSize:32,maxCells:1<<23);
    $f=$frames->create('sync-bench');
    $fq='\\pasm\\'.$class;
    $c=$fq::forFrame($f,$segments);
    $c->clearDirty();
    $x=0;

    $t=hrtime(true);
    switch($class){
        case 'Vector':
            for($i=0;$i<$n;$i++)$c->add($i);
            for($i=0;$i<$n;$i++)$x^=(int)$c->get($i);
            break;
        case 'Stack':
            for($i=0;$i<$n;$i++)$c->push($i);
            for($i=0;$i<$n;$i++)$x^=(int)$c->pop();
            break;
        case 'Queue':
            for($i=0;$i<$n;$i++)$c->enqueue($i);
            for($i=0;$i<$n;$i++)$x^=(int)$c->dequeue();
            break;
        case 'Deque':
            for($i=0;$i<$n;$i++)$c->pushFront($i);
            for($i=0;$i<$n;$i++)$x^=(int)$c->popBack();
            break;
        case 'Map':
            for($i=0;$i<$n;$i++)$c->put($i,$i);
            for($i=0;$i<$n;$i++)$x^=(int)$c->get($i);
            break;
        case 'Set':
            for($i=0;$i<$n;$i++)$c->add($i);
            for($i=0;$i<$n;$i++)$x^=(int)$c->has($i);
            break;
        default: throw new InvalidArgumentException("Unknown container {$class}");
    }
    $hot=(hrtime(true)-$t)/1e6;

    $t=hrtime(true);
    $dirty=$c->dirtySegments();
    $sync=(hrtime(true)-$t)/1e6;
    $pages=0;foreach($dirty as $p)$pages+=count($p);

    return ['hot_ms'=>$hot,'sync_ms'=>$sync,'dirty_pages'=>$pages,'checksum'=>$x];
}

$classes=['Vector','Stack','Queue','Deque','Map','Set'];
$report=['suite'=>'pasm-container-sync/1','reps'=>$reps,'warmups'=>$warmups,'sizes'=>$sizes,'results'=>[]];

foreach($sizes as $ops){
    foreach($classes as $class){
        for($i=0;$i<$warmups;$i++)sync_once($class,$ops);
        $hot=[];$sync=[];$pages=null;$checksum=null;
        for($i=0;$i<$reps;$i++){
            $r=sync_once($class,$ops);$hot[]=$r['hot_ms'];$sync[]=$r['sync_ms'];
            if($pages===null)$pages=$r['dirty_pages'];elseif($pages!==$r['dirty_pages'])throw new RuntimeException("Dirty page count changed: {$class}");
            if($checksum===null)$checksum=$r['checksum'];elseif($checksum!==$r['checksum'])throw new RuntimeException("Checksum changed: {$class}");
        }
        $hs=sync_summary($hot);$ss=sync_summary($sync);
        $report['results'][(string)$ops][$class]=[
            'hot'=>$hs,'sync'=>$ss,'total_median_ms'=>$hs['median_ms']+$ss['median_ms'],
            'dirty_pages'=>$pages,'checksum'=>$checksum,
        ];
    }
}

if($jsonOnly){echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";exit;}
foreach($report['results'] as $ops=>$rows){
    echo "\n{$ops} ops + one canonical page export; reps={$reps}; warmups={$warmups}\n";
    foreach($rows as $class=>$r){
        printf("%-7s hot=%9.3f  sync=%9.3f  total=%9.3f ms  pages=%d\n",$class,$r['hot']['median_ms'],$r['sync']['median_ms'],$r['total_median_ms'],$r['dirty_pages']);
    }
}
