<?php declare(strict_types=1);
require_once __DIR__.'/pasm-oop-containers.php';
use pasm\{Vector,Queue,Deque,Map,Set,PASMFramePool,PASMSegmentRegistry};

function one(string $class,int $ops): array{
    $n=intdiv($ops,2);$frames=new PASMFramePool();$segments=new PASMSegmentRegistry(pageSize:32,maxCells:1<<23);$f=$frames->create('sync-bench');
    $fq='\\pasm\\'.$class;$c=$fq::forFrame($f,$segments);$c->clearDirty();
    $t=hrtime(true);
    switch($class){
        case 'Vector':for($i=0;$i<$n;$i++)$c->add($i);for($i=0;$i<$n;$i++)$x=$c->get($i);break;
        case 'Queue':for($i=0;$i<$n;$i++)$c->enqueue($i);for($i=0;$i<$n;$i++)$x=$c->dequeue();break;
        case 'Deque':for($i=0;$i<$n;$i++)$c->pushFront($i);for($i=0;$i<$n;$i++)$x=$c->popBack();break;
        case 'Map':for($i=0;$i<$n;$i++)$c->put($i,$i);for($i=0;$i<$n;$i++)$x=$c->get($i);break;
        case 'Set':for($i=0;$i<$n;$i++)$c->add($i);for($i=0;$i<$n;$i++)$x=$c->has($i);break;
    }
    $hot=(hrtime(true)-$t)/1e6;
    $t=hrtime(true);$dirty=$c->dirtySegments();$sync=(hrtime(true)-$t)/1e6;
    $pages=0;foreach($dirty as $p)$pages+=count($p);
    return ['hot_ms'=>$hot,'sync_ms'=>$sync,'total_ms'=>$hot+$sync,'dirty_pages'=>$pages];
}
foreach([100000,1000000] as $ops){echo "\n{$ops} ops + one canonical page export\n";foreach(['Vector','Queue','Deque','Map','Set'] as $c){$r=one($c,$ops);printf("%-7s hot=%9.3f  sync=%9.3f  total=%9.3f ms  pages=%d\n",$c,$r['hot_ms'],$r['sync_ms'],$r['total_ms'],$r['dirty_pages']);}}
