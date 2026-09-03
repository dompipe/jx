#!/usr/bin/env php
<?php declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/pasm-lang.php';
require_once dirname(__DIR__, 2) . '/pasm-jxl.php';

use pasm\PASMAssembler;
use pasm\PASMJxlCompiler;
use pasm\PASMOptimizingAssembler;
use pasm\lang\Engine;

/**
 * PASL prepared-format benchmark.
 *
 * Measures the migration boundary explicitly:
 *   PASL -> JXL compile
 *   PASL -> legacy PBC compile
 *   JXL validation
 *   JXL -> PASM admission/transcode
 *   JXL end-to-end execution (current compatibility path)
 *   PBC end-to-end execution (legacy baseline)
 *
 * JXL PASL opcodes are not yet directly dispatched by the native x86-64 JXL
 * executor. Do not describe the JXL execution number below as native JXL.
 */

$options = [
    'compile_iters' => 300,
    'run_iters' => 40,
    'rounds' => 5,
    'json' => false,
    'optimize' => true,
];

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if ($arg === '--json') {
        $options['json'] = true;
    } elseif ($arg === '--no-optimize') {
        $options['optimize'] = false;
    } elseif ($arg === '--compile-iters' && isset($argv[$i + 1])) {
        $options['compile_iters'] = max(1, (int)$argv[++$i]);
    } elseif ($arg === '--run-iters' && isset($argv[$i + 1])) {
        $options['run_iters'] = max(1, (int)$argv[++$i]);
    } elseif ($arg === '--rounds' && isset($argv[$i + 1])) {
        $options['rounds'] = max(1, (int)$argv[++$i]);
    } elseif (ctype_digit($arg)) {
        // Convenient compatibility with the older PASL benchmark style.
        $options['compile_iters'] = max(1, (int)$arg);
    } else {
        fwrite(STDERR, "Usage: php pasl/bench/bench-jxl.php [--compile-iters N] [--run-iters N] [--rounds N] [--no-optimize] [--json]\n");
        exit(2);
    }
}

$cases = [
    'scalar' => '$a=1;$b=2;$c=$a+$b;$d=$c*3;$result=$d-1;',
    'movi64' => '$a=4294967301;$b=7;$result=$a+$b;',
    'while64' => '$sum=0;$i=64;while($i){$sum=$sum+$i;$i--;}$result=$sum;',
    'for128' => '$i=0;$sum=0;for($i=0;$i<=127;$i++){$sum+=$i;}$result=$sum;',
    'nested16' => '$i=0;$j=0;$sum=0;for($i=0;$i<16;$i++){for($j=0;$j<16;$j++){$sum+=$i+$j;}}$result=$sum;',
    'signed128' => '$i=-64;$sum=0;while($i<64){$sum+=$i;$i++;}$result=$sum;',
];

function median(array $values): float
{
    sort($values, SORT_NUMERIC);
    $n = count($values);
    if ($n === 0) return 0.0;
    $m = intdiv($n, 2);
    return ($n % 2) ? (float)$values[$m] : ((float)$values[$m - 1] + (float)$values[$m]) / 2.0;
}

function measureUs(callable $fn, int $iters, int $rounds): float
{
    $warm = min(8, $iters);
    for ($i = 0; $i < $warm; $i++) $fn();

    $samples = [];
    for ($r = 0; $r < $rounds; $r++) {
        $t0 = hrtime(true);
        for ($i = 0; $i < $iters; $i++) $fn();
        $elapsedNs = hrtime(true) - $t0;
        $samples[] = ($elapsedNs / $iters) / 1000.0;
    }
    return median($samples);
}

function ratio(float $a, float $b): float
{
    return $b > 0.0 ? $a / $b : 0.0;
}

function geometricMean(array $values): float
{
    $values = array_values(array_filter($values, static fn($v) => is_numeric($v) && (float)$v > 0.0));
    if ($values === []) return 0.0;
    $sum = 0.0;
    foreach ($values as $v) $sum += log((float)$v);
    return exp($sum / count($values));
}

$optimize = (bool)$options['optimize'];
$bridge = new PASMJxlCompiler();
$results = [];

foreach ($cases as $name => $source) {
    $engine = new Engine($optimize, false);

    $jxl = $engine->compileJxl($source);
    $pbc = $engine->compilePbc($source);

    if (!PASMJxlCompiler::isJxl($jxl)) {
        throw new RuntimeException("{$name}: compiler did not emit valid JXL");
    }

    $assembler = $optimize ? new PASMOptimizingAssembler(true) : new PASMAssembler();
    $admittedPbc = $assembler->compile($bridge->toPasmAssembly($jxl));

    $jxlResult = $engine->runCode($jxl);
    $pbcResult = $engine->runCode($pbc);
    $admittedResult = $engine->runCode($admittedPbc);
    if ($jxlResult !== $pbcResult || $jxlResult !== $admittedResult) {
        throw new RuntimeException("{$name}: JXL/PBC semantic mismatch");
    }

    $jxlCompileUs = measureUs(static fn() => $engine->compileJxl($source), $options['compile_iters'], $options['rounds']);
    $pbcCompileUs = measureUs(static fn() => $engine->compilePbc($source), $options['compile_iters'], $options['rounds']);
    $validateUs = measureUs(static fn() => PASMJxlCompiler::isJxl($jxl), $options['compile_iters'], $options['rounds']);
    $admitUs = measureUs(static function() use ($bridge, $assembler, $jxl): void {
        $assembler->compile($bridge->toPasmAssembly($jxl));
    }, $options['compile_iters'], $options['rounds']);

    $jxlRunUs = measureUs(static fn() => $engine->runCode($jxl), $options['run_iters'], $options['rounds']);
    $pbcRunUs = measureUs(static fn() => $engine->runCode($pbc), $options['run_iters'], $options['rounds']);
    $admittedRunUs = measureUs(static fn() => $engine->runCode($admittedPbc), $options['run_iters'], $options['rounds']);

    $results[] = [
        'case' => $name,
        'source_bytes' => strlen($source),
        'jxl_bytes' => strlen($jxl),
        'jxl_cells' => intdiv(strlen($jxl), PASMJxlCompiler::CELL_BYTES),
        'pbc_bytes' => strlen($pbc),
        'size_jxl_over_pbc' => ratio((float)strlen($jxl), (float)strlen($pbc)),
        'jxl_compile_us' => $jxlCompileUs,
        'pbc_compile_us' => $pbcCompileUs,
        'compile_jxl_over_pbc' => ratio($jxlCompileUs, $pbcCompileUs),
        'jxl_validate_us' => $validateUs,
        'jxl_admit_us' => $admitUs,
        'jxl_run_us' => $jxlRunUs,
        'pbc_run_us' => $pbcRunUs,
        'admitted_pbc_run_us' => $admittedRunUs,
        'run_jxl_over_pbc' => ratio($jxlRunUs, $pbcRunUs),
        'admission_fraction_of_jxl_run' => ratio($admitUs, $jxlRunUs),
        'result' => $jxlResult,
    ];
}

$summary = [
    'size_jxl_over_pbc_gmean' => geometricMean(array_column($results, 'size_jxl_over_pbc')),
    'compile_jxl_over_pbc_gmean' => geometricMean(array_column($results, 'compile_jxl_over_pbc')),
    'run_jxl_over_pbc_gmean' => geometricMean(array_column($results, 'run_jxl_over_pbc')),
    'admission_fraction_of_jxl_run_gmean' => geometricMean(array_column($results, 'admission_fraction_of_jxl_run')),
];

$payload = [
    'benchmark' => 'pasl-jxl-vs-pbc',
    'php' => PHP_VERSION,
    'platform' => PHP_OS_FAMILY . ' ' . php_uname('m'),
    'optimize' => $optimize,
    'compile_iters' => $options['compile_iters'],
    'run_iters' => $options['run_iters'],
    'rounds' => $options['rounds'],
    'jxl_cell_bytes' => PASMJxlCompiler::CELL_BYTES,
    'jxl_direct_native_dispatch' => false,
    'execution_note' => 'PASL JXL currently re-admits through PASM bytecode before VM execution; native 0x51-0x77 dispatch is not yet implemented.',
    'results' => $results,
    'summary' => $summary,
];

if ($options['json']) {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

echo "PASL prepared-format benchmark\n";
echo "PHP {$payload['php']} | {$payload['platform']} | optimize=" . ($optimize ? 'yes' : 'no') . " | compile={$options['compile_iters']} run={$options['run_iters']} rounds={$options['rounds']}\n";
echo "JXL execution is compatibility admission through PASM, not direct native JXL yet.\n\n";

printf("%-10s %6s %6s %6s %7s %10s %10s %8s\n", 'case', 'srcB', 'jxlB', 'pbcB', 'J/P sz', 'JXL cmp us', 'PBC cmp us', 'J/P cmp');
foreach ($results as $r) {
    printf("%-10s %6d %6d %6d %7.2fx %10.2f %10.2f %8.2fx\n",
        $r['case'], $r['source_bytes'], $r['jxl_bytes'], $r['pbc_bytes'],
        $r['size_jxl_over_pbc'], $r['jxl_compile_us'], $r['pbc_compile_us'], $r['compile_jxl_over_pbc']);
}

echo "\n";
printf("%-10s %9s %10s %10s %10s %9s\n", 'case', 'valid us', 'admit us', 'JXL run us', 'PBC run us', 'J/P run');
foreach ($results as $r) {
    printf("%-10s %9.2f %10.2f %10.2f %10.2f %9.2fx\n",
        $r['case'], $r['jxl_validate_us'], $r['jxl_admit_us'], $r['jxl_run_us'], $r['pbc_run_us'], $r['run_jxl_over_pbc']);
}

echo "\nGeometric means:\n";
printf("  JXL/PBC prepared size : %.3fx\n", $summary['size_jxl_over_pbc_gmean']);
printf("  JXL/PBC compile time  : %.3fx\n", $summary['compile_jxl_over_pbc_gmean']);
printf("  JXL/PBC run time      : %.3fx\n", $summary['run_jxl_over_pbc_gmean']);
printf("  admission/JXL run     : %.3fx\n", $summary['admission_fraction_of_jxl_run_gmean']);
