#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * PASL runner — silent by default.
 *
 *   php pasm-run.php program.pbc
 *   php pasm-run.php -c 'source'
 *   php pasm-run.php -o out.pbc source.pasl
 *   php pasm-run.php --print ...
 */

namespace pasm\lang;

$root = __DIR__;
require_once $root . '/pasm-runtime.php';
require_once $root . '/pasm-bytecode.php';
require_once $root . '/pasm-bytecode-optimized.php';
require_once $root . '/pasm-lang.php';

$verbose = false;
$print = false;
$optimize = true;
$outFile = null;
$sourceArg = null;
$pbcArg = null;
$inline = null;

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

while ($argv !== []) {
    $a = array_shift($argv);
    if ($a === '-v' || $a === '--verbose') {
        $verbose = true;
        $print = true;
    } elseif ($a === '--print') {
        $print = true;
    } elseif ($a === '-O0') {
        $optimize = false;
    } elseif ($a === '-O1' || $a === '-O') {
        $optimize = true;
    } elseif ($a === '-o') {
        $outFile = array_shift($argv);
    } elseif ($a === '-c') {
        $inline = array_shift($argv);
    } elseif ($a === '-h' || $a === '--help') {
        fwrite(STDOUT, "Usage: pasm-run.php [--print|-v] [-O0|-O1] [-o out.pbc] [-c 'src'] [file.pasl|file.pbc]\n");
        exit(0);
    } elseif (is_string($a) && str_ends_with($a, '.pbc')) {
        $pbcArg = $a;
    } else {
        $sourceArg = $a;
    }
}

try {
    $eng = new Engine($optimize, $verbose);

    if ($inline !== null) {
        if ($outFile !== null) {
            $eng->compileFile($inline, $outFile);
            exit(0);
        }
        $result = $eng->runSource($inline);
        if ($print) {
            echo $result, "\n";
        }
        exit(0);
    }

    if ($pbcArg !== null) {
        $result = $eng->runFile($pbcArg);
        if ($print) {
            echo $result, "\n";
        }
        exit(0);
    }

    if ($sourceArg !== null) {
        $src = file_get_contents($sourceArg);
        if ($src === false) {
            fwrite(STDERR, "Cannot read {$sourceArg}\n");
            exit(1);
        }
        if ($outFile !== null) {
            $eng->compileFile($src, $outFile);
            exit(0);
        }
        $result = $eng->runSource($src);
        if ($print) {
            echo $result, "\n";
        }
        exit(0);
    }

    fwrite(STDERR, "Nothing to run. Use -h for help.\n");
    exit(1);
} catch (LangException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
