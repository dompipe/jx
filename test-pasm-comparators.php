<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-lang.php';
require_once __DIR__ . '/pasm-lang-x86.php';

use pasm\lang\Engine;
use pasm\lang\X86Compiler;

$cases = [
    ['-5 < -2', -5, '<', -2, 1],
    ['-2 < -5', -2, '<', -5, 0],
    ['5 <= 5', 5, '<=', 5, 1],
    ['6 <= 5', 6, '<=', 5, 0],
    ['7 > -3', 7, '>', -3, 1],
    ['-7 > 3', -7, '>', 3, 0],
    ['9 >= 9', 9, '>=', 9, 1],
    ['8 >= 9', 8, '>=', 9, 0],
    ['4 == 4', 4, '==', 4, 1],
    ['4 != 4', 4, '!=', 4, 0],
    ['4 != 5', 4, '!=', 5, 1],
];

foreach ($cases as [$label, $a, $op, $b, $expected]) {
    $source = '$a=' . $a . '; $b=' . $b . '; $result=0; if($a ' . $op . ' $b){ $result=1; }';
    $actual = (new Engine(true, false))->runSource($source);
    if ((int)$actual !== $expected) {
        throw new RuntimeException("Comparator failed {$label}: expected {$expected}, got {$actual}");
    }
}

$loop = '$i=0; $sum=0; while($i < 5){ $sum += $i; $i++; }';
if ((int)(new Engine(true, false))->runSource($loop) !== 10) {
    throw new RuntimeException('while < comparator failed');
}

// Allocate $i first so $sum remains the compiler-selected result register.
$for = '$i=0; $sum=0; for($i=0; $i <= 4; $i++){ $sum += $i; }';
if ((int)(new Engine(true, false))->runSource($for) !== 10) {
    throw new RuntimeException('for <= comparator failed');
}

$x86 = (new X86Compiler(true))->compile('$a=-3; $b=4; $result=0; if($a < $b){ $result=1; }');
if (!str_contains($x86, '    jl ')) {
    throw new RuntimeException('x86 relational lowering did not emit jl');
}

$x86 = (new X86Compiler(true))->compile('$a=4; $b=4; $result=0; if($a >= $b){ $result=1; }');
if (!str_contains($x86, '    jge ')) {
    throw new RuntimeException('x86 relational lowering did not emit jge');
}

echo "PASS PASL comparators == != < <= > >= signed loops native lowering\n";
