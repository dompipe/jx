<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-loop-space.php';

use pasm\lang\PASMVarOp;
use pasm\lang\PASMLoopKind;
use pasm\lang\PASMLoopSpace;

$eq = static function(mixed $a, mixed $b, string $label): void {
    if ($a !== $b) {
        fwrite(STDERR, "FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");
        exit(1);
    }
};

$eq(PASMVarOp::parse('$i++')['op'], PASMVarOp::VINC, 'post increment');
$eq(PASMVarOp::parse('--$i')['op'], PASMVarOp::VDEC, 'pre decrement');
$eq(PASMVarOp::parse('$x += 8')['op'], PASMVarOp::VADD, 'add assign');
$eq(PASMVarOp::parse('$x = $x * 2 + 1')['op'], PASMVarOp::VALG, 'recursive algebra');
$eq(PASMVarOp::lowering(PASMVarOp::parse('$i++'))['asm'], ['inc [i]'], 'inc one instruction intent');
$eq(PASMVarOp::lowering(PASMVarOp::parse('$i -= 2'))['asm'], ['sub [i], 2'], 'sub one instruction intent');

$space = new PASMLoopSpace(3);
$outer = $space->enter(PASMLoopKind::FOR, '$i < 10', '$sum += $i;', '$i = 0', '$i++');
$eq($outer->slot, 0, 'outer slot');
$eq($outer->depth, 0, 'outer depth');
$eq($outer->controller()[0]['op'], 'LCHECK', 'controller check');
$eq($outer->controller()[1]['op'], 'LCALL', 'controller body call');
$eq($outer->controller()[2]['op'], 'LCALL', 'controller step call');

$inner = $space->enter(PASMLoopKind::WHILE, '$j != 0', '$j--;');
$eq($inner->slot, 1, 'inner slot');
$eq($inner->controller()[0]['op'], 'LCHECK', 'while check');
$eq($inner->controller()[1]['op'], 'LCALL', 'while call');

$deep = $space->enter(PASMLoopKind::REPEAT, '$k < 2', '$k++;');
$eq($deep->slot, 2, 'depth-3 slot');

$threw = false;
try {
    $space->enter(PASMLoopKind::WHILE, '1', '');
} catch (InvalidArgumentException) {
    $threw = true;
}
$eq($threw, true, 'nesting cap');

$space->leave();
$space->leave();
$space->leave();

// Sequential loops reuse bounded loop-space slots after scope exit.
$again = $space->enter(PASMLoopKind::FOR, '$n < 4', '$n++;', '$n = 0', '$n++');
$eq($again->slot, 0, 'slot reuse after leave');

fwrite(STDOUT, "PASS PASM loop-space variable-ops maxDepth=".$space->maxDepth()." compiled=".count($space->compiled())."\n");
