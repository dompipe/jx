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

## Next: NoSQL as its own object

NoSQL should be a sibling, not a fake SQL dialect:

```text
Book
|- SQL
|- NoSQL
|- Bags
`- Pages
```

The two persistence objects can share JX laws around secrets, host boundaries, result import, Bag synchronization, and secure transport while keeping their native data models intact.
