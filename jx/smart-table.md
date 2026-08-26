# Smart Table Maker (jx)

Evolution of `pasm-master-table.php`.

## Purpose

Catalogue every method so compiler and AI interpreter can extrude native sequences that obey the memory law, or emit **Resistant** code.

## Schema (v0.1)

| Column | Description |
|--------|-------------|
| id | Stable id (`bag.set`, `task.push`, …) |
| name | Surface name |
| module | Bag, Task, Book, global, … |
| arity | Argument count / range |
| arg_shapes | Accepted shapes |
| side_effect | none / read / write-bag / schedule / io |
| requires_ref | Live refSign required? |
| memory_class | pure / underwritten-only / task-local |
| native_template | Preferred lowering |
| resistant_template | Fallback |
| purity_score | 1.0 = pure native |
| notes | Guidance |

## Process

Resolve call → match shapes → prefer native_template under memory/const/delivery facts → else resistant_template and mark.

Seed rows should be derived from existing PASM opcodes and container methods in this repo.
