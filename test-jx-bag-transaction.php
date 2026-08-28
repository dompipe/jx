<?php declare(strict_types=1);

require_once __DIR__ . '/jx-bag-transaction.php';

use jx\memory\BagWriteException;
use jx\memory\BagWriteTransaction;
use jx\memory\MemoryBag;
use jx\memory\RefAuthority;

$authority = new RefAuthority(str_repeat('k', 32));
$bag = new MemoryBag('bag:test');

$tx = new BagWriteTransaction($authority, $bag, 'program:2');
$token = $tx->sign();
assert($tx->state() === BagWriteTransaction::SIGNED);
$ref = $authority->verify($token);
assert($ref['bag'] === 'bag:test');
assert($ref['subject'] === 'program:2');
assert($ref['generation'] === 0);

$tx->authorize(static fn(string $subject, string $cap): bool => $subject === 'program:2' && $cap === 'bag.write')
   ->reserve()
   ->write('counter', 41);
assert($tx->state() === BagWriteTransaction::WRITTEN);
$generation = $tx->commit();
assert($generation === 1);
assert($bag->generation() === 1);
assert($bag->values()['counter'] === 41);
assert($tx->state() === BagWriteTransaction::COMMITTED);

// A signed reference is generation-bound and cannot be reused after another commit.
$stale = new BagWriteTransaction($authority, $bag, 'program:2');
$staleToken = $stale->sign();
$winner = new BagWriteTransaction($authority, $bag, 'program:2');
$winner->sign();
$winner->authorize(static fn(): bool => true)->reserve()->write('counter', 42)->commit();
assert($bag->generation() === 2);

$threw = false;
try {
    $stale->authorize(static fn(): bool => true);
} catch (BagWriteException $e) {
    $threw = str_contains($e->getMessage(), 'stale');
}
assert($threw);

// Tampering any token byte invalidates the MAC.
$tampered = $staleToken;
$tampered[5] = $tampered[5] === 'A' ? 'B' : 'A';
$threw = false;
try {
    $authority->verify($tampered);
} catch (BagWriteException $e) {
    $threw = str_contains($e->getMessage(), 'Invalid RefSign MAC') || str_contains($e->getMessage(), 'Malformed');
}
assert($threw);

// Authorization is mandatory before reserve/write.
$denied = new BagWriteTransaction($authority, $bag, 'program:9');
$denied->sign();
$threw = false;
try {
    $denied->authorize(static fn(): bool => false);
} catch (BagWriteException $e) {
    $threw = str_contains($e->getMessage(), 'lacks bag.write');
}
assert($threw);
$denied->abort();
assert($denied->state() === BagWriteTransaction::ABORTED);

echo "PASS RefSign + Bag write transaction law\n";
