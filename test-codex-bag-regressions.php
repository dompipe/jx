<?php declare(strict_types=1);

require_once __DIR__ . '/jx.php';
require_once __DIR__ . '/jx-bag-containers.php';

use jx\BagContainers;
use LogicException;

function require_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// Codex P2: checkpoint revisions belong to each named node, not the container globally.
$vector = BagContainers::vector(4096, 'int');
$vector->append(10)->append(20);
$vector->checkpoint('a')->checkpoint('b');
$a = $vector->bag()->peek('a');
$b = $vector->bag()->peek('b');
require_true(is_array($a) && is_array($b), 'Both named checkpoints must be written at the same revision');
require_true(($a['payload']['values'] ?? null) === [10, 20], 'Checkpoint a payload mismatch');
require_true(($b['payload']['values'] ?? null) === [10, 20], 'Checkpoint b payload mismatch');

$vector->append(30);
$vector->checkpoint('b')->checkpoint('a');
$a = $vector->bag()->peek('a');
$b = $vector->bag()->peek('b');
require_true(($a['payload']['values'] ?? null) === [10, 20, 30], 'Checkpoint a must refresh after mutation');
require_true(($b['payload']['values'] ?? null) === [10, 20, 30], 'Checkpoint b must refresh after mutation');

// Codex P2: an explicitly stored null is present and must not collapse to the caller default.
$map = BagContainers::map(4096);
$map->put('present-null', null);
require_true($map->has('present-null'), 'Map must report an explicitly stored null key as present');
require_true($map->get('present-null', 'fallback') === null, 'Map get() must preserve explicitly stored null');
require_true($map->get('missing', 'fallback') === 'fallback', 'Map get() must use default only for a missing key');

// Codex P2: SetBag must never admit arbitrary MapBag keys that bypass canonical keyOf(value).
$set = BagContainers::set(4096);
$set->add('x');
require_true($set->contains('x'), 'Set add()/contains() invariant failed');

$putBlocked = false;
try {
    $set->put('arbitrary', 'y');
} catch (LogicException) {
    $putBlocked = true;
}
require_true($putBlocked, 'SetBag must block inherited map-key put()');
require_true(!$set->contains('y'), 'Blocked SetBag put() must not mutate the set');

$removeBlocked = false;
try {
    $set->remove('arbitrary');
} catch (LogicException) {
    $removeBlocked = true;
}
require_true($removeBlocked, 'SetBag must block inherited map-key remove()');
require_true($set->contains('x'), 'Blocked SetBag remove() must not corrupt canonical membership');

$set->emplace('z');
require_true($set->contains('z'), 'Set emplace(value) must preserve canonical membership');
require_true($set->discard('z'), 'Set discard(value) must remove canonical membership');
require_true(!$set->contains('z'), 'Discarded set member must be absent');

echo "PASS Codex Bag regressions: per-node checkpoints, null map values, canonical set mutation\n";
