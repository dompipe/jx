<?php declare(strict_types=1);

namespace jx;

use InvalidArgumentException;
use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

/**
 * Fast SQLite resolver for JX/Anemone-style knowledge stores.
 *
 * Design goals:
 * - native SQLite3 handle instead of the portable PDO SQL path;
 * - read-only by default so a live knowledge builder may remain the sole writer;
 * - cached prepared statements and cached table metadata;
 * - match one value against any visible column in a row;
 * - exact -> prefix -> contains resolution, stopping as soon as enough evidence exists;
 * - treat a directory of rank_*.sqlite3 / edge_*.sqlite3 shards as one logical store;
 * - return ordinary keyed JX rows; no ORM/object inflation in the hot path.
 */
final class SQLiteResolver
{
    public const EXACT = 'exact';
    public const PREFIX = 'prefix';
    public const CONTAINS = 'contains';

    private SQLite3 $db;
    /** @var array<string,SQLite3Stmt> */
    private array $statements = [];
    /** @var array<string,list<string>> */
    private array $columns = [];
    /** @var null|list<string> */
    private ?array $tablesCache = null;

    public function __construct(
        public readonly string $path,
        public readonly bool $readOnly = true,
        int $busyTimeoutMs = 250,
    ) {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Invalid SQLite resolver path');
        }
        if (!is_file($path)) {
            throw new RuntimeException("SQLite resolver file does not exist: {$path}");
        }
        if (!class_exists(SQLite3::class)) {
            throw new RuntimeException('PHP SQLite3 extension is required for SQLiteResolver');
        }

        $flags = $readOnly ? SQLITE3_OPEN_READONLY : (SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $this->db = new SQLite3($path, $flags);
        $this->db->enableExceptions(true);
        $this->db->busyTimeout(max(0, $busyTimeoutMs));

        // These are connection-local and safe on a read-only handle.
        $this->db->exec('PRAGMA query_only = ' . ($readOnly ? 'ON' : 'OFF'));
        $this->db->exec('PRAGMA temp_store = MEMORY');
        $this->db->exec('PRAGMA cache_size = -8192'); // 8 MiB page cache per open shard.
    }

    /** @return list<string> */
    public function tables(): array
    {
        if ($this->tablesCache !== null) return $this->tablesCache;

        $rows = $this->rows(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );
        $out = [];
        foreach ($rows as $row) {
            $name = (string)($row['name'] ?? '');
            // FTS shadow tables are implementation detail; the virtual table itself remains searchable.
            if ($name === '' || preg_match('/_(?:data|idx|docsize|config|content|segments|segdir)$/', $name)) continue;
            $out[] = $name;
        }
        return $this->tablesCache = $out;
    }

    /** @return list<string> */
    public function columns(string $table): array
    {
        $table = self::identifier($table);
        if (isset($this->columns[$table])) return $this->columns[$table];

        $result = $this->db->query('PRAGMA table_xinfo(' . self::quoteIdentifier($table) . ')');
        if (!$result instanceof SQLite3Result) return $this->columns[$table] = [];

        $out = [];
        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            // hidden=1 is an FTS/internal column. hidden=2/3 are generated columns and are still readable,
            // but do not help generic row resolution enough to justify duplicate work.
            if ((int)($row['hidden'] ?? 0) !== 0) continue;
            $name = (string)($row['name'] ?? '');
            if ($name !== '') $out[] = $name;
        }
        $result->finalize();
        return $this->columns[$table] = $out;
    }

    /**
     * Match one value against ANY visible column of each row.
     *
     * Each hit carries resolver metadata:
     *   _jx_score          300 exact, 200 prefix, 100 contains
     *   _jx_match_columns  columns in that row that actually matched
     *
     * @return list<array<string,mixed>>
     */
    public function matchAny(
        string $table,
        string|int|float $needle,
        string $mode = self::EXACT,
        int $limit = 32,
    ): array {
        $table = self::identifier($table);
        $mode = self::mode($mode);
        $limit = max(1, min(4096, $limit));
        $columns = $this->columns($table);
        if ($columns === []) return [];

        $searchable = array_map(static fn(string $c): string => self::quoteIdentifier($c), $columns);
        $tests = [];
        foreach ($searchable as $column) {
            $expr = 'CAST(' . $column . ' AS TEXT)';
            $tests[] = match ($mode) {
                self::EXACT => $expr . ' = :jx_q COLLATE NOCASE',
                self::PREFIX, self::CONTAINS => $expr . " LIKE :jx_q ESCAPE '\\' COLLATE NOCASE",
            };
        }

        $sql = 'SELECT * FROM ' . self::quoteIdentifier($table)
            . ' WHERE (' . implode(' OR ', $tests) . ') LIMIT :jx_limit';

        $stmt = $this->prepareCached($sql);
        $stmt->reset();
        $boundNeedle = self::pattern((string)$needle, $mode);
        $stmt->bindValue(':jx_q', $boundNeedle, SQLITE3_TEXT);
        $stmt->bindValue(':jx_limit', $limit, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if (!$result instanceof SQLite3Result) return [];

        $score = match ($mode) {
            self::EXACT => 300,
            self::PREFIX => 200,
            self::CONTAINS => 100,
        };
        $out = [];
        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            $matched = [];
            foreach ($columns as $column) {
                if (self::valueMatches($row[$column] ?? null, (string)$needle, $mode)) $matched[] = $column;
            }
            $row['_jx_score'] = $score + min(20, count($matched));
            $row['_jx_match_columns'] = $matched;
            $out[] = $row;
        }
        $result->finalize();
        return $out;
    }

    /**
     * AI-oriented resolver: exact first, then prefix, then contains only if needed.
     * This keeps the common taxonomy/name lookup on the cheapest route.
     *
     * @return list<array<string,mixed>>
     */
    public function resolveTable(string $table, string|int|float $needle, int $limit = 32): array
    {
        $limit = max(1, min(4096, $limit));
        $seen = [];
        $out = [];

        foreach ([self::EXACT, self::PREFIX, self::CONTAINS] as $mode) {
            $remaining = $limit - count($out);
            if ($remaining <= 0) break;
            foreach ($this->matchAny($table, $needle, $mode, $remaining) as $row) {
                $fingerprint = self::fingerprint($row);
                if (isset($seen[$fingerprint])) continue;
                $seen[$fingerprint] = true;
                $out[] = $row;
                if (count($out) >= $limit) break 2;
            }
        }

        usort($out, static fn(array $a, array $b): int => (($b['_jx_score'] ?? 0) <=> ($a['_jx_score'] ?? 0)));
        return $out;
    }

    /**
     * Resolve through every user table in this SQLite file, stopping when limit is satisfied.
     *
     * @return list<array<string,mixed>>
     */
    public function resolve(string|int|float $needle, int $limit = 32): array
    {
        $limit = max(1, min(4096, $limit));
        $out = [];
        foreach ($this->tables() as $table) {
            $remaining = $limit - count($out);
            if ($remaining <= 0) break;
            foreach ($this->resolveTable($table, $needle, $remaining) as $row) {
                $row['_jx_table'] = $table;
                $row['_jx_source'] = $this->path;
                $out[] = $row;
                if (count($out) >= $limit) break 2;
            }
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function rows(string $sql, array $params = []): array
    {
        $stmt = $this->prepareCached($sql);
        $stmt->reset();
        self::bind($stmt, $params);
        $result = $stmt->execute();
        if (!$result instanceof SQLite3Result) return [];
        $out = [];
        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) $out[] = $row;
        $result->finalize();
        return $out;
    }

    public function close(): void
    {
        foreach ($this->statements as $stmt) @$stmt->close();
        $this->statements = [];
        $this->db->close();
    }

    public function __destruct()
    {
        try { $this->close(); } catch (\Throwable) {}
    }

    /**
     * Treat Anemone's mmap taxonomy shard directory as one logical resolver.
     * Rank shards are searched before edge shards because concept/name hits normally
     * provide better AI anchors than relation rows. The catalog is searched last.
     *
     * @return list<array<string,mixed>>
     */
    public static function resolveShards(
        string $directory,
        string|int|float $needle,
        int $limit = 48,
        int $busyTimeoutMs = 100,
    ): array {
        if (!is_dir($directory)) {
            throw new RuntimeException("SQLite shard directory does not exist: {$directory}");
        }
        $limit = max(1, min(4096, $limit));

        $rank = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rank_*.sqlite3') ?: [];
        $edge = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'edge_*.sqlite3') ?: [];
        sort($rank, SORT_NATURAL);
        sort($edge, SORT_NATURAL);
        $catalog = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'catalog.sqlite3';
        $files = array_merge($rank, $edge, is_file($catalog) ? [$catalog] : []);

        $out = [];
        $seen = [];
        foreach ($files as $file) {
            if (count($out) >= $limit) break;
            $resolver = null;
            try {
                $resolver = new self($file, true, $busyTimeoutMs);
                $remaining = $limit - count($out);
                foreach ($resolver->resolve($needle, $remaining) as $row) {
                    $row['_jx_shard'] = basename($file);
                    $fingerprint = self::fingerprint($row);
                    if (isset($seen[$fingerprint])) continue;
                    $seen[$fingerprint] = true;
                    $out[] = $row;
                    if (count($out) >= $limit) break;
                }
            } catch (\Throwable $e) {
                // A live builder can make one shard briefly unavailable. AI resolution should
                // continue through the remaining completed shards instead of failing globally.
                continue;
            } finally {
                if ($resolver instanceof self) $resolver->close();
            }
        }

        usort($out, static fn(array $a, array $b): int => (($b['_jx_score'] ?? 0) <=> ($a['_jx_score'] ?? 0)));
        return array_slice($out, 0, $limit);
    }

    private function prepareCached(string $sql): SQLite3Stmt
    {
        if (isset($this->statements[$sql])) return $this->statements[$sql];
        $stmt = $this->db->prepare($sql);
        if (!$stmt instanceof SQLite3Stmt) throw new RuntimeException('SQLite prepare failed');
        return $this->statements[$sql] = $stmt;
    }

    private static function bind(SQLite3Stmt $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : (str_starts_with((string)$key, ':') ? (string)$key : ':' . $key);
            if ($value === null) $type = SQLITE3_NULL;
            elseif (is_int($value) || is_bool($value)) $type = SQLITE3_INTEGER;
            elseif (is_float($value)) $type = SQLITE3_FLOAT;
            elseif (is_string($value)) $type = SQLITE3_TEXT;
            else throw new InvalidArgumentException('SQLite resolver parameters must be scalar/null');
            $stmt->bindValue($parameter, is_bool($value) ? (int)$value : $value, $type);
        }
    }

    private static function mode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, [self::EXACT, self::PREFIX, self::CONTAINS], true)) {
            throw new InvalidArgumentException("Unknown SQLite resolver match mode: {$mode}");
        }
        return $mode;
    }

    private static function pattern(string $needle, string $mode): string
    {
        if ($mode === self::EXACT) return $needle;
        $escaped = strtr($needle, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
        return $mode === self::PREFIX ? $escaped . '%' : '%' . $escaped . '%';
    }

    private static function valueMatches(mixed $value, string $needle, string $mode): bool
    {
        if ($value === null) return false;
        $value = (string)$value;
        return match ($mode) {
            self::EXACT => strcasecmp($value, $needle) === 0,
            self::PREFIX => strncasecmp($value, $needle, strlen($needle)) === 0,
            self::CONTAINS => stripos($value, $needle) !== false,
        };
    }

    private static function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Unsafe SQLite identifier: {$name}");
        }
        return $name;
    }

    private static function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    private static function fingerprint(array $row): string
    {
        unset($row['_jx_score'], $row['_jx_match_columns'], $row['_jx_source'], $row['_jx_table'], $row['_jx_shard']);
        return hash('xxh3', serialize($row));
    }
}
