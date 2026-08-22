#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * PASL O(n) CLI — silent by default
 *   --x86 | --arm | --pasm
 */
require_once __DIR__ . '/pasl.php';
use pasl\{Compiler, PaslException};

$print = false;
$mode = 'x86';
$out = null;
$inline = null;
$file = null;

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
while ($argv !== []) {
    $a = array_shift($argv);
    match (true) {
        $a === '--print', $a === '-v' => $print = true,
        $a === '--x86', $a === '-x86' => $mode = 'x86',
        $a === '--arm', $a === '-arm', $a === '--aarch64' => $mode = 'arm',
        $a === '--pasm', $a === '-pasm' => $mode = 'pasm',
        $a === '-o' => $out = array_shift($argv),
        $a === '-c' => $inline = array_shift($argv),
        $a === '-h', $a === '--help' => (print(
            "Usage: pasl-run.php [--x86|--arm|--pasm] [-o out] [-c src|file] [--print]\n"
        ) || exit(0)),
        default => $file = $a,
    };
}

try {
    $src = $inline ?? ($file !== null ? file_get_contents($file) : null);
    if ($src === false || $src === null) {
        fwrite(STDERR, "No input\n");
        exit(1);
    }
    $c = new Compiler(true);
    $code = match ($mode) {
        'arm' => $c->toArm($src),
        'pasm' => $c->toPasmAsm($src),
        default => $c->toX86($src),
    };
    $ext = match ($mode) {
        'pasm' => '.asm',
        default => '.s',
    };
    $dest = $out ?? ($file ? preg_replace('/\.pasl$/', '', $file) . $ext : null);
    if ($dest) {
        file_put_contents($dest, $code);
    }
    if ($print || !$dest) {
        echo $code;
    }
    exit(0);
} catch (PaslException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
