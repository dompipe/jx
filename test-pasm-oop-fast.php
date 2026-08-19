<?php declare(strict_types=1);
require_once __DIR__.'/pasm-oop-containers.php';
use pasm\{Vector,Stack,Queue,Deque,Map,Set,PASMFramePool,PASMSegmentRegistry};
function ok(bool $b,string $m):void{if(!$b)throw new RuntimeException($m);}
$v=new Vector([1,'x',true,null]); ok($v->toArray()===[1,'x',true,null],'vector init'); $v->add(9)->set(0,2); ok($v->get(0)===2,'vector get');
$s=new Stack();$s->push(1)->push(2);ok($s->pop()===2&&$s->peek()===1,'stack');
$q=new Queue();for($i=0;$i<5000;$i++)$q->enqueue($i);for($i=0;$i<5000;$i++)ok($q->dequeue()===$i,'queue order');
$d=new Deque();$d->pushBack(2)->pushFront(1)->pushBack(3);ok($d->popFront()===1&&$d->popBack()===3&&$d->popFront()===2,'deque');
$m=new Map();$m->put('a',1)->put(2,'b');ok($m->get('a')===1&&$m->has(2),'map');
$set=new Set([1,'1',true,null,1]);ok($set->count()===4&&$set->has('1')&&!$set->has(false),'set');
$frames=new PASMFramePool();$segments=new PASMSegmentRegistry();$f1=$frames->create('a');$f2=$frames->create('b');
$a=Vector::forFrame($f1,$segments,[42]);$b=Vector::forFrame($f2,$segments,[123]);ok($a->get(0)===42&&$b->get(0)===123,'frame isolation');
$a->loadRegister('R7');ok($f1->get('R7')===$a->containerId(),'canonical register');
$a->add(99);$dirty=$a->dirtySegments();ok($dirty!==[],'dirty pages');$a->clearDirty();ok($a->dirtySegments()===[],'clear dirty');
$before=$segments->forFrame($f1)->stats();$a->defrag();ok($a->toArray()===[42,99],'defrag data');
echo "PASS pasm oop fast\n";
