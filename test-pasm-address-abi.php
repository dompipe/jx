<?php declare(strict_types=1);
require_once __DIR__.'/pasm-address-abi.php';

use pasm\PASMMethodABI;
use pasm\PASMMethodFamily;
use pasm\PASMNamedMemory;
use pasm\PASMMemorySpace;

function must(bool $ok,string $m):void{if(!$ok){fwrite(STDERR,"FAIL {$m}\n");exit(1);}}

$methods=new PASMMethodABI();
$id=$methods->register(PASMMethodFamily::MAP,3,'BEMPLACE',['EMPLACE','INSERT'],fn($k,$v)=>[$k=>$v]);
must($id===0x1203,'method id 0x1203');
must(PASMMethodABI::bytes($id)==="\x12\x03",'method bytes 12 03');
must($methods->resolve('insert')===$id,'method alias');
must($methods->encodeCall('EMPLACE')==="\x12\x03",'two-byte normal call');
must($methods->invoke("\x12\x03",['x',7])===['x'=>7],'two-byte invoke');
$methods->promote($id,0xE3);
must($methods->encodeCall('BEMPLACE')==="\xE3",'one-byte surfaced call');
must($methods->decodeCall("\xE3")['id']===$id,'surfaced decode');
must($methods->invoke("\xE3",['y',9])===['y'=>9],'surfaced invoke');

$mem=new PASMNamedMemory();
$health=$mem->bind(PASMMemorySpace::BAG,3,'player.health',100);
$tmp=$mem->bind(PASMMemorySpace::LOCAL,7,'damage',12);
must($health===0x0503,'bag memory id');
must($tmp===0x0107,'local memory id');
must(PASMNamedMemory::bytes($health)==="\x05\x03",'bag memory bytes 05 03');
must(PASMNamedMemory::bytes($tmp)==="\x01\x07",'local memory bytes 01 07');
must($mem->readBytes("\x05\x03")===100,'read bytes');
$mem->writeBytes("\x05\x03",88);
must($mem->read('player.health',PASMMemorySpace::BAG)===88,'write/read named');
must($mem->resolve(PASMMemorySpace::LOCAL,'damage')===0x0107,'resolve local');

echo "PASS PASM sorted address ABI method=0x1203 memory=0x0503 promoted=0xE3\n";
