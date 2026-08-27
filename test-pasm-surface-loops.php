<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-runtime.php';
require_once __DIR__ . '/pasm-bytecode.php';
require_once __DIR__ . '/pasm-bytecode-optimized.php';
require_once __DIR__ . '/pasm-lang.php';

use pasm\lang\Engine;
use pasm\lang\PASLSurfaceLoops;

$eq = static function(mixed $a, mixed $b, string $label): void {
    if ($a !== $b) {
        fwrite(STDERR, "FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");
        exit(1);
    }
};

$repeat = <<<'PASL'
$sum = 0;
repeat (5) {
    $sum += 2;
}
$result = $sum;
PASL;

$do = <<<'PASL'
$sum = 0;
$counter = 3;
do {
    $sum += $counter;
    $counter--;
} while ($counter);
$result = $sum;
PASL;

$nested = <<<'PASL'
$sum = 0;
repeat (2) {
    $counter = 2;
    do {
        $sum++;
        $counter--;
    } while ($counter);
}
$result = $sum;
PASL;

$lowered = PASLSurfaceLoops::lower($nested);
if (stripos($lowered, 'repeat') !== false || preg_match('/\bdo\s*\{/i', $lowered)) {
    fwrite(STDERR, "FAIL surface loop lowering left high-level loop syntax\n{$lowered}\n");
    exit(1);
}
if (substr_count(strtolower($lowered), 'for (') < 2) {
    fwrite(STDERR, "FAIL expected nested canonical for loops\n{$lowered}\n");
    exit(1);
}

foreach ([false, true] as $opt) {
    $engine = new Engine($opt, false);
    $eq($engine->runSource($repeat), 10, 'repeat '.($opt?'O1':'O0'));
    $eq($engine->runSource($do), 6, 'do-while '.($opt?'O1':'O0'));
    $eq($engine->runSource($nested), 4, 'nested surface loops '.($opt?'O1':'O0'));
}

fwrite(STDOUT, "PASS PASL surface loops do-while repeat optimized+plain\n");
