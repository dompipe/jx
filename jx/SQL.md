# JX v0.1 SQL Contract

SQL is a first-class JX object. It is not a replacement for Bags and it is not loose connection code hidden inside a Page.

```text
SQL  = durable relational-data boundary
Bag  = JX-owned mutable-memory boundary
Book = application ownership boundary
```

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

The public query surface stays the same when the driver changes. JX does not pretend the SQL dialects themselves are identical.

## 2. Credentials are not DSN prose

Prefer environment-backed or external secrets rather than embedding passwords in source or DSNs:

```php
use jx\SQL;

$password = SQL::secretFromEnv('JX_DB_PASSWORD');
```

`SQLSecret` redacts itself from ordinary debug output. Database accounts should also be scoped to the privileges the Book actually needs.

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

Client certificates can use the same `tls` record:

```php
'tls' => [
    'ca' => '/etc/jx/db/ca.pem',
    'cert' => '/etc/jx/db/client.pem',
    'key' => '/etc/jx/db/client-key.pem',
    'cipher' => 'approved-cipher-list',
]
```

JX requests native prepares, disables MySQL multi-statements, disables local infile when the driver exposes that option, and enables server-certificate verification with the configured CA.

Loopback and Unix-socket connections are treated as local transport boundaries. A remote unverified connection requires explicit `allow_insecure=true` and should not be used for production.

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

For remote connections JX accepts `verify-full` or `verify-ca` by default and rejects weaker modes unless `allow_insecure=true` is explicit.

PostgreSQL-compatible services can use this adapter when their PDO/libpq connection requirements are compatible.

## 5. Secure SQLite3

SQLite has no network TLS connection. Its security boundary is the database file, directory permissions, process identity, and host filesystem.

```php
$db = SQL::sqlite3(
    'local',
    '/var/lib/jx/books/calculator/app.sqlite3',
);
```

Disk paths must be absolute. JX enables foreign keys, installs a busy timeout, defaults disk databases to WAL mode, and attempts to keep the file at mode `0600` unless another permission mode is deliberately selected.

Private SQLite files should stay outside the public Apache/Nginx document root. If encryption at rest is required, use filesystem/disk encryption or a compatible encrypted SQLite technology; standard PDO_SQLITE itself is not a TLS or transparent-encryption system.

## 6. Prepared values are the normal form

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
$count = $db->value(
    'SELECT COUNT(*) FROM users WHERE status = :status',
    ['status' => 'active'],
);
```

```php
$changed = $db->execute(
    'UPDATE users SET name = :name WHERE id = :id',
    ['name' => $name, 'id' => $id],
);
```

The query text describes structure. Parameters carry values.

## 7. Identifiers are not values

SQL parameters cannot bind table or column names. Dynamic identifiers pass through a conservative validator and driver-aware quoting boundary:

```php
$table = $db->identifier($requestedTable);
$rows = $db->all("SELECT id, name FROM {$table} WHERE active = :active", [
    'active' => 1,
]);
```

Fixed schema names remain preferable. Arbitrary user input should never be concatenated into SQL structure.

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

If the callback throws, JX rolls back. If it completes, JX commits. Nested SQL work joins an already-active transaction.

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

The relationship remains:

```text
SQL query
   -> imported rows
      -> Bag sign
         -> set
            -> commit
```

External persistence therefore feeds JX state without creating a second mutation law.

## 10. Generic PDO-backed databases

For another installed PDO driver:

```php
$db = SQL::pdo(
    'other',
    $dsn,
    $user,
    SQL::secretFromEnv('JX_DB_PASSWORD'),
    $driverOptions,
    transport_verified: true,
);
```

The common JX query/transaction/Bag guarantees still apply. Transport encryption and authentication for an arbitrary driver remain driver-specific, so important database families should receive dedicated JX adapters instead of being declared secure by a generic DSN alone.

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
|- SQL connections
|- Pages
|- libraries
|- Controls / Collectors
|- Style / Layout
`- PASL / PASM
```

A Book should name SQL objects just as it names Bags:

```text
Book calculator,
    with Bag state,
    with SQL main,
    with Page home,
done.
```

The SQL object owns the live connection. The Book owns whether that connection belongs to the application.

## 12. SQL never crosses into the browser as a live connection

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

Credentials, keys, CA material, and PDO handles remain server-side. The browser receives data-shaped results and Bag changes.

## 13. Apache and SQL are separate boundaries

```text
Internet
  -> Apache
     -> JX host
        -> SQL object
           -> database
```

Apache owns HTTP/TLS. SQL owns database connection security. JX owns the application relationship between them.

## 14. NoSQL is a sibling object

NoSQL should not be squeezed into SQL terminology:

```text
Book
|- SQL
|- NoSQL
|- Bags
`- Pages
```

SQL and NoSQL can share JX principles for secrets, secure transport, result import, Bag synchronization, and transactions where supported while preserving their native data models.
