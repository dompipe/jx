<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxl-compiler.php';

use jx\semantic\JxlPreparedOpcode;
use jx\semantic\JxlPreparedInstruction;
use jx\semantic\PreparedCompiler;

$source = file_get_contents(__DIR__ . '/tests/fixtures/jxl-prepared-loop.jx');
assert($source !== false);

$compiler = new PreparedCompiler();
$compiled = $compiler->compilePreparedProgram($source);
assert(strlen($compiled->jxl) > 0);
assert(strlen($compiled->jxl) % JxlPreparedInstruction::BYTES === 0);
assert(strlen($compiled->registerBinary()) === 64);
assert(str_starts_with($compiler->containerBindingBinary(), 'JXCBIND1'));

$core = 0;
$container = 0;
$halt = 0;
for ($offset = 0; $offset < strlen($compiled->jxl); $offset += 6) {
    $opcode = ord($compiled->jxl[$offset]);
    if ($opcode >= 0x20 && $opcode <= 0x37) {
        $core++;
        $decoded = JxlPreparedInstruction::decode($compiled->jxl, $offset);
        if ($decoded['opcode'] === JxlPreparedOpcode::HALT) $halt++;
        if ($decoded['target'] !== null) {
            assert($decoded['target'] % 6 === 0);
            assert($decoded['target'] >= 0 && $decoded['target'] <= strlen($compiled->jxl));
        }
    } elseif ($opcode >= 0x40 && $opcode <= 0x50) {
        $container++;
    } else {
        throw new RuntimeException(sprintf('Unexpected mixed JXL opcode 0x%02x at %d', $opcode, $offset));
    }
}

assert($core > 0);
assert($container > 0);
assert($halt === 1);
assert(ord($compiled->jxl[strlen($compiled->jxl) - 6]) === JxlPreparedOpcode::HALT);

fwrite(STDOUT, sprintf(
    "prepared global JXL: ok (%d bytes, %d core instructions, %d container instructions, %d bindings)\n",
    strlen($compiled->jxl),
    $core,
    $container,
    count($compiler->containerBindings()->all())
));
