<?php declare(strict_types=1);

require_once __DIR__ . '/jx-semantic.php';

use jx\semantic\Compiler;
use jx\semantic\JxlVm;
use jx\semantic\Parser;
use jx\semantic\Type;

$c = new Compiler();

$language = <<<'JX'
namespace demo.core;
import system.math as math;

function add(int $a, int $b): int {
    return $a + $b;
}

class Counter {
    int $value = 0;
    function constructor(int $start): void {
        $this.value = $start;
    }
    function bump(int $n): int {
        $this.value += $n;
        return $this.value;
    }
}

int $sum = 0;
list $xs = [1, 2, 3, 4];
foreach ($xs as $i => $v) {
    if ($i == 1) continue;
    $sum += $v;
}

do {
    $sum++;
} while ($sum < 9);

repeat (2) {
    $sum += 2;
}

$counter = new Counter(10);
$sum += $counter.bump(3);

try {
    throw 5;
} catch (any $e) {
    $sum += $e;
} finally {
    $sum += 1;
}

$sum;
JX;

$p = (new Parser())->parse($language);
assert($p->namespace === 'demo.core');
assert(count($p->imports) === 1);
assert(isset($p->functions['add']));
assert(isset($p->classes['counter']));
assert($p->functions['add']->data['return'] === Type::INT);
assert($c->run($language) === 32);

$numeric = <<<'JX'
function add(int $a, int $b): int {
    return $a + $b;
}

int $i = 0;
int $sum = 0;
while ($i < 10) {
    if ($i == 3) {
        $i++;
        continue;
    }
    $sum += $i;
    if ($sum > 30) break;
    $i++;
}
$sum = add($sum, 7);
do {
    $sum--;
} while ($sum > 40);
repeat (2) {
    $sum += 2;
}
$sum;
JX;

$semantic = $c->run($numeric);
$jxl = $c->emitJxl($numeric);
$prepared = (new JxlVm())->run($jxl);
assert($semantic === 44);
assert($prepared === 44);
assert(strlen($jxl) > 0);

// JXL executable bytes have the high bit clear; operand/address bytes are
// consumed only as attachments by the decoder. The VM itself rejects an
// attachment at an opcode boundary, so prepend one and require failure.
$failed = false;
try {
    (new JxlVm())->run(chr(0x80) . $jxl);
} catch (\jx\semantic\SemanticException $e) {
    $failed = str_contains($e->getMessage(), 'attachment encountered as opcode');
}
assert($failed);

echo "jx semantic + JXL parity: ok\n";
