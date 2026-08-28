#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxl-book64.php';

use jx\semantic\JxlBook64;
use jx\semantic\SemanticException;

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
if (count($argv) < 2 || in_array($argv[0] ?? '', ['-h','--help'], true)) {
    fwrite(STDERR, "Usage: php jx64-compile.php input.jx output.64B [book-name]\n");
    exit(count($argv) < 2 ? 2 : 0);
}
[$input,$output] = $argv;
$name = $argv[2] ?? null;
try {
    $r = JxlBook64::compileFile($input, $output, $name);
    fwrite(STDOUT, json_encode([
        'output'=>$output,
        'content_sha256'=>$r['content_sha256'],
        'file_sha256'=>$r['file_sha256'],
        'sections'=>$r['manifest']['sections'],
    ], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n");
} catch (SemanticException|JsonException $e) {
    fwrite(STDERR, "jx64-compile: {$e->getMessage()}\n");
    exit(1);
}
