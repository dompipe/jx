<?php declare(strict_types=1);

require_once __DIR__ . '/jx-semantic.php';

use jx\semantic\Compiler;
use jx\semantic\JxlVm;
use jx\semantic\Parser;
use jx\semantic\Type;

$c = new Compiler();
$stage = $argv[1] ?? 'all';

$language = <<<'JX'
namespace demo.core;
import system.math as math;
function add(int $a, int $b): int { return $a + $b; }
class Counter {
    int $value = 0;
    function constructor(int $start): void { $this.value = $start; }
    function bump(int $n): int { $this.value += $n; return $this.value; }
}
int $sum = 0;
list $xs = [1, 2, 3, 4];
foreach ($xs as $i => $v) {
    if ($i == 1) continue;
    $sum += $v;
}
do { $sum++; } while ($sum < 9);
repeat (2) { $sum += 2; }
$counter = new Counter(10);
$sum += $counter.bump(3);
try { throw 5; } catch (any $e) { $sum += $e; } finally { $sum += 1; }
$sum;
JX;

$numeric = <<<'JX'
function add(int $a, int $b): int { return $a + $b; }
int $i = 0;
int $sum = 0;
while ($i < 10) {
    if ($i == 3) { $i++; continue; }
    $sum += $i;
    if ($sum > 30) break;
    $i++;
}
$sum = add($sum, 7);
do { $sum--; } while ($sum > 40);
repeat (2) { $sum += 2; }
$sum;
JX;

$run = static function(string $name, callable $fn) use ($stage): void {
    if ($stage !== 'all' && $stage !== $name) return;
    $fn();
    echo "PASS semantic stage {$name}\n";
};

$jxlCase = static function(string $source, int $expected) use ($c): void {
    $semantic = $c->run($source);
    assert($semantic === $expected);
    $bytes = $c->emitJxl($source);
    assert(strlen($bytes) > 0);
    $prepared = (new JxlVm())->run($bytes);
    assert($prepared === $expected);
};

$run('parse', static function() use ($language): void {
    $p = (new Parser())->parse($language);
    assert($p->namespace === 'demo.core');
    assert(count($p->imports) === 1);
    assert(isset($p->functions['add']));
    assert(isset($p->classes['counter']));
    assert($p->functions['add']->data['return'] === Type::INT);
});
$run('language', static fn() => assert($c->run($language) === 32));
$run('numeric', static fn() => assert($c->run($numeric) === 43));

$run('jxl-basic', static fn() => $jxlCase('int $x = 2; $x += 5; $x;', 7));
$run('jxl-call', static fn() => $jxlCase('function add(int $a, int $b): int { return $a + $b; } int $x = add(4, 6); $x;', 10));
$run('jxl-while', static fn() => $jxlCase('int $i = 0; int $x = 0; while ($i < 5) { $x += $i; $i++; } $x;', 10));
$run('jxl-continue', static fn() => $jxlCase('int $i=0; int $x=0; while ($i < 5) { $i++; if ($i == 3) continue; $x += $i; } $x;', 12));
$run('jxl-break', static fn() => $jxlCase('int $i=0; int $x=0; while ($i < 10) { $x += $i; if ($x > 5) break; $i++; } $x;', 6));
$run('jxl-do', static fn() => $jxlCase('int $x = 4; do { $x--; } while ($x > 2); $x;', 2));
$run('jxl-repeat', static fn() => $jxlCase('int $x = 1; repeat (3) { $x += 2; } $x;', 7));
$run('jxl', static fn() => $jxlCase($numeric, 43));

$run('attachment', static function() use ($c, $numeric): void {
    $jxl = $c->emitJxl($numeric);
    $failed = false;
    try { (new JxlVm())->run(chr(0x80) . $jxl); }
    catch (\jx\semantic\SemanticException $e) { $failed = str_contains($e->getMessage(), 'attachment encountered as opcode'); }
    assert($failed);
});

$known = ['all','parse','language','numeric','jxl-basic','jxl-call','jxl-while','jxl-continue','jxl-break','jxl-do','jxl-repeat','jxl','attachment'];
if (!in_array($stage, $known, true)) { fwrite(STDERR, "Unknown semantic test stage {$stage}\n"); exit(2); }
echo "jx semantic + JXL parity: ok ({$stage})\n";
