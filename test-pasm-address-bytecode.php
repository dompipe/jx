<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-runtime.php';
require_once __DIR__ . '/pasm-bytecode.php';
require_once __DIR__ . '/pasm-bytecode-optimized.php';
require_once __DIR__ . '/pasm-address-abi.php';

use pasm\PASMAssembler;
use pasm\PASMBC;
use pasm\PASMBytecodeVM;
use pasm\PASMMethodABI;
use pasm\PASMMethodFamily;
use pasm\PASMMemorySpace;
use pasm\PASMNamedMemory;
use pasm\PASMOptimizedBytecodeVM;
use pasm\PASMRuntime;

$eq = static function(mixed $a, mixed $b, string $label): void {
    if ($a !== $b) {
        fwrite(STDERR, "FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");
        exit(1);
    }
};

$memory = new PASMNamedMemory();
$health = $memory->bind(PASMMemorySpace::BAG, 3, 'player.health', 100);
$eq($health, 0x0503, 'named address id');

$methods = new PASMMethodABI();
$method = $methods->register(
    PASMMethodFamily::MAP,
    3,
    'BEMPLACE',
    ['EMPLACE'],
    static fn(int $a, int $b): int => $a + $b,
);
$eq($method, 0x1203, 'method id');

$asm = <<<'PASM'
NLOAD ecx 0x0503
MOVI ah 12
SUB ecx ecx ah
NSTORE ecx 0x0503
MOVI adx 5
MOVI bdx 7
MCALL2 0x1203 cdx adx bdx
RET cdx
PASM;

$code = (new PASMAssembler())->compile($asm);
$eq(ord($code[0]), PASMBC::NLOAD, 'NLOAD opcode');
$eq(bin2hex(substr($code, 2, 2)), '0503', 'NLOAD exact two-byte address');

$mc = strpos($code, chr(PASMBC::MCALL2));
if ($mc === false) { fwrite(STDERR, "FAIL MCALL2 opcode missing\n"); exit(1); }
$eq(bin2hex(substr($code, $mc + 1, 2)), '1203', 'MCALL exact two-byte method id');

foreach ([false,true] as $opt) {
    $memory->write($health, 100);
    $rt = new PASMRuntime();
    $vm = $opt
        ? new PASMOptimizedBytecodeVM($rt, 1_000_000, $memory, $methods)
        : new PASMBytecodeVM($rt, 1_000_000, $memory, $methods);
    $eq($vm->run($code), 12, 'method return '.($opt?'optimized':'plain'));
    $eq($memory->read($health), 88, 'named memory store '.($opt?'optimized':'plain'));
}

fwrite(STDOUT, "PASS active PASM named-memory/method bytecode address=2B method=2B\n");
