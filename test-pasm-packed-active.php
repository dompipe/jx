<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-bytecode.php';

use pasm\PASM;
use pasm\PASMAssembler;
use pasm\PASMBytecodeVM;
use pasm\PASMRuntime;

$eq = static function(mixed $a, mixed $b, string $label): void {
    if ($a !== $b) {
        fwrite(STDERR, "FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");
        exit(1);
    }
};

$asm = new PASMAssembler();
$src = <<<'PASM'
MOVI ecx 0
MOVI ah 1
MOVI adx 5
loop:
ADD ecx ecx ah
INC ah
CMP ah adx
JNZ loop
RET ecx
PASM;
$code = $asm->compile($src);
$vm = new PASMBytecodeVM(new PASMRuntime(), 1000);
$ret = $vm->run($code);
$eq($ret, 10, 'mixed packed loop result');
$eq(PASM::$ecx, 10, 'register flush');

// Packed widths are now active assembler widths.
$eq(strlen($asm->compile('MOVR ecx ah')), 2, 'MOVR packed width');
$eq(strlen($asm->compile('ADD ecx ecx ah')), 3, 'ADD packed width');
$eq(strlen($asm->compile('CMP ecx ah')), 2, 'CMP packed width');
$eq(strlen($asm->compile('INC ecx')), 2, 'INC packed width');

// Extended forms keep their widths.
$eq(strlen($asm->compile('MOVI ecx 1')), 10, 'MOVI extended width');
$eq(strlen($asm->compile('JMP 0')), 5, 'JMP extended width');
$eq(strlen($asm->compile('LOAD32 ecx ah 4')), 4, 'LOAD32 extended width');

fwrite(STDOUT, "PASS active PASM packed register bytecode mixed=".strlen($code)."B\n");
