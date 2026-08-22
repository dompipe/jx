#!/usr/bin/env php
<?php declare(strict_types=1);
require_once dirname(__DIR__) . '/pasl.php';
require_once dirname(__DIR__) . '/pasl-strnet.php';
use pasl\Compiler as NumCompiler;
use pasl\strnet\Compiler as FullCompiler;
$iters = (int)($argv[1] ?? 400);
$num = new NumCompiler(true);
$full = new FullCompiler();
$cases = [
  'num_tiny' => ['num', '$x=1; $x++;'],
  'num_loop' => ['num', '$s=0; $i=50; while($i){ $s=$s+$i; $i--; }'],
  'str_concat' => ['full', 'string $a="hi"; string $b="!"; $a=$a.$b; $n=strlen($a);'],
  'bag_fields' => ['full', 'object $o={}; $o.x=10; $o.y=5; $s=$o.x+$o.y;'],
  'arr_sum' => ['full', 'array $a=[10,20,30]; $a[1]=7; $x=$a[0]+$a[1]+$a[2];'],
  'mixed' => ['full', 'array $a=[1,2,3]; object $o={}; $o.n=count($a); string $t="x"; $s=$o.n+strlen($t);'],
];
echo "PASL full-surface benchmark (PHP ".PHP_VERSION.", iters={$iters})\n";
printf("%-12s %8s %12s %10s\n", 'case', 'bytes', 'us/compile', 'compiles/s');
foreach ($cases as $name => [$kind, $src]) {
    $fn = $kind === 'num' ? fn($s) => $num->toC($s) : fn($s) => $full->toC($s);
    for ($i=0;$i<15;$i++) $fn($src);
    $t0 = hrtime(true);
    for ($i=0;$i<$iters;$i++) $fn($src);
    $t1 = hrtime(true);
    $us = (($t1-$t0)/1e9/$iters)*1e6;
    printf("%-12s %8d %12.2f %10.0f\n", $name, strlen($src), $us, 1e6/$us);
}
