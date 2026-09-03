#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxl-compiler.php';

use jx\semantic\PreparedCompiler;
use jx\semantic\SemanticException;

$input = $argv[1] ?? null;
$prefix = $argv[2] ?? null;
if (!is_string($input) || $input === '' || !is_string($prefix) || $prefix === '') {
    fwrite(STDERR, "Usage: php jx-jxl-container-compile.php input.jx output-prefix\n");
    fwrite(STDERR, "Writes: <prefix>.jxl <prefix>.jxcb <prefix>.jxrw <prefix>.json\n");
    exit(2);
}

try {
    $source = file_get_contents($input);
    if ($source === false) throw new SemanticException("Cannot read {$input}", 'io');

    $compiler = new PreparedCompiler();
    $compiled = $compiler->compileContainerSource($source);

    $outputs = [
        $prefix . '.jxl' => $compiled->jxl,
        $prefix . '.jxcb' => $compiler->containerBindingBinary(),
        $prefix . '.jxrw' => $compiled->registerBinary(),
        $prefix . '.json' => $compiled->json(),
    ];

    foreach ($outputs as $path => $bytes) {
        $dir = dirname($path);
        if ($dir !== '.' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new SemanticException("Cannot create output directory {$dir}", 'io');
        }
        if (file_put_contents($path, $bytes) !== strlen($bytes)) {
            throw new SemanticException("Cannot write {$path}", 'io');
        }
    }

    fwrite(STDOUT, json_encode([
        'jxl'=>$prefix . '.jxl',
        'bindings'=>$prefix . '.jxcb',
        'register_window'=>$prefix . '.jxrw',
        'metadata'=>$prefix . '.json',
        'code_bytes'=>strlen($compiled->jxl),
        'bindings_count'=>count($compiler->containerBindings()->all()),
        'code_sha256'=>hash('sha256', $compiled->jxl),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'jx-jxl-container-compile: ' . $e->getMessage() . "\n");
    exit(1);
}
