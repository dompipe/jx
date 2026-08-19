<?php declare(strict_types=1);

/**
 * Parent mode runs each implementation in a fresh PHP process so the legacy
 * and canonical container classes (same namespace/class names) can be compared.
 * "ops" is total API operations; N = ops/2 inserts + ops/2 reads/removals.
 */

if (($argv[1] ?? '') === '--child') {
    $mode = $argv[2] ?? 'new';
    $total = (int)($argv[3] ?? 100000);
    $n = intdiv($total, 2);
    if ($mode === 'old') require_once __DIR__.'/pasm-oop-containers-legacy.php';
    elseif ($mode === 'new') require_once __DIR__.'/pasm-oop-containers.php';

    $measure = static function(callable $fn): float {
        gc_collect_cycles();
        $t = hrtime(true); $fn(); return (hrtime(true)-$t)/1e6;
    };
    $median = static function(array $x): float { sort($x,SORT_NUMERIC); $c=count($x); return $x[intdiv($c,2)]; };
    $bench = static function(callable $fn,int $reps=3) use($measure,$median): float { $a=[]; for($i=0;$i<$reps;$i++)$a[]=$measure($fn); return $median($a); };

    $r=[];
    if ($mode === 'native') {
        $r['Vector add/get']=$bench(function()use($n){$a=[];for($i=0;$i<$n;$i++)$a[]=$i;for($i=0;$i<$n;$i++)$x=$a[$i];});
        $r['Stack push/pop']=$bench(function()use($n){$a=[];for($i=0;$i<$n;$i++)$a[]=$i;for($i=0;$i<$n;$i++)$x=array_pop($a);});
        $r['Queue enq/deq']=$bench(function()use($n){$a=[];$h=0;for($i=0;$i<$n;$i++)$a[]=$i;for($i=0;$i<$n;$i++)$x=$a[$h++];});
        $r['Deque back/front']=$bench(function()use($n){$a=[];$h=0;for($i=0;$i<$n;$i++)$a[]=$i;for($i=0;$i<$n;$i++)$x=$a[$h++];});
        $r['Map put/get']=$bench(function()use($n){$a=[];for($i=0;$i<$n;$i++)$a[$i]=$i;for($i=0;$i<$n;$i++)$x=$a[$i];});
        $r['Set add/has']=$bench(function()use($n){$a=[];for($i=0;$i<$n;$i++)$a[$i]=true;for($i=0;$i<$n;$i++)$x=isset($a[$i]);});
    } else {
        $ns='\\pasm\\';
        $r['Vector add/get']=$bench(function()use($n,$ns){$c=new ($ns.'Vector')();for($i=0;$i<$n;$i++)$c->add($i);for($i=0;$i<$n;$i++)$x=$c->get($i);});
        $r['Stack push/pop']=$bench(function()use($n,$ns){$c=new ($ns.'Stack')();for($i=0;$i<$n;$i++)$c->push($i);for($i=0;$i<$n;$i++)$x=$c->pop();});
        $r['Queue enq/deq']=$bench(function()use($n,$ns){$c=new ($ns.'Queue')();for($i=0;$i<$n;$i++)$c->enqueue($i);for($i=0;$i<$n;$i++)$x=$c->dequeue();});
        $r['Deque back/front']=$bench(function()use($n,$ns){$c=new ($ns.'Deque')();for($i=0;$i<$n;$i++)$c->pushBack($i);for($i=0;$i<$n;$i++)$x=$c->popFront();});
        $r['Map put/get']=$bench(function()use($n,$ns){$c=new ($ns.'Map')();for($i=0;$i<$n;$i++)$c->put($i,$i);for($i=0;$i<$n;$i++)$x=$c->get($i);});
        $r['Set add/has']=$bench(function()use($n,$ns){$c=new ($ns.'Set')();for($i=0;$i<$n;$i++)$c->add($i);for($i=0;$i<$n;$i++)$x=$c->has($i);});
    }
    echo json_encode(['mode'=>$mode,'ops'=>$total,'ms'=>$r,'peak_mb'=>memory_get_peak_usage(true)/1048576],JSON_THROW_ON_ERROR),"\n";
    exit;
}

$php=PHP_BINARY;
$sets=[100000,1000000];
$modes=['old','new','native'];
$out=[];
foreach($sets as $ops){
    foreach($modes as $mode){
        $cmd=escapeshellarg($php).' -d opcache.enable_cli=1 '.escapeshellarg(__FILE__).' --child '.escapeshellarg($mode).' '.escapeshellarg((string)$ops);
        $json=shell_exec($cmd);
        if(!$json) throw new RuntimeException("Benchmark child failed: {$mode}/{$ops}");
        $out[$ops][$mode]=json_decode(trim($json),true,512,JSON_THROW_ON_ERROR);
    }
}

foreach($out as $ops=>$modesOut){
    echo "\nTOTAL OPERATIONS: ",number_format((int)$ops),"\n";
    printf("%-20s %12s %12s %12s %10s\n",'workload','legacy ms','canonical ms','native ms','new/old');
    foreach($modesOut['new']['ms'] as $name=>$new){
        $old=$modesOut['old']['ms'][$name];$native=$modesOut['native']['ms'][$name];
        printf("%-20s %12.3f %12.3f %12.3f %9.2fx\n",$name,$old,$new,$native,$old/$new);
    }
    echo "Peak MB legacy={$modesOut['old']['peak_mb']} canonical={$modesOut['new']['peak_mb']} native={$modesOut['native']['peak_mb']}\n";
}

file_put_contents(__DIR__.'/benchmark-pasm-oop-fast-results.json',json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
