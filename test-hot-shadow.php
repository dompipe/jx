<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\HotRef;
use jx\HotShadow;

assert(HotShadow::STATE === 0);
assert(HotShadow::TASKBAR === 1);
assert(HotShadow::TITLE === 2);
assert(HotShadow::FOCUS === 3);
assert(HotShadow::GEOMETRY === 4);
assert(HotShadow::FIRST_DYNAMIC === 16);

assert(HotRef::pack(17, HotShadow::STATE) === 0x1100);
assert(HotRef::pack(17, HotShadow::TASKBAR) === 0x1101);
assert(HotRef::pack(17, HotShadow::TITLE) === 0x1102);
assert(HotRef::pack(17, HotShadow::FOCUS) === 0x1103);
assert(HotRef::pack(17, HotShadow::GEOMETRY) === 0x1104);
assert(HotShadow::dynamic(0) === 16);
assert(HotShadow::dynamic(239) === 255);

fwrite(STDOUT, "hot-shadow: ok\n");
