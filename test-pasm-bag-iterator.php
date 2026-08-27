<?php declare(strict_types=1);

require_once __DIR__ . '/jx.php';
require_once __DIR__ . '/jx-bag-containers.php';
require_once __DIR__ . '/pasm-bag-iterator.php';

use jx\BagContainers;
use pasm\PASMBagIterator;
use pasm\PASMIterBC;
use pasm\PASMIteratorTable;

$eq = static function(mixed $a, mixed $b, string $label): void {
    if ($a !== $b) {
        fwrite(STDERR, "FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");
        exit(1);
    }
};

$vector = BagContainers::vector(4096, 'int');
$vector->append(10)->append(20)->append(30);
$table = new PASMIteratorTable();
$desc = PASMBagIterator::bind($table, 7, $vector);
$eq(PASMIterBC::encodeForward(7), "\x19\x07", 'forward exact bytes');
$eq(PASMIterBC::encodeReverse(7), "\x1A\x07", 'reverse exact bytes');
$eq(PASMBagIterator::collectForward($table, 7), [10,20,30], 'vector forward');
$desc->resetReverse();
$eq(PASMBagIterator::collectReverse($table, 7), [30,20,10], 'vector reverse');

$map = BagContainers::map(4096, 'int');
$map->emplace('a', 1);
$map->emplace('b', 2);
PASMBagIterator::bind($table, 9, $map);
$eq(PASMBagIterator::collectPairs($table, 9), [
    ['key'=>'a','value'=>1],
    ['key'=>'b','value'=>2],
], 'map key/value forward');

// Binding is stable: later mutation is not visible until rebind.
$map->emplace('c', 3);
$table->descriptor(9)->resetForward();
$eq(PASMBagIterator::collectPairs($table, 9), [
    ['key'=>'a','value'=>1],
    ['key'=>'b','value'=>2],
], 'snapshot stability');
PASMBagIterator::bind($table, 9, $map);
$eq(PASMBagIterator::collectPairs($table, 9), [
    ['key'=>'a','value'=>1],
    ['key'=>'b','value'=>2],
    ['key'=>'c','value'=>3],
], 'rebind sees new revision');

fwrite(STDOUT, "PASS PASM Bag iterator bridge width=2 vector+map forward/reverse\n");
