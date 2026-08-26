<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-bag-hotops.php';

use pasm\PASMBagHotOp;

$expect = static function (mixed $actual, mixed $expected, string $label): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected,true) . " got " . var_export($actual,true) . "\n");
        exit(1);
    }
};

$expect(PASMBagHotOp::canonical('push'), 'BPUSH', 'push alias');
$expect(PASMBagHotOp::canonical('append'), 'BPUSH', 'append alias');
$expect(PASMBagHotOp::canonical('enqueue'), 'BPUSH', 'enqueue alias');
$expect(PASMBagHotOp::canonical('pop'), 'BPOP', 'pop alias');
$expect(PASMBagHotOp::canonical('dequeue'), 'BPOP', 'dequeue alias');
$expect(PASMBagHotOp::canonical('shift'), 'BPOPF', 'shift alias');
$expect(PASMBagHotOp::canonical('unshift'), 'BPUSHF', 'unshift alias');

$cases = [
    ['BPUSH','vector',['mov [cursor], value','add cursor, width']],
    ['BPOP','stack',['sub cursor, width','mov value, [cursor]']],
    ['BPUSH','queue',['mov [tail], value','add tail, width']],
    ['BPOP','queue',['mov value, [head]','add head, width']],
    ['BPUSHF','deque',['sub head, width','mov [head], value']],
    ['BPOPB','deque',['sub tail, width','mov value, [tail]']],
];
foreach ($cases as [$op,$discipline,$asm]) {
    $lower = PASMBagHotOp::lowering($op,$discipline);
    $expect($lower['asm'],$asm,"{$op}/{$discipline}");
    if (count($lower['asm']) > 2) {
        fwrite(STDERR,"FAIL {$op}/{$discipline}: expected <=2 hot instructions\n");
        exit(1);
    }
}

$reserve = PASMBagHotOp::lowering('reserve','vector');
$expect(count($reserve['asm']),3,'reserve is three-line guard');

echo "PASS PASM Bag hot ops aliases=" . count(PASMBagHotOp::aliases()) . "\n";
