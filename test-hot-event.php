<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\HotAddress;
use jx\HotDelivery;
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
}

$reaction = new HotReaction('controls', 3, 12, 1, HotDelivery::COUNT);
$d = $reaction->descriptor();
if ($d['address'] !== 0x030c01 || $d['canonical'] !== 'W3:[12:1]' || $d['delivery'] !== 'count') {
    throw new RuntimeException('hot reaction descriptor mismatch');
}

echo "jx-hot-event: ok\n";
