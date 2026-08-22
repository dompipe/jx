#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/pasl.php';
use pasl\{Compiler, PaslException};

$print = false; $x86 = false; $pasm = false; $out = null; $inline = null; $file = null;
$argv = $_SERVER['argv'] ?? []; array_shift($argv);
while ($argv !== []) {
    $a = array_shift($argv);
    match (true) {
        $a === '--print' || $a === '-v' => $print = true,
        $a === '--x86' || $a === '-x86' => $x86 = true,
        $a === '--pasm' || $a === '-pasm' => $pasm = true,
        $a === '-o' => $out = array_shift($argv),
        $a === '-c' => $inline = array_shift($argv),
        $a === '-h' || $a === '--help' => (print("Usage: pasl-run.php [--x86|--pasm] [-o out] [-c src|file] [--print]\n") || exit(0)),
        default => $file = $a,
    };
}
try {
    $src = $inline ?? ($file !== null ? file_get_contents($file) : null);
    if ($src === false || $src === null) { fwrite(STDERR, "No input\n"); exit(1); }
    $c = new Compiler(true);
    if ($x86) {
        $code = $c->toX86($src);
        $dest = $out ?? ($file ? preg_replace('/\.pasl$/', '', $file) . '.s' : null);
        if ($dest) file_put_contents($dest, $code);
        if ($print || !$dest) echo $code;
        exit(0);
    }
    $code = $c->toPasmAsm($src);
    $dest = $out ?? ($file ? preg_replace('/\.pasl$/', '', $file) . '.asm' : null);
    if ($dest) file_put_contents($dest, $code);
    if ($print || !$dest) echo $code;
    exit(0);
} catch (PaslException $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
