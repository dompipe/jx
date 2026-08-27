<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/jx/bootstrap.php';
require_once dirname(__DIR__) . '/pasl/xi/src/Bag.php';

use jx\RefIds;

function refidSmoke(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('refid smoke failed: ' . $message);
}

// Canonical Bags are RefId-capable through the core registry. The same live Bag
// always resolves to the same process-local RefId.
$core = \jx\Bag::underwrite(4096);
$core->write('value', 42);
$coreRefA = RefIds::for($core);
$coreRefB = RefIds::for($core);
refidSmoke($coreRefA === $coreRefB, 'canonical Bag keeps one live RefId');
refidSmoke($coreRefA->kind() === 'bag', 'canonical Bag RefId kind');
refidSmoke(RefIds::resolve($coreRefA) === $core, 'RefId resolves to live canonical Bag');

$state = $coreRefA->capture($core, 1, ['id', 'capacity', 'used', 'available', 'bindings']);
refidSmoke(($state['ref'] ?? null) === $coreRefA->id(), 'capture names RefId');
refidSmoke(isset($state['capacity'], $state['used']), 'capture includes Bag size state');

// XI/channel Bags install RefId immediately at construction and expose it.
$channel = new \Bag(['hello' => 'world'], 65_536, 1024);
$channelRef = $channel->refId();
refidSmoke($channelRef->kind() === 'bag', 'XI Bag installs RefId');
refidSmoke($channelRef->segmentBytes() === 1024, 'developer selects RefId segment size');
$beforeState = $channelRef->jsonSerialize()['state'] ?? null;
$channel->set('next', 2);
$afterState = $channelRef->jsonSerialize()['state'] ?? null;
refidSmoke($beforeState !== $afterState, 'Bag mutation refreshes RefId state');

// RefId memory never overflows into another object. Oversized writes are
// refused as false and the process continues.
$smallRef = RefIds::install(new stdClass(), 'object', 'small-memory', 256);
refidSmoke($smallRef->remember('tiny', 'ok'), 'small RefId write accepted');
refidSmoke(!$smallRef->remember('too-big', str_repeat('x', 8192)), 'oversized RefId write refused');
refidSmoke($smallRef->saturated(), 'RefId marks its own segment saturated');

// A call writer records intent only. It does not reach into the target object.
$target = new class {
    public int $value = 7;
    public function id(): string { return 'target'; }
};
$targetRef = RefIds::install($target, 'worker', 'target-worker', 1024);
$call = $channelRef->call($targetRef, 'refresh', ['amount' => 3], 'result');
refidSmoke(($call['accepted'] ?? false) === true, 'method call record accepted');
refidSmoke(($call['target'] ?? null) === $targetRef->id(), 'call addresses target RefId');
refidSmoke(($call['callable'] ?? null) === 'refresh', 'call records method');
refidSmoke($target->value === 7, 'writing call does not mutate target memory');

$fn = $channelRef->callFunction('recalculate', ['x' => 4], 'answer');
refidSmoke(($fn['type'] ?? null) === 'function', 'function call record written');
refidSmoke(count($channelRef->calls()) >= 2, 'RefId retains bounded call memory');

// A RefId descriptor can itself be stored as Bag data without storing the live
// referenced object.
$store = \jx\Bag::underwrite(4096);
refidSmoke($channelRef->store($store, 'player-ref'), 'RefId descriptor stores in Bag');
$saved = $store->read('player-ref');
refidSmoke(is_array($saved) && ($saved['id'] ?? null) === $channelRef->id(), 'stored RefId is data-shaped');

echo "refid-smoke: ok\n";
