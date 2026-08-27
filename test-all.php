<?php declare(strict_types=1);

/**
 * Exhaustive active-tree runnable gate.
 *
 * - syntax-check every PHP file in the repository (except frozen .git data)
 * - execute every root test-*.php automatically
 * - execute every runnable root PHP example
 * - exercise JX/PASL CLI source and PBC paths
 * - execute every benchmark harness at its normal/default entrypoint
 *
 * Any non-zero child exit fails the gate immediately.
 */

$root=__DIR__;
$php=PHP_BINARY;
$failures=[];
$ran=[];

function run_cmd(string $label,array $argv,string $cwd):void
{
    global $ran,$failures;
    $cmd=implode(' ',array_map('escapeshellarg',$argv));
    echo "\n=== {$label} ===\n$ {$cmd}\n";
    $descriptors=[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']];
    $p=proc_open($cmd,$descriptors,$pipes,$cwd);
    if(!is_resource($p))throw new RuntimeException("Cannot start {$label}");
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($p);
    if($out!=='')echo $out;
    if($err!=='')fwrite(STDERR,$err);
    $ran[]=$label;
    if($status!==0){$failures[]=['label'=>$label,'exit'=>$status];throw new RuntimeException("{$label} failed with exit {$status}");}
}

try {
    // 1. Syntax: every PHP source in the working tree.
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    $phpFiles=[];
    foreach($it as $f){
        $path=$f->getPathname();
        if(!$f->isFile()||strtolower($f->getExtension())!=='php')continue;
        if(str_contains($path,DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR))continue;
        $phpFiles[]=$path;
    }
    sort($phpFiles,SORT_STRING);
    echo "Linting ".count($phpFiles)." PHP files...\n";
    foreach($phpFiles as $file)run_cmd('lint '.substr($file,strlen($root)+1),[$php,'-l',$file],$root);

    // 2. Every root regression test is automatically owned by this gate.
    $tests=glob($root.'/test-*.php')?:[];
    sort($tests,SORT_STRING);
    foreach($tests as $file){
        if(basename($file)===basename(__FILE__))continue;
        run_cmd('test '.basename($file),[$php,'-d','zend.assertions=1','-d','assert.exception=1',$file],$root);
    }

    // 3. Runnable PHP examples.
    $examples=glob($root.'/examples/*.php')?:[];
    sort($examples,SORT_STRING);
    foreach($examples as $file)run_cmd('example '.basename($file),[$php,'-d','zend.assertions=1','-d','assert.exception=1',$file],$root);

    // 4. Public CLI paths: JX, PASL, nested PASL, PBC round trip, and x86 emission.
    run_cmd('CLI JX hello',[$php,$root.'/jx-run.php','--print',$root.'/examples/hello.jx'],$root);
    run_cmd('CLI PASL arith',[$php,$root.'/pasm-run.php','--print',$root.'/examples/arith.pasl'],$root);
    if(is_file($root.'/examples/pasl/arith.pasl'))run_cmd('CLI PASL nested arith',[$php,$root.'/pasm-run.php','--print',$root.'/examples/pasl/arith.pasl'],$root);
    if(is_file($root.'/examples/pasl/complex-and-loops.pasl'))run_cmd('CLI PASL complex loops',[$php,$root.'/pasm-run.php','--print',$root.'/examples/pasl/complex-and-loops.pasl'],$root);

    $tmpPbc=sys_get_temp_dir().'/jx-full-gate-'.getmypid().'.pbc';
    run_cmd('CLI compile PBC',[$php,$root.'/pasm-run.php','-o',$tmpPbc,$root.'/examples/arith.pasl'],$root);
    if(!is_file($tmpPbc)||filesize($tmpPbc)===0)throw new RuntimeException('PBC output missing');
    run_cmd('CLI run PBC',[$php,$root.'/pasm-run.php','--print',$tmpPbc],$root);
    @unlink($tmpPbc);

    if(is_file($root.'/examples/pasl/x86-sum.pasl')){
        $tmpAsm=sys_get_temp_dir().'/jx-full-gate-'.getmypid().'.s';
        run_cmd('CLI x86 emission',[$php,$root.'/pasm-run.php','--x86','-o',$tmpAsm,$root.'/examples/pasl/x86-sum.pasl'],$root);
        if(!is_file($tmpAsm)||filesize($tmpAsm)===0)throw new RuntimeException('x86 assembly output missing');
        @unlink($tmpAsm);
    }

    // 5. Every benchmark harness is executable. Defaults intentionally run the harness's own normal path.
    $benches=glob($root.'/benchmark-*.php')?:[];
    sort($benches,SORT_STRING);
    foreach($benches as $file)run_cmd('benchmark '.basename($file),[$php,$file],$root);

    echo "\nPASS FULL RUNNABLE GATE files=".count($phpFiles)." steps=".count($ran)." tests=".(count($tests)-1)." examples=".count($examples)." benchmarks=".count($benches)."\n";
    exit(0);
} catch(Throwable $e){
    fwrite(STDERR,"\nFULL GATE FAIL: {$e->getMessage()}\n");
    if($failures!==[])fwrite(STDERR,json_encode($failures,JSON_PRETTY_PRINT)."\n");
    exit(1);
}
