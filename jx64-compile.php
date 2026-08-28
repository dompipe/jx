#!/usr/bin/env php
<?php declare(strict_types=1);

// Compatibility entry point. The public compiled-Book extension is .jxb;
// JX64B001 remains the internal v1 package ABI/magic.
require_once __DIR__ . '/jx-jxb.php';

use jx\semantic\JxbBook;
use jx\semantic\SemanticException;

$args = $_SERVER['argv'] ?? [];
array_shift($args);
if ($args === [] || in_array($args[0] ?? '', ['-h','--help'], true)) {
    fwrite(STDERR, "Usage: php jx64-compile.php input.jx [output.jxb] [book-name]\n");
    fwrite(STDERR, "Compatibility alias; prefer: php jxb-compile.php ...\n");
    exit($args === [] ? 2 : 0);
}

$input = $args[0];
$output = $args[1] ?? null;
$name = $args[2] ?? null;
try {
    $r = JxbBook::compileFile($input, $output, $name);
    fwrite(STDOUT, json_encode([
        'output'=>$r['path'],
        'public_format'=>'jxb',
        'internal_format'=>$r['manifest']['format'] ?? null,
        'content_sha256'=>$r['content_sha256'],
        'file_sha256'=>$r['file_sha256'],
        'sections'=>$r['manifest']['sections'] ?? [],
    ], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n");
} catch (SemanticException|JsonException $e) {
    fwrite(STDERR, "jx64-compile: {$e->getMessage()}\n");
    exit(1);
}
