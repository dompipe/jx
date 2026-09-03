<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxl-compiler.php';

use jx\semantic\JxlContainerInstruction;
use jx\semantic\JxlContainerOpcode;
use jx\semantic\JxlOp;
use jx\semantic\PreparedCompiler;

$compiler = new PreparedCompiler();

// Operation-specific admission bindings. Public spellings are deliberately
// varied to prove that aliases/discipline decisions disappear before JXL.
$qPush = $compiler->bindContainer(10, 'queue', 'enqueue', 8, 1024);
$qPop  = $compiler->bindContainer(10, 'queue', 'dequeue', 8, 1024);
$vPush = $compiler->bindContainer(11, 'vector', 'append', 8, 4096);
$vGet  = $compiler->bindContainer(11, 'vector', 'read', 8, 4096);
$vPut  = $compiler->bindContainer(11, 'vector', 'write', 8, 4096);
$vEmplace = $compiler->bindContainer(11, 'vector', 'insert', 8, 4096);
$dFront = $compiler->bindContainer(12, 'deque', 'pushfront', 8, 256);
$dBack  = $compiler->bindContainer(12, 'deque', 'push', 8, 256);
$mPut = $compiler->bindContainer(13, 'map', 'put', 8, 2048);
$mGet = $compiler->bindContainer(13, 'map', 'get', 8, 2048);
$mHas = $compiler->bindContainer(13, 'map', 'contains', 8, 2048);
$mRemove = $compiler->bindContainer(13, 'map', 'remove', 8, 2048);
$sAdd = $compiler->bindContainer(14, 'set', 'add', 8, 2048);
$sHas = $compiler->bindContainer(14, 'set', 'has', 8, 2048);
$sRemove = $compiler->bindContainer(14, 'set', 'erase', 8, 2048);
$rGet = $compiler->bindContainer(15, 'record', 'get', 8, 32);
$rPut = $compiler->bindContainer(15, 'record', 'put', 8, 32);
$sync = $compiler->bindContainer(10, 'queue', 'checkpoint', 8, 1024);

// Map/Set capacities are ordinary array capacities. Deliberately use non-power-
// of-two values to make a regression back to hash/ring admission impossible.
$mOdd = $compiler->bindContainer(16, 'map', 'put', 8, 1500);
$sOdd = $compiler->bindContainer(17, 'set', 'add', 8, 1501);
$mReserve = $compiler->bindContainer(16, 'map', 'reserve', 8, 1500);
$sReserve = $compiler->bindContainer(17, 'set', 'reserve', 8, 1501);

assert($qPush->operation === 'PUSH');
assert($qPush->nativeSymbol === 'jx_queue_push_u64');
assert($qPop->operation === 'POP');
assert($qPop->nativeSymbol === 'jx_queue_pop_u64');
assert($vPush->nativeSymbol === 'jx_vector_push_u64');
assert($vGet->nativeSymbol === 'jx_vector_get_u64');
assert($vPut->nativeSymbol === 'jx_vector_put_u64');
assert($vEmplace->nativeSymbol === 'jx_vector_emplace_u64');
assert($dFront->operation === 'PUSHF');
assert($dFront->nativeSymbol === 'jx_deque_push_front_u64');
assert($dBack->operation === 'PUSHB');
assert($dBack->nativeSymbol === 'jx_deque_push_back_u64');
assert($mPut->nativeSymbol === 'jx_map_vector_put_u64');
assert($mGet->nativeSymbol === 'jx_map_vector_get_u64');
assert($mHas->nativeSymbol === 'jx_map_vector_has_u64');
assert($mRemove->nativeSymbol === 'jx_map_vector_remove_u64');
assert($sAdd->operation === 'EMPLACE');
assert($sAdd->nativeSymbol === 'jx_set_add_u64');
assert($sHas->nativeSymbol === 'jx_set_has_u64');
assert($sRemove->nativeSymbol === 'jx_set_remove_u64');
assert($rGet->nativeSymbol === 'jx_record_get_u64');
assert($rPut->nativeSymbol === 'jx_record_put_u64');
assert($sync->nativeSymbol === 'jx_bag_sync');
assert($mReserve->nativeSymbol === 'jx_sorted_reserve_u64');
assert($sReserve->nativeSymbol === 'jx_sorted_reserve_u64');

// Identical prepared operations dedupe to exactly one binding ID.
$qPushAgain = $compiler->bindContainer(10, 'queue', 'BPUSH', 8, 1024);
assert($qPushAgain->id === $qPush->id);

// Container opcode range must remain separate from the existing core JXL op set.
$coreOps = (new ReflectionClass(JxlOp::class))->getConstants();
foreach ($coreOps as $value) {
    if (is_int($value)) assert($value < JxlContainerOpcode::PUSH || $value > JxlContainerOpcode::SYNC);
}

// PUSH: src0=R2, no src1, discard return value.
$pushBytes = $compiler->emitContainer($qPush, 2);
assert(strlen($pushBytes) === JxlContainerInstruction::BYTES);
assert((ord($pushBytes[0]) & 0x80) === 0);
for ($i = 1; $i < JxlContainerInstruction::BYTES; $i++) {
    assert((ord($pushBytes[$i]) & 0x80) !== 0);
}
$pushDecoded = JxlContainerInstruction::decode($pushBytes);
assert($pushDecoded['binding_id'] === $qPush->id);
assert($pushDecoded['operation'] === 'PUSH');
assert($pushDecoded['src0'] === 2);
assert($pushDecoded['src1'] === null);
assert($pushDecoded['dst'] === null);
assert($pushDecoded['next'] === 6);

// POP: no inputs, result -> R3.
$popBytes = $compiler->emitContainer($qPop, null, null, 3);
$popDecoded = JxlContainerInstruction::decode($popBytes);
assert($popDecoded['src0'] === null && $popDecoded['src1'] === null);
assert($popDecoded['dst'] === 3);

// GET: index/key R1 -> R4.
$getBytes = $compiler->emitContainer($vGet, 1, null, 4);
$getDecoded = JxlContainerInstruction::decode($getBytes);
assert($getDecoded['src0'] === 1 && $getDecoded['dst'] === 4);

// EMPLACE: index/key R0 + value R1 -> optional result R5.
$emplaceBytes = $compiler->emitContainer($vEmplace, 0, 1, 5);
$emplaceDecoded = JxlContainerInstruction::decode($emplaceBytes);
assert($emplaceDecoded['src0'] === 0 && $emplaceDecoded['src1'] === 1 && $emplaceDecoded['dst'] === 5);

// Malformed attachments are rejected.
$bad = $pushBytes;
$bad[3] = chr(2);
$failed = false;
try {
    JxlContainerInstruction::decode($bad);
} catch (InvalidArgumentException) {
    $failed = true;
}
assert($failed);

$failed = false;
try {
    $compiler->bindContainer(99, 'queue', 'put', 8, 16);
} catch (InvalidArgumentException) {
    $failed = true;
}
assert($failed);

// Ring capacity remains power-of-two. Map/Set are ordinary ordered arrays and
// must never acquire a hash/ring mask.
assert($qPush->capacity === 1024 && $qPush->mask === 1023);
assert($mPut->capacity === 2048 && $mPut->mask === 0);
assert($sAdd->capacity === 2048 && $sAdd->mask === 0);
assert($mOdd->capacity === 1500 && $mOdd->mask === 0);
assert($sOdd->capacity === 1501 && $sOdd->mask === 0);

$failed = false;
try {
    $compiler->bindContainer(18, 'map', 'put', 8, 1500, 1499);
} catch (InvalidArgumentException) {
    $failed = true;
}
assert($failed);

$binary = $compiler->containerBindingBinary();
assert(str_starts_with($binary, 'JXCBIND1'));
assert(strlen($binary) > 12);

$json = $compiler->containerBindingJson();
assert(str_contains($json, 'jx.jxl-container-bindings/1'));
assert(str_contains($json, 'jx_queue_push_u64'));
assert(str_contains($json, 'jx_map_vector_put_u64'));
assert(str_contains($json, 'jx_set_add_u64'));
assert(str_contains($json, 'jx_sorted_reserve_u64'));
assert(!str_contains($json, 'jx_hash_reserve_u64'));
assert(!str_contains(strtolower($json), 'enqueue'));
assert(!str_contains(strtolower($json), 'append'));

// Assembly source is the actual native container implementation. The active
// Map backend must be the keyed Vector, while the split implementation remains
// linked only as an explicit comparison backend for the later A/B benchmark.
$coreAsm = file_get_contents(__DIR__ . '/native/x86_64/jxl_containers.asm');
$mapAsm = file_get_contents(__DIR__ . '/native/x86_64/jxl_map_vector.asm');
$tableAsm = file_get_contents(__DIR__ . '/native/x86_64/jxl_container_native_table.asm');
$execAsm = file_get_contents(__DIR__ . '/native/x86_64/jxl_container_executor.asm');
assert(is_string($coreAsm) && is_string($mapAsm) && is_string($tableAsm) && is_string($execAsm));
foreach ([
    'global jx_vector_push_u64',
    'global jx_queue_push_u64',
    'global jx_deque_push_front_u64',
    'global jx_sorted_find_u64',
    'global jx_sorted_reserve_u64',
    'global jx_set_add_u64',
    'global jx_bag_sync',
] as $symbol) assert(str_contains($coreAsm, $symbol));
foreach ([
    'global jx_map_vector_find_u64',
    'global jx_map_vector_emplace_u64',
    'global jx_map_vector_get_u64',
    'global jx_map_vector_put_u64',
    'global jx_map_vector_has_u64',
    'global jx_map_vector_remove_u64',
] as $symbol) assert(str_contains($mapAsm, $symbol));
assert(str_contains($tableAsm, 'dq jx_map_vector_put_u64'));
assert(str_contains($coreAsm, 'global jx_map_put_u64')); // retained split comparison backend
assert(!str_contains($coreAsm, 'global jx_map_probe_u64'));
assert(str_contains($execAsm, 'global jx_jxl_container_execute'));
assert(str_contains($execAsm, 'call qword [rbx + B_FN]'));
assert(!str_contains(strtolower($coreAsm . $mapAsm . $execAsm), 'php_'));

printf(
    "JXL native containers: ok (%d prepared bindings, %d-byte instructions, keyed-vector Map + ordered Set)\n",
    count($compiler->containerBindings()->all()),
    JxlContainerInstruction::BYTES,
);
