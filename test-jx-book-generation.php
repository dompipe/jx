<?php declare(strict_types=1);

require_once __DIR__ . '/jx-book-generation.php';

use jx\semantic\BookAdmissionPolicy;
use jx\semantic\BookGenerationException;
use jx\semantic\BookGenerationManager;
use jx\semantic\BookTrust;
use jx\semantic\JxlBook64;

$src1 = 'int $x = 1; $x;';
$src2 = 'int $x = 2; $x;';
$src3 = 'int $x = 3; $x;';
$b1 = JxlBook64::compile($src1, 'live');
$b2 = JxlBook64::compile($src2, 'live');
$b3 = JxlBook64::compile($src3, 'live');

if (BookTrust::sodiumAvailable()) {
    $key = BookTrust::keypair('jx-generation-test');
    $resolver = static fn(string $keyId): ?string => $keyId === $key['key_id'] ? $key['public'] : null;
    $policy = new BookAdmissionPolicy(['bag.write'], $resolver, true);
    $sign = static fn(array $book): array => BookTrust::sign($book['bytes'], ['bag.write'], 'jx-ci', $key['key_id'], $key['secret']);
} else {
    $policy = new BookAdmissionPolicy();
    $sign = static fn(array $book): ?array => null;
}

$m = new BookGenerationManager($policy);
$m->seedState(['bag_id' => 'bag:1', 'counter' => 10, 'nested' => ['v' => 1]]);

$c1 = $m->stage($b1['bytes'], $sign($b1));
assert($c1->generation === 1);
$a1 = $m->activate(static function(array $state): array {
    $state['counter'] += 1;
    return $state;
});
assert($a1->generation === 1);
assert($m->state()['counter'] === 11);
assert($m->historyDepth() === 0);

$c2 = $m->stage($b2['bytes'], $sign($b2));
assert($c2->generation === 2);
$a2 = $m->activate(static function(array $state, $old, $candidate): array {
    assert($old?->generation === 1);
    assert($candidate->generation === 2);
    $state['counter'] = 20;
    $state['nested']['v'] = 2;
    return $state;
});
assert($a2->generation === 2);
assert($m->state()['counter'] === 20);
assert($m->historyDepth() === 1);

// Failed migration never replaces active Book or state.
$m->stage($b3['bytes'], $sign($b3));
$before = $m->state();
$threw = false;
try {
    $m->activate(static function(array $state): array {
        $state['counter'] = 999;
        throw new RuntimeException('migration exploded');
    });
} catch (BookGenerationException $e) {
    $threw = str_contains($e->getMessage(), 'Candidate activation failed');
}
assert($threw);
assert($m->active()?->generation === 2);
assert($m->candidate() === null);
assert($m->state() === $before);

// Rollback restores both previous executable generation and its state snapshot.
$r = $m->rollback();
assert($r->generation === 1);
assert($m->state()['counter'] === 11);
assert($m->state()['nested']['v'] === 1);
assert($m->historyDepth() === 0);

// Identical active bytes are not a new generation.
$threw = false;
try { $m->stage($b1['bytes'], $sign($b1)); }
catch (BookGenerationException $e) { $threw = str_contains($e->getMessage(), 'identical'); }
assert($threw);

echo "PASS transactional Book generation activation/rollback\n";
