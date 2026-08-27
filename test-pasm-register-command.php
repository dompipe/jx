<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-bytecode.php';
require_once __DIR__ . '/pasm-register-command.php';

use pasm\PASMBC;
use pasm\PASMRegisterCommand;
use pasm\PASMRegisterCommandVM;

$eq = static function(mixed $a, mixed $b, string $label): void {
    if ($a !== $b) {
        fwrite(STDERR, "FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");
        exit(1);
    }
};

// MOVR dst,src: opcode + 6 register bits = exactly 2 bytes.
$movr = PASMRegisterCommand::encode(PASMBC::MOVR, ['adx','ecx']);
$eq(strlen($movr), 2, 'MOVR compact size');
$eq(PASMRegisterCommand::bindings($movr), ['dst'=>'adx','src'=>'ecx'], 'MOVR order');

// ADD dst,a,b: opcode + 9 register bits = exactly 3 bytes.
$add = PASMRegisterCommand::encode(PASMBC::ADD, ['bdx','ecx','adx']);
$eq(strlen($add), 3, 'ADD compact size');
$eq(PASMRegisterCommand::bindings($add), ['dst'=>'bdx','a'=>'ecx','b'=>'adx'], 'ADD order');

$inc = PASMRegisterCommand::encode(PASMBC::INC, ['ecx']);
$eq(strlen($inc), 2, 'INC compact size');
$eq(PASMRegisterCommand::bindings($inc), ['dst'=>'ecx'], 'INC order');

// Execute bytecode directly over the ordered register tuples.
$stream = '';
$stream .= PASMRegisterCommand::encode(PASMBC::ADD, ['bdx','ecx','adx']);   // bdx = 10 + 7 = 17
$stream .= PASMRegisterCommand::encode(PASMBC::INC, ['bdx']);              // bdx = 18
$stream .= PASMRegisterCommand::encode(PASMBC::MOVR, ['cdx','bdx']);       // cdx = 18
$stream .= PASMRegisterCommand::encode(PASMBC::RET, ['cdx']);

$vm = new PASMRegisterCommandVM([
    PASMBC::regId('ecx') => 10,
    PASMBC::regId('adx') => 7,
]);
$eq($vm->run($stream), 18, 'ordered register command execution');
$eq($vm->get('bdx'), 18, 'bdx result');
$eq($vm->get('cdx'), 18, 'movr result');

// Encoding is deterministic and positional: changing order changes meaning.
$sub = PASMRegisterCommand::encode(PASMBC::SUB, ['edx','ecx','adx']);
$vm2 = new PASMRegisterCommandVM([
    PASMBC::regId('ecx') => 10,
    PASMBC::regId('adx') => 7,
]);
$eq($vm2->run($sub), null, 'sub stream no return');
$eq($vm2->get('edx'), 3, 'SUB dst,a,b positional semantics');

fwrite(STDOUT, "PASS PASM ordered-register commands movr=".strlen($movr)."B add=".strlen($add)."B inc=".strlen($inc)."B\n");
