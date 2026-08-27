<?php declare(strict_types=1);

require_once __DIR__ . '/jx-shadow-runtime.php';

use jx\CallbackExecutableShadow;
use jx\MutableSource;
use jx\PASMExecutableShadow;
use jx\ReactiveShadowRuntime;

$eq=static function(mixed $a,mixed $b,string $label):void{
    if($a!==$b){fwrite(STDERR,"FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");exit(1);}
};

$a=new MutableSource('shadow-a',2);
$b=new MutableSource('shadow-b',3);
$c=new MutableSource('shadow-c',10);

$rt=new ReactiveShadowRuntime(true);
$rt->addSource($a)->addSource($b)->addSource($c);

// This is a real PASM execution shadow. JX/PHP-ish source is not revisited on
// reactive dispatch; source identities are prelinked directly to registers.
$sum=new PASMExecutableShadow(
    'shadow.sum',
    [$a->id()=>'ecx',$b->id()=>'ah'],
    "ADD bdx ecx ah\nRET bdx\n"
);
$rt->addShadow($sum,true);
$eq($sum->runs(),1,'initial PASM shadow run');
$eq($sum->lastResult(),5,'initial PASM result');
$eq($sum->bytecodeBytes(),5,'minimal PASM shadow bytes');

// An unrelated source has no edge to shadow.sum and must not execute it.
$c->set(11);
$eq($sum->runs(),1,'unrelated source does not rerun PASM shadow');
$eq($sum->lastResult(),5,'unrelated source leaves result');

// Direct dependency dispatches only the indexed shadow.
$a->set(7);
$eq($sum->runs(),2,'dependency reruns PASM shadow');
$eq($sum->lastResult(),10,'dependency result');

// Second shadow depends only on c; a/b must not touch it.
$hostRuns=0;
$host=new CallbackExecutableShadow('shadow.c',[$c->id()],function(ReactiveShadowRuntime $runtime)use(&$hostRuns,$c):int{
    $hostRuns++;
    return (int)$runtime->source($c->id())->value()*2;
});
$rt->addShadow($host,true);
$eq($hostRuns,1,'host initial');
$b->set(8);
$eq($sum->runs(),3,'b reruns PASM shadow');
$eq($sum->lastResult(),15,'b result');
$eq($hostRuns,1,'b does not rerun c shadow');
$c->set(12);
$eq($hostRuns,2,'c reruns only c shadow');
$eq($host->lastResult(),24,'c result');
$eq($sum->runs(),3,'c still does not rerun sum');

$idx=$rt->dependencyIndex();
$eq($idx[$a->id()]??null,['shadow.sum'],'a dependency index');
$eq($idx[$c->id()]??null,['shadow.c'],'c dependency index');

// Batch mode: two dependency changes dirty the same shadow, settle executes it once.
$x=new MutableSource('batch-x',1);
$y=new MutableSource('batch-y',2);
$batch=new ReactiveShadowRuntime(false);
$batch->addSource($x)->addSource($y);
$xy=new PASMExecutableShadow('shadow.xy',[$x->id()=>'ecx',$y->id()=>'ah'],"ADD bdx ecx ah\nRET bdx\n");
$batch->addShadow($xy,true);
$x->set(20);
$y->set(30);
$eq($xy->runs(),1,'batch does not autorun');
$eq($xy->dirty(),true,'batch shadow dirty');
$eq($batch->settle(),1,'batch settles one shadow once');
$eq($xy->runs(),2,'batch single rerun');
$eq($xy->lastResult(),50,'batch result');
$eq($batch->settle(),0,'clean settle no work');

fwrite(STDOUT,"PASS JX dependency-indexed executable shadows PASM selective dispatch batch-settle\n");
