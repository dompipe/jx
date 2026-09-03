<?php declare(strict_types=1);

require_once __DIR__ . '/jx.php';
require_once __DIR__ . '/jx-bag-containers.php';

use jx\BagContainers;

/**
 * Complete Bag-container benchmark.
 *
 * Every discipline uses the same total-operation convention:
 *   N = total_ops / 2
 *   N writes/inserts + N reads/removals = total_ops
 *
 * Usage:
 *   php benchmark-jx-bag-containers.php [ops] [reps] [warmups] [--json]
 *   php benchmark-jx-bag-containers.php 1000000 9 2
 */

$ops = max(2, (int)($argv[1] ?? 1_000_000));
$reps = max(1, (int)($argv[2] ?? 9));
$warmups = max(0, (int)($argv[3] ?? 2));
$jsonOnly = in_array('--json', $argv, true);
$n = intdiv($ops, 2);

function bag_bench_stats(array $xs): array
{
    sort($xs, SORT_NUMERIC);
    $count = count($xs);
    $median = $count % 2
        ? $xs[intdiv($count, 2)]
        : ($xs[$count/2 - 1] + $xs[$count/2]) / 2;
    $p95Index = max(0, min($count - 1, (int)ceil($count * 0.95) - 1));
    return [
        'median_ms' => $median,
        'min_ms' => $xs[0],
        'p95_ms' => $xs[$p95Index],
    ];
}

function bag_bench(string $name, int $ops, int $reps, int $warmups, callable $fn): array
{
    for ($r = 0; $r < $warmups; $r++) {
        $fn();
    }

    $times = [];
    $checksum = null;
    for ($r = 0; $r < $reps; $r++) {
        gc_collect_cycles();
        $t0 = hrtime(true);
        $value = $fn();
        $times[] = (hrtime(true) - $t0) / 1e6;
        if ($checksum === null) {
            $checksum = $value;
        } elseif ($checksum !== $value) {
            throw new RuntimeException("{$name}: checksum changed between benchmark repetitions");
        }
    }

    $stats = bag_bench_stats($times);
    $seconds = $stats['median_ms'] / 1000.0;
    return [
        'name' => $name,
        'ops' => $ops,
        'median_ms' => $stats['median_ms'],
        'min_ms' => $stats['min_ms'],
        'p95_ms' => $stats['p95_ms'],
        'mops_s' => $seconds > 0 ? ($ops / $seconds) / 1e6 : INF,
        'ns_op' => ($stats['median_ms'] * 1e6) / $ops,
        'checksum' => $checksum,
    ];
}

$cases = [];

$cases[] = bag_bench('record put/get', $ops, $reps, $warmups, function () use ($n): int {
    $r = BagContainers::record(4096, ['health'=>'int', 'phi'=>'int', 'level'=>'int']);
    $slot = $r->slot('health');
    $x = 0;
    for ($i = 0; $i < $n; $i++) {
        $r->put($slot, $i);
        $x ^= (int)$r->get($slot);
    }
    return $x;
});

$cases[] = bag_bench('vector append/get', $ops, $reps, $warmups, function () use ($n): int {
    $v = BagContainers::vector(max(4096, $n * 16), 'int');
    for ($i = 0; $i < $n; $i++) $v->append($i);
    $x = 0;
    for ($i = 0; $i < $n; $i++) $x ^= (int)$v->get($i);
    return $x;
});

$cases[] = bag_bench('stack push/pop', $ops, $reps, $warmups, function () use ($n): int {
    $s = BagContainers::stack(max(4096, $n * 16), 'int');
    for ($i = 0; $i < $n; $i++) $s->pushValue($i);
    $x = 0;
    for ($i = 0; $i < $n; $i++) $x ^= (int)$s->pop();
    return $x;
});

$cases[] = bag_bench('queue enqueue/dequeue', $ops, $reps, $warmups, function () use ($n): int {
    $q = BagContainers::queue(max(4096, $n * 16), 'int', 1024);
    for ($i = 0; $i < $n; $i++) $q->enqueue($i);
    $x = 0;
    for ($i = 0; $i < $n; $i++) $x ^= (int)$q->dequeue();
    return $x;
});

$cases[] = bag_bench('deque back/front', $ops, $reps, $warmups, function () use ($n): int {
    $d = BagContainers::deque(max(4096, $n * 16), 'int', 1024);
    for ($i = 0; $i < $n; $i++) $d->pushBack($i);
    $x = 0;
    for ($i = 0; $i < $n; $i++) $x ^= (int)$d->popFront();
    return $x;
});

$cases[] = bag_bench('map put/get', $ops, $reps, $warmups, function () use ($n): int {
    $m = BagContainers::map(max(4096, $n * 24), 'int');
    for ($i = 0; $i < $n; $i++) $m->put($i, $i);
    $x = 0;
    for ($i = 0; $i < $n; $i++) $x ^= (int)$m->get($i);
    return $x;
});

$cases[] = bag_bench('set add/contains', $ops, $reps, $warmups, function () use ($n): int {
    $s = BagContainers::set(max(4096, $n * 16), 'int');
    for ($i = 0; $i < $n; $i++) $s->add($i);
    $x = 0;
    for ($i = 0; $i < $n; $i++) $x ^= (int)$s->contains($i);
    return $x;
});

// Canonicalization is deliberately separate from hot operations. This bounded
// case prevents a normal benchmark run from serializing millions of values.
$checkpointItems = min($n, 10_000);
$checkpoint = bag_bench('vector checkpoint boundary', 1, $reps, $warmups, function () use ($checkpointItems): int {
    $v = BagContainers::vector(max(65_536, $checkpointItems * 24), 'int');
    for ($i = 0; $i < $checkpointItems; $i++) $v->append($i);
    $v->checkpoint();
    return $v->count();
});
$checkpoint['items_serialized'] = $checkpointItems;

$result = [
    'suite' => 'jx-bag-containers',
    'ops' => $ops,
    'reps' => $reps,
    'warmups' => $warmups,
    'cases' => $cases,
    'checkpoint' => $checkpoint,
    'process_peak_mb' => memory_get_peak_usage(true) / 1048576,
];

if ($jsonOnly) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    exit;
}

echo "JX Bag-backed containers; total_ops={$ops}; reps={$reps}; warmups={$warmups}\n";
printf("%-24s %10s %10s %10s %10s %10s\n", 'workload', 'median', 'min', 'p95', 'Mops/s', 'ns/op');
foreach ($cases as $case) {
    printf(
        "%-24s %9.3f %9.3f %9.3f %10.2f %10.2f\n",
        $case['name'], $case['median_ms'], $case['min_ms'], $case['p95_ms'], $case['mops_s'], $case['ns_op']
    );
}
printf(
    "checkpoint: %d vector items -> median %.3f ms (p95 %.3f ms)\n",
    $checkpointItems, $checkpoint['median_ms'], $checkpoint['p95_ms']
);
printf("process peak: %.3f MB\n", $result['process_peak_mb']);
