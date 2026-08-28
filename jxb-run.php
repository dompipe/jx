#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxb.php';

use jx\semantic\JxbBook;
use jx\semantic\SemanticException;

$args = $_SERVER['argv'] ?? [];
array_shift($args);
if (count($args) !== 1 || in_array($args[0] ?? '', ['-h','--help'], true)) {
    fwrite(STDERR, "Usage: php jxb-run.php program.jxb\n");
    exit(count($args) !== 1 ? 2 : 0);
}

try {
    $result = JxbBook::runFile($args[0]);
    fwrite(STDOUT, (string)$result . "\n");
} catch (SemanticException $e) {
    fwrite(STDERR, "jxb-run: {$e->getMessage()}\n");
    exit(1);
}
