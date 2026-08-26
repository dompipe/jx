# JX Language Specification (v0.1)

Integrated target: this repository (`pasm-v2` → **JX**).

## Identity

- Name: **JX** · pronunciation: *jinx*
- Version: **0.1**
- Foundation: PHP host + PASL compiler + PASM engine
- Compilation: native preferred; Resistant lowering remains compilable
- Memory: explicit ownership through Books, Bags, Pages, and Tasks
- Persistence: explicit database boundaries through first-class SQL and NoSQL objects

## Ontology

- **Book** — coherent application/compiled unit; owns its working pieces
- **Page** — runnable surface backed by task/frame state
- **Bag** — mutable, underwritten memory container
- **Task** — execution Bag with identity and state
- **Delivery** — deep path extract/rebind
- **Library** — reusable program material linked into a Book or compile unit
- **Control** — host-neutral UI/movement contract
- **SQL** — secure relational persistence object; owns a named database connection, prepared query boundary, transactions, and import into Bags
- **NoSQL** — sibling non-relational persistence object; preserves the native model of its adapter rather than pretending to be SQL
- **Resistant** — marked safe lowering used when the preferred native route is not valid

## Rhetorical grammar

Public JX APIs should be predictable from left to right. The preferred semantic order is:

```text
subject -> from -> through -> to -> like -> with
```

Related forms are:

```text
callback -> at -> with
value -> into -> at
source -> to -> with
query -> with parameters
SQL -> into Bag -> at node
```

The rule is not to imitate English. The rule is to reduce surprise: every argument should make the next *kind* of argument easier to predict.

For a longer thought, keep one subject and use a chain rather than building one huge constructor. A sentence carries one action; a paragraph keeps one subject.

Executable adapters and examples live in `Flow.php` and `../examples/jx-rhetorical-flow.php`. Full convention: `RHETORIC.md`.

## Keywords and surface ideas

Selected surface concepts include `const` (castable), complex literals (`3+4i`), Delivery paths, rhetorical direction (`put` / `take`, `from` / `through` / `to`, `like` / `with`), named persistence objects (`SQL`, `NoSQL`), and symbolic assembly constants (`SYS_*`, `STDOUT`, …).

## Memory law

Writes require underwritten Bag capacity and a legal handshake. Quotient oversight refuses capacity overflow rather than allowing the host process to fail unpredictably.

Short rhetorical forms may hide ceremony but may not bypass the law. For example, `Flow::put(value, bag, node)` still signs, commits, and revokes underneath.

External persistence does not bypass that law. SQL/NoSQL results cross the JX boundary as data and are then committed into Bags through the normal Bag handshake.

## Persistence law

SQL is a server-side relational-data boundary. A live SQL connection, password, client key, CA material, PDO handle, or other database credential must never cross the browser host boundary.

The guaranteed SQL families for v0.1 are:

```text
MySQL / MariaDB
PostgreSQL-family
SQLite3
other installed PDO-backed engines through the generic adapter
```

Remote MySQL/MariaDB and PostgreSQL connections use verified transport by default. SQLite3 has no network TLS layer; its security boundary is the file, directory permissions, process identity, and host filesystem.

Prepared parameters are the normal value path. Dynamic identifiers must pass a strict identifier boundary rather than being concatenated from arbitrary input. Transactions belong to the SQL object.

NoSQL follows the same JX laws for secrets, secure transport, host isolation, and Bag synchronization while retaining adapter-native operations and data models.

## Tight vs verbose

Verbose forms (`tell` / `pass`) lower to tight operations before code generation. A long Resistant implementation must also lower and compile in the selected environment; Resistant changes the road, not the source language.

## Compilation direction

The stable public thought is:

```text
compile(source, target, options)
```

Backends may include PASM, portable C, architecture output, and native assembler routes. A backend is a destination, not a new language surface.

## Read next

See sibling files for rhetorical flow, the Smart Table, Delivery, complex numbers, hosting, Controls, Style, SQL, PASM mapping, edge cases, and known gaps.
