<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-iterator-abi.php';

use pasm\PASMIterBC;
use pasm\PASMIteratorDescriptor;
use pasm\PASMIteratorTable;

$eq = static function(mixed $a, mixed $b, string $label): void {
    if ($a !== $b) {
        fwrite(STDERR, "FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");
        exit(1);
    }
};

$f = PASMIterBC::encodeForward(7);
$r = PASMIterBC::encodeReverse(7);
$eq(strlen($f), 2, 'forward width');
$eq(strlen($r), 2, 'reverse width');
$eq(bin2hex($f), '1907', 'forward encoding');
$eq(bin2hex($r), '1a07', 'reverse encoding');
$eq(PASMIterBC::decode($f)['slot'], 7, 'one-byte slot');
$eq(PASMIterBC::decode($r)['reverse'], true, 'reverse opcode');

$values = [10,20,30,40];
$table = new PASMIteratorTable();
$table->bind(new PASMIteratorDescriptor(
    slot: 7,
    count: count($values),
    read: static fn(int $i) => $values[$i],
));

$out=[];
while (($x=$table->execute($f))->valid) $out[]=$x->value;
$eq($out, [10,20,30,40], 'forward values');

$table->replace(new PASMIteratorDescriptor(
    slot: 7,
    count: count($values),
    read: static fn(int $i) => $values[$i],
));
$out=[];
while (($x=$table->execute($r))->valid) $out[]=$x->value;
$eq($out, [40,30,20,10], 'reverse values');

fwrite(STDOUT, "PASS PASM iterator ABI width=2 slots=256 forward=0x19 reverse=0x1A\n");
