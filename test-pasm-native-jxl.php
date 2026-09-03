#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/php-jxl-driver.php';

use jx\PhpJxlDriver;

$outDir = $argv[1] ?? (__DIR__ . '/build/native-jxl-test');
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    throw new RuntimeException("Cannot create {$outDir}");
}

$cases = [
    'scalar' => [
        'source' => '<?php $a=1;$b=2;$c=$a+$b;$d=$c*3;$result=$d-1;',
        'expected' => 8,
    ],
    'while64' => [
        'source' => '<?php $sum=0;$i=64;while($i){$sum+=$i;$i--;}$result=$sum;',
        'expected' => 2080,
    ],
    'for128' => [
        'source' => '<?php $sum=0;$i=0;for($i=0;$i<128;$i++){$sum+=$i;}$result=$sum;',
        'expected' => 8128,
    ],
    'signed128' => [
        'source' => '<?php $sum=0;$i=-64;while($i<64){$sum+=$i;$i++;}$result=$sum;',
        'expected' => -64,
    ],
    'bitmix' => [
        'source' => '<?php $a=305419896;$b=252645135;$c=$a^$b;$d=$c&16711935;$result=$d|65536;',
        'expected' => 3866743,
    ],
];

$driver = new PhpJxlDriver();
$manifest = [];
foreach ($cases as $name => $case) {
    $compiled = $driver->compileDetailed($case['source']);
    $path = rtrim($outDir, '/\\') . DIRECTORY_SEPARATOR . $name . '.jxl';
    if (file_put_contents($path, $compiled['jxl']) !== strlen($compiled['jxl'])) {
        throw new RuntimeException("Cannot write {$path}");
    }
    $manifest[$name] = [
        'file' => $path,
        'expected' => $case['expected'],
        'bytes' => strlen($compiled['jxl']),
        'architecture' => $compiled['architecture'],
        'variables' => $compiled['variables'],
        'pasm' => $compiled['pasm'],
    ];
}

file_put_contents(
    rtrim($outDir, '/\\') . DIRECTORY_SEPARATOR . 'manifest.json',
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);

foreach ($manifest as $name => $row) {
    printf("%-10s %4d bytes expected=%d\n", $name, $row['bytes'], $row['expected']);
}
