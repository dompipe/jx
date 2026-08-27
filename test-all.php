<?php declare(strict_types=1);

/** Exhaustive active-tree runnable gate. Any non-zero child exit fails immediately. */
$root=__DIR__;$php=PHP_BINARY;$failures=[];$ran=[];

function run_cmd(string $label,array $argv,string $cwd):void
{
    global $ran,$failures;
    $cmd=implode(' ',array_map('escapeshellarg',$argv));
    echo "\n=== {$label} ===\n$ {$cmd}\n";
    $p=proc_open($cmd,[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$cwd);
    if(!is_resource($p))throw new RuntimeException("Cannot start {$label}");
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($p);
    if($out!=='')echo $out;if($err!=='')fwrite(STDERR,$err);$ran[]=$label;
    if($status!==0){$failures[]=['label'=>$label,'exit'=>$status];throw new RuntimeException("{$label} failed with exit {$status}");}
}

try {
    // 1. Syntax-check every standalone PHP source. *.h1.php/*.h2.php are deliberate split-source fragments and are checked assembled below.
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));$phpFiles=[];$fragments=[];
    foreach($it as $f){
        $path=$f->getPathname();if(!$f->isFile()||strtolower($f->getExtension())!=='php')continue;
        if(str_contains($path,DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR))continue;
        if(preg_match('/\.h[12]\.php$/',$path)){ $fragments[]=$path; continue; }
        if(str_ends_with($path,'XipEngine.assembled.php'))continue;
        $phpFiles[]=$path;
    }
    sort($phpFiles,SORT_STRING);sort($fragments,SORT_STRING);
    echo "Linting ".count($phpFiles)." standalone PHP files; split fragments=".count($fragments)."...\n";
    foreach($phpFiles as $file)run_cmd('lint '.substr($file,strlen($root)+1),[$php,'-l',$file],$root);

    // Exercise the real split-source loader, then lint the exact generated class.
    $xip=$root.'/pasl/xi/src/XipEngine.php';
    if(is_file($xip)){
        run_cmd('assemble XipEngine fragments',[$php,'-r','require '.var_export($xip,true).'; echo class_exists("XipEngine",false)?"XipEngine loaded\\n":"";'],$root);
        $assembled=$root.'/pasl/xi/src/XipEngine.assembled.php';
        if(!is_file($assembled)||filesize($assembled)===0)throw new RuntimeException('XipEngine assembled source missing');
        run_cmd('lint assembled XipEngine',[$php,'-l',$assembled],$root);
    }

    // 2. Every root regression test is automatically owned by this gate.
    $tests=glob($root.'/test-*.php')?:[];sort($tests,SORT_STRING);
    foreach($tests as $file){if(basename($file)===basename(__FILE__))continue;run_cmd('test '.basename($file),[$php,'-d','zend.assertions=1','-d','assert.exception=1',$file],$root);}

    // 3. Every runnable root PHP example.
    $examples=glob($root.'/examples/*.php')?:[];sort($examples,SORT_STRING);
    foreach($examples as $file)run_cmd('example '.basename($file),[$php,'-d','zend.assertions=1','-d','assert.exception=1',$file],$root);

    // 4. Public CLI paths: JX, PASL, nested PASL, PBC round trip, and x86 emission.
    run_cmd('CLI JX hello',[$php,$root.'/jx-run.php','--print',$root.'/examples/hello.jx'],$root);
    run_cmd('CLI PASL arith',[$php,$root.'/pasm-run.php','--print',$root.'/examples/arith.pasl'],$root);
    if(is_file($root.'/examples/pasl/arith.pasl'))run_cmd('CLI PASL nested arith',[$php,$root.'/pasm-run.php','--print',$root.'/examples/pasl/arith.pasl'],$root);
    if(is_file($root.'/examples/pasl/complex-and-loops.pasl'))run_cmd('CLI PASL complex loops',[$php,$root.'/pasm-run.php','--print',$root.'/examples/pasl/complex-and-loops.pasl'],$root);
    $tmpPbc=sys_get_temp_dir().'/jx-full-gate-'.getmypid().'.pbc';
    run_cmd('CLI compile PBC',[$php,$root.'/pasm-run.php','-o',$tmpPbc,$root.'/examples/arith.pasl'],$root);
    if(!is_file($tmpPbc)||filesize($tmpPbc)===0)throw new RuntimeException('PBC output missing');
    run_cmd('CLI run PBC',[$php,$root.'/pasm-run.php','--print',$tmpPbc],$root);@unlink($tmpPbc);
    if(is_file($root.'/examples/pasl/x86-sum.pasl')){$tmpAsm=sys_get_temp_dir().'/jx-full-gate-'.getmypid().'.s';run_cmd('CLI x86 emission',[$php,$root.'/pasm-run.php','--x86','-o',$tmpAsm,$root.'/examples/pasl/x86-sum.pasl'],$root);if(!is_file($tmpAsm)||filesize($tmpAsm)===0)throw new RuntimeException('x86 assembly output missing');@unlink($tmpAsm);}

    // 5. Every root benchmark harness, normal/default entrypoint.
    $benches=glob($root.'/benchmark-*.php')?:[];sort($benches,SORT_STRING);
    foreach($benches as $file)run_cmd('benchmark '.basename($file),[$php,$file],$root);

    echo "\nPASS FULL RUNNABLE GATE standalone_files=".count($phpFiles)." fragments=".count($fragments)." steps=".count($ran)." tests=".(count($tests)-1)." examples=".count($examples)." benchmarks=".count($benches)."\n";
    exit(0);
}catch(Throwable $e){fwrite(STDERR,"\nFULL GATE FAIL: {$e->getMessage()}\n");if($failures!==[])fwrite(STDERR,json_encode($failures,JSON_PRETTY_PRINT)."\n");exit(1);}
