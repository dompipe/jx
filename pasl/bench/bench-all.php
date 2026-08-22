#!/usr/bin/env php
<?php declare(strict_types=1);
require_once dirname(__DIR__) . '/pasl.php';
use pasl\Package;

$iters = (int)($argv[1] ?? 400);
$cases = [
  'num_tiny' => '$x=1; $x++;',
  'num_loop' => '$s=0; $i=50; while($i){ $s=$s+$i; $i--; }',
  'str_concat' => 'string $a="hi"; string $b="!"; $a=$a.$b; $n=strlen($a);',
  'bag_fields' => 'object $o={}; $o.x=10; $o.y=5; $s=$o.x+$o.y;',
  'arr_sum' => 'array $a=[10,20,30]; $a[1]=7; $x=$a[0]+$a[1]+$a[2];',
  'mixed' => 'array $a=[1,2,3]; object $o={}; $o.n=count($a); string $t="x"; $s=$o.n+strlen($t);',
];

echo "PASL Package benchmark (PHP ".PHP_VERSION.", strnet=".(Package::hasStrnet()?'yes':'no').", iters={$iters})\n";
printf("%-12s %8s %12s %10s\n", 'case', 'bytes', 'us/compile', 'compiles/s');
foreach ($cases as $name => $src) {
    for ($i=0;$i<10;$i++) Package::toC($src);
    $t0 = hrtime(true);
    for ($i=0;$i<$iters;$i++) Package::toC($src);
    $t1 = hrtime(true);
    $us = (($t1-$t0)/1e9/$iters)*1e6;
    printf("%-12s %8d %12.2f %10.0f\n", $name, strlen($src), $us, 1e6/$us);
}
