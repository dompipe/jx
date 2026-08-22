#!/usr/bin/env php
<?php declare(strict_types=1);
/** PASL benchmark harness — see README for tables */
require_once dirname(__DIR__) . '/pasl.php';
use pasl\Compiler;
$programs = require __DIR__ . '/programs.php';
$iters = (int)($argv[1] ?? 500);
$c = new Compiler(true);
$targets = [
    'ir' => fn(string $s) => $c->toIr($s),
    'x86' => fn(string $s) => $c->toX86($s),
    'arm' => fn(string $s) => $c->toArm($s),
    'pasm' => fn(string $s) => $c->toPasmAsm($s),
];
echo "PASL O(n) Benchmark\nPHP ".PHP_VERSION." iters={$iters}\n";
printf("%-10s %-6s %10s %12s %10s\n", 'program', 'tgt', 'bytes', 'us/compile', 'compiles/s');
foreach ($programs as $name => $src) {
    foreach ($targets as $tgt => $fn) {
        for ($i=0;$i<10;$i++) $fn($src);
        $t0 = hrtime(true);
        for ($i=0;$i<$iters;$i++) $fn($src);
        $t1 = hrtime(true);
        $us = (($t1-$t0)/1e9/$iters)*1e6;
        printf("%-10s %-6s %10d %12.2f %10.0f\n", $name, $tgt, strlen($src), $us, 1e6/$us);
    }
}
