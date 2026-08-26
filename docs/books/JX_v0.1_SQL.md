# JX v0.1 SQL

## SQL is an object, not loose connection code

JX treats relational persistence as a named application object:

```text
Book
|- Bags
|- SQL
|- Pages
|- Controls / Collectors
|- Style / Layout
`- PASL / PASM
```

The SQL object owns the connection and query boundary. Bags remain the JX-owned mutable-memory boundary.

> **Query through SQL. Work through Bags. Persist deliberately.**

The executable implementation is `jx/SQL.php`.

## Secure connection families

JX v0.1 has first-class adapters for:

```text
SQL::mysql()       MySQL / MariaDB
SQL::postgresql()  PostgreSQL-family
SQL::postgres()    PostgreSQL alias
SQL::sqlite()      SQLite3
SQL::sqlite3()     SQLite3 alias
```

An advanced `SQL::pdo()` route permits other installed PDO drivers while keeping the same JX query/result surface. When a database family needs a guaranteed connection-security policy, it should receive a dedicated JX adapter rather than forcing every Page to understand that driver's TLS/authentication details.

### MySQL / MariaDB

Remote connections require a trusted CA by default:

```php
$db = jx\SQL::mysql('main', [
    'host' => 'db.example.com',
    'database' => 'jx_app',
    'user' => 'jx_app',
    'password' => jx\SQL::secretFromEnv('JX_DB_PASSWORD'),
    'tls' => [
        'ca' => '/etc/jx/db/mysql-ca.pem',
    ],
]);
```

The adapter uses native prepared statements, disables multi-statements, disables local infile when the driver exposes the option, and verifies the server certificate when TLS is configured.

Client certificates can be included in the same `tls` record:

```php
'tls' => [
    'ca' => '/etc/jx/db/ca.pem',
    'cert' => '/etc/jx/db/client.pem',
    'key' => '/etc/jx/db/client-key.pem',
]
```

### PostgreSQL

Remote PostgreSQL defaults to certificate-verifying TLS:

```php
$db = jx\SQL::postgresql('main', [
    'host' => 'pg.example.com',
    'database' => 'jx_app',
    'user' => 'jx_app',
    'password' => jx\SQL::secretFromEnv('JX_DB_PASSWORD'),
    'sslmode' => 'verify-full',
    'sslrootcert' => '/etc/jx/db/postgres-ca.pem',
]);
```

JX accepts `verify-full` and `verify-ca` for secure remote mode. Weaker remote modes require an explicit insecure escape rather than silently reducing verification.

### SQLite3

SQLite has no remote TLS connection. Its security boundary is the local file and the process identity allowed to access it:

```php
$db = jx\SQL::sqlite3(
    'local',
    '/var/lib/jx/books/calculator/app.sqlite3',
);
```

Disk paths must be absolute. JX enables foreign keys, a busy timeout, WAL by default for disk databases, and attempts to keep the database file private (`0600`) unless another mode is deliberately selected.

A private SQLite database should live outside the public Apache/Nginx document root.

## Passwords should not be literals

```php
$password = jx\SQL::secretFromEnv('JX_DB_PASSWORD');
```

`SQLSecret` redacts ordinary debug output. A production database account should also be scoped to the permissions the Book actually needs.

## Prepared parameters are the normal form

```php
$user = $db->one(
    'SELECT id, name FROM users WHERE id = :id',
    ['id' => $userId],
);
```

```php
$rows = $db->all(
    'SELECT id, name FROM users WHERE status = :status',
    ['status' => 'active'],
);
```

```php
$changed = $db->execute(
    'UPDATE users SET name = :name WHERE id = :id',
    ['name' => $name, 'id' => $id],
);
```

The SQL text supplies structure. Parameters supply values.

Dynamic table or column names are different because SQL parameters cannot bind identifiers. JX therefore provides a conservative identifier validator/quoting boundary:

```php
$table = $db->identifier($requestedTable);
$rows = $db->all("SELECT id, name FROM {$table} WHERE active = :active", [
    'active' => 1,
]);
```

Fixed schema names remain preferable.

## Transactions belong to the SQL object

```php
$db->transaction(function (jx\SQL $db) use ($from, $to, $amount) {
    $db->execute(
        'UPDATE accounts SET balance = balance - :amount WHERE id = :id',
        ['amount' => $amount, 'id' => $from],
    );

    $db->execute(
        'UPDATE accounts SET balance = balance + :amount WHERE id = :id',
        ['amount' => $amount, 'id' => $to],
    );
});
```

The callback commits as one unit or rolls back if it throws.

## SQL can fill a Bag

SQL does not receive a secret shortcut around Bag memory law:

```php
$state = jx\Bag::underwrite(16_384);

$db->into(
    $state,
    'active-users',
    'SELECT id, name FROM users WHERE active = :active',
    ['active' => 1],
);
```

Internally the relationship remains:

```text
SQL query
   -> rows
      -> Boundary import
         -> Bag sign
            -> set
               -> commit
```

External persistence therefore feeds JX state without becoming a second mutation model.

## Other SQL engines

PDO currently provides drivers for several additional database families. JX can reach an installed driver with `SQL::pdo()`:

```php
$db = jx\SQL::pdo(
    'other',
    $dsn,
    $user,
    jx\SQL::secretFromEnv('JX_DB_PASSWORD'),
    $driverOptions,
    transport_verified: true,
);
```

Possible adapter families include SQL Server / Azure SQL, Oracle, Firebird, DB2/ODBC, CUBRID, Informix, and other PDO-backed engines.

The common JX surface can remain:

```text
all
one
value
execute
transaction
identifier
into Bag
```

but JX should not claim that one generic DSN automatically makes every database transport secure. Dedicated adapters own those guarantees.

## SQL never goes to the browser

```text
Browser
   |
JX host
   |
Book / Page
   |
SQL
   |
Database
```

Credentials, CA/key material, and PDO handles stay server-side. The browser sees host-protocol results and Bag state, not database access credentials.

## Apache and database security are separate boundaries

```text
Internet
   -> Apache
      -> persistent JX host
         -> SQL object
            -> database
```

Apache owns public HTTP/TLS. The SQL adapter owns database authentication/TLS/file security. The Book owns the application relationship between them.

## Bags own live data bindings

A Page should not repeatedly hunt for one hard-coded SQL instance. The external-data relationship belongs to the Bag because the Bag is the JX object that receives, owns, and remembers the resulting value.

The reactive direction is:

```text
SQL / NoSQL / other source
          -> Bag.bind(...)
             -> Bag node
                -> Page Binding uses Bag
                   -> Controls / Collectors / Style
                      -> Page patch
```

The core rule is:

> **The Bag remembers its data source. Page Binding remembers that the Page uses the Bag.**

A Bag binding is a serializable descriptor, not a live database handle:

```php
$state = jx\Bag::underwrite(16_384);

$sourceBinding = $state->bind(
    'main',
    'active-users',
    'users',
    'auto',
);
```

Read it as:

> Bind this Bag from source `main`, through `active-users`, at node `users`, automatically.

`unbind()` removes the data-source relationship without erasing the Bag's current value:

```php
$state->unbind($sourceBinding);
```

The last successfully committed value can therefore remain visible while the Bag is no longer listening for future changes.

### Page Binding uses the Bag

Page navigation does not need to own SQL source names or query text. It only needs to know which Bags the active Page depends on:

```php
$binding->useBag('users', 'state');
```

This separates two lifetimes cleanly:

```text
Bag binding
    source -> query/listener -> Bag node

Page Binding
    Page -> uses Bag
```

The JX host can activate the relevant Bag bindings when a Page that uses that Bag is active, or keep a Book-scoped Bag live when the Book deliberately requests that behavior.

For compatibility, the earlier `Binding::listen(...)` form can lower immediately into a Bag source binding plus a Page-to-Bag use record. It should not become a second persistence model.

### Named sources and named queries

A Bag depends on stable source names such as:

```text
main.active-users
```

not on PDO handles, hostnames, passwords, ports, or TLS material.

An SQL object may name reusable queries/listeners:

```php
$main->query(
    'active-users',
    'SELECT id, name, status FROM users WHERE active = :active'
);
```

The host resolves `main`. The Bag descriptor says which named query/listener it wants.

## Bindings can coerce incoming values

External data often arrives in the wrong representation for the Page. JX therefore allows a Bag binding to declare how the value should be coerced before it is committed.

Simple coercion can turn a source value into algebra or a string:

```php
$state->bind(
    'main',
    'price',
    'price',
    'auto',
    ['as' => 'algebra'],
);
```

```php
$state->bind(
    'main',
    'status',
    'status-label',
    'auto',
    ['as' => 'string'],
);
```

The coercion boundary also supports `number`, `integer`, `float`, `boolean`, and `json` where those are useful.

### Algebra can be an expression

A bound row can be reduced to an arithmetic result without arbitrary code evaluation:

```php
$state->bind(
    'main',
    'cart-row',
    'total',
    'auto',
    [
        'as' => 'algebra',
        'expression' => 'price * quantity + shipping',
    ],
);
```

The allowed algebra grammar is deliberately small:

```text
numbers
named fields
nested dotted fields
+  -  *  /  %
parentheses
unary + / -
```

There are no function calls and no PHP `eval()`. Names resolve only from the incoming bound data, with `value` meaning the current pipeline value.

### Strings can be templates

```php
$state->bind(
    'main',
    'player',
    'label',
    'auto',
    [
        'as' => 'string',
        'template' => '{player.name}: {player.score}',
    ],
);
```

A template may use `{value}` or named/dotted fields from the original bound value.

### Coercions can form a pipeline

Algebra can feed a string without teaching the database or Page about the conversion:

```php
$state->bind(
    'main',
    'cart-row',
    'total-label',
    'auto',
    [
        'coerce' => [
            [
                'as' => 'algebra',
                'expression' => 'price * quantity',
            ],
            [
                'as' => 'string',
                'template' => 'Total: {value}',
            ],
        ],
    ],
);
```

The flow is:

```text
source row
   -> algebra
      -> string template
         -> Boundary import
            -> Bag sign / set / commit
```

This coercion contract is implemented in `jx/BindingCoercion.php`. It is intentionally data-shaped so PASL can later compile algebra coercions instead of changing the public Bag sentence.

### Persistence changes flow through Bags

SQL should never repaint the Page directly and should never bypass Bag law.

```text
Database changes
      -> source adapter notices
      -> resolve Bag binding
      -> apply declared coercion
      -> Boundary import
      -> Bag sign / set / commit
      -> dependent Controls / Collectors / Style resolve
      -> Page patch
```

This lets database state affect both content and appearance. A theme Bag can receive values such as `#69F0AE`, `background-image`, or `background-opacity`, and the existing Style/Collector contract can repaint without reconstructing the entire Page.

### Listening strategy is adapter-specific

`mode = auto` describes the desired behavior, not one database vendor's mechanism. The SQL adapter should choose the strongest legal strategy available:

```text
PostgreSQL
    -> LISTEN / NOTIFY when configured
    -> otherwise revision/change-token polling

MySQL / MariaDB
    -> revision/change-token polling
    -> optional future binlog/change-stream adapter

SQLite3
    -> local data-version/revision checks

other SQL adapters
    -> safe polling or a dedicated native notification mechanism
```

The Bag and Page should not change just because the Book changes database engines.

### Binding snapshots never contain live credentials

A Bag binding may snapshot source names, listener/query names, modes, destination nodes, coercion instructions, and revision tokens. It must not snapshot PDO handles, database passwords, certificates, private keys, or other live connection material.

Page Binding snapshots Page-to-Bag use, not database credentials.

That keeps the same relationship portable across browser hosting, Apache deployment, native hosts, Book restarts, and restored sessions.

The same ownership model prepares JX for NoSQL:

```text
SQL -----\
          \
NoSQL ----> Bag.bind -> Bag -> Page Binding -> Page
          /
Channel -/
```

**Implementation status:** the SQL object, canonical Bag source-binding metadata, XI Bag persistence adapter, Page-to-Bag Binding records, and binding coercion boundary are present. The host-side executor that actively resolves named SQL sources and refreshes bound Bags is the next integration step and still needs execution tests against PDO SQLite/MySQL/PostgreSQL drivers.

## Next: NoSQL as its own object

NoSQL should be a sibling, not a fake SQL dialect:

```text
Book
|- SQL
|- NoSQL
|- Bags
`- Pages
```

The two persistence objects can share JX laws around secrets, host boundaries, result import, Bag synchronization, secure transport, source binding, and coercion while keeping their native data models intact.
