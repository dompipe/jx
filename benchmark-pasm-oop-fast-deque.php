<?php declare(strict_types=1);
if(($argv[1]??'')==='--child'){
    $mode=$argv[2]??'new';$total=(int)($argv[3]??10000);$n=intdiv($total,2);
    if($mode==='old')require_once __DIR__.'/pasm-oop-containers-legacy.php';
    elseif($mode==='new')require_once __DIR__.'/pasm-oop-containers.php';
    $t=hrtime(true);
    if($mode==='native'){
        // SplDoublyLinkedList is a fair native deque-like baseline with O(1) ends.
        $d=new SplDoublyLinkedList();for($i=0;$i<$n;$i++)$d->unshift($i);for($i=0;$i<$n;$i++)$x=$d->pop();
    }else{
        $d=new \pasm\Deque();for($i=0;$i<$n;$i++)$d->pushFront($i);for($i=0;$i<$n;$i++)$x=$d->popBack();
    }
    echo json_encode(['mode'=>$mode,'ops'=>$total,'ms'=>(hrtime(true)-$t)/1e6,'peak_mb'=>memory_get_peak_usage(true)/1048576],JSON_THROW_ON_ERROR),"\n";exit;
}
foreach([10000,20000] as $ops){echo "\nOpposite-end deque operations: {$ops}\n";foreach(['old','new','native'] as $mode){$cmd=escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=1 '.escapeshellarg(__FILE__).' --child '.$mode.' '.$ops;$r=json_decode(trim((string)shell_exec($cmd)),true);printf("%-7s %10.3f ms  %10.2f Mops/s\n",$mode,$r['ms'],($ops/($r['ms']/1000))/1e6);}}
