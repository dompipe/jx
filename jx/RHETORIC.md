# JX v0.1 — Rhetorical Flow

JX calls should be easy to continue reading.

The idea is simple: **each argument should make the next kind of argument easier to predict**. A programmer should not have to stop in the middle of a call and remember whether style came before destination, or whether a source came after its options.

This is not an attempt to make code look like English prose. Extra words can make code slower. The goal is **predictable order**.

> Start the thought. Narrow the thought. Finish the thought.

## The next-token test

Read a call from left to right and cover everything after the current argument.

If you can usually guess what *kind* of thing belongs next, the signature has good flow.

```php
Flow::curve(
    'sweep',
    Flow::from(0, 80),
    Flow::through(40, 10),
    Flow::through(120, 130),
    Flow::to(180, 80),
    Flow::like(['smooth' => 0.82]),
);
```

The thought develops in one direction:

```text
curve -> which curve -> from -> through -> through -> to -> like
```

There is little reason to pause because the previous argument prepares the next one.

## The v0.1 order

When the concepts apply, JX prefers this order:

```text
subject -> from -> through -> to -> like -> with
```

Not every call needs every role. The roles mean:

| Role | Question it answers |
|---|---|
| subject | What are we acting on or making? |
| from | Where does it begin? |
| through | What intermediate path does it take? |
| to | Where does it finish? |
| like | How should it behave? |
| with | What should accompany, paint, carry, or configure it? |

For location rather than travel, use `at`.

```text
callback -> at -> with
```

For data movement, prefer:

```text
value -> into -> at
```

For compilation, prefer:

```text
source -> to
```

and when compilation options become part of the stable public compiler surface:

```text
source -> to -> with
```

## Real JX code

`jx/Flow.php` is an executable adapter over the current JX runtime. It lets the public order stay rhetorical while lowering to the existing engine contracts.

```php
require 'jx/Flow.php';

use jx\Flow;

$curve = Flow::curve(
    'motion-curve',
    Flow::from(0, 80),
    Flow::through(40, 10),
    Flow::through(120, 130),
    Flow::to(180, 80),
    Flow::like(['smooth' => 0.82]),
);
```

Internally, the current `Control::curve()` stores properties before its degree points. `Flow::curve()` deliberately accepts the human order and lowers it to the engine order. **Public grammar does not have to inherit storage grammar.**

That rule is important for JX.

> The engine may rearrange. The reader should not have to.

## Lines

A line has fewer decisions, so it stays short:

```php
$line = Flow::line(
    'sweep-line',
    Flow::from(26, 150),
    Flow::to(330, 34),
    Flow::like(Control::pong()),
);
```

With an image brush:

```php
$line = Flow::line(
    'image-trail',
    Flow::from(16, 42),
    Flow::to(220, 42),
    Flow::like(Control::reset(0.25, 0.75)),
    Flow::with(Image::blur('neon-line.png', 'image/png', 8)),
);
```

Read it aloud without translating:

> Line `image-trail`, from here, to here, like this, with this.

That is the target.

## Bags

The low-level memory law remains explicit:

```php
$bag = jx\Jx::bag(256);
$ref = $bag->sign('message');
$bag->set('hello', 'message')->commit($ref);
$bag->unsign($ref);
```

For ordinary one-shot work, the rhetorical surface keeps the same law but removes ceremony:

```php
Flow::put('hello', $bag, 'message');
$value = Flow::take($bag, 'message');
```

The parameter order is intentional:

```text
put(value, into, at)
take(from, at)
```

`Flow::put()` still signs, commits, and revokes underneath. The shorter sentence is not a shortcut around the memory model.

> Say less; enforce the same law.

## Delivery

Deep-path access follows the direction of the thought:

```php
$name = Flow::deliver(
    $state,
    'player.profile.name',
    'unknown',
);
```

Read it as:

> Deliver from this root, through this path, or use this fallback.

A rebind is similarly directional:

```php
$next = Flow::rebind(
    $state,
    'player.position.x',
    42,
);
```

```text
rebind(root, through, to)
```

## Output

Events normally read:

```php
$output = Flow::output(
    'controls.movementPicked',
    Flow::at(Control::XY_RT),
    Flow::with([
        'source' => 'sweep-line',
        'run' => 'reset',
    ]),
);
```

```text
output(callback, at, with)
```

The callback comes first because it establishes the purpose. Position narrows where. Payload comes last because it qualifies the event.

## A Book should read as a paragraph

Long constructors are not rhetorical. Once a thought contains several independent clauses, JX should use a chain.

```php
$book = Flow::book('learning-room')
    ->withBag('state', 4096)
    ->withBag('messages', 8192)
    ->withPage('home', function (jx\Task $task) {
        $task->push('opened', true);
        return $task->id();
    })
    ->done();
```

The paragraph keeps one subject: the Book.

```text
Book learning-room,
    with Bag state,
    with Bag messages,
    with Page home,
done.
```

This is preferable to a single constructor containing every Bag, Page, library, quota, host, window, compiler target, and option.

> A sentence carries one action. A paragraph keeps one subject.

That is the JX paragraph rule.

## Keep the subject alive

Once a chain establishes its subject, methods should avoid needlessly repeating it.

Good:

```php
Flow::book('demo')
    ->withBag('state', 4096)
    ->withPage('home', $home)
    ->done();
```

Harder to scan:

```php
BookFactory::addBagToBook(
    BookFactory::addPageToBook(
        BookFactory::createBook('demo'),
        'home',
        $home,
    ),
    'state',
    4096,
);
```

The second form keeps restarting the thought.

## Conventional pairs stay conventional

Rhetorical flow should not fight forms programmers already predict easily.

Examples:

```php
$map->put($key, $value);
$array[$key] = $value;
$memory->writeU32($pointer, $value);
```

`key, value` and `pointer, value` are already low-pause pairs. JX does not need to rename every familiar convention to sound more English.

The rule is not "make it conversational."

The rule is:

> **Reduce surprise.**

## Booleans should not hide meaning

A bare boolean in the middle of a call often makes the reader stop.

Harder:

```php
Control::line('sweep', $from, $to, true);
```

Better:

```php
Flow::line(
    'sweep',
    Flow::from(0, 40),
    Flow::to(160, 40),
    Flow::like(Control::pong()),
);
```

The existing boolean form can remain for compatibility, but new examples should prefer the named behavior.

## Options belong at the end

Configuration is usually a qualification of a complete thought, so it belongs after the required meaning.

Preferred:

```text
compile(source, to, with)
move(subject, from, to, like, with)
open(name, at, with)
```

Avoid putting an option bag first unless the option bag itself is the subject.

## Geometry has its own grammar

For two points:

```text
from -> to
```

For many points:

```text
from -> through -> through -> ... -> to
```

Then behavior:

```text
like
```

Then appearance or attached material:

```text
with
```

This gives motion APIs one stable mental map whether the host is HTML, Win32, X11, or another renderer.

## Compilation has the same direction

The compiler surface should follow the artifact as it changes:

```php
$native = Flow::compile($source, 'c');
$pasm = Flow::compile($source, 'pasm');
$x86 = Flow::compile($source, 'x86');
$arm = Flow::compile($source, 'arm');
```

The reading order is always:

```text
compile this -> to this
```

As JX adds stable NASM and C-with-ASM backends, they should use the same slot rather than inventing different compiler verbs.

```text
compile(source, 'nasm')
compile(source, 'c-asm')
```

The backend changes. The sentence does not.

## Libraries and Books

A library is supporting material, so when it is attached to a Book its public grammar should be:

```text
Book -> withLibrary(name, source)
```

A Page is part of the Book:

```text
Book -> withPage(name, doing)
```

A Bag is owned memory:

```text
Book -> withBag(name, memory)
```

When the compiler-facing Book builder is completed, the intended paragraph is:

```php
$book = Flow::book('paint')
    ->withBag('state', 4096)
    ->withLibrary('motion', 'libraries/motion.pasl')
    ->withPage('home', 'pages/home.pasl')
    ->compileTo('nasm');
```

`withLibrary()` and compiler-backed `withPage()` are the direction for the coherent Book compiler; the current `BookFlow` implements runtime Bags and runtime Pages now.

## Resistant code must keep the sentence

A Resistant lowering is allowed to become longer internally. It should not force the author to use a different public grammar.

```text
compile(source, to)
```

may lower to a short native template or a long Resistant implementation. The source call remains the same.

> Resistant means a safer road, not a different language.

This also means documentation should show the clean public call first, then show the longer lowering when teaching the engine.

## The paragraph test

Before adding a public JX API, put three to six related calls together.

Ask:

1. Is the subject obvious after the first line?
2. Does each line naturally follow the line before it?
3. Can a reader predict the next category of argument?
4. Are optional details near the end?
5. Are booleans hiding a named concept?
6. Can the block be read aloud without mentally rearranging it?

If the answer is mostly yes, the API will usually scale from a single call into a readable paragraph.

## The compiler may be strict about roles

`Flow::from()`, `through()`, `to()`, `like()`, `with()`, and `at()` carry role information in their PHP representation. That is useful beyond appearance: a compiler or analyzer can eventually catch sentences that are grammatically wrong.

For example, this should be diagnosable:

```php
Flow::curve(
    'wrong-way',
    Flow::with(['smooth' => 1]),
    Flow::to(100, 100),
);
```

The roles can become compile-time evidence without costing anything in a native lowering after the call has been resolved.

That is where rhetorical flow becomes more than style: **the readable order can also become a checkable order.**

## v0.1 mnemonic

The short form to remember is:

> **This. From here. Through here. To there. Like this. With this.**

For code that does not move:

> **This. At here. With this.**

For data:

> **This. Into this. At here.**

For compiling:

> **This. To this.**

The words are not meant to appear everywhere. They describe the order that JX APIs should naturally follow.
