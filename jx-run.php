#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * jx executable compiler / interpreter
 *
 *   php jx-run.php [--print|-v] [-O0|-O1] [-o out.pbc] [-c 'src'] [file.jx|file.pasl|file.pbc]
 *
 * .jx  → JxEngine (bags/tasks + PASL bytecode lowering)
 * .pasl / inline arithmetic → PASL Engine (bytecode VM)
 * .pbc → run bytecode
 */
namespace jx;

$root = __DIR__;
require_once $root . '/jx-lang.php';

use pasm\lang\Engine as PaslEngine;
use pasm\lang\LangException;
use pasm\lang\PbcFile;
use Throwable;

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
        fwrite(STDOUT, "Usage: jx-run.php [--print|-v] [-O0|-O1] [-o out.pbc] [-c 'src'] [file.jx|file.pasl|file.pbc]\n");
        exit(0);
    } elseif (is_string($a) && str_ends_with($a, '.pbc')) {
        $pbcArg = $a;
    } else {
        $sourceArg = $a;
    }
}

try {
    $jx = new JxEngine($optimize, $verbose);
    $pasl = new PaslEngine($optimize, $verbose);

    if ($inline !== null) {
        $isJx = (bool)preg_match('/\b(Bag|Task|Book|delivery|underwrite|\.sign\s*\(|\.commit\s*\()/i', $inline);
        if ($outFile !== null && !$isJx) {
            $pasl->compileFile($inline, $outFile);
            exit(0);
        }
        $result = $isJx ? $jx->runSource($inline) : $pasl->runSource($inline);
        if ($print) {
            echo is_scalar($result) || $result === null ? (string)$result : json_encode($result), "\n";
        }
        exit(0);
    }

    if ($pbcArg !== null) {
        $result = $pasl->runFile($pbcArg);
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
        $isJx = str_ends_with($sourceArg, '.jx')
            || (bool)preg_match('/\b(Bag|Task|Book|delivery|underwrite)\b/i', $src);

        if ($outFile !== null) {
            if ($isJx) {
                // Compile only PASL-lowerable extract: full .jx books stay interpreted
                fwrite(STDERR, "Note: full .jx programs interpret via JxEngine; -o emits PASL bytecode only for pure arithmetic files.\n");
            }
            $pasl->compileFile($src, $outFile);
            exit(0);
        }

        $result = $isJx ? $jx->runSource($src) : $pasl->runSource($src);
        if ($print) {
            if (is_object($result) && method_exists($result, '__toString')) {
                echo $result, "\n";
            } elseif (is_scalar($result) || $result === null) {
                echo $result, "\n";
            } else {
                echo json_encode($result), "\n";
            }
        }
        exit(0);
    }

    fwrite(STDERR, "Nothing to run. Use -h for help.\n");
    exit(1);
} catch (JxException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} catch (LangException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
