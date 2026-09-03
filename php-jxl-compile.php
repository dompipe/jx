#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/php-jxl-driver.php';

use jx\PhpJxlDriver;

if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "usage: php php-jxl-compile.php input.php [output.jxl]\n");
    exit(2);
}

$input = $argv[1];
$output = $argv[2] ?? preg_replace('/\.[^.]+$/', '', $input) . '.jxl';
if (!is_string($output) || $output === '') $output = $input . '.jxl';

try {
    $result = (new PhpJxlDriver())->compileFile($input, $output);
    printf(
        "PHP -> PASM -> native JXL: %s (%d bytes, %s, %d variables)\n",
        $output,
        strlen($result['jxl']),
        $result['architecture'],
        count($result['variables'])
    );
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
