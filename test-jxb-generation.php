<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxb-generation.php';

use jx\semantic\JxbBook;
use jx\semantic\JxbGeneration;
use jx\semantic\SemanticException;

$current = JxbBook::compile(<<<'JX'
namespace demo;
function scale(int $x): int { return $x * 2; }
int $value = scale(3);
$value;
JX, 'generation-current');

$additive = JxbBook::compile(<<<'JX'
namespace demo;
function scale(int $x): int { return $x * 2; }
function plusOne(int $x): int { return $x + 1; }
int $value = plusOne(scale(3));
$value;
JX, 'generation-additive');

$ok = JxbGeneration::compare($current['bytes'], $additive['bytes']);
assert($ok['compatible'] === true);
assert($ok['reasons'] === []);
JxbGeneration::assertCompatible($current['bytes'], $additive['bytes']);

$breakingSignature = JxbBook::compile(<<<'JX'
namespace demo;
function scale(int $x, int $factor): int { return $x * $factor; }
int $value = scale(3, 2);
$value;
JX, 'generation-breaking-signature');

$bad = JxbGeneration::compare($current['bytes'], $breakingSignature['bytes']);
assert($bad['compatible'] === false);
assert(in_array('function parameter signature changed: scale', $bad['reasons'], true));

$breakingNamespace = JxbBook::compile(<<<'JX'
namespace other;
function scale(int $x): int { return $x * 2; }
int $value = scale(3);
$value;
JX, 'generation-breaking-namespace');

$badNamespace = JxbGeneration::compare($current['bytes'], $breakingNamespace['bytes']);
assert($badNamespace['compatible'] === false);
assert(in_array('canonical namespace changed', $badNamespace['reasons'], true));

$thrown = false;
try { JxbGeneration::assertCompatible($current['bytes'], $breakingSignature['bytes']); }
catch (SemanticException $e) { $thrown = $e->phase === 'jxb-generation'; }
assert($thrown);

echo "PASS JXB live-generation executable compatibility gate\n";
