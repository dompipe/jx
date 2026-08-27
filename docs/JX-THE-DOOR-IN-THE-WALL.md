# JX (Jinx): The Door in the Wall

## A language for intuitive programmers, portable programs, and environments that stop pretending they are separate worlds

**JX** is pronounced **jinx**.

It is a small name for a large idea: a programmer should be able to say what a program means once, carry that meaning through different environments, and let the machinery beneath the language decide how to execute it efficiently.

The language is designed around an observation that becomes more obvious the longer one writes software: most of the complexity in a modern program does not come from the idea being expressed. It comes from rebuilding the same idea several times because the browser, server, database, operating system, compiler, event system, memory model, and deployment environment each insist on being treated as a separate country.

JX starts by refusing that premise.

The door is already in the wall.

The programmer should be able to walk through it.

---

# Chapter 1 — The Name, the Language, and the Acumen of Prescience

JX is not named for a syntax trick. It is named as a complete programming surface. PASM is the execution engine beneath it, but the programmer writes and thinks in JX.

The easiest way to understand JX is to begin with a sentence:

> **JX says what. PASL says it lower. PASM says it small. The host makes it happen.**

That sentence is more than branding. It is a division of responsibility.

JX is the place where the programmer names an intention. PASL is the structured lowering language that preserves that intention while removing unnecessary surface variation. PASM is the compact execution representation. The host is the browser, native process, server runtime, or other environment that finally gives the operation physical consequence.

This separation lets the language be easy without forcing the engine to be vague.

A traditional application often repeats the same intention several times. A value is named in JavaScript, translated to JSON, renamed in an HTTP route, validated again in a server framework, translated to SQL, renamed in a result object, serialized again, and finally reconstructed in the browser. Every boundary creates another opportunity for drift.

JX treats those boundaries as stages of one program.

The design discipline behind that is what this book calls **prescience**.

Prescience here is not prediction in a mystical sense. It is the engineering practice of carrying forward enough information about identity, environment, status, stage, ownership, and intended operation that later stages do not have to rediscover what earlier stages already knew.

If the compiler already knows that a value is a Bag field, it should not force the runtime to search a string table every time the field is touched. If the linker already knows which method an alias means, the runtime should not resolve that alias again. If a loop body is fixed, the body should be compiled once instead of interpreted anew every iteration. If a program knows that it is crossing from a browser host into a server host, the crossing should preserve the Book and its identities instead of rebuilding the application as a pile of unrelated requests.

That is prescience as a language method:

```text
human intention
      ↓
known identity
      ↓
known environment
      ↓
known stage
      ↓
canonical meaning
      ↓
chosen execution form
```

The acumen of JX is to keep asking one question earlier than most runtimes ask it:

**What do we already know?**

Knowing earlier creates simpler code later.

Consider a human-facing command:

```jx
bag.insert(3, value)
```

A programmer reads that immediately. But the runtime does not need the word `insert`. The compiler can resolve it before execution:

```text
insert
   ↓ alias resolution
BEMPLACE
   ↓ Bag discipline
vector emplace
   ↓ native lowering
address + bulk move + store
```

The surface stays friendly. The executable stays small.

This is one of the central laws of JX:

> **Rhetoric belongs at the human edge. Positional precision belongs at the machine edge.**

A language can therefore be expressive without requiring a verbose runtime.

The second law follows naturally:

> **Canonical JX is permanent meaning. Execution shadows are disposable acceleration.**

The programmer should never have to sacrifice the readable program in order to obtain the efficient program. JX keeps canonical meaning and allows lower stages to build specialized shadows of it.

A compiled offset can replace a field-name lookup. A direct branch can replace a dynamic dispatch. A fused native instruction can replace several canonical operations. None of those optimizations becomes the source of truth.

That makes modification safer.

Suppose a program contains:

```jx
player.health = player.health - damage;
```

The canonical meaning is a mutation of `player.health` using `damage`.

One target might preserve a named access. Another might know that `player.health` is slot 3 in Bag space 5 and reduce the access to two address bytes:

```text
05 03
```

A native target might go farther and place the Bag base in a register, turning the field access into a fixed offset from that register.

The programmer still owns this:

```jx
player.health = player.health - damage;
```

The machine may execute something closer to this:

```asm
sub qword [r14 + 24], r9
```

The readable meaning remains intact while the disposable shadow becomes extremely specific.

That is the first reason JX can aim to be portable, usable, and modifiable at the same time.

Portability is not achieved by making every target equally generic. Portability is achieved by preserving one meaning while allowing each target to become appropriately specific.

### A first runnable JX example

The repository already contains a direct JX runner. A minimal program can be executed with:

```bash
jx --print examples/hello.jx
```

The language runtime exposes the first group of JX objects through a small set of concepts: Book, Bag, Task, Page, Delivery, Complex, and related facilities.

A Bag can be created and used directly:

```php
require_once __DIR__ . '/jx.php';

use jx\Jx;

$bag = Jx::bag(256);
$ref = $bag->sign('message');
$bag->set('hello', 'message')->commit($ref);

echo $bag->get($ref, 'message');
```

The example is intentionally explicit. A Bag is not anonymous unbounded memory. It is underwritten with capacity. A writable node is signed. A write is prepared. A commit crosses the mutation boundary.

That sequence gives JX something valuable: a canonical memory law that can survive changes in physical representation.

A faster compiled target does not need to enact every PHP method call literally. It only needs to preserve the same contract.

This is a recurring pattern throughout JX. The language defines a clear, inspectable operation. The compiler is then allowed to remove ceremony that has become provably unnecessary.

### Why this can feel easier instead of more restrictive

A programmer should not need to study the entire engine before writing ordinary JX. The engine exists so ordinary JX can remain ordinary.

The language therefore works from two directions at once.

From the human side, it accepts useful names, aliases, familiar container operations, normal loops, methods, controls, styles, SQL concepts, and host operations.

From the machine side, those many names converge toward a small set of canonical operations and compact identities.

The many-to-one relationship is deliberate:

```text
append ─┐
push   ─┼──> BPUSH
add    ─┤
enqueue─┘
```

The programmer gets vocabulary. The machine gets singular meaning.

This is JX's first form of easy.

It is not easy because nothing is happening.

It is easy because the language is doing the bookkeeping once.

## Things Addressed — Chapter 1

- JX is the public language name; PASM is its execution engine.
- Prescience is defined as carrying known identity, environment, status, and staging information forward instead of rediscovering it at runtime.
- Canonical meaning and optimized execution are deliberately separated.
- Human aliases may be broad while canonical operations remain singular.
- The Bag handshake demonstrates a portable semantic contract that a compiler may later lower more aggressively.
- The chapter's runnable checkpoint is `jx --print examples/hello.jx` plus the Bag sign/set/commit/get sequence.

---

# Chapter 2 — One Program, Many Stages

Most programming systems present compilation as a one-way disappearance. Source code enters a compiler, machine-oriented output emerges, and the useful relationship between the two becomes progressively harder to inspect.

JX treats lowering as a chain of accountable transformations.

The useful model is:

```text
JX source
   ↓
canonical aliases and identities
   ↓
PASL semantic lowering
   ↓
PASM compact operations
   ↓
bytecode or native shadow
   ↓
host execution
```

Every downward step should answer two questions.

First: what meaning is being preserved?

Second: what information is no longer necessary at the next stage?

That second question is where much of the speed comes from.

Take an increment:

```jx
count++;
```

At the human surface, `count++` is convenient syntax. At the canonical mutation layer, JX can record:

```text
VINC count
```

At PASM, if `count` is already in a register, the operation can become:

```asm
INC ecx
```

At x86-64, if `ecx` has been assigned to a native register, it can become:

```asm
inc r8
```

Nothing about the meaning became less clear merely because the execution became smaller.

The same principle works for a compound mutation:

```jx
total += price;
```

Canonical form:

```text
VADD total, price
```

Possible PASM:

```asm
ADD ecx ecx ah
```

Possible native form:

```asm
add r8, r9
```

The compiler is not searching for cleverness after the fact. The source has already declared a mutation. The lower stages only need to preserve it.

### Idempotency through canonicalization

The phrase **idempotency of post-chapter testing** matters to the language design as much as it matters to this book.

A transformation is easier to trust when applying the canonicalization process again does not keep changing the meaning.

Suppose a programmer writes:

```jx
bag.append(value)
```

The alias layer resolves that to:

```text
BPUSH
```

Running alias resolution on `BPUSH` again should still produce `BPUSH`.

That is a useful compiler property:

```text
canonicalize(canonicalize(x)) = canonicalize(x)
```

The same idea appears in linked identities. Once a method has been assigned a canonical method ID, later execution should use that identity rather than repeatedly rebuilding it from source spelling.

JX therefore makes compilation less like translation between unrelated languages and more like progressive removal of uncertainty.

### The optimized and unoptimized paths must agree

A language becomes difficult to trust when optimization secretly changes its bytecode dialect.

The active JX/PASM branch now keeps the optimizing execution facade on the same packed base PASM bytecode ABI as ordinary execution. The optimized layer may still choose different source transformations, but it does not get to invent a second incompatible register encoding.

That gives a very simple regression principle:

```php
$source = '$sum=0;$i=0;for($i=0;$i!=5;$i++){$sum+=$i;}';

$plain = (new pasm\lang\Engine(false, false))->runSource($source);
$fast  = (new pasm\lang\Engine(true, false))->runSource($source);

assert($plain === $fast);
```

The example is valuable because it tests a contract, not an implementation detail.

The optimized path may fuse, fold, or choose a different lowering. The observable program must agree.

### Bytecode is an executable stage, not a documentation artifact

A PASL program can be compiled into a PBC file and executed again:

```bash
php pasm-run.php -o /tmp/example.pbc examples/arith.pasl
php pasm-run.php --print /tmp/example.pbc
```

That round trip matters.

It proves that the compiler output is not merely a debug dump. It is a portable executable artifact for the PASM VM.

The current CI gate performs that round trip automatically.

### Native is another stage of the same program

The x86 backend follows the same law. PASL first becomes canonical PASM. PASM register identities are then mapped to native registers.

The current x86-64 register map is deliberately explicit:

```text
ecx -> r8
ah  -> r9
adx -> r10
bdx -> r11
cdx -> r12
ddx -> r13
edx -> r14
rdx -> r15
```

A program such as:

```pasl
$sum = 0;
$counter = 5;
while ($counter) {
    $sum = $sum + $counter;
    $counter--;
}
$result = $sum;
```

can therefore move through the entire ladder:

```text
PASL source
   ↓
PASM loop and mutation operations
   ↓
GNU x86-64 assembly
   ↓
object code
   ↓
native executable
```

The test gate does not stop after checking that the `.s` file exists. It links the assembly with a tiny C caller, executes the resulting program, and expects the result `15`.

That distinction is important to JX documentation.

A feature is not called runnable merely because code for it exists.

It becomes runnable when the stage can execute and return the expected result.

## Things Addressed — Chapter 2

- JX lowering is presented as progressive removal of uncertainty rather than unrelated translation steps.
- Canonicalization is expected to be idempotent.
- Optimized and non-optimized execution share the packed PASM ABI.
- PBC compilation is tested as a real write/read/execute round trip.
- The x86 backend is treated as another lowering stage of the same semantic program.
- The chapter's post-test is semantic equality between optimized and ordinary execution, plus a native result check of `15` for the sample sum program.

---

# Chapter 3 — Bags: Memory You Can Reason About

A Bag is JX's answer to a recurring language problem: programs need memory that is both easy to name and strict enough to optimize.

The naive solution is an unbounded associative object. It is pleasant at first and expensive to reason about later.

The opposite naive solution is raw memory. It is efficient and hostile to ordinary application programming.

A Bag sits between those extremes.

A Bag has identity. It has capacity. It has a canonical state. It can issue a reference to a node. It can stage a write. It can commit a write. It can report remaining capacity.

That gives the compiler a contract with shape.

```php
$bag = jx\Bag::underwrite(4096);
$ref = $bag->sign('health');
$bag->set(100, 'health')->commit($ref);
$value = $bag->get($ref, 'health');
```

The important word is `underwrite`.

The program is not asking for vaguely infinite storage. It is declaring an allowance.

That makes capacity part of meaning instead of an accident discovered only when the environment fails.

The word `sign` matters for the same reason. A write is attached to a live relationship with a Bag node.

The word `commit` makes the mutation boundary explicit.

These concepts are intentionally stronger than an ordinary property assignment, but JX does not require every target to execute them as repeated method calls. Once the compiler proves identity and ownership, a native shadow can remove the runtime ceremony.

This produces another core JX sentence:

> **Be native while working. Become canonical at the Bag boundary.**

### Containers are not a second memory system

JX does not need one runtime for Bags and another runtime for containers.

A container is a Bag with an access discipline.

That single idea removes a great deal of boilerplate.

A record Bag is a Bag whose hot access is fixed slots. A vector Bag is a Bag whose hot access is contiguous indexed storage. A stack is the same contiguous discipline constrained to LIFO semantics. A queue uses FIFO semantics. A deque adds both ends. A map uses key lookup. A set uses unique keys.

The programmer can construct them directly:

```php
$state = jx\BagContainers::record(4096, [
    'health' => 'int',
    'phi' => ['type' => 'int', 'default' => 0],
]);

$state->put('health', 100);
```

A vector is equally direct:

```php
$players = jx\BagContainers::vector(65536, 'Player');
$players->append($alice);
$players->append($bob);
```

A queue expresses its discipline in the vocabulary the programmer expects:

```php
$jobs = jx\BagContainers::queue(65536, 'Task');
$jobs->enqueue($taskA);
$jobs->enqueue($taskB);
$next = $jobs->dequeue();
```

The canonical state can then be checkpointed:

```php
$jobs->checkpoint();
```

The hot path and canonical path are different responsibilities.

That is how JX avoids requiring an expensive canonical rewrite after every ordinary container operation.

### BEMPLACE: one insertion idea, several physical meanings

`BEMPLACE` is a particularly useful example of JX factoring.

At the human surface, a programmer may say:

```text
insert
emplace
packin
putifabsent
addifabsent
```

Those spellings converge to one canonical operation:

```text
BEMPLACE
```

Then the Bag discipline decides what emplace physically means.

For a vector, the operation is positional. If the vector contains:

```text
[1, 3]
```

then:

```php
$vector->emplace(1, 2);
```

produces:

```text
[1, 2, 3]
```

The conceptual native lowering is:

```asm
lea insert, [base+index*width]
memmove [insert+width], [insert], cursor-insert
mov [insert], value
```

The point is not that a real architecture must literally expose `memmove` as one machine instruction. The point is that the address is computed once and the tail is moved as one overlap-safe bulk operation rather than as a user-visible loop.

For a map, emplace means insert only if the key does not already exist:

```php
$map->emplace('a', 1);
$map->emplace('a', 99);
```

The second operation keeps the existing `1`.

Conceptually:

```asm
call map_probe_insert_address
jc .exists
mov [slot], key_value
```

For a set, the same canonical command means probe once and add only if absent.

This is an important JX pattern. A canonical verb can preserve a general human meaning while the discipline supplies the efficient physical interpretation.

### Two-instruction hot operations

Once a sequential Bag has `base`, `cursor`, and `end` placed appropriately, ordinary push and pop operations can become extremely small.

A vector push can conceptually become:

```asm
mov [cursor], value
add cursor, width
```

A vector pop can become:

```asm
sub cursor, width
mov value, [cursor]
```

A queue enqueue can become:

```asm
mov [tail], value
add tail, width
```

These are not promises that every PHP fallback executes in two host instructions. The PHP layer is the semantic fallback and canonical implementation. The compiler's native shadow is where the compact hot ABI matters.

That distinction protects the documentation from pretending wrapper overhead is machine performance.

JX is allowed to say both of these things at once:

The Bag model is useful in PHP today.

The Bag model is designed to disappear into fixed native operations when enough information is known.

Those statements reinforce each other.

## Things Addressed — Chapter 3

- Bags provide identity, bounded capacity, signed references, staged writes, and canonical checkpoints.
- Containers are Bag disciplines rather than a second memory system.
- Record, vector, stack, queue, deque, map, and set semantics all fit the Bag model.
- `BEMPLACE` unifies human insertion vocabulary while preserving discipline-specific lowering.
- Native hot operations are distinguished from PHP fallback performance.
- The chapter's post-test is `php test-jx-bag-containers.php` together with `php test-pasm-bag-hotops.php`.

---

# Chapter 4 — Names for People, IDs for Machines

Programming languages become harder than necessary when they force humans to think like linkers.

They become slower than necessary when they force machines to think like humans.

JX gives each side the representation it deserves.

A programmer can write:

```text
append
push
enqueue
add
```

A Bag hot-operation domain can resolve those names to:

```text
BPUSH
```

The executable does not need to remember which synonym was typed.

The compiler can retain that fact as provenance if diagnostics need it:

```text
source_spelling = enqueue
alias_domain    = bag.hot
canonical_op    = BPUSH
```

That separation is one of the simplest ways JX reduces runtime work without reducing programmer vocabulary.

### Alias domains prevent semantic collisions

The word `open` might mean something useful in several parts of a system. The solution is not to ban the word. The solution is to resolve it inside a domain.

JX defines alias domains for Bags, Bag hot operations, Books, Tasks, Pages, Delivery, functions, methods, controls, styles, events, channels, SQL, charts, hosts, windows, libraries, plugins, PASL, and PASM.

That lets a human word remain intuitive in context.

For example:

```text
Book.load     -> OPEN
Page.start    -> SPAWN or RUN according to the canonical surface
SQL.exec      -> EXECUTE
Event.trigger -> EMIT
Chart.draw    -> RENDER
PASM.JNE      -> JNZ
```

The canonicalizer resolves the rhetoric before semantic lowering.

The runtime sees the canonical operation.

### Two bytes for sorted methods

The same philosophy now exists below aliases in the method ABI.

A method can be identified by two bytes:

```text
[family][slot]
```

For example, map-family slot 3 can be represented as:

```text
12 03
```

The current test uses that address for a canonical `BEMPLACE` method:

```php
$methods = new pasm\PASMMethodABI();

$id = $methods->register(
    pasm\PASMMethodFamily::MAP,
    3,
    'BEMPLACE',
    ['EMPLACE', 'INSERT'],
    fn($key, $value) => [$key => $value]
);

assert($id === 0x1203);
assert(pasm\PASMMethodABI::bytes($id) === "\x12\x03");
```

This is where sorted identity becomes useful.

The high byte says what family of operation is being addressed. The low byte selects the method inside that family.

The source name can remain descriptive while the executable call site becomes two bytes.

### A hot method may become one byte

JX does not assume that every method deserves the shortest possible encoding.

It allows measured-hot methods to be promoted.

```php
$methods->promote($id, 0xE3);
$call = $methods->encodeCall('BEMPLACE');
```

The resulting call is one byte:

```text
E3
```

That is another example of prescience.

The system learns enough at link or profiling time to know that a call is worth surfacing into a shorter form.

The canonical method ID still exists. The one-byte form is merely a faster execution identity.

### Named memory follows the same pattern

A programmer should be able to think in names:

```text
player.health
local.damage
sql.result
control.value
```

The runtime should not need to repeatedly hash those strings.

JX therefore uses the same two-byte sorted-address idea for named memory:

```text
[space][slot]
```

A Bag field at space `0x05`, slot `0x03` becomes:

```text
05 03
```

A local value at space `0x01`, slot `0x07` becomes:

```text
01 07
```

The current implementation can bind and use these addresses directly:

```php
$memory = new pasm\PASMNamedMemory();

$health = $memory->bind(
    pasm\PASMMemorySpace::BAG,
    3,
    'player.health',
    100
);

$damage = $memory->bind(
    pasm\PASMMemorySpace::LOCAL,
    7,
    'damage',
    12
);

$memory->write(
    $health,
    $memory->read($health) - $memory->read($damage)
);
```

The human-readable link exists once. The execution address is compact.

The result is not a language that forbids names.

It is a language that knows when names have finished their job.

## Things Addressed — Chapter 4

- Human-friendly aliases resolve before runtime and preserve source spelling only as provenance.
- Domain-scoped aliases allow familiar words without global semantic collisions.
- Methods can use a sorted two-byte family/slot identity.
- Measured-hot methods can be promoted to a one-byte execution opcode.
- Named memory uses the same two-byte space/slot strategy.
- The chapter's post-test is `php test-jx-alias.php`, `php test-jx-lang-alias.php`, and `php test-pasm-address-abi.php`.

---

# Chapter 5 — Loops Should Know They Are Loops

A loop is one of the oldest ideas in programming, yet implementations often make it pay for uncertainty it does not possess.

If a compiler knows the body of a loop, that body does not need to be rediscovered every iteration.

JX's loop-space design treats the loop body as a compiled block with a bounded slot and a known continuation.

Consider:

```pasl
$sum = 0;
$counter = 5;

while ($counter) {
    $sum = $sum + $counter;
    $counter--;
}
```

The active PASL compiler does not simply paste the body inline and call that the final model. It describes a loop controller and a compiled body block.

The canonical idea is:

```text
LCHECK condition
LCALL  compiled_body
LREPEAT loop_slot
```

On the current PASM target, `LCALL` can lower to a direct branch because the continuation is fixed.

That is an example of resisting unnecessary machinery.

The language does not add a runtime call stack merely because the conceptual operation is named `LCALL`.

The canonical operation says what relationship exists. The target chooses the cheapest valid physical form.

### For loops have a separately compiled step

A `for` loop adds one more known stage:

```pasl
for ($counter = 0; $counter != 4; $counter++) {
    $sum = $sum + 1;
}
```

Its conceptual structure is:

```text
init once
   ↓
condition check
   ↓ true
compiled body
   ↓
compiled step
   ↓
condition check
```

This makes `continue` simple to reason about. It targets the step block.

`break` targets the loop exit.

The compiler is able to enforce bounded loop nesting because loop slots exist during compilation. The default maximum depth is eight. Sequential loops can reuse slots after leaving scope.

That is a small but important example of environmental staging. The compiler knows whether it is entering, inside, or leaving a loop. It uses that state immediately rather than reconstructing it later.

### Foreach is a collection problem as well as a loop problem

A `foreach` loop requires more than a condition and a body. It requires an iteration source.

JX separates that concern through the iterator ABI.

A compact iterator operation uses two bytes and can encode forward or reverse iteration.

The current executable iterator test binds a collection to slot 7:

```php
$values = [1, 2, 3];
$table = new pasm\PASMIteratorTable();

$table->bind(new pasm\PASMIteratorDescriptor(
    7,
    count($values),
    fn(int $i) => $values[$i]
));
```

Forward iteration then executes through the compact iterator command:

```php
$forward = [];

while (($item = $table->execute(
    pasm\PASMIterBC::encodeForward(7)
))->valid) {
    $forward[] = $item->value;
}
```

The result is:

```text
[1, 2, 3]
```

Reverse iteration resets the descriptor and executes the reverse command:

```text
[3, 2, 1]
```

The important architectural point is that collection traversal gets a compact executable identity of its own. A future surface `foreach` can link into that ABI instead of inventing a different iteration machine.

At the current stage, ordinary PASL `for` and `while` are fully linked into the front end. The iterator ABI itself is executable and tested. Full surface `foreach` syntax remains a front-end convergence task rather than something this book will falsely call complete.

That distinction is part of JX's documentation discipline: describe the implemented stage exactly, then show the next wall that has already been given a door.

## Things Addressed — Chapter 5

- Loop bodies are compiled once into bounded out-of-line loop-space blocks.
- Current PASM lowers conceptual loop calls to direct branches when the continuation is fixed.
- `for` loop step blocks give `continue` and `break` precise targets.
- Loop nesting is bounded and checked during compilation.
- The compact forward/reverse iterator ABI is executable and tested.
- Surface PASL `foreach` is identified accurately as a remaining front-end link, not falsely documented as complete.
- The chapter's post-test is `php test-pasm-loop-space.php`, `php test-pasm-loop-compiler.php`, and `php test-pasm-iterator-abi.php`.

---

# Chapter 6 — The Browser Is a Host, Not a Separate Language

The browser has spent decades being treated as a special kingdom.

Server code lives here. Browser code lives there. Data is packed into a treaty called JSON and shipped across a border. State is reconstructed. Names are repeated. Validation is repeated. The same object develops several almost-identical forms.

JX takes a simpler position:

**The browser is a host.**

So is a native window system.

So is a server process.

A host gives a program access to an environment. It should not redefine the program's identity.

This is where the Book becomes especially useful.

A Book owns a coherent room of program state. Bags, Pages, Tasks, channels, bindings, and history can belong to that Book. A browser host may present the Book. A native host may present the Book. A server may persist or operate on the Book.

Changing the host should not require inventing a new application object model.

The repository's current host direction uses a versioned `jx.host/1` exchange contract. That gives browser and native presentations a stable place to meet the same Book semantics.

The result is a more useful way to think about full-stack code:

```text
Book
 ├── state Bags
 ├── Tasks
 ├── Pages
 ├── events/channels
 ├── controls/styles
 └── host binding
       ├── browser
       ├── native window
       └── server process
```

The host changes.

The Book does not need to become a different concept.

### A Page is executable state, not a filename

A Page in JX can be created as a runtime object:

```php
$page = jx\Jx::page(
    function ($page) {
        $page->push('ran', true);
        return $page->id();
    },
    1024,
    'home'
);
```

The Page can be registered with a Book:

```php
$book = jx\Jx::book('site');
$book->registerPage('home', $page);
```

The same semantic Page can later be presented by a browser host or a native host.

That is already a useful conceptual break from web boilerplate. A Page is not defined merely by where an HTML file sits. It is a named executable part of a Book.

### Controls should carry their own environmental intent

A control is one of the places where browser/server separation becomes needlessly visible in conventional stacks.

The JX direction is to let a control carry style, event, state, tooltip, grouping, and background intent as data that belongs to the control contract.

A style should be able to say familiar things:

```text
gap: 12px
background: image(...)
opacity: 0.75
color: #ffffff
```

The browser host may realize those properties as CSS. A native host may realize them through another renderer. The canonical control should not be forced to become CSS merely because one host happens to use CSS.

This mirrors the entire JX philosophy.

A human-friendly surface can look familiar while the canonical layer remains host-neutral.

Consider a conceptual JX control declaration:

```jx
panel.style({
    gap: 12,
    color: "#ffffff",
    background: heroImage,
    opacity: 0.75
});
```

A browser shadow might become:

```css
.panel {
    gap: 12px;
    color: #ffffff;
    background-image: url(...);
    opacity: 0.75;
}
```

A native host might instead construct equivalent rendering state.

The programmer should not have to rewrite the application merely because the representation changed.

### Events are another wall that can disappear

The alias model already gives events a canonical vocabulary:

```text
listen / bind / subscribe -> ON
unlisten / unbind         -> OFF
fire / trigger / send     -> EMIT
```

That makes an event less dependent on whichever library happens to own the current host.

A browser can lower `ON` to `addEventListener`. A server channel can lower it to a subscription. A native host can lower it to its event loop.

The source-level intention remains readable.

This is the degree of portability JX is pursuing: not lowest-common-denominator code, but stable intention plus host-specific execution.

## Things Addressed — Chapter 6

- The browser is modeled as a host rather than a separate programming universe.
- A Book retains program identity while different hosts present or execute it.
- Pages are runtime program objects rather than merely files.
- Controls and styles are described as canonical intent that a browser may lower to CSS without making CSS the language's storage grammar.
- Event aliases already converge to canonical `ON`, `OFF`, and `EMIT` operations.
- The chapter identifies host-neutral controls/styles as an architectural direction where renderer coverage must continue to be implemented and tested.

---

# Chapter 7 — Server Without Ceremony

Breaking the wall between browser and server does not mean pretending security boundaries do not exist.

It means refusing to confuse a security boundary with a language boundary.

A server still owns secrets. A server still owns privileged filesystem access. A server still owns protected SQL credentials. A browser still runs in a hostile client environment.

JX's goal is to let those facts be environmental classes and capabilities rather than reasons to duplicate the language.

The programmer should be able to express one operation and allow staging to determine where the operation is legal.

For example, SQL belongs to a server-capable environment.

A source program might conceptually say:

```jx
rows = db.query(
    "select id, name from player where clan_id = ?",
    [clanId]
);
```

The language should not embed a browser password to make this portable.

Portability means the SQL operation has a canonical form while the host staging rules keep the credential in the server environment.

That is prescience applied to security: the system knows the environmental class of the operation before execution.

### SQL is an object, not a string escape hatch

JX's SQL domain already names canonical operations such as:

```text
PREPARE
QUERY
EXECUTE
BEGIN
COMMIT
ROLLBACK
SAVEPOINT
```

Human aliases can remain familiar:

```text
exec -> EXECUTE
select/read/fetch -> QUERY
start -> BEGIN
save -> COMMIT
undo -> ROLLBACK
```

The important requirement is that parameter binding remains data, not string concatenation.

This is the easy path JX should encourage:

```jx
stmt = db.prepare("select * from cards where id = ?");
card = stmt.query([cardId]);
```

This should remain awkward or invalid:

```jx
// intentionally undesirable style
card = db.query("select * from cards where id = " + userInput);
```

The language does not need to invent a strange new SQL dialect to improve safety. It needs to make the secure route the natural route.

### Transactions should read like program state

A transaction can remain simple:

```jx
db.begin();

try {
    db.execute("update wallet set gold = gold - ? where id = ?", [cost, buyer]);
    db.execute("update inventory set owner_id = ? where card_id = ?", [buyer, card]);
    db.commit();
} catch (e) {
    db.rollback();
    throw e;
}
```

The useful future optimization is not to make that source harder.

It is to let the SQL object prelink prepared statements, column identities, typed result layouts, and transaction state where possible.

Once again, human clarity and execution specificity do not need to be enemies.

### Apache and Nginx become hosts around JX, not replacements for it

When JX runs behind Apache or Nginx, the web server does not become the application language.

Apache can invoke or proxy a JX host process. Nginx can terminate TLS, serve static assets, and proxy dynamic requests. The JX Book remains the application unit.

A useful deployment picture is:

```text
internet
   ↓
Apache or Nginx
   ↓
JX host
   ↓
Book
   ↓
Pages / Tasks / Bags / SQL / Channels
```

The wall is still doing useful work. TLS termination, static-file efficiency, process supervision, and network policy belong there.

What disappears is the unnecessary idea that the application must be re-described because the request crossed that wall.

## Things Addressed — Chapter 7

- Browser/server convergence is separated from security-boundary erasure.
- Environmental classes determine where privileged capabilities may execute.
- SQL is treated as a first-class canonical domain with prepared/parameterized operations.
- Transactions remain readable source while lower stages may prelink statements and layouts.
- Apache and Nginx are deployment hosts/proxies around the JX application rather than alternative application models.
- Full SQL connector/runtime coverage remains an implementation area that must receive dedicated integration tests before being claimed complete.

---

# Chapter 8 — Intuitive Programming Is a Compiler Feature

There is a tendency to describe intuitive programming as a matter of syntax aesthetics.

JX treats it as a compiler responsibility.

A language is intuitive when the programmer is allowed to state the idea in the vocabulary that naturally belongs to that idea, while the compiler carries the burden of convergence.

A queue programmer should be able to say:

```jx
jobs.enqueue(task);
```

A vector programmer should be able to say:

```jx
players.append(player);
```

A stack programmer should be able to say:

```jx
undo.push(action);
```

The compiler can canonicalize all three hot additions to `BPUSH` because the discipline already tells it what the physical behavior is.

This is much better than forcing every programmer to memorize a machine-oriented universal verb.

The machine gets universality.

The programmer gets context.

### Aliases are not parser clutter when they disappear early

The usual objection to aliases is that too many names make a language inconsistent.

That objection is correct only when aliases survive too far.

JX resolves aliases before lowering.

After canonicalization, the engine is not carrying four spellings of push. It is carrying one `BPUSH` operation plus optional provenance.

The parser is therefore allowed to be generous without making the runtime confused.

That is a major theme of JX:

> **Surface grammar and storage grammar do not need to match.**

Human grammar is allowed to explain itself.

Machine grammar is allowed to be positional.

### A method call can begin as rhetoric and end as an address

Imagine this source:

```jx
inventory.insert(cardId, card);
```

At the human layer, `insert` is a word.

At the alias layer:

```text
insert -> BEMPLACE
```

At the method linker:

```text
BEMPLACE -> 0x1203
```

If profiling shows it is sufficiently hot:

```text
0x1203 -> 0xE3
```

At execution, the call site may now be one byte.

The path is:

```text
inventory.insert(cardId, card)
          ↓
BEMPLACE
          ↓
12 03
          ↓ hot promotion
E3
```

This is what intuitive programming looks like when compiler architecture takes it seriously.

Ease at the source is paid for by better compilation, not by more runtime ambiguity.

### Modification becomes less frightening

Because source meaning is retained canonically, implementation changes do not have to rewrite the programmer's mental model.

A map might begin as a PHP associative array fallback. Later it might lower to a native hash table. Later still, a particular immutable map might become a perfect-hash layout.

The source can continue to say:

```jx
value = settings.get(key);
```

The representation can evolve beneath it.

That is one of the strongest arguments for keeping execution shadows disposable.

A language becomes modifiable when it does not confuse yesterday's optimization with today's meaning.

## Things Addressed — Chapter 8

- Intuitive programming is defined as compiler-supported convergence, not merely pleasant punctuation.
- Container-specific vocabulary can converge to shared canonical hot operations.
- Early alias elimination prevents a rich surface vocabulary from bloating runtime semantics.
- Method identities demonstrate a full path from human rhetoric to one-byte hot execution.
- Disposable execution shadows allow representations to evolve without rewriting source meaning.
- The chapter's post-test is to confirm aliases canonicalize identically regardless of chosen human spelling and that promoted method calls still resolve to the same canonical method ID.

---

# Chapter 9 — Testing as a Language Property

A language project can accumulate an impressive amount of code while quietly losing the ability to run it as one system.

JX does not get to call that success.

The current branch therefore has a full runnable gate named:

```text
test-all.php
```

Its purpose is deliberately broader than a normal unit-test script.

It syntax-checks every standalone PHP file in the active tree. It recognizes the deliberately split XipEngine source fragments, assembles them through the real loader, and then syntax-checks the generated whole class. It automatically discovers every root `test-*.php` harness. It executes runnable PHP examples. It exercises the JX CLI. It exercises PASL. It compiles PBC bytecode and runs it again. It drives the native x86 emitter, assembles the result with the system compiler, links a native process, executes that process, and checks its return value. It then executes the benchmark harnesses.

The gate is intentionally unpleasant to stale code.

That is exactly what makes it useful.

During its construction it found assumptions that narrower CI had missed: an old Bag example, a nullable segment-registry contract mismatch, stale PASM syntax in an OOP example, an old complex/loop sample that exceeded the active register model, and a corrupted compressed x86 payload wrapper.

Those are not failures of the testing strategy.

They are evidence that the broader gate was necessary.

### Split source must be tested as split source

One instructive example is `XipEngine`.

Two source fragments are intentionally concatenated by a loader. Linting each fragment separately reports an error because each fragment is not supposed to be a whole class.

A naive “lint everything” policy would therefore create a false failure.

The correct test is more informed:

```text
fragment 1 + fragment 2
        ↓ real loader
assembled class
        ↓
PHP syntax check
```

This is prescience applied to testing.

The gate knows the environmental class of those files: fragments, not standalone programs.

A good test suite does not merely execute more commands. It understands what kind of thing it is testing.

### Examples are part of the product

Documentation examples rot quickly when they are not executable.

JX's gate treats runnable examples as code.

The `full-stack-runnable.php` example intentionally exercises several layers in one process: alias canonicalization, vector/map/set Bag behavior, named-memory addressing, method addressing and hot promotion, forward/reverse iterator execution, packed PASM bytecode, and optimized/unoptimized PASL agreement.

The output contains a visible status:

```json
{
    "status": "PASS",
    "vector": [1, 2, 3],
    "named_health": 88,
    "method_id": "0x1203",
    "method_hot_opcode": "0xE3",
    "iterator_forward": [1, 2, 3],
    "iterator_reverse": [3, 2, 1]
}
```

This kind of example has two jobs.

It teaches.

It breaks loudly when architectural layers stop agreeing.

That is exactly the relationship this book will maintain between rhetoric and code.

### Benchmarks are executable evidence, not slogans

JX contains several benchmark lines because representation choices have to be measured.

The project has already learned one important lesson from those measurements: a Bag-backed PHP wrapper is not automatically a speed layer.

The wrapper provides canonical semantics and a useful fallback. Native fixed-offset lowering is where large speedups appear.

That lesson matters because it prevents an easy documentation mistake. A language manual should not take a native fixed-field benchmark and imply that ordinary PHP wrapper calls achieved the same factor.

The project instead keeps a cost rule:

```text
proven target        -> direct call / branch / fixed offset
small dynamic set    -> guard / PIC / compiled switch
truly dynamic target -> table or resolver fallback
```

The representation is selected by evidence.

That is how a language can remain ambitious without becoming promotional fiction.

## Things Addressed — Chapter 9

- The active branch contains a full runnable gate rather than only isolated unit tests.
- Split-source files are tested according to their actual environmental class.
- Runnable examples are treated as executable product surface.
- PBC and native x86 paths are tested as round trips, not file-existence checks.
- Benchmarks are used to choose representations and to prevent misleading performance claims.
- The chapter's post-test is `php -d zend.assertions=1 -d assert.exception=1 test-all.php`, which is also the CI entrypoint.

---

# Chapter 10 — The Door in the Wall

The wall is not one technology.

It is the accumulated habit of treating every boundary as a reason to start over.

Browser to server.

Server to SQL.

Object to bytecode.

Bytecode to native.

Name to address.

Container to memory.

Source loop to execution loop.

Event to host callback.

Style to renderer.

JX's central move is to ask whether those boundaries can instead become **stages**.

A stage has context.

A stage has legal capabilities.

A stage has known identities.

A stage can eliminate information that is no longer needed.

A stage can choose a representation without replacing the source meaning.

This gives JX a different kind of portability.

The language does not promise that every environment is identical.

It promises that environmental differences can be described without forcing the programmer to abandon the language.

That means a browser may realize controls through DOM and CSS. A server may realize SQL through a protected driver. A native host may realize a Page through Win32 or X11. A PASM VM may execute packed bytecode. A native backend may turn the same canonical operations into x86-64.

The programmer should be able to recognize the same program throughout those stages.

### The boilerplate JX is trying to replace

A conventional full-stack operation may look conceptually like this:

```text
browser click
  -> JavaScript handler
  -> construct JSON
  -> fetch route
  -> server router
  -> request decoder
  -> service method
  -> SQL builder
  -> database driver
  -> result mapper
  -> JSON serializer
  -> browser decoder
  -> UI state update
```

Every one of those steps may be valid. The problem is that the programmer is commonly forced to restate identity at each one.

JX asks for a more factored path:

```text
control event
   ↓ canonical event
Page / Task operation
   ↓ staged host boundary
server capability
   ↓ canonical SQL operation
result Bag
   ↓ delivery path
control state
```

The network still exists.

Serialization may still exist.

SQL still exists.

Security still exists.

What disappears is repeated invention.

This is why JX is intended to be greater than new boilerplate.

It is not another framework that hides one wall by constructing a larger wall around it.

It is an attempt to make the language aware of the stages that frameworks normally glue together manually.

### A future JX program should read from the intention outward

Imagine a card-market Page:

```jx
book = Book.open("market");

orders = Bag.map(65536, "Order");
selected = Bag.record(4096, {
    cardId: "int",
    price: "int"
});

buyButton.on("click", {
    db.begin();

    order = db.query(
        "select id, card_id, price from orders where id = ?",
        [selected.orderId]
    );

    inventory.emplace(order.card_id, order);
    db.execute(
        "update orders set buyer_id = ? where id = ?",
        [player.id, order.id]
    );

    db.commit();
    market.emit("changed", order);
});
```

This example is intentionally aspirational at the complete surface-integration level. Not every displayed high-level form is yet implemented by the current JX parser.

But every major idea in it is already represented in the architecture being built: Books, Bag disciplines, `BEMPLACE`, event canonicalization, SQL canonical operations, methods, Pages, host staging, and identity lowering.

That is the correct way to use an aspirational example in a serious language manual.

It should not pretend the parser can execute syntax it cannot yet execute.

It should demonstrate how implemented pieces are converging and make the remaining front-end work obvious.

### The regression direction of the documentation

This book is designed to be read forward and audited backward.

Forward, a programmer sees an increasingly capable language.

Backward, every claim should lead toward one of four things:

```text
current runnable code
current regression test
current benchmark
clearly labeled convergence target
```

That backward path is how the documentation keeps its life.

When a convergence target becomes runnable, the chapter should change category. The example moves from architectural direction into tested language surface. The post-chapter gate gains the new harness. The claim becomes stronger because code earned it.

That is the completion point this book is aiming for: documentation whose rhetoric encourages the programmer while its structure continuously forces the project to prove what it says.

The door in the wall is not a metaphor for ignoring complexity.

It is a metaphor for factoring it.

The wall can stay where it is useful.

The programmer no longer has to rebuild the room on both sides.

## Things Addressed — Chapter 10

- Browser, server, SQL, bytecode, native code, controls, and events are treated as stages rather than unrelated languages.
- JX portability means preserved semantic identity with host-specific realization, not a lowest-common-denominator runtime.
- The intended reduction in full-stack boilerplate is repeated identity reconstruction, not necessary security or transport work.
- Aspirational code is explicitly labeled when surface syntax is not yet fully implemented.
- Documentation is required to regress from every claim toward runnable code, a test, a benchmark, or a clearly marked convergence target.
- The next documentation volumes should deepen each wall separately: language surface, memory, compiler, native ABI, hosts, controls/styles, SQL, plugins, packaging, diagnostics, and benchmark methodology.

---

# Closing — JX Says What

JX begins with a practical belief about programmers.

They should be allowed to think in the language of the problem.

A queue should enqueue.

A vector should append.

A map should emplace.

A Page should run.

A Book should own its room.

An event should emit.

A transaction should begin and commit.

A browser should be a host.

A server should be a host with different capabilities.

A compiler should remember what it already knows.

A machine should receive compact identities instead of human rhetoric it no longer needs.

And an optimization should never be allowed to erase the readable meaning that justified it.

That is the language named **JX**.

That is **Jinx**.

The code can stay easy because the architecture is willing to work harder.

The program can stay portable because the canonical meaning is not the same thing as the physical representation.

The program can stay modifiable because the fastest representation is allowed to be temporary.

The browser and the server can stop feeling like two unrelated programs because environment is allowed to become staging rather than syntax.

There is still a wall.

There should be. Walls carry structure, security, ownership, and boundaries.

But the door is sitting there in the wall.

JX is being built so the programmer can use it.
