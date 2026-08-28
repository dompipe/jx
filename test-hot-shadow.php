<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\HotRef;
use jx\HotShadow;

assert(HotShadow::STATE === 0);
assert(HotShadow::TASKBAR === 1);
assert(HotShadow::TITLE === 2);
assert(HotShadow::FOCUS === 3);
assert(HotShadow::GEOMETRY === 4);
assert(HotShadow::BANKS === 16);
assert(HotShadow::SHADOWS_PER_BANK === 8);
assert(HotShadow::HOT_ENTRIES === 128);

assert(HotShadow::opcode(0, 0) === 0x80);
assert(HotShadow::opcode(0, 7) === 0x87);
assert(HotShadow::opcode(1, 0) === 0x88);
assert(HotShadow::opcode(15, 7) === 0xff);
assert(HotShadow::decodeOpcode(0x80) === ['bank'=>0, 'shadow'=>0]);
assert(HotShadow::decodeOpcode(0xff) === ['bank'=>15, 'shadow'=>7]);
assert(HotShadow::isHotOpcode(0x80));
assert(!HotShadow::isHotOpcode(0x7f));

assert(HotRef::pack(17, HotShadow::STATE) === 0x1100);
assert(HotRef::pack(17, HotShadow::TASKBAR) === 0x1101);
assert(HotRef::pack(17, HotShadow::TITLE) === 0x1102);
assert(HotRef::pack(17, HotShadow::FOCUS) === 0x1103);
assert(HotRef::pack(17, HotShadow::GEOMETRY) === 0x1104);

/* Canonical dynamic identities still exist, but are lowered before execution. */
assert(HotShadow::dynamic(0) === 16);
assert(HotShadow::dynamic(239) === 255);

fwrite(STDOUT, "hot-shadow: v4 16x8 one-byte opcode map ok\n");
