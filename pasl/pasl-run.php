#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * PASL O(n) CLI — silent by default
 *   --c --bin --x86 --arm --pasm
 */
require_once __DIR__ . '/pasl.php';
use pasl\{Compiler, PaslException};

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
        $a === '--x86', $a === '-x86' => $mode = 'x86',
        $a === '--arm', $a === '-arm', $a === '--aarch64' => $mode = 'arm',
        $a === '--pasm', $a === '-pasm' => $mode = 'pasm',
        $a === '--bin' => $doBin = true,
        $a === '--exe' => $mode = 'c',
        $a === '-o' => $out = array_shift($argv),
        $a === '-c' => $inline = array_shift($argv),
        $a === '-h', $a === '--help' => (print(
            "Usage: pasl-run.php [--c|--x86|--arm|--pasm] [--bin] [-o out] [-c src|file] [--print]\n"
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
        'x86' => $c->toX86($src),
        'arm' => $c->toArm($src),
        'pasm' => $c->toPasmAsm($src),
        default => $c->toC($src),
    };
    $ext = match ($mode) {
        'x86', 'arm' => '.s',
        'pasm' => '.asm',
        default => '.c',
    };
    $dest = $out;
    if ($dest === null && $file !== null) {
        $dest = preg_replace('/\.pasl$/', '', $file) . $ext;
    }
    if ($dest !== null) {
        file_put_contents($dest, $code);
    }
    if ($print || $dest === null) {
        echo $code;
    }
    if ($doBin) {
        $cfile = ($dest !== null && str_ends_with($dest, '.c')) ? $dest : sys_get_temp_dir() . '/pasl_tmp.c';
        if ($cfile !== $dest) {
            file_put_contents($cfile, $code);
        }
        $binOut = '/tmp/' . basename(preg_replace('/\.c$/', '', $cfile));
        if ($binOut === '/tmp/' || str_ends_with($binOut, '/')) {
            $binOut = '/tmp/pasl_out';
        }
        $cmd = 'gcc -O2 -o ' . escapeshellarg($binOut) . ' ' . escapeshellarg($cfile) . ' 2>&1';
        exec($cmd, $lines, $rc);
        if ($rc !== 0) {
            fwrite(STDERR, implode("\n", $lines) . "\n");
            exit($rc);
        }
        if ($print) {
            fwrite(STDERR, "binary: {$binOut}\n");
        }
    }
    exit(0);
} catch (PaslException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
