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

$multiCheckpoint = BagContainers::vector(4096, 'int');
$multiCheckpoint->append(10)->checkpoint('a')->checkpoint('b');
$multiCheckpoint->put(0, 20)->checkpoint('b');
$multiCheckpoint->restore('a');
same($multiCheckpoint->get(0), 10, 'checkpoint revisions are tracked per node');
$multiCheckpoint->restore('b');
same($multiCheckpoint->get(0), 20, 'second checkpoint node keeps its own latest revision');

$vector = BagContainers::vector(4096, 'int');
$vector->append(1)->append(3)->emplace(1,2);
same($vector->toArray(), [1,2,3], 'vector emplace packs tail');
same($vector->get(1), 2, 'vector get');
same($vector->pop(), 3, 'vector pop');

$stack = BagContainers::stack(4096, 'string');
$stack->pushValue('a')->pushValue('c')->emplace(1,'b');
same($stack->toArray(), ['a','b','c'], 'stack contiguous emplace');
same($stack->top(), 'c', 'stack top');
same($stack->pop(), 'c', 'stack pop');

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
$map->put('b', 2)->put('a', 1);
same($map->emplace('a', 99), 1, 'map emplace returns existing');
same($map->get('a'), 1, 'map emplace does not replace');
$map->put('a', 7);
same($map->get('a'), 7, 'map put overwrites Entry.value in place');
same($map->emplace('c', 3), 3, 'map emplace inserts absent');
same($map->get('c'), 3, 'map emplace stored absent');
$map->put('nullable', null);
same($map->has('nullable'), true, 'map null key exists');
same($map->get('nullable', 'fallback'), null, 'map preserves stored null over default');
same($map->get('missing', 'fallback'), 'fallback', 'map default only applies to missing key');
$mapCanonical = $map->canonical();
same($mapCanonical['layout']['strategy'], 'ordered-keyed-vector', 'map is keyed Vector');
same($mapCanonical['payload']['entries'][0], ['a',7], 'map first canonical entry');
same($mapCanonical['payload']['entries'][1], ['b',2], 'map second canonical entry');
same($mapCanonical['payload']['entries'][2], ['c',3], 'map third canonical entry');

$set = BagContainers::set(4096, 'string');
same($set->emplace('x'), 'x', 'set emplace inserts');
same($set->emplace('x'), 'x', 'set emplace existing');
$set->add('x')->add('y');
same(count($set), 2, 'set uniqueness');
same($set->contains('y'), true, 'set contains');
$putBlocked = false;
try { $set->put('arbitrary', 'z'); } catch (LogicException) { $putBlocked = true; }
same($putBlocked, true, 'set blocks inherited arbitrary-key put');
same(count($set), 2, 'blocked set put does not corrupt count');
same($set->contains('z'), false, 'blocked set put cannot create unreachable value');

$canonical = $deque->canonical();
same($canonical['abi'], 'jx.bag.container/1', 'canonical ABI');
same($canonical['discipline'], 'deque', 'canonical discipline');
same($canonical['layout']['strategy'], 'double-ended-power-of-two-ring', 'native shadow hint');

echo "PASS JX Bag-backed containers\n";
