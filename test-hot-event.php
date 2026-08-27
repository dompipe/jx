<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\HotAddress;
use jx\HotDelivery;
use jx\HotInputPolicy;
use jx\HotPacket;
use jx\HotReaction;

$cases = [
    ['W0:[0:0]', 0x000000],
    ['W0:[17:3]', 0x001103],
    ['W3:[12:1]', 0x030c01],
    ['W255:[255:255]', 0xffffff],
];

foreach ($cases as [$canonical, $packed]) {
    if (HotAddress::parse($canonical) !== $packed) {
        throw new RuntimeException("hot address parse mismatch: {$canonical}");
    }
    if (HotAddress::canonical($packed) !== $canonical) {
        throw new RuntimeException("hot address canonical mismatch: {$canonical}");
    }
}

$parts = HotAddress::unpack(0x030c01);
if ($parts !== ['register'=>3, 'slot'=>12, 'shadow'=>1, 'ref'=>0x0c01]) {
    throw new RuntimeException('hot address unpack mismatch');
}
if (HotAddress::bytes(0x030c01) !== [3,12,1]) {
    throw new RuntimeException('hot address byte order mismatch');
}

foreach (HotDelivery::values() as $policy) {
    if (HotDelivery::normalize(strtoupper($policy)) !== $policy) {
        throw new RuntimeException("delivery normalization failed: {$policy}");
    }
    if (HotDelivery::fromCode(HotDelivery::code($policy)) !== $policy) {
        throw new RuntimeException("delivery code round-trip failed: {$policy}");
    }
}

if (HotInputPolicy::forEvent('pointer-move') !== HotDelivery::LATEST ||
    HotInputPolicy::forEvent('wheel') !== HotDelivery::ACCUMULATE ||
    HotInputPolicy::forEvent('click') !== HotDelivery::COUNT ||
    HotInputPolicy::forEvent('key-down') !== HotDelivery::QUEUE) {
    throw new RuntimeException('hot input default delivery policy mismatch');
}

$reaction = new HotReaction('controls', 3, 12, 1, HotDelivery::COUNT);
$d = $reaction->descriptor();
if ($d['address'] !== 0x030c01 || $d['canonical'] !== 'W3:[12:1]' || $d['delivery'] !== 'count') {
    throw new RuntimeException('hot reaction descriptor mismatch');
}

$packet = HotPacket::encode(0x030c01, "\x01\x02click", HotDelivery::COUNT, 0x80);
if (strlen($packet) !== HotPacket::HEADER_BYTES + 7) {
    throw new RuntimeException('hot packet encoded length mismatch');
}
$wire = array_values(unpack('C*', substr($packet, 0, HotPacket::HEADER_BYTES)));
if ($wire !== [1, 3, 12, 1, 3, 128, 0, 7]) {
    throw new RuntimeException('hot packet header bytes mismatch: '.json_encode($wire));
}
$decoded = HotPacket::decode($packet);
if ($decoded['canonical'] !== 'W3:[12:1]' || $decoded['delivery'] !== HotDelivery::COUNT ||
    $decoded['flags'] !== 0x80 || $decoded['payload'] !== "\x01\x02click") {
    throw new RuntimeException('hot packet round-trip mismatch');
}

echo "jx-hot-event: ok\n";
