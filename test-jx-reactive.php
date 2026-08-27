<?php declare(strict_types=1);

require_once __DIR__ . '/jx-reactive.php';

use jx\BagContainers;
use jx\BagReactiveSource;
use jx\DerivedReactiveSource;
use jx\EnvironmentProfile;
use jx\FileReactiveSource;
use jx\MediaReactiveSource;
use jx\MutableSource;
use jx\ReactiveGraph;
use jx\JxSql;
use jx\SqlConfig;
use jx\SqlReactiveSource;

$eq=static function(mixed $a,mixed $b,string $label):void{
    if($a!==$b){fwrite(STDERR,"FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");exit(1);}
};

// Dependency-selective derived shadows.
$a=new MutableSource('a',2);
$b=new MutableSource('b',3);
$unrelated=new MutableSource('unrelated',100);
$sum=new DerivedReactiveSource('sum',[$a,$b],static fn($x,$y)=>$x+$y);
$eq($sum->value(),5,'initial derived value');
$eq($sum->runs(),1,'initial derived run');
$unrelated->set(200);
$sum->refresh();
$eq($sum->runs(),1,'unrelated source does not rerun shadow');
$a->set(7);
$sum->refresh();
$eq($sum->value(),10,'dependent change recomputes');
$eq($sum->runs(),2,'dependent change one rerun');
$a->set(7);
$sum->refresh();
$eq($sum->runs(),2,'same value does not dirty');

// Bag source publishes only on Bag revision change.
$vector=BagContainers::vector(4096,'int');
$vector->append(10)->append(20);
$bagSource=new BagReactiveSource('numbers',$vector);
$bagRev=$bagSource->revision();
$eq($bagSource->refresh(),false,'unchanged Bag no publish');
$vector->append(30);
$eq($bagSource->refresh(),true,'Bag revision publishes');
$eq($bagSource->revision(),$bagRev+1,'Bag reactive revision increments once');

// File/media source: canonical refresh boundary works without requiring a watcher implementation.
$tmp=tempnam(sys_get_temp_dir(),'jx-reactive-');
if($tmp===false){fwrite(STDERR,"FAIL temp file\n");exit(1);}
file_put_contents($tmp,'abc');
$file=new FileReactiveSource($tmp,EnvironmentProfile::test('reactive-file'));
$media=new MediaReactiveSource($tmp,EnvironmentProfile::test('reactive-media'));
$fileRev=$file->revision();
$mediaRev=$media->revision();
file_put_contents($tmp,'abcdefghi');
$eq($file->refresh(),true,'file size change publishes');
$eq($media->refresh(),true,'media file change publishes');
$eq($file->revision(),$fileRev+1,'file revision');
$eq($media->revision(),$mediaRev+1,'media revision');
$eq($media->value()['size'],9,'media metadata size');
unlink($tmp);

// SQL query source reruns a prepared query and only publishes when rows change.
$sql=new JxSql(SqlConfig::sqliteMemory(EnvironmentProfile::test('reactive-sql')));
$sql->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, value INTEGER NOT NULL)');
$sql->execute('INSERT INTO items(value) VALUES (?)',[5]);
$sqlSource=new SqlReactiveSource('items',$sql,'SELECT value FROM items ORDER BY id');
$sqlRev=$sqlSource->revision();
$eq($sqlSource->refresh(),false,'unchanged SQL result no publish');
$sql->execute('INSERT INTO items(value) VALUES (?)',[8]);
$eq($sqlSource->refresh(),true,'SQL row change publishes');
$eq($sqlSource->revision(),$sqlRev+1,'SQL source revision');
$eq(array_column($sqlSource->value()['rows'],'value'),[5,8],'SQL reactive rows');

// Graph settles source changes into only dependent derived nodes.
$graph=new ReactiveGraph();
$left=new MutableSource('graph-left',1);
$right=new MutableSource('graph-right',2);
$noise=new MutableSource('graph-noise',9);
$total=new DerivedReactiveSource('graph-total',[$left,$right],static fn($x,$y)=>$x+$y);
$graph->add($left);$graph->add($right);$graph->add($noise);$graph->add($total);
$before=$total->runs();
$noise->set(10);
$graph->refresh();
$eq($total->runs(),$before,'graph unrelated change skips derived shadow');
$left->set(20);
$graph->refresh();
$eq($total->value(),22,'graph dependent change settles');
$eq($total->runs(),$before+1,'graph reruns affected shadow once');

fwrite(STDOUT,"PASS JX reactive graph mutable Bag file/media SQL selective-derived shadows\n");
