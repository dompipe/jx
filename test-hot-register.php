<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\HotRef;
use jx\HotRegisterBank;
use jx\DesktopWindowRegister;

$bank = new HotRegisterBank('bag');
if ($bank->intern('desktop-windows') !== 0) throw new RuntimeException('first hot register must be 0');
if ($bank->intern('desktop-windows') !== 0) throw new RuntimeException('hot register interning must be stable');
if ($bank->target(0) !== 'desktop-windows') throw new RuntimeException('hot register target mismatch');

$cases = [
    [0, 0, 0x0000, '[0:0]'],
    [17, 3, 0x1103, '[17:3]'],
    [255, 255, 0xffff, '[255:255]'],
];
foreach ($cases as [$slot, $shadow, $packed, $canonical]) {
    if (HotRef::pack($slot, $shadow) !== $packed) throw new RuntimeException('HotRef pack mismatch');
    if (HotRef::canonical($packed) !== $canonical) throw new RuntimeException('HotRef canonical mismatch');
    if (HotRef::parse($canonical) !== $packed) throw new RuntimeException('HotRef parse mismatch');
    if (DesktopWindowRegister::pack($slot, $shadow) !== $packed) throw new RuntimeException('Window register ABI mismatch');
}

echo "hot-register: ok\n";
