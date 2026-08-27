<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-lang.php';

use pasm\lang\Compiler;

function assertHas(string $haystack, string $needle, string $label): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "FAIL {$label}: missing {$needle}\n");
        exit(1);
    }
}

function assertBefore(string $text, string $a, string $b, string $label): void
{
    $pa = strpos($text, $a); $pb = strpos($text, $b);
    if ($pa === false || $pb === false || $pa >= $pb) {
        fwrite(STDERR, "FAIL {$label}: expected {$a} before {$b}\n");
        exit(1);
    }
}

$c = new Compiler(true, false, 3);
$asm = $c->compile(<<<'PASL'
$i = 0;
$sum = 0;
for ($i = 0; $i != 4; $i++) {
    $sum += $i;
}
while ($i != 0) {
    $i--;
}
PASL);

assertHas($asm, 'LCHECK + LCALL(body)', 'controller annotation');
assertHas($asm, '__jx_loop_body_0:', 'for body emitted as block');
assertHas($asm, '__jx_loop_step_0:', 'for step emitted as block');
assertHas($asm, '__jx_loop_body_1:', 'while body emitted as block');
assertHas($asm, 'VAROP VINC i', 'for step canonical variable op');
assertHas($asm, 'VAROP VDEC i', 'while body canonical variable op');
assertBefore($asm, '__jx_program_end', '__jx_loop_body_0:', 'main jumps over deferred blocks');

$stats = $c->loopStats();
if (($stats['compiled_blocks'] ?? 0) !== 2) {
    fwrite(STDERR, 'FAIL compiled loop count: '.var_export($stats, true)."\n");
    exit(1);
}

$nested = (new Compiler(true, false, 2))->compile(<<<'PASL'
$i = 0;
$j = 0;
while ($i != 2) {
    while ($j != 2) {
        $j++;
    }
    $i++;
}
PASL);
assertHas($nested, 'slot 0 depth 0: while', 'outer slot');
assertHas($nested, 'slot 1 depth 1: while', 'inner slot');

$threw = false;
try {
    (new Compiler(true, false, 1))->compile(<<<'PASL'
$i = 0;
$j = 0;
while ($i != 2) {
    while ($j != 2) {
        $j++;
    }
    $i++;
}
PASL);
} catch (Throwable $e) {
    $threw = str_contains($e->getMessage(), 'Loop nesting exceeds limit');
}
if (!$threw) {
    fwrite(STDERR, "FAIL compiler nesting cap\n");
    exit(1);
}

echo "PASS PASL compiled loop-space emission\n";
