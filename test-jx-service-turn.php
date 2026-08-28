<?php declare(strict_types=1);

require_once __DIR__ . '/jx-service-turn.php';

use jx\runtime\ServiceMutation;
use jx\runtime\ServiceTurn;

$queues = [
    1 => ['b1','b2'],
    3 => ['f1','f2','f3','f4','f5'],
    5 => ['c1'],
];
$wakes = [];
$executed = [];
$published = [];
$dirty = [];
$presents = 0;

$turn = new ServiceTurn(4);
$stats = $turn->run(
    static fn(): ?int => 3,
    static fn(): array => [5,3,1],
    static function(int $pid) use (&$queues): mixed {
        if (($queues[$pid] ?? []) === []) return null;
        return array_shift($queues[$pid]);
    },
    static function(int $pid) use (&$wakes): void { $wakes[] = $pid; },
    static function(int $pid, mixed $event) use (&$executed): ServiceMutation {
        $executed[] = [$pid, $event];
        return new ServiceMutation(true, 'bag:' . $pid, count($executed), true);
    },
    static function(string $bag, int $generation) use (&$published): void { $published[] = [$bag, $generation]; },
    static function(int $pid) use (&$dirty): void { $dirty[] = $pid; },
    static function() use (&$presents): void { $presents++; },
);

assert($stats['route'] === [3,1,5]);
assert($stats['foreground_events'] === 4);
assert($stats['background_events'] === 2);
assert($stats['events'] === 6);
assert($stats['mutations'] === 6);
assert($stats['presents'] === 1);
assert($presents === 1);
assert(array_slice($executed, 0, 4) === [[3,'f1'],[3,'f2'],[3,'f3'],[3,'f4']]);
assert($executed[4] === [1,'b1']);
assert($executed[5] === [5,'c1']);
assert($queues[3] === ['f5']);
assert($queues[1] === ['b2']);

// No valid primary: stable PID order, one event each.
$queues = [2 => ['a'], 4 => ['b']];
$order = [];
$stats2 = (new ServiceTurn(8))->run(
    static fn(): ?int => 99,
    static fn(): array => [4,2],
    static function(int $pid) use (&$queues): mixed { return array_shift($queues[$pid]); },
    static fn(int $pid) => null,
    static function(int $pid, mixed $event) use (&$order): ServiceMutation { $order[] = $pid; return ServiceMutation::none(); },
    static fn(string $bag, int $generation) => null,
    static fn(int $pid) => null,
    static fn() => null,
);
assert($stats2['route'] === [2,4]);
assert($order === [2,4]);
assert($stats2['presents'] === 0);

echo "PASS foreground-first fair service turn\n";
