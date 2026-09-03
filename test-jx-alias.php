<?php declare(strict_types=1);

require_once __DIR__ . '/jx-alias.php';
require_once __DIR__ . '/pasm-bag-hotops.php';

use jx\AliasDomain;
use jx\JxAlias;
use pasm\PASMBagHotOp;

$expect = static function (mixed $actual, mixed $expected, string $label): void {
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected,true) . " got " . var_export($actual,true) . "\n");
        exit(1);
    }
};

$expect(JxAlias::canonical(AliasDomain::BOOK, 'load'), 'OPEN', 'book load');
$expect(JxAlias::canonical(AliasDomain::BAG, 'allocate'), 'UNDERWRITE', 'bag allocate');
$expect(JxAlias::canonical(AliasDomain::DELIVERY, 'deliver'), 'EXTRACT', 'delivery deliver');
$expect(JxAlias::canonical(AliasDomain::SQL, 'exec'), 'EXECUTE', 'sql exec');
$expect(JxAlias::canonical(AliasDomain::CHART, 'draw'), 'RENDER', 'chart draw');
$expect(JxAlias::canonical(AliasDomain::EVENT, 'trigger'), 'EMIT', 'event trigger');
$expect(JxAlias::canonical(AliasDomain::PASM, 'jne'), 'JNZ', 'pasm jne');
$expect(JxAlias::canonical(AliasDomain::BAG_HOT, 'packin'), 'BEMPLACE', 'packin emplace');
$expect(JxAlias::canonical(AliasDomain::BAG_HOT, 'putifabsent'), 'BEMPLACE', 'map emplace alias');

$prov = JxAlias::resolve(AliasDomain::BAG_HOT, 'enqueue', 'queue')->provenance();
$expect($prov['source_spelling'], 'enqueue', 'provenance spelling');
$expect($prov['canonical_op'], 'BPUSH', 'provenance canonical');
$expect($prov['alias_context'], 'queue', 'provenance context');

$surface = JxAlias::canonicalizeSurface('book = Book.load("demo")');
$expect($surface, 'book = Book.open("demo")', 'surface Book.load');
$surface = JxAlias::canonicalizeSurface('bag = Bag.allocate(4096)');
$expect($surface, 'bag = Bag.underwrite(4096)', 'surface Bag.allocate');
$surface = JxAlias::canonicalizeSurface('x = deliver(root, "a.b")');
$expect($surface, 'x = delivery(root, "a.b")', 'surface deliver');

$map = PASMBagHotOp::lowering('emplace', 'map');
$expect($map['kind'], 'ordered-keyed-vector-emplace', 'map emplace lowering');
$expect($map['find_once'], true, 'map single position find');
$expect($map['layout'], ['Entry[]','Entry=[key,value]'], 'map keyed-vector layout');
$expect($map['entry_width'], 'key_width + value_width', 'map entry width');
$expect($map['hashing'], false, 'map hashing prohibited');
$expect($map['insert_if_absent'], true, 'map absent semantics');
$set = PASMBagHotOp::lowering('insert', 'set');
$expect($set['kind'], 'ordered-unique-array-emplace', 'set emplace lowering');
$expect($set['layout'], ['keys[]'], 'set 1D array layout');
$expect($set['hashing'], false, 'set hashing prohibited');
$vector = PASMBagHotOp::lowering('packin', 'vector');
$expect($vector['bulk_move'], true, 'vector bulk pack');
$expect(count($vector['asm']), 3, 'vector emplace three-line semantic lowering');

JxAlias::registerPlugin('jx.test', 'DO_THING', ['THING','DOIT']);
$expect(JxAlias::canonical(AliasDomain::PLUGIN, 'doit', 'jx.test'), 'DO_THING', 'plugin contextual alias');

$collision = false;
try {
    JxAlias::register(AliasDomain::SQL, 'OTHER', ['EXEC']);
} catch (InvalidArgumentException) {
    $collision = true;
}
$expect($collision, true, 'collision rejected');

echo 'PASS JX aliases domains=' . count([
    AliasDomain::BAG, AliasDomain::BAG_HOT, AliasDomain::BOOK, AliasDomain::TASK,
    AliasDomain::PAGE, AliasDomain::DELIVERY, AliasDomain::FUNCTION_, AliasDomain::METHOD,
    AliasDomain::CONTROL, AliasDomain::STYLE, AliasDomain::EVENT, AliasDomain::CHANNEL,
    AliasDomain::SQL, AliasDomain::CHART, AliasDomain::HOST, AliasDomain::WINDOW,
    AliasDomain::LIBRARY, AliasDomain::PLUGIN, AliasDomain::PASL, AliasDomain::PASM,
]) . "\n";
