<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-runtime.php';
require_once __DIR__ . '/pasm-iterator-abi.php';
require_once __DIR__ . '/pasm-bytecode.php';
require_once __DIR__ . '/pasm-bytecode-optimized.php';

use pasm\PASMAssembler;
use pasm\PASMBC;
use pasm\PASMBytecodeVM;
use pasm\PASMIteratorDescriptor;
use pasm\PASMIteratorResult;
use pasm\PASMIteratorTable;
use pasm\PASMOptimizedBytecodeVM;
use pasm\PASMRuntime;

$eq = static function(mixed $a, mixed $b, string $label): void {
    if ($a !== $b) {
        fwrite(STDERR, "FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");
        exit(1);
    }
};

$values = [10,20,30];
$table = new PASMIteratorTable();
$table->bind(new PASMIteratorDescriptor(7, count($values), static fn(int $i): int => $values[$i]));

$forward = (new PASMAssembler())->compile("ITERF 7\nHALT");
$reverse = (new PASMAssembler())->compile("ITERR 7\nHALT");
$eq(bin2hex(substr($forward,0,2)), '1907', 'ITERF exact active bytes');
$eq(bin2hex(substr($reverse,0,2)), '1a07', 'ITERR exact active bytes');
$eq(ord($forward[0]), PASMBC::ITERF, 'active ITERF opcode');
$eq(ord($reverse[0]), PASMBC::ITERR, 'active ITERR opcode');

foreach ([false,true] as $opt) {
    $table->descriptor(7)->resetForward();
    $seen=[];
    for($i=0;$i<4;$i++){
        $vm=$opt
            ? new PASMOptimizedBytecodeVM(new PASMRuntime(),1_000_000,null,null,$table)
            : new PASMBytecodeVM(new PASMRuntime(),1_000_000,null,null,$table);
        $item=$vm->run($forward);
        if(!$item instanceof PASMIteratorResult){fwrite(STDERR,"FAIL iterator result type\n");exit(1);}
        if($item->valid)$seen[]=$item->value;
    }
    $eq($seen,[10,20,30],'active forward '.($opt?'optimized':'plain'));

    $table->descriptor(7)->resetReverse();
    $seen=[];
    for($i=0;$i<4;$i++){
        $vm=$opt
            ? new PASMOptimizedBytecodeVM(new PASMRuntime(),1_000_000,null,null,$table)
            : new PASMBytecodeVM(new PASMRuntime(),1_000_000,null,null,$table);
        $item=$vm->run($reverse);
        if($item->valid)$seen[]=$item->value;
    }
    $eq($seen,[30,20,10],'active reverse '.($opt?'optimized':'plain'));
}

fwrite(STDOUT,"PASS active PASM iterator bytecode ITERF=2B ITERR=2B optimized+plain\n");
