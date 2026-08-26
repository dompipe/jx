<?php declare(strict_types=1);

require_once __DIR__ . '/jx.php';
require_once __DIR__ . '/jx-bag-containers.php';

use jx\BagContainers;

function same(mixed $actual, mixed $expected, string $label): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL {$label}: got " . var_export($actual, true) . " expected " . var_export($expected, true) . PHP_EOL);
        exit(1);
    }
}

$record = BagContainers::record(4096, [
    'health' => 'int',
    'phi' => ['type' => 'int', 'default' => 2],
]);
$record->put('health', 100)->checkpoint();
$record->put('health', 7)->restore();
same($record->get('health'), 100, 'record checkpoint restore');
same($record->slot('phi'), 1, 'record dense slot');

$vector = BagContainers::vector(4096, 'int');
$vector->append(1)->append(2);
same($vector->get(1), 2, 'vector get');
same($vector->pop(), 2, 'vector pop');

$stack = BagContainers::stack(4096, 'string');
$stack->pushValue('a')->pushValue('b');
same($stack->top(), 'b', 'stack top');
same($stack->pop(), 'b', 'stack pop');

$queue = BagContainers::queue(4096, 'int', 2);
$queue->enqueue(1)->enqueue(2)->enqueue(3);
same($queue->dequeue(), 1, 'queue dequeue');
same($queue->front(), 2, 'queue front');

$deque = BagContainers::deque(4096, 'int', 2);
$deque->pushBack(2)->pushFront(1)->pushBack(3);
same($deque->popFront(), 1, 'deque pop front');
same($deque->popBack(), 3, 'deque pop back');
same($deque->back(), 2, 'deque back');

$map = BagContainers::map(4096, 'int');
$map->put('a', 1)->put('b', 2);
same($map->get('a'), 1, 'map get');
same($map->has('b'), true, 'map has');

$set = BagContainers::set(4096, 'string');
$set->add('x')->add('x')->add('y');
same(count($set), 2, 'set uniqueness');
same($set->contains('y'), true, 'set contains');

$canonical = $deque->canonical();
same($canonical['abi'], 'jx.bag.container/1', 'canonical ABI');
same($canonical['discipline'], 'deque', 'canonical discipline');
same($canonical['layout']['strategy'], 'double-ended-power-of-two-ring', 'native shadow hint');

echo "PASS JX Bag-backed containers\n";
