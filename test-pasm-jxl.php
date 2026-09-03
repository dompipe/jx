<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-lang.php';
require_once __DIR__ . '/pasm-jxl.php';

use pasm\PASMAssembler;
use pasm\PASMBC;
use pasm\PASMJxlCompiler;
use pasm\lang\Engine;

$asm = <<<'PASM'
start:
    MOVI ecx, -4294967301
    MOVR ah, ecx
    ADD adx, ecx, ah
    SUB bdx, adx, ah
    MUL cdx, bdx, ah
    DIV ddx, cdx, ah
    MOD edx, ddx, ah
    AND rdx, edx, ah
    OR adx, rdx, ah
    XOR bdx, adx, ah
    SHL cdx, bdx, ah
    SHR ddx, cdx, ah
    CMP ecx, ah
    JZ branch
    JNZ branch
    JL branch
    JLE branch
    JG branch
    JGE branch
    JMP branch
branch:
    PUSH ecx
    POP ah
    LOAD32 adx, bdx, 255
    STORE32 cdx, ddx, 254
    INC ecx
    DEC ah
    NEG adx
    RET bdx
    ITERF 250
    ITERR 249
    NLOAD cdx, 65535
    NSTORE ddx, 65534
    MCALL0 60000, ecx
    MCALL1 60001, ecx, ah
    MCALL2 60002, ecx, ah, adx
    MCALL3 60003, ecx, ah, adx, bdx
    IRESET 248
    HALT
PASM;

$bridge = new PASMJxlCompiler();
$jxl = $bridge->compile($asm);
assert(PASMJxlCompiler::isJxl($jxl));
assert((strlen($jxl) % PASMJxlCompiler::CELL_BYTES) === 0);

$ops = PASMJxlCompiler::supportedPasmOpcodes();
assert(count($ops) === 38);
assert($ops === range(PASMBC::HALT, PASMBC::JGE));

for ($pc=0; $pc<strlen($jxl); $pc+=6) {
    assert((ord($jxl[$pc]) & 0x80) === 0);
    for ($i=1;$i<6;$i++) assert((ord($jxl[$pc+$i]) & 0x80) !== 0);
}

$roundTripAsm = $bridge->toPasmAssembly($jxl);
$assembler = new PASMAssembler();
assert($assembler->compile($roundTripAsm) === $assembler->compile($asm));

// PASL returns the most recently allocated source variable. Copy the loop sum
// into an explicit final variable rather than using a bare expression statement.
$source = '$sum=0;$i=0;for($i=0;$i!=4;$i++){$sum+=$i;}$result=$sum;';
$engine = new Engine(true,false);
$compiled = $engine->compile($source);
assert(PASMJxlCompiler::isJxl($compiled));
assert($engine->runCode($compiled) === 6);
assert($engine->runSource($source) === 6);

echo 'PASL -> JXL: ok (' . count($ops) . ' PASM opcodes, ' . strlen($jxl) . " fixture bytes, result=6)\n";