<?php declare(strict_types=1);

require_once __DIR__ . '/jx.php';
require_once __DIR__ . '/jx-bag-containers.php';

use jx\BagContainers;

$ops = (int)($argv[1] ?? 1_000_000);
$reps = (int)($argv[2] ?? 7);

function median(array $xs): float
{
    sort($xs, SORT_NUMERIC);
    $n = count($xs);
    return $n % 2 ? $xs[intdiv($n, 2)] : ($xs[$n/2-1] + $xs[$n/2]) / 2;
}

function bench(string $name, int $reps, callable $fn): array
{
    $times = [];
    for ($r=0; $r<$reps; $r++) {
        $t0 = hrtime(true);
        $fn();
        $times[] = (hrtime(true)-$t0)/1e6;
    }
    return [$name, median($times)];
}

$cases = [];
$cases[] = bench('vector append/pop', $reps, function () use ($ops) {
    $v = BagContainers::vector(max(4096, $ops*16), 'int');
    for ($i=0; $i<$ops; $i++) $v->append($i);
    for ($i=0; $i<$ops; $i++) $v->pop();
});
$cases[] = bench('queue enqueue/dequeue', $reps, function () use ($ops) {
    $q = BagContainers::queue(max(4096, $ops*16), 'int', 1024);
    for ($i=0; $i<$ops; $i++) $q->enqueue($i);
    for ($i=0; $i<$ops; $i++) $q->dequeue();
});
$cases[] = bench('deque back/front', $reps, function () use ($ops) {
    $d = BagContainers::deque(max(4096, $ops*16), 'int', 1024);
    for ($i=0; $i<$ops; $i++) $d->pushBack($i);
    for ($i=0; $i<$ops; $i++) $d->popFront();
});
$cases[] = bench('map put/get', $reps, function () use ($ops) {
    $m = BagContainers::map(max(4096, $ops*24), 'int');
    for ($i=0; $i<$ops; $i++) $m->put($i,$i);
    $sum=0;
    for ($i=0; $i<$ops; $i++) $sum += $m->get($i);
    if ($sum < 0) echo '';
});
$cases[] = bench('record dense slot', $reps, function () use ($ops) {
    $r = BagContainers::record(4096, ['health'=>'int','phi'=>'int','level'=>'int']);
    $slot = $r->slot('health');
    for ($i=0; $i<$ops; $i++) $r->put($slot,$i);
    $v=$r->get($slot);
    if ($v < 0) echo '';
});
$cases[] = bench('checkpoint boundary', $reps, function () use ($ops) {
    $v = BagContainers::vector(max(65536, min($ops,10000)*24), 'int');
    $n=min($ops,10000);
    for($i=0;$i<$n;$i++)$v->append($i);
    $v->checkpoint();
});

echo "JX Bag-backed containers; ops={$ops}; reps={$reps}\n";
foreach($cases as [$name,$ms]) printf("%-24s %10.3f ms\n", $name, $ms);
