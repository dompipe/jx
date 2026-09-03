<?php declare(strict_types=1);

require_once __DIR__ . '/jx-lang.php';
require_once __DIR__ . '/jx-jxl-compiler.php';
require_once __DIR__ . '/jx-jxb.php';
require_once __DIR__ . '/pasm-lang.php';

use jx\JxEngine;
use jx\semantic\JxbBook;
use jx\semantic\JxlVm;
use jx\semantic\PreparedCompiler;
use pasm\lang\Engine as PaslEngine;

$reps = max(5, (int)($argv[1] ?? 21));
$warmups = max(1, (int)($argv[2] ?? 5));

function median(array $xs): float {
    sort($xs, SORT_NUMERIC);
    $n = count($xs);
    $m = intdiv($n, 2);
    return $n % 2 ? (float)$xs[$m] : ((float)$xs[$m - 1] + (float)$xs[$m]) / 2.0;
}
function percentile95(array $xs): float {
    sort($xs, SORT_NUMERIC);
    $i = max(0, min(count($xs) - 1, (int)ceil(count($xs) * 0.95) - 1));
    return (float)$xs[$i];
}
function bench(string $name, callable $fn, int $reps, int $warmups, ?callable $verify = null): array {
    for ($i = 0; $i < $warmups; $i++) {
        $v = $fn();
        if ($verify && !$verify($v)) throw new RuntimeException("{$name} warmup verification failed");
    }
    $times = [];
    $last = null;
    for ($i = 0; $i < $reps; $i++) {
        $t0 = hrtime(true);
        $last = $fn();
        $times[] = (hrtime(true) - $t0) / 1e6;
        if ($verify && !$verify($last)) throw new RuntimeException("{$name} verification failed");
    }
    return [
        'name' => $name,
        'median_ms' => median($times),
        'min_ms' => min($times),
        'p95_ms' => percentile95($times),
        'reps' => $reps,
    ];
}
function runCommand(string $command): int {
    $lines = [];
    $status = 0;
    exec($command . ' >/dev/null 2>&1', $lines, $status);
    if ($status !== 0) throw new RuntimeException("command failed ({$status}): {$command}");
    return 1;
}
function semanticSource(int $mutations): string {
    $src = 'int $x=0;';
    for ($i = 0; $i < $mutations; $i++) $src .= '$x += 1;';
    return $src . '$x;';
}

$semanticSmall = 'function add(int $a,int $b): int { return $a+$b; } int $x=add(20,22); repeat (3) { $x += 2; } $x;';
$semanticHttp = 'int $x=0; repeat (100) { $x += 1; } $x;';
$paslLoop = <<<'PASL'
$i = 0;
$sum = 0;
while ($i < 10000) {
    $sum += $i;
    $i++;
}
PASL;
$hostJx = 'bag = Bag.underwrite(256); ref = bag.sign("msg"); bag.set("hello-jx").commit(ref); q = bag.quotient();';

$out = [
    'suite' => 'jx-php-only-surface/1',
    'php_version' => PHP_VERSION,
    'platform' => PHP_OS_FAMILY . ' ' . php_uname('m'),
    'reps' => $reps,
    'warmups' => $warmups,
    'in_process' => [],
    'source_scaling' => [],
    'cold_cli' => [],
    'native_build' => null,
    'http_nojs' => [],
];

$paslPrepared = (new PaslEngine(true, false))->compile($paslLoop);
$semanticPrepared = (new PreparedCompiler())->emitJxl($semanticSmall);
$semanticBook = JxbBook::compile($semanticSmall, 'bench')['bytes'];

$out['in_process'][] = bench('php-loop-direct', static function (): int {
    $sum = 0;
    for ($i = 0; $i < 10000; $i++) $sum += $i;
    return $sum;
}, $reps, $warmups, static fn($v): bool => $v === 49995000);

$out['in_process'][] = bench('pasl-source-compile-run-o1', fn() => (new PaslEngine(true, false))->runSource($paslLoop), $reps, $warmups, static fn($v): bool => (int)$v === 49995000);
$out['in_process'][] = bench('pasl-compile-jxl-o1', fn() => strlen((new PaslEngine(true, false))->compile($paslLoop)), $reps, $warmups, static fn($v): bool => (int)$v > 0);
$out['in_process'][] = bench('pasl-compile-jxl-o0', fn() => strlen((new PaslEngine(false, false))->compile($paslLoop)), $reps, $warmups, static fn($v): bool => (int)$v > 0);
$out['in_process'][] = bench('pasl-prepared-jxl-run', fn() => (new PaslEngine(true, false))->runCode($paslPrepared), $reps, $warmups, static fn($v): bool => (int)$v === 49995000);
$out['in_process'][] = bench('semantic-jx-run', fn() => (new PreparedCompiler())->run($semanticSmall), $reps, $warmups, static fn($v): bool => (int)$v === 48);
$out['in_process'][] = bench('semantic-jx-compile-jxl', fn() => strlen((new PreparedCompiler())->emitJxl($semanticSmall)), $reps, $warmups, static fn($v): bool => (int)$v > 0);
$out['in_process'][] = bench('semantic-jxl-run', fn() => (new JxlVm())->run($semanticPrepared), $reps, $warmups, static fn($v): bool => (int)$v === 48);
$out['in_process'][] = bench('semantic-jx-compile-jxb', fn() => strlen(JxbBook::compile($semanticSmall, 'bench')['bytes']), $reps, $warmups, static fn($v): bool => (int)$v > 0);
$out['in_process'][] = bench('compiled-jxb-validate-admit-run', fn() => JxbBook::run($semanticBook), $reps, $warmups, static fn($v): bool => (int)$v === 48);
$out['in_process'][] = bench('host-jx-bag-page-run', fn() => (new JxEngine(true, false))->runSource($hostJx), $reps, $warmups);

foreach ([10, 100, 1000, 5000] as $mutations) {
    $src = semanticSource($mutations);
    $metric = bench("semantic-jxl-compile-{$mutations}-mutations", fn() => strlen((new PreparedCompiler())->emitJxl($src)), $reps, $warmups, static fn($v): bool => (int)$v > 0);
    $metric['source_bytes'] = strlen($src);
    $metric['mutations'] = $mutations;
    $out['source_scaling'][] = $metric;
}

$tmp = sys_get_temp_dir() . '/jx-php-only-' . getmypid();
@mkdir($tmp, 0777, true);
$jxRunner = escapeshellarg(__DIR__ . '/jx-run.php');
$php = escapeshellarg(PHP_BINARY);
$srcArg = escapeshellarg($semanticSmall);
$jxlPath = $tmp . '/program.jxl';
$jxbPath = $tmp . '/program.jxb';
file_put_contents($jxlPath, $semanticPrepared);
file_put_contents($jxbPath, $semanticBook);

$coldReps = max(5, min(15, intdiv($reps + 1, 2)));
$coldWarm = 2;
$out['cold_cli'][] = bench('cold-php-process-help', fn() => runCommand($php . ' ' . $jxRunner . ' --help'), $coldReps, $coldWarm);
$out['cold_cli'][] = bench('cold-cli-semantic-run', fn() => runCommand($php . ' ' . $jxRunner . ' --quiet -c ' . $srcArg), $coldReps, $coldWarm);
$out['cold_cli'][] = bench('cold-cli-compile-jxl', fn() => runCommand($php . ' ' . $jxRunner . ' --quiet --jxl -o ' . escapeshellarg($tmp . '/cold.jxl') . ' -c ' . $srcArg), $coldReps, $coldWarm);
$out['cold_cli'][] = bench('cold-cli-compile-jxb', fn() => runCommand($php . ' ' . $jxRunner . ' --quiet --jxb -o ' . escapeshellarg($tmp . '/cold.jxb') . ' -c ' . $srcArg), $coldReps, $coldWarm);
$out['cold_cli'][] = bench('cold-cli-run-jxl', fn() => runCommand($php . ' ' . $jxRunner . ' --quiet ' . escapeshellarg($jxlPath)), $coldReps, $coldWarm);
$out['cold_cli'][] = bench('cold-cli-run-jxb', fn() => runCommand($php . ' ' . $jxRunner . ' --quiet ' . escapeshellarg($jxbPath)), $coldReps, $coldWarm);

if (trim((string)shell_exec('command -v nasm 2>/dev/null')) !== '' && trim((string)shell_exec('command -v ld 2>/dev/null')) !== '') {
    $buildReps = 5;
    $out['native_build'] = bench('native-jxl-container-assembly-link', fn() => runCommand('bash ' . escapeshellarg(__DIR__ . '/native/x86_64/build-jxl-containers.sh')), $buildReps, 1);
} else {
    $out['native_build'] = ['name' => 'native-jxl-container-assembly-link', 'status' => 'unavailable'];
}

$httpCompiler = new PreparedCompiler();
$httpJxl = $httpCompiler->emitJxl($semanticHttp);
$httpJxb = JxbBook::compile($semanticHttp, 'http-bench')['bytes'];
$httpJxlPath = $tmp . '/http.jxl';
$httpJxbPath = $tmp . '/http.jxb';
file_put_contents($httpJxlPath, $httpJxl);
file_put_contents($httpJxbPath, $httpJxb);

$port = 19000 + (getmypid() % 1000);
$address = '127.0.0.1:' . $port;
$router = __DIR__ . '/benchmarks/nojs-http-router.php';
$log = $tmp . '/php-server.log';
$spec = [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']];
$env = $_ENV + ['JX_BENCH_JXL' => $httpJxlPath, 'JX_BENCH_JXB' => $httpJxbPath];
$proc = proc_open([PHP_BINARY, '-d', 'opcache.enable_cli=1', '-S', $address, $router], $spec, $pipes, __DIR__, $env);
if (is_resource($proc)) {
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if ($fp) { fclose($fp); $ready = true; break; }
        usleep(20000);
    }
    if ($ready) {
        $httpReps = max(15, $reps);
        foreach (['php', 'jx', 'jxl', 'jxb', 'host'] as $mode) {
            $url = 'http://' . $address . '/?mode=' . $mode . '&rows=100';
            $metric = bench('http-nojs-' . $mode, static function () use ($url): int {
                $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
                $body = file_get_contents($url, false, $ctx);
                if (!is_string($body) || !str_contains($body, '<table>')) throw new RuntimeException('HTTP benchmark response invalid');
                return strlen($body);
            }, $httpReps, 5, static fn($v): bool => (int)$v > 1000);
            $metric['rows_rendered'] = 100;
            $out['http_nojs'][] = $metric;
        }
    } else {
        $out['http_nojs'][] = ['name' => 'http-nojs', 'status' => 'server-start-failed'];
    }
    proc_terminate($proc);
    proc_close($proc);
} else {
    $out['http_nojs'][] = ['name' => 'http-nojs', 'status' => 'proc-open-failed'];
}

foreach (glob($tmp . '/*') ?: [] as $file) @unlink($file);
@rmdir($tmp);

file_put_contents(__DIR__ . '/benchmark-php-only-surface-results.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
