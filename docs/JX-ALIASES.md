# JX language-wide aliases

Aliases are a first-class compiler feature.

> Human spelling is flexible. Canonical meaning is singular. Execution sees only canonical operations.

## Pipeline

```text
source spelling
    ↓ alias resolution
canonical operation
    ↓ semantic lowering
PASL / PASM / native shadow
```

The runtime never performs an alias lookup. Provenance retains the spelling a coder used:

```text
source_spelling: enqueue
alias_domain: bag.hot
canonical_op: BPUSH
alias_context: queue
```

## Rules

1. One canonical operation owns semantics.
2. Aliases disappear during parse/link.
3. Aliases may not alter behavior.
4. Domains isolate common words (`open`, `run`, `set`, `push`).
5. Context can refine an alias where the object/discipline is known.
6. A collision inside one domain/context is a compile/link error.
7. Plugins register aliases into their own context and cannot silently replace core aliases.
8. Diagnostics may show both source spelling and canonical operation.
9. Canonical Shadow provenance records the canonical op and the original spelling.
10. Native lowering never pays for alias flexibility.

## Implemented domains

`jx-alias.php` currently defines domains for:

- Bag runtime
- Bag hot operations
- Book
- Task
- Page
- Delivery
- function
- method
- Control
- Style
- Event
- Channel
- SQL
- `jx.chart`
- Host
- Window
- Library
- Plugin
- PASL
- PASM

Additional plugins may register contextual aliases at link time.

## Bag hot vocabulary

| Canonical | Representative aliases |
|---|---|
| `BPUSH` | push, append, add, enqueue, enq |
| `BPOP` | pop, take, dequeue, deq |
| `BPUSHF` | pushfront, unshift |
| `BPUSHB` | pushback |
| `BPOPF` | popfront, shift |
| `BPOPB` | popback |
| `BEMPLACE` | emplace, insert, packin, putifabsent, addifabsent |
| `BPEEK` | peek, top, front |
| `BRESERVE` | reserve, ensure |
| `BDIRTY` | dirty |
| `BSYNC` | sync, checkpoint, commitbag |

The Bag discipline then determines the physical native lowering.

## BEMPLACE

`BEMPLACE` is deliberately discipline-aware.

### Vector / stack

Calculate the insertion address once, open one overlap-safe gap, then store:

```asm
lea insert, [base+index*width]
memmove [insert+width], [insert], cursor-insert
mov [insert], value
```

The cursor is advanced once after the packed move. The canonical operation treats the tail shift as one bulk move, not a loop of pushes.

### Map

Probe once for the existing key or insertion address:

```asm
call map_probe_insert_address
jc .exists
mov [slot], key_value
```

If the key already exists, emplace returns/uses the existing entry instead of replacing it.

### Set

The same address/probe idea without a value payload:

```asm
call set_probe_insert_address
jc .exists
mov [slot], key
```

## Public extension API

```php
JxAlias::register($domain, $canonical, $aliases);
JxAlias::registerPlugin('jx.myplugin', 'DO_THING', ['THING', 'DOIT']);
```

Collision detection is mandatory. A plugin or library must not redefine an existing alias in the same domain/context to another canonical operation.

## Compiler principle

Aliases increase language readability without increasing executable mechanism:

```text
ENQUEUE → BPUSH → queue → tail-write-inc
APPEND  → BPUSH → vector → cursor-write-inc
PUSH    → BPUSH → stack → cursor-write-inc
```

After linking, all three source words are gone.
