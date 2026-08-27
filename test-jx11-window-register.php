<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\Bag;
use jx\DesktopHostBridge;
use jx\DesktopWindowRegister;

$r = new DesktopWindowRegister();
if ($r->intern('desktop-windows') !== 0) throw new RuntimeException('first WindowBag register must be W0');
if ($r->intern('desktop-windows') !== 0) throw new RuntimeException('WindowBag register interning must be stable');
if ($r->bag(0) !== 'desktop-windows') throw new RuntimeException('W0 must resolve its canonical Bag');

$cases = [[0,0],[17,3],[255,255]];
foreach ($cases as [$slot,$shadow]) {
    $packed = DesktopWindowRegister::pack($slot,$shadow);
    $parts = DesktopWindowRegister::unpack($packed);
    if ($parts['slot'] !== $slot || $parts['shadow'] !== $shadow) throw new RuntimeException('Window ref round trip failed');
    $canonical = "[{$slot}:{$shadow}]";
    if (DesktopWindowRegister::canonical($packed) !== $canonical) throw new RuntimeException('canonical window ref mismatch');
    if (DesktopWindowRegister::parse($canonical) !== $packed) throw new RuntimeException('window ref parse mismatch');
}

$bag = Bag::empty(65536);
$bridge = new DesktopHostBridge($bag);
$bridge->apply([
    'event'=>'window-open',
    'window'=>[
        'host_id'=>'x11:01020304',
        'window_ref'=>'[17:3]',
        'title'=>'Editor',
        'width'=>800,
        'height'=>600,
    ],
]);
$row = $bridge->rows()[0] ?? null;
if (!is_array($row)) throw new RuntimeException('bridge row missing');
if ($row['window_ref'] !== 0x1103 || $row['slot'] !== 17 || $row['shadow'] !== 3) {
    throw new RuntimeException('bridge did not retain packed [slot:shadow] identity');
}

echo "jx11-window-register: ok\n";
