<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-lang.php';

use pasm\lang\Engine;

$vector = new Engine(true, false);
$vector->bindCollection('items', [10, 20, 30]);
if ($vector->collectionKind('items') !== 'vector') {
    throw new RuntimeException('Flat arrays must canonicalize to Vector foreach admission');
}
$vectorResult = $vector->runSource(<<<'PASL'
$total = 0;
foreach ($items as $key => $value) {
    $total += $key * 100 + $value;
}
$result = $total;
PASL);
if ($vectorResult !== 360) {
    throw new RuntimeException('Vector foreach must use numeric position keys 0..n-1');
}

$map = new Engine(true, false);
$map->bindCollection('weights', [2 => 10, 5 => 20, 9 => 30]);
if ($map->collectionKind('weights') !== 'map') {
    throw new RuntimeException('Keyed arrays must canonicalize to Map foreach admission');
}
$mapResult = $map->runSource(<<<'PASL'
$total = 0;
foreach ($weights as $key => $value) {
    $total += $key + $value;
}
$result = $total;
PASL);
if ($mapResult !== 76) {
    throw new RuntimeException('Map foreach must preserve explicit keys');
}

$generator = static function (): Generator {
    yield 'alpha' => 7;
    yield 'beta' => 8;
};
$iterableMap = new Engine(true, false);
$iterableMap->bindCollection('named', $generator());
if ($iterableMap->collectionKind('named') !== 'map') {
    throw new RuntimeException('Keyed iterables must canonicalize once to Map admission');
}

fwrite(STDOUT, "canonical foreach collections: ok\n");
