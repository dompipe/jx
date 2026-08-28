<?php declare(strict_types=1);

/**
 * JX language documentation contract.
 *
 * This intentionally gates only architecture/language claims that future
 * compiler and AI-authored documentation must not silently blur together.
 */

$root = __DIR__;
$guidePath = $root . '/docs/JX-PROGRAMMING-TUTELAGE.md';
$jxlPath = $root . '/docs/JXL-PREPARED-EXECUTION.md';
$abiPath = $root . '/docs/HOT-CALL-ABI-V4.md';

foreach ([$guidePath, $jxlPath, $abiPath] as $path) {
    if (!is_file($path) || filesize($path) < 1000) {
        throw new RuntimeException('Required JX language document missing or unexpectedly small: ' . basename($path));
    }
}

$guide = file_get_contents($guidePath);
$jxl = file_get_contents($jxlPath);
$abi = file_get_contents($abiPath);
if ($guide === false || $jxl === false || $abi === false) {
    throw new RuntimeException('Cannot read JX language documents');
}

$mustGuide = [
    'ACTIVE',
    'PHP-BACKED',
    'PLANNED',
    'while',
    'for',
    'foreach',
    'do ... while',
    'repeat',
    'select',
    'switch',
    'break',
    'continue',
    'Bag.underwrite',
    'Book.open',
    'Task.underwrite',
    'delivery(',
    'complex',
    'Bags remember. Registers react. Prepared code executes.',
    'Resolve cold -> bind once -> execute hot.',
    'JX is the clear language people and AI write.',
];
foreach ($mustGuide as $needle) {
    if (!str_contains($guide, $needle)) {
        throw new RuntimeException("Programming tutelage lost required concept: {$needle}");
    }
}

$mustJxl = [
    '0xxxxxxx = executable JXL opcode',
    '1xxxxxxx = attached extension/data byte; never an opcode',
    '0 = JX ABI mode',
    '1 = JXL mode',
    'JX is the language people read; JXL is the execution the compiler remembers.',
];
foreach ($mustJxl as $needle) {
    if (!str_contains($jxl, $needle)) {
        throw new RuntimeException("JXL contract lost required invariant: {$needle}");
    }
}

$mustAbi = [
    '1xxxxxxx            = one-byte hot call',
    '0xxxxxxx xxxxxxxx   = two-byte extended call',
    '8 shadows',
];
foreach ($mustAbi as $needle) {
    if (!str_contains($abi, $needle)) {
        throw new RuntimeException("Hot ABI v4 contract lost required invariant: {$needle}");
    }
}

if (!str_contains($guide, 'recognized but intentionally not active') &&
    !str_contains($guide, 'Recognized but intentionally not active')) {
    throw new RuntimeException('Guide must keep foreach status explicit until collection lowering is linked');
}

if (str_contains($jxl, '1xxxxxxx = executable JXL opcode')) {
    throw new RuntimeException('JXL executable/data bit law was accidentally reversed');
}

if (!str_contains($guide, 'F0-FF') || !str_contains($guide, 'protected/unassigned')) {
    throw new RuntimeException('Protected F0-FF invariant missing from programming guide');
}

fwrite(STDOUT, "JX LANGUAGE DOC CONTRACT: PASS\n");
