# JX v0.1 SQL Contract

SQL is a first-class JX object. It is not a replacement for Bags and it is not an array-shaped helper hidden inside a Page.

The division is:

```text
SQL  = durable relational data boundary
Bag  = JX-owned mutable memory boundary
Book = application ownership boundary
```

A useful rule is:

> **Query through SQL. Work through Bags. Persist deliberately.**

The executable implementation is `jx/SQL.php`.

## 1. One SQL object, several database families

The guaranteed v0.1 adapters are:

```text
SQL::mysql()       MySQL / MariaDB through PDO_MYSQL
SQL::postgresql()  PostgreSQL-family through PDO_PGSQL
SQL::postgres()    alias of postgresql()
SQL::sqlite()      SQLite3 through PDO_SQLITE
SQL::sqlite3()     alias of sqlite()
SQL::pdo()         advanced route for another installed PDO driver
```

The public query surface stays the same even when the driver changes.

PDO supplies a consistent connection/query interface. JX does not pretend that MySQL, PostgreSQL, SQLite, SQL Server, Oracle, Firebird, DB2, or other SQL dialects are identical. Driver-specific SQL remains driver-specific.

## 2. Credentials are not DSN prose

Prefer credentials from the environment or another secret provider rather than embedding passwords in source code or putting them into a DSN.

```php
use jx\SQL;

$password = SQL::secretFromEnv('JX_DB_PASSWORD');
```

`SQLSecret` redacts itself from ordinary debug output.

The database user should be an application-specific account with only the privileges the Book actually needs.

## 3. Secure MySQL / MariaDB

A remote MySQL connection requires a CA by default:

```php
$db = SQL::mysql('main', [
    'host' => 'db.example.com',
    'port' => 3306,
    'database' => 'jx_app',
    'user' => 'jx_app',
    'password' => SQL::secretFromEnv('JX_DB_PASSWORD'),
    'tls' => [
        'ca' => '/etc/jx/db/mysql-ca.pem',
    ],
]);
```

JX sets native prepares, disables MySQL multi-statements, disables local infile when the driver exposes that option, and enables server-certificate verification with the configured CA.

Client certificates may also be supplied:

```php
'dtls' => [] // not a JX property; shown only to emphasize that the real key is `tls`
```

Use the actual contract:

```php
'tls' => [
    'ca' => '/etc/jx/db/ca.pem',
    'cert' => '/etc/jx/db/client.pem',
    'key' => '/etc/jx/db/client-key.pem',
]
```

Loopback and Unix-socket connections are considered local transport boundaries. A remote unverified connection requires the explicit `allow_insecure` escape and should not be used for production.

## 4. Secure PostgreSQL

Remote PostgreSQL defaults to certificate-verifying TLS:

```php
$db = SQL::postgresql('main', [
    'host' => 'pg.example.com',
    'port' => 5432,
    'database' => 'jx_app',
    'user' => 'jx_app',
    'password' => SQL::secretFromEnv('JX_DB_PASSWORD'),
    'sslmode' => 'verify-full',
    'sslrootcert' => '/etc/jx/db/postgres-ca.pem',
]);
```

For a remote connection, JX accepts `verify-full` or `verify-ca` by default and rejects weaker modes unless `allow_insecure` is explicitly enabled.

`verify-full` is the preferred production mode because it verifies the certificate chain and the server identity expected by the connection.

PostgreSQL-compatible services that speak the same protocol can use this adapter when their PDO/libpq connection requirements are compatible.

## 5. Secure SQLite3

SQLite is different because there is no remote network connection to encrypt. The security boundary is the database file and the process that can access it.

```php
$db = SQL::sqlite3(
    'local',
    '/var/lib/jx/books/calculator/app.sqlite3',
);
```

Disk paths must be absolute. JX enables foreign keys, installs a busy timeout, defaults disk databases to WAL mode, and attempts to keep the database file at mode `0600` unless another permission mode is explicitly requested.

Do not put a private SQLite database under the public web document root.

If encryption at rest is required, use filesystem/disk encryption or a SQLite-compatible encrypted database technology. Standard PDO_SQLITE itself is a local SQLite3 driver, not a TLS or transparent-encryption system.

## 6. Prepared values are the normal form

Values belong in parameters:

```php
$user = $db->one(
    'SELECT id, name FROM users WHERE id = :id',
    ['id' => $userId],
);
```

Multiple rows:

```php
$rows = $db->all(
    'SELECT id, name FROM users WHERE status = :status',
    ['status' => 'active'],
);
```

One scalar value:

```php
$count = $db->value(
    'SELECT COUNT(*) FROM users WHERE status = :status',
    ['status' => 'active'],
);
```

Writes:

```php
$changed = $db->execute(
    'UPDATE users SET name = :name WHERE id = :id',
    ['name' => $name, 'id' => $id],
);
```

The query text describes structure. Parameters carry values.

## 7. Identifiers are not values

SQL parameters cannot represent table names or column names. Dynamic identifiers therefore pass through a strict identifier validator:

```php
$table = $db->identifier($requestedTable);
$rows = $db->all("SELECT id, name FROM {$table} WHERE active = :active", [
    'active' => 1,
]);
```

`identifier()` accepts only conservative identifier components and quotes them for the current database family.

Prefer fixed schema names whenever possible. Dynamic identifiers are an exception, not a reason to concatenate arbitrary user input into SQL.

## 8. Transactions are first-class

```php
$db->transaction(function (SQL $db) use ($from, $to, $amount) {
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

If the callback throws, JX rolls back the transaction. If it completes, JX commits it.

Nested SQL work joins an already active transaction instead of silently opening an unrelated transaction.

## 9. SQL can fill a Bag without bypassing Bag law

```php
$state = jx\Bag::underwrite(16_384);

$count = $db->into(
    $state,
    'active-users',
    'SELECT id, name FROM users WHERE active = :active',
    ['active' => 1],
);
```

The SQL object does not write directly into Bag internals. It performs the normal JX sequence:

```text
query
  -> import rows
     -> sign Bag node
        -> set
           -> commit
```

That keeps external persistence from becoming a second mutation law.

## 10. Generic PDO-backed databases

PHP exposes PDO drivers for several database systems beyond the three guaranteed adapters. JX provides an advanced route:

```php
$db = SQL::pdo(
    'legacy',
    $dsn,
    $user,
    SQL::secretFromEnv('JX_DB_PASSWORD'),
    $driverOptions,
    transport_verified: true,
);
```

The common JX guarantees still apply to query parameters, exceptions, transactions, result import, and Bag writes.

Transport authentication/encryption for an arbitrary PDO driver is driver-specific. A dedicated JX adapter should be added when a database family becomes important enough to deserve a guaranteed secure connection policy.

Likely adapter families include:

```text
Microsoft SQL Server / Azure SQL
Oracle
Firebird
DB2 / ODBC
CUBRID
Informix
other PDO-backed engines
```

## 11. SQL belongs to the Book

The coherent ownership model is:

```text
Book
|- Bags
|- Pages
|- SQL connections
|- libraries
|- Controls / Collectors
|- Style / Layout
`- PASL / PASM
```

A Book should eventually name SQL objects just as it names Bags:

```text
Book calculator,
    with Bag state,
    with SQL main,
    with Page home,
done.
```

The SQL object owns its connection lifetime. The Book owns whether that SQL object belongs to the application.

## 12. SQL is not serialized into the browser

A browser Page may request data through the JX host, but database credentials and live PDO handles never cross the host boundary.

```text
Browser
   |
JX host protocol
   |
Book / Page
   |
SQL object
   |
Database
```

The browser receives data-shaped results or Bag changes, not database credentials.

## 13. Apache and SQL stay separate

Apache should not connect to the application database merely because it proxies the Book.

```text
Internet
  -> Apache
     -> JX host
        -> SQL object
           -> MySQL / PostgreSQL / SQLite / adapter
```

Apache owns HTTP/TLS. SQL owns database security. JX owns application semantics between them.

## 14. Next: NoSQL

NoSQL should become its own sibling object rather than being squeezed into SQL terminology:

```text
Book
|- SQL
|- NoSQL
`- Bags
```

The two persistence families can share JX principles such as secrets, secure transport, parameterized operations, transactions where supported, result import, and Bag synchronization without pretending a document store or key/value store speaks SQL.
