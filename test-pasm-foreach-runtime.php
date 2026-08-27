<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-lang.php';
require_once __DIR__ . '/pasm-iterator-abi.php';
require_once __DIR__ . '/pasm-bytecode.php';

use pasm\PASMAssembler;
use pasm\PASMBC;
use pasm\PASMIterBC;
use pasm\lang\Engine;

$eq=static function(mixed $a,mixed $b,string $label):void{
    if($a!==$b){fwrite(STDERR,"FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");exit(1);}
};

// The ABI itself stays two bytes: one opcode + one u8 slot.
$asm=new PASMAssembler();
$eq(strlen($asm->compile('ITERF 7')),2,'ITERF width');
$eq(strlen($asm->compile('ITERR 7')),2,'ITERR width');
$eq(strlen($asm->compile('IRESET 7')),2,'IRESET width');
$eq(bin2hex($asm->compile('ITERF 7')),sprintf('%02x07',PASMBC::ITERF),'ITERF bytes');
$eq(bin2hex($asm->compile('ITERR 7')),sprintf('%02x07',PASMBC::ITERR),'ITERR bytes');
$eq(bin2hex($asm->compile('IRESET 7')),sprintf('%02x07',PASMBC::IRESET),'IRESET bytes');
$eq(PASMIterBC::WIDTH,2,'iterator ABI width');

foreach([false,true] as $opt){
    $mode=$opt?'O1':'O0';

    $e=new Engine($opt,false);
    $e->bindCollection('nums',[1,2,3,4]);
    $src=<<<'PASL'
$sum = 0;
foreach ($nums as $v) {
    $sum += $v;
}
$result = $sum;
PASL;
    $eq($e->runSource($src),10,"foreach forward {$mode}");
    $binding=$e->iteratorBindings()[0]??null;
    $eq($binding['reverse']??null,false,"foreach direction {$mode}");

    $e=new Engine($opt,false);
    $e->bindCollection('nums',[1,2,3,4]);
    $src=<<<'PASL'
$acc = 0;
reveach ($nums as $v) {
    $acc = $acc * 10 + $v;
}
$result = $acc;
PASL;
    $eq($e->runSource($src),4321,"reveach reverse {$mode}");
    $eq($e->iteratorBindings()[0]['reverse']??null,true,"reveach direction {$mode}");

    $e=new Engine($opt,false);
    $e->bindCollection('pairs',[2=>10,5=>20,9=>30]);
    $src=<<<'PASL'
$sum = 0;
foreach ($pairs as $k => $v) {
    $sum += $k + $v;
}
$result = $sum;
PASL;
    $eq($e->runSource($src),76,"foreach key value {$mode}");
    $binding=$e->iteratorBindings()[0];
    if($binding['key_reg']===null){fwrite(STDERR,"FAIL key register not prelinked {$mode}\n");exit(1);}

    // Inner loop site is re-entered for every outer item. Without IRESET this
    // returns 7 rather than 21 after the first inner traversal is exhausted.
    $e=new Engine($opt,false);
    $e->bindCollection('a',[1,2]);
    $e->bindCollection('b',[3,4]);
    $src=<<<'PASL'
$sum = 0;
foreach ($a as $x) {
    foreach ($b as $y) {
        $sum += $x * $y;
    }
}
$result = $sum;
PASL;
    $eq($e->runSource($src),21,"nested foreach reset {$mode}");
    $eq(count($e->iteratorBindings()),2,"nested iterator sites {$mode}");

    // Same foreach site is re-entered after an early break.
    $e=new Engine($opt,false);
    $e->bindCollection('nums',[1,2,3]);
    $src=<<<'PASL'
$sum = 0;
repeat (2) {
    foreach ($nums as $v) {
        $sum += $v;
        break;
    }
}
$result = $sum;
PASL;
    $eq($e->runSource($src),2,"break then re-enter foreach {$mode}");
}

fwrite(STDOUT,"PASS PASL foreach/reveach runtime 2-byte iterator ABI nested reset key/value optimized+plain\n");
