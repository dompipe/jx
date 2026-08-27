<?php declare(strict_types=1);

require_once __DIR__ . '/../pasm-lang.php';

use pasm\lang\Engine;

$engine = new Engine(true, false);
$engine->bindCollection('scores', [10, 20, 30, 40]);

$forward = $engine->runSource(<<<'PASL'
$sum = 0;
foreach ($scores as $score) {
    $sum += $score;
}
$result = $sum;
PASL);

$reverseEngine = new Engine(true, false);
$reverseEngine->bindCollection('digits', [1, 2, 3, 4]);
$reverse = $reverseEngine->runSource(<<<'PASL'
$number = 0;
reveach ($digits as $digit) {
    $number = $number * 10 + $digit;
}
$result = $number;
PASL);

$keyEngine = new Engine(true, false);
$keyEngine->bindCollection('weights', [2 => 10, 5 => 20, 9 => 30]);
$keyValue = $keyEngine->runSource(<<<'PASL'
$total = 0;
foreach ($weights as $key => $value) {
    $total += $key + $value;
}
$result = $total;
PASL);

if ($forward !== 100 || $reverse !== 4321 || $keyValue !== 76) {
    throw new RuntimeException('foreach/reveach example failed');
}

fwrite(STDOUT, json_encode([
    'foreach_sum' => $forward,
    'reveach_number' => $reverse,
    'key_value_sum' => $keyValue,
    'hot_width_bytes' => 2,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
