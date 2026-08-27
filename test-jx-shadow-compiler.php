<?php declare(strict_types=1);

require_once __DIR__ . '/jx-shadow-compiler.php';

use jx\MutableSource;
use jx\ReactiveShadowCompiler;
use jx\ReactiveShadowRuntime;

$eq=static function(mixed $a,mixed $b,string $label):void{
    if($a!==$b){fwrite(STDERR,"FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");exit(1);}
};

foreach([false,true] as $opt){
    $mode=$opt?'O1':'O0';
    $left=new MutableSource('compiler-left-'.$mode,2);
    $right=new MutableSource('compiler-right-'.$mode,3);
    $noise=new MutableSource('compiler-noise-'.$mode,99);
    $runtime=new ReactiveShadowRuntime(true);
    $runtime->addSource($noise);

    $compiler=new ReactiveShadowCompiler($opt);
    $shadow=$compiler->compileInto($runtime,'compiled.total.'.$mode,<<<'PASL'
$sum = $left + $right;
$result = $sum * 2;
PASL,[
        'left'=>$left,
        'right'=>$right,
    ],true);

    $eq($shadow->runs(),1,"initial runs {$mode}");
    $eq($shadow->lastResult(),10,"initial result {$mode}");
    if($shadow->bytecodeBytes()<=0){fwrite(STDERR,"FAIL no bytecode {$mode}\n");exit(1);}

    $deps=$shadow->dependencies();
    sort($deps);
    $expected=[$left->id(),$right->id()];sort($expected);
    $eq($deps,$expected,"compiler dependency ids {$mode}");

    // No source edge means no dispatch.
    $noise->set(100);
    $eq($shadow->runs(),1,"unrelated source no rerun {$mode}");

    $left->set(5);
    $eq($shadow->runs(),2,"left rerun {$mode}");
    $eq($shadow->lastResult(),16,"left result {$mode}");

    $right->set(10);
    $eq($shadow->runs(),3,"right rerun {$mode}");
    $eq($shadow->lastResult(),30,"right result {$mode}");

    $idx=$runtime->dependencyIndex();
    $eq($idx[$left->id()]??null,['compiled.total.'.$mode],"left index {$mode}");
    $eq(isset($idx[$noise->id()]),false,"noise absent from index {$mode}");
}

fwrite(STDOUT,"PASS JX reactive shadow compiler automatic source->register->PASM dependency lowering O0+O1\n");
