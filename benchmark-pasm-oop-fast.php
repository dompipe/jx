<?php declare(strict_types=1);

/**
 * Low-level PASM OOP container benchmark against PHP baselines.
 *
 * Each implementation runs in a fresh PHP process. Paired workloads use
 * N = total_ops / 2 inserts/writes and N = total_ops / 2 reads/removals.
 *
 * Historical PASM OOP has Vector/Stack/Queue/Deque/Map/Set. Record is a JX Bag
 * discipline and is intentionally not fabricated here; Record is measured by
 * benchmark-jx-bag-containers.php and the master container suite.
 *
 * Usage:
 *   php benchmark-pasm-oop-fast.php
 *   php benchmark-pasm-oop-fast.php 1000,10000,100000,1000000 9 2
 *   php benchmark-pasm-oop-fast.php --stress 9 2
 */

if (($argv[1] ?? '') === '--child') {
    $mode = $argv[2] ?? 'new';
    $total = max(2, (int)($argv[3] ?? 100000));
    $reps = max(1, (int)($argv[4] ?? 9));
    $warmups = max(0, (int)($argv[5] ?? 2));
    $n = intdiv($total, 2);

    if ($mode === 'old') require_once __DIR__.'/pasm-oop-containers-legacy.php';
    elseif ($mode === 'new') require_once __DIR__.'/pasm-oop-containers.php';

    $summary = static function(array $xs): array {
        sort($xs, SORT_NUMERIC);
        $count = count($xs);
        $median = $count % 2
            ? $xs[intdiv($count, 2)]
            : ($xs[$count/2-1] + $xs[$count/2]) / 2;
        $p95Index = max(0, min($count-1, (int)ceil($count*0.95)-1));
        return ['median_ms'=>$median, 'min_ms'=>$xs[0], 'p95_ms'=>$xs[$p95Index]];
    };

    $bench = static function(callable $fn) use($summary,$reps,$warmups,$total): array {
        for($i=0;$i<$warmups;$i++)$fn();
        $times=[];$checksum=null;
        for($i=0;$i<$reps;$i++){
            gc_collect_cycles();
            $t0=hrtime(true);$value=$fn();$times[]=(hrtime(true)-$t0)/1e6;
            if($checksum===null)$checksum=$value;
            elseif($checksum!==$value)throw new RuntimeException('Benchmark checksum changed between repetitions');
        }
        $s=$summary($times);$seconds=$s['median_ms']/1000.0;
        return $s+[
            'mops_s'=>$seconds>0?($total/$seconds)/1e6:INF,
            'ns_op'=>($s['median_ms']*1e6)/$total,
            'checksum'=>$checksum,
        ];
    };

    $work=[];
    if($mode==='native'){
        $work['Record put/get']=static function()use($n):int{$a=['health'=>0,'phi'=>0,'level'=>0];$x=0;for($i=0;$i<$n;$i++){$a['health']=$i;$x^=$a['health'];}return $x;};
        $work['Vector add/get']=static function()use($n):int{$a=[];for($i=0;$i<$n;$i++)$a[]=$i;$x=0;for($i=0;$i<$n;$i++)$x^=$a[$i];return $x;};
        $work['Stack push/pop']=static function()use($n):int{$a=[];for($i=0;$i<$n;$i++)$a[]=$i;$x=0;for($i=0;$i<$n;$i++)$x^=(int)array_pop($a);return $x;};
        $work['Queue enq/deq']=static function()use($n):int{$a=[];$h=0;for($i=0;$i<$n;$i++)$a[]=$i;$x=0;for($i=0;$i<$n;$i++)$x^=$a[$h++];return $x;};
        $work['Deque back/front']=$work['Queue enq/deq'];
        $work['Map put/get']=static function()use($n):int{$a=[];for($i=0;$i<$n;$i++)$a[$i]=$i;$x=0;for($i=0;$i<$n;$i++)$x^=$a[$i];return $x;};
        $work['Set add/has']=static function()use($n):int{$a=[];for($i=0;$i<$n;$i++)$a[$i]=true;$x=0;for($i=0;$i<$n;$i++)$x^=(int)isset($a[$i]);return $x;};
    }elseif($mode==='spl'){
        $work['Record put/get']=static function()use($n):int{$a=new SplFixedArray(3);$a[0]=0;$a[1]=0;$a[2]=0;$x=0;for($i=0;$i<$n;$i++){$a[0]=$i;$x^=(int)$a[0];}return $x;};
        $work['Vector add/get']=static function()use($n):int{$a=new SplFixedArray($n);for($i=0;$i<$n;$i++)$a[$i]=$i;$x=0;for($i=0;$i<$n;$i++)$x^=(int)$a[$i];return $x;};
        $work['Stack push/pop']=static function()use($n):int{$s=new SplStack();for($i=0;$i<$n;$i++)$s->push($i);$x=0;for($i=0;$i<$n;$i++)$x^=(int)$s->pop();return $x;};
        $work['Queue enq/deq']=static function()use($n):int{$q=new SplQueue();for($i=0;$i<$n;$i++)$q->enqueue($i);$x=0;for($i=0;$i<$n;$i++)$x^=(int)$q->dequeue();return $x;};
        $work['Deque back/front']=static function()use($n):int{$d=new SplDoublyLinkedList();for($i=0;$i<$n;$i++)$d->push($i);$x=0;for($i=0;$i<$n;$i++)$x^=(int)$d->shift();return $x;};
    }else{
        $ns='\\pasm\\';
        $work['Vector add/get']=static function()use($n,$ns):int{$c=new ($ns.'Vector')();for($i=0;$i<$n;$i++)$c->add($i);$x=0;for($i=0;$i<$n;$i++)$x^=(int)$c->get($i);return $x;};
        $work['Stack push/pop']=static function()use($n,$ns):int{$c=new ($ns.'Stack')();for($i=0;$i<$n;$i++)$c->push($i);$x=0;for($i=0;$i<$n;$i++)$x^=(int)$c->pop();return $x;};
        $work['Queue enq/deq']=static function()use($n,$ns):int{$c=new ($ns.'Queue')();for($i=0;$i<$n;$i++)$c->enqueue($i);$x=0;for($i=0;$i<$n;$i++)$x^=(int)$c->dequeue();return $x;};
        $work['Deque back/front']=static function()use($n,$ns):int{$c=new ($ns.'Deque')();for($i=0;$i<$n;$i++)$c->pushBack($i);$x=0;for($i=0;$i<$n;$i++)$x^=(int)$c->popFront();return $x;};
        $work['Map put/get']=static function()use($n,$ns):int{$c=new ($ns.'Map')();for($i=0;$i<$n;$i++)$c->put($i,$i);$x=0;for($i=0;$i<$n;$i++)$x^=(int)$c->get($i);return $x;};
        $work['Set add/has']=static function()use($n,$ns):int{$c=new ($ns.'Set')();for($i=0;$i<$n;$i++)$c->add($i);$x=0;for($i=0;$i<$n;$i++)$x^=(int)$c->has($i);return $x;};
    }

    $metrics=[];foreach($work as $name=>$fn)$metrics[$name]=$bench($fn);
    echo json_encode([
        'mode'=>$mode,'ops'=>$total,'reps'=>$reps,'warmups'=>$warmups,
        'metrics'=>$metrics,'peak_mb'=>memory_get_peak_usage(true)/1048576,
    ],JSON_THROW_ON_ERROR),"\n";
    exit;
}

$arg1=$argv[1]??'';
if($arg1==='--stress')$sets=[1000,10000,100000,1000000,10000000];
elseif($arg1!=='' && !str_starts_with($arg1,'--'))$sets=array_values(array_filter(array_map('intval',explode(',',$arg1)),static fn(int $x):bool=>$x>=2));
else $sets=[1000,10000,100000,1000000];
$reps=max(1,(int)($argv[2]??9));
$warmups=max(0,(int)($argv[3]??2));
$modes=['old','new','native','spl'];$out=[];

foreach($sets as $ops){
    foreach($modes as $mode){
        $cmd=escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=1 '.escapeshellarg(__FILE__).' --child '.escapeshellarg($mode).' '.escapeshellarg((string)$ops).' '.escapeshellarg((string)$reps).' '.escapeshellarg((string)$warmups);
        $json=shell_exec($cmd);if(!$json)throw new RuntimeException("Benchmark child failed: {$mode}/{$ops}");
        $out[$ops][$mode]=json_decode(trim($json),true,512,JSON_THROW_ON_ERROR);
    }

    $allNames=[];foreach($modes as $mode)$allNames=array_merge($allNames,array_keys($out[$ops][$mode]['metrics']));
    $allNames=array_values(array_unique($allNames));
    foreach($allNames as $name){
        $checks=[];foreach($modes as $mode)if(isset($out[$ops][$mode]['metrics'][$name]))$checks[$mode]=$out[$ops][$mode]['metrics'][$name]['checksum'];
        if(count(array_unique($checks,SORT_REGULAR))>1)throw new RuntimeException("Cross-implementation checksum mismatch for {$name} at {$ops} ops");
    }
}

foreach($out as $ops=>$modesOut){
    echo "\nTOTAL OPERATIONS: ",number_format((int)$ops),"  reps={$reps} warmups={$warmups}\n";
    printf("%-22s %12s %12s %12s %12s\n",'workload','legacy','canonical','PHP array','PHP SPL');
    $names=[];foreach($modes as $mode)$names=array_merge($names,array_keys($modesOut[$mode]['metrics']));$names=array_values(array_unique($names));
    foreach($names as $name){
        $cell=static function(string $mode)use($modesOut,$name):string{return isset($modesOut[$mode]['metrics'][$name])?sprintf('%9.3f ms',$modesOut[$mode]['metrics'][$name]['median_ms']):'          —';};
        printf("%-22s %12s %12s %12s %12s\n",$name,$cell('old'),$cell('new'),$cell('native'),$cell('spl'));
    }
    echo "Peak MB legacy={$modesOut['old']['peak_mb']} canonical={$modesOut['new']['peak_mb']} php-array={$modesOut['native']['peak_mb']} php-spl={$modesOut['spl']['peak_mb']}\n";
}

echo "\nRecord note: historical PASM OOP has no Record class; use benchmark-jx-bag-containers.php for JX RecordBag.\n";
file_put_contents(__DIR__.'/benchmark-pasm-oop-fast-results.json',json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
