<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/jx.php';
}

namespace jx {

use PDO;
use PDOStatement;
use Throwable;

/**
 * Redacted database secret. Prefer environment-backed secrets to credentials
 * written directly into source or DSNs.
 */
final class SQLSecret
{
    private function __construct(private string $value) {}

    public static function fromEnv(string $name): self
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            throw new JxException("SQL secret environment variable is missing: {$name}", 'sql.secret', true);
        }
        return new self($value);
    }

    public function reveal(): string
    {
        return $this->value;
    }

    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }
}

/**
 * First-class JX SQL boundary.
 *
 * SQL owns connection security, prepared queries, transactions, and result
 * import. Bags remain the JX mutable-memory boundary.
 */
final class SQL
{
    private ?PDO $pdo;

    private function __construct(
        private string $name,
        private string $driver,
        PDO $pdo,
        private bool $transportVerified,
    ) {
        $this->pdo = $pdo;
    }

    public static function secretFromEnv(string $name): SQLSecret
    {
        return SQLSecret::fromEnv($name);
    }

    /**
     * Secure MySQL / MariaDB connection.
     *
     * Remote connections require a verified CA by default. Local loopback or
     * unix-socket connections may run without TLS because they do not cross a
     * network boundary. Set allow_insecure=true only as an explicit escape.
     *
     * @param array<string,mixed> $with
     */
    public static function mysql(string $named, array $with): self
    {
        self::needDriver('mysql');

        $database = self::needString($with, 'database');
        $user = self::needString($with, 'user');
        $password = self::secretValue($with['password'] ?? null);
        $socket = self::optionalString($with, 'socket');
        $host = self::optionalString($with, 'host') ?? '127.0.0.1';
        $port = max(1, min(65535, (int)($with['port'] ?? 3306)));
        $allowInsecure = !empty($with['allow_insecure']);
        $tls = is_array($with['tls'] ?? null) ? $with['tls'] : [];

        $local = $socket !== null || self::isLocalHost($host);
        $ca = self::optionalArrayString($tls, 'ca');
        if (!$local && !$allowInsecure && $ca === null) {
            throw new JxException(
                'Remote MySQL requires tls.ca with server verification',
                'sql.mysql.tls',
                true,
                ['connection' => $named, 'host' => $host],
            );
        }

        $dsn = $socket !== null
            ? 'mysql:unix_socket=' . self::dsnPart($socket) . ';dbname=' . self::dsnPart($database) . ';charset=utf8mb4'
            : 'mysql:host=' . self::dsnPart($host) . ';port=' . $port . ';dbname=' . self::dsnPart($database) . ';charset=utf8mb4';

        $options = self::baseOptions();
        $options[PDO::ATTR_EMULATE_PREPARES] = false;
        self::putPdoOption($options, 'PDO::MYSQL_ATTR_MULTI_STATEMENTS', false);
        self::putPdoOption($options, 'PDO::MYSQL_ATTR_LOCAL_INFILE', false);

        if ($ca !== null) {
            self::putPdoOption($options, 'PDO::MYSQL_ATTR_SSL_CA', $ca);
            self::putPdoOption($options, 'PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT', true);
            if (($cert = self::optionalArrayString($tls, 'cert')) !== null) {
                self::putPdoOption($options, 'PDO::MYSQL_ATTR_SSL_CERT', $cert);
            }
            if (($key = self::optionalArrayString($tls, 'key')) !== null) {
                self::putPdoOption($options, 'PDO::MYSQL_ATTR_SSL_KEY', $key);
            }
            if (($cipher = self::optionalArrayString($tls, 'cipher')) !== null) {
                self::putPdoOption($options, 'PDO::MYSQL_ATTR_SSL_CIPHER', $cipher);
            }
        }

        $pdo = self::connect($dsn, $user, $password, $options, $named, 'mysql');
        return new self($named, 'mysql', $pdo, $local || $ca !== null);
    }

    /**
     * Secure PostgreSQL-family connection.
     *
     * Remote connections default to sslmode=verify-full. verify-ca is also
     * accepted. We reject weaker remote modes unless allow_insecure=true.
     *
     * @param array<string,mixed> $with
     */
    public static function postgresql(string $named, array $with): self
    {
        self::needDriver('pgsql');

        $database = self::needString($with, 'database');
        $user = self::needString($with, 'user');
        $password = self::secretValue($with['password'] ?? null);
        $host = self::optionalString($with, 'host') ?? '127.0.0.1';
        $port = max(1, min(65535, (int)($with['port'] ?? 5432)));
        $allowInsecure = !empty($with['allow_insecure']);
        $local = self::isLocalHost($host) || str_starts_with($host, '/');
        $sslmode = strtolower((string)($with['sslmode'] ?? ($local ? 'prefer' : 'verify-full')));

        if (!$local && !$allowInsecure && !in_array($sslmode, ['verify-ca', 'verify-full'], true)) {
            throw new JxException(
                'Remote PostgreSQL requires sslmode=verify-ca or verify-full',
                'sql.postgresql.tls',
                true,
                ['connection' => $named, 'host' => $host, 'sslmode' => $sslmode],
            );
        }

        $dsn = 'pgsql:host=' . self::dsnPart($host)
            . ';port=' . $port
            . ';dbname=' . self::dsnPart($database)
            . ';sslmode=' . self::dsnPart($sslmode);

        if (($root = self::optionalString($with, 'sslrootcert')) !== null) {
            $dsn .= ';sslrootcert=' . self::dsnPart($root);
        }
        if (($cert = self::optionalString($with, 'sslcert')) !== null) {
            $dsn .= ';sslcert=' . self::dsnPart($cert);
        }
        if (($key = self::optionalString($with, 'sslkey')) !== null) {
            $dsn .= ';sslkey=' . self::dsnPart($key);
        }
        if (isset($with['connect_timeout'])) {
            $dsn .= ';connect_timeout=' . max(1, (int)$with['connect_timeout']);
        }

        $pdo = self::connect($dsn, $user, $password, self::baseOptions(), $named, 'pgsql');
        $verified = $local || in_array($sslmode, ['verify-ca', 'verify-full'], true);
        return new self($named, 'pgsql', $pdo, $verified);
    }

    /** Alias for the PostgreSQL-family adapter. */
    public static function postgres(string $named, array $with): self
    {
        return self::postgresql($named, $with);
    }

    /**
     * Secure SQLite3 connection.
     *
     * SQLite has no network TLS boundary. Security is the database file,
     * directory permissions, process identity, and host filesystem. Disk paths
     * must be absolute. New/existing files default to mode 0600 when possible.
     *
     * @param array<string,mixed> $with
     */
    public static function sqlite(string $named, string $path, array $with = []): self
    {
        self::needDriver('sqlite');

        $memory = $path === ':memory:';
        if (!$memory && !self::isAbsolutePath($path)) {
            throw new JxException('SQLite path must be absolute or :memory:', 'sql.sqlite.path', true);
        }
        if (str_contains($path, "\0")) {
            throw new JxException('SQLite path contains NUL', 'sql.sqlite.path', true);
        }

        if (!$memory) {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                throw new JxException('SQLite directory does not exist', 'sql.sqlite.path', true, ['directory' => $dir]);
            }
            if (!is_writable($dir) && !is_file($path)) {
                throw new JxException('SQLite directory is not writable', 'sql.sqlite.path', true, ['directory' => $dir]);
            }
        }

        $dsn = $memory ? 'sqlite::memory:' : 'sqlite:' . $path;
        $pdo = self::connect($dsn, null, null, self::baseOptions(), $named, 'sqlite');

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = ' . max(0, (int)($with['busy_timeout_ms'] ?? 5000)));
        if (!$memory && ($with['journal_mode'] ?? 'WAL') === 'WAL') {
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        if (!$memory) {
            $mode = (int)($with['permissions'] ?? 0600);
            if (is_file($path)) @chmod($path, $mode);
        }

        return new self($named, 'sqlite', $pdo, true);
    }

    /** Alias using the explicit database version in the public name. */
    public static function sqlite3(string $named, string $path, array $with = []): self
    {
        return self::sqlite($named, $path, $with);
    }

    /**
     * Advanced adapter for any installed PDO driver.
     *
     * JX still enforces prepared parameter binding and exception behavior, but
     * connection encryption/authentication is driver-specific. Pass
     * transport_verified=true only after that driver's secure options have
     * been configured.
     *
     * @param array<int,mixed> $options
     */
    public static function pdo(
        string $named,
        string $dsn,
        ?string $user = null,
        string|SQLSecret|null $password = null,
        array $options = [],
        bool $transport_verified = false,
    ): self {
        $driver = strtolower((string)strtok($dsn, ':'));
        if ($driver === '') {
            throw new JxException('PDO DSN has no driver', 'sql.pdo', true);
        }
        self::needDriver($driver);

        $pdo = self::connect(
            $dsn,
            $user,
            self::secretValue($password),
            array_replace(self::baseOptions(), $options),
            $named,
            $driver,
        );
        return new self($named, $driver, $pdo, $transport_verified);
    }

    public function name(): string { return $this->name; }
    public function driver(): string { return $this->driver; }
    public function transportVerified(): bool { return $this->transportVerified; }
    public function inTransaction(): bool { return $this->db()->inTransaction(); }

    /** @return list<array<string,mixed>> */
    public function all(string $query, array $with = []): array
    {
        $rows = $this->statement($query, $with)->fetchAll(PDO::FETCH_ASSOC);
        return Boundary::import(is_array($rows) ? $rows : []);
    }

    /** @return array<string,mixed>|null */
    public function one(string $query, array $with = []): ?array
    {
        $row = $this->statement($query, $with)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : Boundary::import($row);
    }

    public function value(string $query, array $with = []): mixed
    {
        $value = $this->statement($query, $with)->fetchColumn();
        return $value === false ? null : Boundary::import($value);
    }

    /** Execute a prepared INSERT/UPDATE/DELETE or other statement. */
    public function execute(string $query, array $with = []): int
    {
        return $this->statement($query, $with)->rowCount();
    }

    /**
     * Run work atomically. Nested calls join the existing transaction.
     */
    public function transaction(callable $doing): mixed
    {
        $pdo = $this->db();
        if ($pdo->inTransaction()) {
            return $doing($this);
        }

        $pdo->beginTransaction();
        try {
            $result = $doing($this);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Query rows directly into a Bag through the normal JX write law.
     */
    public function into(Bag $into, string $at, string $query, array $with = []): int
    {
        $rows = $this->all($query, $with);
        $ref = $into->sign($at, 0, true);
        try {
            $into->set($rows, $at)->commit($ref);
        } finally {
            $into->unsign($ref);
        }
        return count($rows);
    }

    /**
     * Safely quote a dynamic identifier after strict validation.
     * Values must still use prepared parameters; identifiers cannot be bound.
     */
    public function identifier(string $name): string
    {
        $parts = explode('.', $name);
        if ($parts === []) throw new JxException('Empty SQL identifier', 'sql.identifier', true);

        $quote = $this->driver === 'mysql' ? '`' : '"';
        foreach ($parts as &$part) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new JxException('Unsafe SQL identifier', 'sql.identifier', true, ['identifier' => $name]);
            }
            $part = $quote . $part . $quote;
        }
        unset($part);
        return implode('.', $parts);
    }

    public function close(): void
    {
        $this->pdo = null;
    }

    private function statement(string $query, array $with): PDOStatement
    {
        if (trim($query) === '') {
            throw new JxException('SQL query is empty', 'sql.query', true);
        }

        $stmt = $this->db()->prepare($query);
        foreach ($with as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : (str_starts_with((string)$key, ':') ? (string)$key : ':' . $key);
            [$bound, $type] = self::bindable($value);
            $stmt->bindValue($parameter, $bound, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return array{0:mixed,1:int} */
    private static function bindable(mixed $value): array
    {
        $value = Boundary::export($value);
        if ($value === null) return [null, PDO::PARAM_NULL];
        if (is_bool($value)) return [$value, PDO::PARAM_BOOL];
        if (is_int($value)) return [$value, PDO::PARAM_INT];
        if (is_float($value)) return [(string)$value, PDO::PARAM_STR];
        if (is_string($value)) return [$value, PDO::PARAM_STR];
        throw new JxException('SQL parameters must be scalar/null', 'sql.parameter', true, ['type' => get_debug_type($value)]);
    }

    private function db(): PDO
    {
        if (!$this->pdo instanceof PDO) {
            throw new JxException('SQL connection is closed', 'sql.connection', true, ['connection' => $this->name]);
        }
        return $this->pdo;
    }

    /** @return array<int,mixed> */
    private static function baseOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
    }

    /** @param array<int,mixed> $options */
    private static function putPdoOption(array &$options, string $constant, mixed $value): void
    {
        if (defined($constant)) {
            $key = constant($constant);
            if (is_int($key)) $options[$key] = $value;
        }
    }

    /** @param array<string,mixed> $with */
    private static function needString(array $with, string $key): string
    {
        $value = self::optionalString($with, $key);
        if ($value === null) throw new JxException("SQL option is required: {$key}", 'sql.config', true);
        return $value;
    }

    /** @param array<string,mixed> $with */
    private static function optionalString(array $with, string $key): ?string
    {
        if (!array_key_exists($key, $with) || $with[$key] === null) return null;
        $value = trim((string)$with[$key]);
        return $value === '' ? null : $value;
    }

    /** @param array<string,mixed> $with */
    private static function optionalArrayString(array $with, string $key): ?string
    {
        if (!array_key_exists($key, $with) || $with[$key] === null) return null;
        $value = trim((string)$with[$key]);
        return $value === '' ? null : $value;
    }

    private static function secretValue(mixed $value): ?string
    {
        if ($value instanceof SQLSecret) return $value->reveal();
        if ($value === null) return null;
        if (!is_string($value)) throw new JxException('SQL password must be string, secret, or null', 'sql.secret', true);
        return $value;
    }

    private static function needDriver(string $driver): void
    {
        if (!in_array($driver, PDO::getAvailableDrivers(), true)) {
            throw new JxException('PDO driver is unavailable: ' . $driver, 'sql.driver', true, ['driver' => $driver]);
        }
    }

    private static function connect(
        string $dsn,
        ?string $user,
        ?string $password,
        array $options,
        string $named,
        string $driver,
    ): PDO {
        try {
            return new PDO($dsn, $user, $password, $options);
        } catch (Throwable $e) {
            throw new JxException(
                'SQL connection failed',
                'sql.connection',
                true,
                ['connection' => $named, 'driver' => $driver, 'reason' => $e->getMessage()],
            );
        }
    }

    private static function isLocalHost(string $host): bool
    {
        return in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
    }

    private static function dsnPart(string $value): string
    {
        if ($value === '' || preg_match('/[;\r\n\0]/', $value)) {
            throw new JxException('Unsafe value in SQL DSN component', 'sql.dsn', true);
        }
        return $value;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}

}
