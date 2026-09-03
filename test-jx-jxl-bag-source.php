<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxl-compiler.php';

use jx\semantic\JxlContainerInstruction;
use jx\semantic\PreparedCompiler;
use jx\semantic\Type;

$source = <<<'JX'
bag Jobs {
    type: queue
    of: Task
    capacity: 1024
    handle: 10
}

bag Seen {
    type: set
    of: int
    capacity: 128
    handle: 11
}

bag State {
    type: record
    health: int
    phi: int
    handle: 12
}

int task = 42;
Jobs.enqueue(task);
int next = Jobs.dequeue();
int added = Seen.add(task);
State.health = next;
int hp = State.health;
Jobs.checkpoint();
JX;

$compiler = new PreparedCompiler();
$program = $compiler->parse($source);

// The canonical Bag blocks are now accepted through the prepared semantic
// compiler and become ordinary typed Bag declarations in semantic IR.
assert(count($program->statements) === 9);
assert($program->statements[0]->op === 'decl' && $program->statements[0]->type === Type::BAG);
assert($program->statements[1]->op === 'decl' && $program->statements[1]->type === Type::BAG);
assert($program->statements[2]->op === 'decl' && $program->statements[2]->type === Type::BAG);

$compiled = $compiler->compileContainerSource($source);
assert(strlen($compiled->jxl) === 6 * JxlContainerInstruction::BYTES);
assert(strlen($compiled->registerBinary()) === 64);

$bags = $compiled->bags;
assert($bags['jobs']->discipline === 'queue');
assert($bags['jobs']->capacity === 1024 && $bags['jobs']->handle === 10);
assert($bags['seen']->discipline === 'set' && $bags['seen']->capacity === 128);
assert($bags['state']->discipline === 'record');
assert($bags['state']->capacity === 2);
assert($bags['state']->fields['health']['slot'] === 0);
assert($bags['state']->fields['phi']['slot'] === 1);

$decoded = [];
for ($offset = 0; $offset < strlen($compiled->jxl); $offset += JxlContainerInstruction::BYTES) {
    $decoded[] = JxlContainerInstruction::decode($compiled->jxl, $offset);
}
assert(array_column($decoded, 'operation') === ['PUSH','POP','EMPLACE','PUT','GET','SYNC']);

// Queue alias vocabulary has disappeared before executable JXL.
$bindings = $compiler->containerBindings()->all();
assert($bindings[0]->nativeSymbol === 'jx_queue_push_u64');
assert($bindings[1]->nativeSymbol === 'jx_queue_pop_u64');
assert($bindings[2]->nativeSymbol === 'jx_set_add_u64');
assert($bindings[3]->nativeSymbol === 'jx_record_put_u64');
assert($bindings[4]->nativeSymbol === 'jx_record_get_u64');
assert($bindings[5]->nativeSymbol === 'jx_bag_sync');

// Set ADD is source-arity one while retaining the global two-source EMPLACE
// opcode shape. The second selector is deliberately duplicated; set ASM ignores
// it and installs its own sentinel value.
assert($decoded[2]['src0'] === $compiled->registers['task']);
assert($decoded[2]['src1'] === $compiled->registers['task']);
assert($decoded[2]['dst'] === $compiled->registers['added']);

// Named record fields resolve once to a numeric slot constant. Both PUT and GET
// reuse the same prepared selector for State.health -> slot 0.
$healthSelector = $compiled->constants['0'];
assert($decoded[3]['src0'] === $healthSelector);
assert($decoded[4]['src0'] === $healthSelector);
assert($decoded[3]['src1'] === $compiled->registers['next']);
assert($decoded[4]['dst'] === $compiled->registers['hp']);

// Register initialization is an admission/startup artifact, not executable
// container traffic. task begins at 42 before the first native instruction.
$taskSelector = $compiled->registers['task'];
$regs = $compiled->registerBinary();
$off = $taskSelector * 8;
$lo = unpack('V', substr($regs, $off, 4))[1];
$hi = unpack('V', substr($regs, $off + 4, 4))[1];
assert($lo === 42 && $hi === 0);

$json = $compiled->json();
assert(str_contains($json, 'jx.jxl-container-source/1'));
assert(str_contains($json, 'jx_queue_push_u64'));
assert(str_contains($json, 'jx_set_add_u64'));
assert(str_contains($json, '"health"'));
assert(!str_contains(strtolower($json), 'enqueue'));
assert(!str_contains(strtolower($json), 'dequeue'));

// emitJxl() automatically recognizes the canonical Bag source family now.
$auto = new PreparedCompiler();
assert($auto->emitJxl($source) === $compiled->jxl);
assert($auto->lastContainerCompilation() !== null);

printf(
    "JXL canonical Bag source: ok (%d instructions, %d bindings, %d registers)\n",
    count($decoded),
    count($bindings),
    count($compiled->registers) + count($compiled->constants),
);
