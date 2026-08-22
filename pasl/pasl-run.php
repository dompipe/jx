#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * PASL CLI — numeric + full surface via pasl\Package
 */
require_once __DIR__ . '/pasl.php';

use pasl\Package;
use pasl\PaslException;

$print = false;
$mode = 'c';
$out = null;
$inline = null;
$file = null;
$doBin = false;

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
while ($argv !== []) {
    $a = array_shift($argv);
    match (true) {
        $a === '--print', $a === '-v' => $print = true,
        $a === '--c' => $mode = 'c',
        $a === '--strnet', $a === '--net', $a === '--strings', $a === '--full' => $mode = 'strnet',
        $a === '--x86', $a === '-x86' => $mode = 'x86',
        $a === '--arm', $a === '-arm', $a === '--aarch64' => $mode = 'arm',
        $a === '--pasm', $a === '-pasm' => $mode = 'pasm',
        $a === '--bin' => $doBin = true,
        $a === '--exe' => $mode = 'c',
        $a === '-o' => $out = array_shift($argv),
        $a === '-c' => $inline = array_shift($argv),
        $a === '-h', $a === '--help' => (static function (): void {
            fwrite(STDERR, "PASL CLI — require only pasl/pasl.php (strnet included when present)\n"
                . "  --c|--strnet|--x86|--arm|--pasm  --bin  -o path  -c 'src'|file\n");
            exit(0);
        })(),
        default => $file = $a,
    };
}

try {
    $src = $inline ?? ($file !== null ? file_get_contents($file) : null);
    if ($src === null || $src === false || $src === '') {
        fwrite(STDERR, "PASL: no source\n");
        exit(2);
    }

    $result = Package::compile($src, $mode);
    $code = $result['code'];
    $backend = $result['backend'];

    if ($out !== null) {
        file_put_contents($out, $code);
    }
    if ($print || $out === null) {
        echo $code;
    }

    if ($doBin && in_array($backend, ['c', 'strnet'], true)) {
        $cFile = $out ?? (sys_get_temp_dir() . '/pasl_' . getmypid() . '.c');
        if ($out === null) {
            file_put_contents($cFile, $code);
        }
        $bin = sys_get_temp_dir() . '/pasl_' . getmypid() . '_bin';
        exec('gcc -O2 -o ' . escapeshellarg($bin) . ' ' . escapeshellarg($cFile) . ' 2>&1', $lines, $st);
        if ($st !== 0) {
            fwrite(STDERR, implode("\n", $lines) . "\n");
            exit($st ?: 1);
        }
        fwrite(STDERR, "PASL: binary {$bin}\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
