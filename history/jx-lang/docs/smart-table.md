# Smart Table Maker

The smart table maker is the heart of jx compilation and AI interpretation.

## Purpose

Maintain a living catalogue of every method so that both the batch compiler and any AI interpreter instance can **extrude** the highest-performance native sequence that still obeys the memory model. When a pure native path is impossible, emit **Resistant code**.

## Schema (v0.1)

Each row describes one known method / operation.

| Column | Type | Description |
|--------|------|-------------|
| `id` | string | Unique stable identifier (`bag.set`, `task.push`, `delivery.extract`, …) |
| `name` | string | Surface name as written by the programmer |
| `module` | string | Owning module / type (`Bag`, `Task`, `Book`, `global`, …) |
| `arity` | int or range | Number of arguments |
| `arg_shapes` | list | Accepted type / shape patterns |
| `side_effect` | enum | `none`, `read`, `write-bag`, `schedule`, `io`, … |
| `requires_ref` | bool | Whether a live `refSign` is mandatory |
| `memory_class` | enum | `pure`, `underwritten-only`, `task-local`, … |
| `native_template` | string / IR | Preferred native lowering (register mapping, instruction sequence skeleton) |
| `resistant_template` | string / IR | Fallback sequence when native_template cannot be applied safely |
| `purity_score` | float | 1.0 = fully pure native, lower = more Resistant |
| `notes` | string | Human / AI guidance |

## Extrusion Process

1. Resolve the call to a table row (or set of candidate rows).
2. Match argument shapes and side-effect constraints.
3. If a high-purity `native_template` applies under current memory / const / delivery facts → emit it.
4. Otherwise instantiate `resistant_template`, mark the region as Resistant, and continue.
5. Record the choice for later audit and for AI interpreters that may re-lower live.

## AI Interpreter Contract

Any AI instance that “knows every function and remembers every method and their functional writing style” is expected to consult the same table (or an equivalent in-memory form). The goal is that fastening high-level jx to assembly becomes a table-driven, almost mechanical act rather than open-ended invention.

## Extensibility

New methods are added by inserting rows. The compiler and AI interpreters pick them up without further hard-coding. Deprecated methods are marked but retained for Resistant compatibility.
