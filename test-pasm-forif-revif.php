<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-lang.php';
require_once __DIR__ . '/pasm-foreach-pass.php';

use pasm\lang\Engine;
use pasm\lang\PASMForeachPass;

$forward = new Engine(true, false);
$forward->bindCollection('values', [1, 2, 3, 4, 5, 6]);
$sum = $forward->runSource(<<<'PASL'
$total = 0;
forif ($values as $value if _ % 2 == 0) {
    $total += _;
}
$result = $total;
PASL);
if ($sum !== 12) throw new RuntimeException('forif forward filter/current-value operator failed');

$pythonLike = new Engine(true, false);
$pythonLike->bindCollection('values', [1, 2, 3, 4, 5]);
$sumPython = $pythonLike->runSource(<<<'PASL'
$total = 0;
forif ($value in $values if _ > 2) $total += _;
$result = $total;
PASL);
if ($sumPython !== 12) throw new RuntimeException('forif Python-like inline form failed');

$reverse = new Engine(true, false);
$reverse->bindCollection('digits', [1, 2, 3, 4, 5]);
$number = $reverse->runSource(<<<'PASL'
$result = 0;
revif ($digit in $digits if _ >= 3) {
    $result = $result * 10 + _;
}
PASL);
if ($number !== 543) throw new RuntimeException('revif reverse filter failed');

$keyed = new Engine(true, false);
$keyed->bindCollection('weights', [2 => 10, 5 => 20, 9 => 30]);
$keySum = $keyed->runSource(<<<'PASL'
$total = 0;
forif ($weights as $key => $value if _ >= 20) {
    $total += $key + _;
}
$result = $total;
PASL);
if ($keySum !== 64) throw new RuntimeException('forif keyed Map filter failed');

// Callback contract: `_` is argument zero/current value. This assertion tests
// surface lowering independently of whether a particular callback host is linked.
$pass = new PASMForeachPass(['values']);
$lowered = $pass->lower('forif ($value in $values if _ > 0) callback(_, $value);');
if (!str_contains($lowered, 'callback($value, $value)')) {
    throw new RuntimeException('_ must lower as callback argument zero/current value');
}
if (str_contains($lowered, 'callback(_, ')) {
    throw new RuntimeException('raw _ leaked through filtered-loop callback lowering');
}

fwrite(STDOUT, "forif/revif: ok\n");
