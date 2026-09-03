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
$expect(PASMBagHotOp::canonical('emplace'), 'BEMPLACE', 'emplace alias');
$expect(PASMBagHotOp::canonical('packin'), 'BEMPLACE', 'packin alias');
$expect(PASMBagHotOp::canonical('putifabsent'), 'BEMPLACE', 'map emplace alias');

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

$vector = PASMBagHotOp::lowering('emplace','vector');
$expect($vector['kind'],'address-gap-pack-store','vector emplace kind');
$expect($vector['bulk_move'],true,'vector emplace bulk move');
$expect($vector['overlap_safe'],true,'vector emplace overlap safe');
$expect(count($vector['asm']),3,'vector emplace semantic lines');

$map = PASMBagHotOp::lowering('putifabsent','map');
$expect($map['kind'],'ordered-keyed-vector-emplace','map emplace kind');
$expect($map['find_once'],true,'map find once');
$expect($map['layout'],['Entry[]','Entry=[key,value]'],'map keyed-vector layout');
$expect($map['entry_width'],'key_width + value_width','map entry stride');
$expect($map['hashing'],false,'map cannot hash');
$expect($map['insert_if_absent'],true,'map absent-only');

$set = PASMBagHotOp::lowering('addifabsent','set');
$expect($set['kind'],'ordered-unique-array-emplace','set emplace kind');
$expect($set['find_once'],true,'set find once');
$expect($set['layout'],['keys[]'],'set 1D array layout');
$expect($set['hashing'],false,'set cannot hash');
$expect($set['insert_if_absent'],true,'set absent-only');

echo "PASS PASM Bag hot ops aliases=" . count(PASMBagHotOp::aliases()) . " keyed-vector-map-set\n";
