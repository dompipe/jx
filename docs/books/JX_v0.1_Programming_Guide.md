# JX v0.1 Programming Guide

## Write the thought. Let JX carry it down.

JX is a programming and application layer built over PASL and PASM. You can begin with a small idea - a value, a Bag, a Page, a movement - and follow the same idea downward until it becomes bytecode, C, architecture output, or host work.

You do not need to begin by studying compiler theory.

Begin with what the program is doing.

---

## 1. The short map

```text
JX program / Book
        |
       PASL
        |
       PASM
        |
  runtime / compiler
        |
 browser / native host
```

The important names are small:

- **Book** - the application-sized subject.
- **Page** - runnable work or a surface inside a Book.
- **Bag** - owned mutable memory.
- **Task** - execution state with identity.
- **Delivery** - follow a path into structured data.
- **Control** - a host-neutral UI or movement description.
- **PASL** - the higher-level compiler language.
- **PASM** - the machine/bytecode engine underneath.
- **Resistant** - a safe compiled route when the shortest native route is not valid.

A useful sentence is:

> **A Book owns the room. Pages do the work. Bags remember. PASL explains it to PASM.**

---

## 2. Your first JX memory

The repository includes this real JX source in `examples/hello.jx`:

```jx
bag = Bag.underwrite(256);
ref = bag.sign("msg");
bag.set("hello-jx").commit(ref);
q = bag.quotient();
```

Read it in order.

1. Give the Bag room.
2. Sign the place you intend to change.
3. Prepare the value.
4. Commit the write with the sign.
5. Ask how much room remains.

The Bag is not just an array with another name. Capacity and the write handshake are part of the program model.

### Why the sign exists

A normal PHP assignment can change memory directly:

```php
$state['message'] = 'hello';
```

JX makes the mutation boundary visible:

```php
$ref = $bag->sign('message');
$bag->set('hello', 'message')->commit($ref);
$bag->unsign($ref);
```

That extra structure gives the runtime a place to enforce ownership and capacity.

For ordinary one-shot work, JX v0.1 also has a rhetorical helper:

```php
use jx\Flow;

Flow::put('hello', $bag, 'message');
$message = Flow::take($bag, 'message');
```

The short call still uses the handshake underneath.

> **Say less; enforce the same law.**

---

## 3. Rhetorical flow

A JX call should not make you stop halfway through and remember where the destination goes.

The v0.1 public order is:

```text
subject -> from -> through -> to -> like -> with
```

Not every call needs every part.

A line is naturally:

```text
line -> from -> to -> like -> with
```

A curve is naturally:

```text
curve -> from -> through -> through -> to -> like
```

A data write is:

```text
put -> value -> into -> at
```

Compilation is:

```text
compile -> source -> to
```

This is similar to reading a stream of predictable tokens: the context already established should make the next category easier to guess.

### Real runnable curve

`jx/Flow.php` lowers the rhetorical order to the existing Control contract:

```php
$curve = Flow::curve(
    'motion-curve',
    Flow::from(0, 80),
    Flow::through(40, 10),
    Flow::through(120, 130),
    Flow::to(180, 80),
    Flow::like(['smooth' => 0.82]),
);
```

Read it without translation:

> Curve `motion-curve`, from here, through here, through here, to here, like this.

The engine is free to rearrange storage internally.

The reader should not have to.

---

## 4. A Book reads like a paragraph

A single call is a sentence. A larger object should not become one enormous constructor.

JX keeps the Book as the subject and adds clauses:

```php
use jx\Flow;
use jx\Task;

$book = Flow::book('learning-room')
    ->withBag('state', 4096)
    ->withBag('messages', 8192)
    ->withPage('home', function (Task $task): int {
        $task->push('opened', true);
        return $task->id();
    })
    ->done();
```

Read it as a paragraph:

```text
Book learning-room,
    with Bag state,
    with Bag messages,
    with Page home,
done.
```

That is the JX paragraph rule:

> **A sentence carries one action. A paragraph keeps one subject.**

### Running a Page

A runtime Book can retrieve a Page and run it:

```php
$result = $book->page('home')->run();
```

The Page wraps a Task. When it runs, the Task changes state through the execution lifecycle.

---

## 5. Bags: room before writing

A Bag is underwritten memory.

```php
$bag = jx\Jx::bag(1024);
```

It reports:

```php
$bag->capacity();
$bag->used();
$bag->quotient();
```

A simple relationship is:

```text
quotient = capacity - used
```

The quotient is the room still available.

If a write needs more room than the Bag has, JX refuses the write instead of allowing an uncontrolled overflow.

### Properties

A Bag also supports preassigned properties:

```php
$bag->push('owner', 'home-page');
$owner = $bag->prop('owner');
```

The property still counts against the Bag's capacity.

### Tight and verbose forms

JX keeps a tight form:

```php
$bag->set($data, 'node')->commit($ref);
```

and a verbose-compatible form:

```php
$bag->tell('set', $data, 'node')->pass($ref);
```

The longer form is not a separate machine. It lowers to the tight behavior.

---

## 6. Delivery: follow the path

Delivery reads deeply nested data without making the caller manually walk every level.

```php
$state = [
    'player' => [
        'profile' => [
            'name' => 'Ada',
        ],
    ],
];

$name = Flow::deliver(
    $state,
    'player.profile.name',
    'unknown',
);
```

The thought is:

> Deliver from this root, through this path, or use this fallback.

To create an updated structure:

```php
$next = Flow::rebind(
    $state,
    'player.position.x',
    42,
);
```

The rhetorical form is:

```text
rebind(root, through, to)
```

Delivery itself does not silently mutate a Bag. If the result belongs in a Bag, commit it through the Bag law.

---

## 7. Controls: describe the idea, not the host

A JX Control is a contract that can be rendered by a host. HTML is one host. Win32 and X11 can read the same kind of description.

Basic controls include text, spin, toggle, drawing, and image contracts.

### A line

```php
$line = Flow::line(
    'sweep-line',
    Flow::from(26, 150),
    Flow::to(330, 34),
    Flow::like(Control::pong()),
);
```

### A line with a reset window

```php
$line = Flow::line(
    'reset-line',
    Flow::from(0, 70),
    Flow::to(160, 70),
    Flow::like(Control::reset(0.25, 0.75)),
);
```

`pong()` travels forward and then backward. `reset()` travels forward and jumps back to the beginning of its run window.

### Output at a picked point

```php
$output = Flow::output(
    'controls.movementPicked',
    Flow::at(Control::XY_RT),
    Flow::with([
        'source' => 'reset-line',
        'run' => 'reset',
    ]),
);
```

The order is:

```text
output(callback, at, with)
```

### Images can be brushes

The existing Control system also supports images attached to paths, replacement image sets, image pins, display state, spin themes, zoom themes, and curve motion.

The important idea is that the Book describes the control. The host decides how to present it.

---

## 8. PASL: programming above PASM

PASL is a restricted language that lowers to the PASM engine and also has newer native compiler routes.

The repository contains this real PASL example in `examples/pasl/complex-and-loops.pasl`:

```pasl
$sum = 0;
$i = 5;
while ($i) {
    $sum = $sum + $i;
    $i--;
}

$j = 0;
for ($k = 0; $k != 4; $k++) {
    $j = $j + 1;
}

complex $z = 3+4i;
complex $w = 1-2i;
complex $p = $z * $w;

$mode = 2;
select ($mode) {
    case 1:
        $sum = $sum + 100;
    case 2:
        $sum = $sum + $j;
    default:
        $sum = $sum + 999;
}
```

This is not full PHP. It is intentionally lowerable.

The numeric PASL core supports integer arithmetic, complex arithmetic, loops, conditions, and selection with a direct relationship to registers and jumps.

---

## 9. Compile the same thought to different destinations

The public compilation direction is short:

```text
compile(source, target)
```

With `Flow`:

```php
$c = Flow::compile($source, 'c');
$pasm = Flow::compile($source, 'pasm');
$x86 = Flow::compile($source, 'x86');
$arm = Flow::compile($source, 'arm');
```

The PASL package also supports the fuller C surface when strings, arrays, objects, network calls, or live features are used.

JX keeps the target in one slot. New native destinations such as NASM or C-with-inline-ASM belong in that same slot rather than creating a second language.

> **Change the destination, not the sentence.**

---

## 10. What PASM is doing underneath

You do not need PASM to write your first JX program, but it helps to know what is below you.

PASM has a bytecode instruction set with operations such as:

```text
MOVI MOVR
ADD SUB MUL DIV MOD
AND OR XOR SHL SHR
CMP JMP JZ JNZ
PUSH POP INC DEC NEG
LOAD32 STORE32
RET HALT
```

The optimized assembler can fuse common patterns into superinstructions.

A PASL loop becomes labels, comparisons, jumps, and register work. A high-level expression becomes a much smaller machine vocabulary.

That is why PASL is deliberately constrained: each construct has a path downward.

---

## 11. Libraries

A library should feel like supporting material attached to the same Book, not like a second application model.

The v0.1 grammar for the compiler-facing Book is:

```text
Book -> withLibrary(name, source)
Book -> withPage(name, program)
Book -> compileTo(target)
```

The runtime `BookFlow` already implements `withBag()` and runtime `withPage()`. Compiler-backed library/page linking is being unified behind the same paragraph shape.

The intended reading is:

```php
$book = Flow::book('paint')
    ->withBag('state', 4096)
    ->withLibrary('motion', 'libraries/motion.pasl')
    ->withPage('home', 'pages/home.pasl')
    ->compileTo('nasm');
```

The useful part is not the English-looking method names by themselves. It is that each clause belongs to the Book already established above it.

---

## 12. Resistant code

Sometimes the shortest lowering is not legal or not provably safe.

JX calls that route **Resistant**.

A Resistant route can be longer internally. It is still supposed to compile in its target environment.

The programmer should not have to rewrite the source call merely because the compiler chose the safer road.

```text
same JX/PASL sentence
        |
   compiler decision
     /        \
 native    Resistant
     \        /
       target
```

> **Resistant means a safer road, not a different language.**

This is especially important for younger programmers: the public language should remain learnable even when the engine needs more machinery underneath.

---

## 13. Complex numbers

JX and PASL treat complex values as real programming values rather than a library afterthought.

```pasl
complex $z = 3+4i;
complex $w = 1-2i;
complex $p = $z * $w;
```

The PASL numeric lowering uses two registers for a complex value: one for the real part and one for the imaginary part.

The multiplication follows:

```text
(a + bi)(c + di) = (ac - bd) + (ad + bc)i
```

JX also contains a runtime `Complex` object with parsing, add, subtract, multiply, conjugate, and magnitude operations.

---

## 14. The browser is a host, not the language

The browser PASM VM currently implements a compact instruction interpreter in JavaScript. It runs embedded PASM programs and reports host events through the JX host protocol.

That JavaScript is replaceable glue.

The Book and PASL program are the portable ideas.

A later VM can change without teaching the Book a new language.

---

## 15. Learn by building one small Book

A good first project is a Book with:

- one state Bag,
- one Page,
- one line or curve,
- one Delivery path,
- one PASL calculation.

For example:

```php
$state = jx\Jx::bag(1024);
Flow::put(['x' => 0, 'y' => 80], $state, 'position');

$path = Flow::curve(
    'player-path',
    Flow::from(0, 80),
    Flow::through(40, 10),
    Flow::to(180, 80),
    Flow::like(['smooth' => 0.75]),
);

$book = Flow::book('first-book')
    ->withBag('state', $state)
    ->withPage('home', function (jx\Task $task) use ($path) {
        $task->push('path', $path);
        return $task->id();
    })
    ->done();
```

Nothing in that example requires you to know how an optimizing assembler fuses `CMP` and `JNZ`.

You can learn that later, when you want to know why it is fast.

---

## 16. The habits JX v0.1 wants to teach

### Give memory a home

Use Books, Pages, Tasks, and Bags to make ownership visible.

### Move left to right

Prefer signatures where the next argument is predictable from the last one.

### Keep one subject per paragraph

When a constructor becomes a paragraph, use a chain.

### Keep options late

Finish the meaning before decorating it.

### Prefer named behavior to mystery booleans

```php
Control::pong()
```

is easier to remember than a bare `true` in the fourth slot.

### Let lower levels stay lower

PASM can remain positional and machine-like. JX should not expose internal storage order just because the engine uses it.

---

## 17. Where to read next

Inside the repository:

```text
jx/SPEC.md
jx/RHETORIC.md
jx/hosting-api.md
jx/CONTROL.md
jx/COMPILER.md
jx/PASM_MAP.md
jx/smart-table.md
```

Then read the engine book when you want to follow the same program through the compiler, bytecode, runtime memory, containers, scheduling, and host boundary.

---

# JX v0.1 mnemonic page

> **A Book owns the room. Pages do the work. Bags remember.**

> **This. From here. Through here. To there. Like this. With this.**

> **Put the value into the Bag at the place.**

> **Write PASL once. Let PASM carry it down.**

> **Change the destination, not the sentence.**

> **Resistant means a safer road, not a different language.**

> **A sentence carries one action. A paragraph keeps one subject.**
