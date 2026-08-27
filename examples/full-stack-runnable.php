<?php declare(strict_types=1);

require_once dirname(__DIR__).'/jx.php';
require_once dirname(__DIR__).'/jx-alias.php';
require_once dirname(__DIR__).'/jx-bag-containers.php';
require_once dirname(__DIR__).'/pasm-runtime.php';
require_once dirname(__DIR__).'/pasm-bytecode.php';
require_once dirname(__DIR__).'/pasm-bytecode-optimized.php';
require_once dirname(__DIR__).'/pasm-loop-space.php';
require_once dirname(__DIR__).'/pasm-iterator-abi.php';
require_once dirname(__DIR__).'/pasm-address-abi.php';
require_once dirname(__DIR__).'/pasm-lang.php';

use jx\BagContainers;
use jx\JxAlias;
use jx\AliasDomain;
use pasm\PASMAssembler;
use pasm\PASMBytecodeVM;
use pasm\PASMRuntime;
use pasm\PASMIteratorDescriptor;
use pasm\PASMIteratorTable;
use pasm\PASMIterBC;
use pasm\PASMMethodABI;
use pasm\PASMMethodFamily;
use pasm\PASMNamedMemory;
use pasm\PASMMemorySpace;
use pasm\lang\Engine;

function check_it(bool $ok,string $label):void{if(!$ok)throw new RuntimeException("full-stack check failed: {$label}");}

// Aliases collapse before lowering.
check_it(JxAlias::canonical(AliasDomain::BAG_HOT,'insert')==='BEMPLACE','alias canonical BEMPLACE');

// Bag disciplines and BEMPLACE fallback behavior.
$vector=BagContainers::vector(4096,'int');
$vector->append(1)->append(3)->emplace(1,2);
check_it($vector->toArray()===[1,2,3],'vector emplace');
$map=BagContainers::map(4096,'int');
$map->emplace('a',1);$map->emplace('a',99);$map->emplace('b',2);
check_it($map->toArray()===['a'=>1,'b'=>2],'map emplace absent only');
$set=BagContainers::set(4096,'int');
$set->emplace(1);$set->emplace(1);$set->emplace(2);
check_it($set->count()===2,'set uniqueness');
$vector->checkpoint();

// Named memory is human-linked once, then 2-byte space+slot addressing.
$memory=new PASMNamedMemory();
$health=$memory->bind(PASMMemorySpace::BAG,3,'player.health',100);
$damage=$memory->bind(PASMMemorySpace::LOCAL,7,'damage',12);
check_it(PASMNamedMemory::bytes($health)==="\x05\x03",'named memory bytes');
$memory->write($health,$memory->read($health)-$memory->read($damage));
check_it($memory->readBytes("\x05\x03")===88,'named memory execution');

// Sorted method identity and a one-byte promoted hot method.
$methods=new PASMMethodABI();
$emplace=$methods->register(PASMMethodFamily::MAP,3,'BEMPLACE',['INSERT'],function($k,$v)use($map){return $map->emplace($k,$v);});
check_it(PASMMethodABI::bytes($emplace)==="\x12\x03",'method bytes');
$methods->invoke("\x12\x03",['c',3]);
$methods->promote($emplace,0xE3);
$methods->invoke("\xE3",['d',4]);
check_it($map->get('c')===3&&$map->get('d')===4,'method invoke normal+promoted');

// 2-byte forward/reverse iterator calls over the Bag-backed vector.
$values=$vector->toArray();
$table=new PASMIteratorTable();
$table->bind(new PASMIteratorDescriptor(7,count($values),fn(int $i)=>$values[$i]));
$f=[];while(($x=$table->execute(PASMIterBC::encodeForward(7)))->valid)$f[]=$x->value;
check_it($f===[1,2,3],'forward iterator');
$table->descriptor(7)->resetReverse();
$r=[];while(($x=$table->execute(PASMIterBC::encodeReverse(7)))->valid)$r[]=$x->value;
check_it($r===[3,2,1],'reverse iterator');

// Active mixed packed PASM execution with labels.
$asm=<<<'ASM'
MOVI ecx 0
MOVI ah 1
MOVI adx 5
loop:
ADD ecx ecx ah
DEC adx
MOVI bdx 0
CMP adx bdx
JNZ loop
RET ecx
ASM;
$code=(new PASMAssembler())->compile($asm);
$result=(new PASMBytecodeVM(new PASMRuntime(),100))->run($code);
check_it($result===5,'packed PASM loop');

// PASL through both execution facades must agree on runnable loop code.
$pasl='$sum=0;$i=0;for($i=0;$i!=5;$i++){$sum+=$i;}';
$plain=(new Engine(false,false))->runSource($pasl);
$optimized=(new Engine(true,false))->runSource($pasl);
check_it($plain===$optimized,'optimized/unoptimized PASL ABI agreement');

$out=[
 'status'=>'PASS',
 'vector'=>$vector->toArray(),
 'map'=>$map->toArray(),
 'named_health'=>$memory->read($health),
 'method_id'=>sprintf('0x%04X',$emplace),
 'method_hot_opcode'=>'0xE3',
 'iterator_forward'=>$f,
 'iterator_reverse'=>$r,
 'packed_bytes'=>strlen($code),
 'pasl_result'=>$optimized,
];
echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
