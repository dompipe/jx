<?php declare(strict_types=1);

namespace jx;

use InvalidArgumentException;

/**
 * JX language-wide alias registry.
 *
 * Public spellings are human-facing. Canonical operations are compiler-facing.
 * Aliases are resolved during parse/link and MUST NOT survive into PASL/PASM/
 * native execution. Provenance retains the source spelling separately.
 */
final class AliasDomain
{
    public const BAG = 'bag';
    public const BAG_HOT = 'bag.hot';
    public const BOOK = 'book';
    public const TASK = 'task';
    public const PAGE = 'page';
    public const DELIVERY = 'delivery';
    public const FUNCTION_ = 'function';
    public const METHOD = 'method';
    public const CONTROL = 'control';
    public const STYLE = 'style';
    public const EVENT = 'event';
    public const CHANNEL = 'channel';
    public const SQL = 'sql';
    public const CHART = 'chart';
    public const HOST = 'host';
    public const WINDOW = 'window';
    public const LIBRARY = 'library';
    public const PLUGIN = 'plugin';
    public const PASL = 'pasl';
    public const PASM = 'pasm';
}

final class AliasResolution
{
    public function __construct(
        public readonly string $domain,
        public readonly string $source,
        public readonly string $canonical,
        public readonly ?string $context = null,
    ) {}

    public function provenance(): array
    {
        return [
            'source_spelling' => $this->source,
            'alias_domain' => $this->domain,
            'canonical_op' => $this->canonical,
            'alias_context' => $this->context,
        ];
    }
}

final class JxAlias
{
    /** @var array<string,array<string,string>> */
    private static array $global = [];
    /** @var array<string,array<string,array<string,string>>> */
    private static array $contextual = [];
    private static bool $booted = false;

    public static function resolve(string $domain, string $spelling, ?string $context = null, bool $strict = true): AliasResolution
    {
        self::boot();
        $domain = strtolower(trim($domain));
        $source = trim($spelling);
        $key = self::key($source);
        $ctx = $context === null ? null : strtolower(trim($context));

        if ($ctx !== null && isset(self::$contextual[$domain][$ctx][$key])) {
            return new AliasResolution($domain, $source, self::$contextual[$domain][$ctx][$key], $ctx);
        }
        if (isset(self::$global[$domain][$key])) {
            return new AliasResolution($domain, $source, self::$global[$domain][$key], $ctx);
        }
        if (!$strict) {
            return new AliasResolution($domain, $source, strtoupper($source), $ctx);
        }
        throw new InvalidArgumentException("Unknown alias {$domain}:{$spelling}" . ($ctx ? " for {$ctx}" : ''));
    }

    public static function canonical(string $domain, string $spelling, ?string $context = null, bool $strict = true): string
    {
        return self::resolve($domain, $spelling, $context, $strict)->canonical;
    }

    public static function register(string $domain, string $canonical, array $aliases = [], ?string $context = null): void
    {
        self::boot(false);
        $domain = strtolower(trim($domain));
        $canonical = strtoupper(trim($canonical));
        if ($domain === '' || $canonical === '') {
            throw new InvalidArgumentException('Alias domain and canonical op are required');
        }
        $all = array_values(array_unique(array_merge([$canonical], $aliases)));
        $ctx = $context === null ? null : strtolower(trim($context));
        foreach ($all as $alias) {
            $key = self::key((string)$alias);
            if ($ctx === null) {
                self::insert(self::$global[$domain], $key, $canonical, $domain);
            } else {
                self::insert(self::$contextual[$domain][$ctx], $key, $canonical, $domain . ':' . $ctx);
            }
        }
    }

    public static function registerPlugin(string $plugin, string $canonical, array $aliases = []): void
    {
        self::register(AliasDomain::PLUGIN, $canonical, $aliases, strtolower($plugin));
    }

    public static function has(string $domain, string $spelling, ?string $context = null): bool
    {
        try {
            self::resolve($domain, $spelling, $context, true);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** @return array<string,string> */
    public static function aliases(string $domain, ?string $context = null): array
    {
        self::boot();
        $domain = strtolower($domain);
        if ($context === null) return self::$global[$domain] ?? [];
        return self::$contextual[$domain][strtolower($context)] ?? [];
    }

    /**
     * Canonicalizes static/class/global spellings. Static class forms are
     * case-sensitive so a runtime variable such as `bag` is never mistaken for
     * the class token `Bag`. Dynamic members are canonicalized separately after
     * runtime type inference.
     */
    public static function canonicalizeSurface(string $statement): string
    {
        self::boot();
        $s = $statement;

        $s = preg_replace_callback('/\bBook\.(\w+)\s*\(/', static function(array $m): string {
            $op = self::canonical(AliasDomain::BOOK, $m[1], null, false);
            return 'Book.' . strtolower($op) . '(';
        }, $s) ?? $s;
        $s = preg_replace_callback('/\bBag\.(\w+)\s*\(/', static function(array $m): string {
            $op = self::canonical(AliasDomain::BAG, $m[1], null, false);
            return 'Bag.' . strtolower($op) . '(';
        }, $s) ?? $s;
        $s = preg_replace_callback('/\bTask\.(\w+)\s*\(/', static function(array $m): string {
            $op = self::canonical(AliasDomain::TASK, $m[1], null, false);
            return 'Task.' . strtolower($op) . '(';
        }, $s) ?? $s;
        $s = preg_replace_callback('/\bPage\.(\w+)\s*\(/', static function(array $m): string {
            $op = self::canonical(AliasDomain::PAGE, $m[1], null, false);
            return 'Page.' . strtolower($op) . '(';
        }, $s) ?? $s;

        $s = preg_replace_callback('/\b(delivery|deliver|extract|pullpath|fetchpath)\s*\(/i', static function(array $m): string {
            $op = self::canonical(AliasDomain::DELIVERY, $m[1], null, false);
            return strtolower($op) === 'extract' ? 'delivery(' : strtolower($op) . '(';
        }, $s) ?? $s;

        return $s;
    }

    private static function insert(?array &$map, string $alias, string $canonical, string $scope): void
    {
        $map ??= [];
        if (isset($map[$alias]) && $map[$alias] !== $canonical) {
            throw new InvalidArgumentException("Alias collision in {$scope}: {$alias} => {$map[$alias]} / {$canonical}");
        }
        $map[$alias] = $canonical;
    }

    private static function key(string $s): string
    {
        return strtoupper(trim($s));
    }

    private static function boot(bool $seed = true): void
    {
        if (self::$booted || !$seed) return;
        self::$booted = true;

        self::register(AliasDomain::BAG, 'UNDERWRITE', ['ALLOC','ALLOCATE','CREATE','NEW','RESERVE']);
        self::register(AliasDomain::BAG, 'SIGN', ['AUTHORIZE','REF','REFERENCE']);
        self::register(AliasDomain::BAG, 'UNSIGN', ['RELEASE','UNREF']);
        self::register(AliasDomain::BAG, 'SET', ['WRITE','PUT']);
        self::register(AliasDomain::BAG, 'COMMIT', ['PASS','APPLY']);
        self::register(AliasDomain::BAG, 'GET', ['READ']);
        self::register(AliasDomain::BAG, 'PEEK', ['INSPECT']);
        self::register(AliasDomain::BAG, 'QUOTIENT', ['FREE','REMAINING']);
        self::register(AliasDomain::BAG, 'CAPACITY', ['LIMIT']);
        self::register(AliasDomain::BAG, 'USED', ['USAGE']);
        self::register(AliasDomain::BAG, 'ID', ['IDENTITY']);

        self::register(AliasDomain::BAG_HOT, 'BPUSH', ['PUSH','APPEND','ADD','ENQUEUE','ENQ','QPUSH','SPUSH','VAPPEND']);
        self::register(AliasDomain::BAG_HOT, 'BPOP', ['POP','TAKE','DEQUEUE','DEQ','QPOP','SPOP','VPOP']);
        self::register(AliasDomain::BAG_HOT, 'BPUSHF', ['PUSHF','PUSHFRONT','UNSHIFT','DPUSHF']);
        self::register(AliasDomain::BAG_HOT, 'BPUSHB', ['PUSHB','PUSHBACK','DPUSHB']);
        self::register(AliasDomain::BAG_HOT, 'BPOPF', ['POPF','POPFRONT','SHIFT','DPOPF']);
        self::register(AliasDomain::BAG_HOT, 'BPOPB', ['POPB','POPBACK','DPOPB']);
        self::register(AliasDomain::BAG_HOT, 'BEMPLACE', ['EMPLACE','INSERT','BINSERT','PACKIN','PUTIFABSENT','ADDIFABSENT']);
        self::register(AliasDomain::BAG_HOT, 'BPEEK', ['PEEK','TOP','FRONT']);
        self::register(AliasDomain::BAG_HOT, 'BRESERVE', ['RESERVE','ENSURE']);
        self::register(AliasDomain::BAG_HOT, 'BDIRTY', ['DIRTY']);
        self::register(AliasDomain::BAG_HOT, 'BSYNC', ['SYNC','CHECKPOINT','COMMITBAG']);

        self::register(AliasDomain::BOOK, 'OPEN', ['LOAD','CREATE','NEW']);
        self::register(AliasDomain::BOOK, 'REGISTER_BAG', ['ADDBAG','WITHBAG']);
        self::register(AliasDomain::BOOK, 'REGISTER_PAGE', ['ADDPAGE','WITHPAGE']);
        self::register(AliasDomain::BOOK, 'BAG', ['GETBAG']);
        self::register(AliasDomain::BOOK, 'PAGE', ['GETPAGE']);

        self::register(AliasDomain::TASK, 'UNDERWRITE', ['CREATE','NEW','ALLOC']);
        self::register(AliasDomain::TASK, 'STATE', ['STATUS']);
        self::register(AliasDomain::TASK, 'SETSTATE', ['STATESET','STATUSSET']);
        self::register(AliasDomain::PAGE, 'SPAWN', ['OPEN','CREATE','NEW']);
        self::register(AliasDomain::PAGE, 'RUN', ['EXECUTE','EXEC','START']);

        self::register(AliasDomain::DELIVERY, 'EXTRACT', ['DELIVERY','DELIVER','PULLPATH','FETCHPATH']);
        self::register(AliasDomain::DELIVERY, 'REBIND', ['WRITEPATH','SETPATH','BINDPATH']);

        self::register(AliasDomain::FUNCTION_, 'CALL', ['INVOKE','RUN','EXECUTE']);
        self::register(AliasDomain::FUNCTION_, 'RETURN', ['RET','YIELDVALUE']);
        self::register(AliasDomain::METHOD, 'CALL', ['INVOKE','SEND']);
        self::register(AliasDomain::METHOD, 'RETURN', ['RET']);
        self::register(AliasDomain::LIBRARY, 'LINK', ['USE','IMPORT','WITH']);
        self::register(AliasDomain::LIBRARY, 'UNLINK', ['REMOVE','DETACH']);
        self::register(AliasDomain::PLUGIN, 'LINK', ['USE','ENABLE','INSTALL']);
        self::register(AliasDomain::PLUGIN, 'UNLINK', ['DISABLE','REMOVE','UNINSTALL']);

        self::register(AliasDomain::CONTROL, 'SET', ['PUT','WRITE','CHANGE']);
        self::register(AliasDomain::CONTROL, 'GET', ['READ','VALUE']);
        self::register(AliasDomain::CONTROL, 'SHOW', ['OPEN','DISPLAY']);
        self::register(AliasDomain::CONTROL, 'HIDE', ['CLOSE','CONCEAL']);
        self::register(AliasDomain::CONTROL, 'ENABLE', ['ON','ACTIVATE']);
        self::register(AliasDomain::CONTROL, 'DISABLE', ['OFF','DEACTIVATE']);
        self::register(AliasDomain::STYLE, 'SET', ['APPLY','STYLE','PUT']);
        self::register(AliasDomain::STYLE, 'GAP', ['SPACING','SPACE']);
        self::register(AliasDomain::STYLE, 'BACKGROUND', ['BG','BACKDROP']);
        self::register(AliasDomain::STYLE, 'OPACITY', ['ALPHA','TRANSPARENCY']);
        self::register(AliasDomain::EVENT, 'ON', ['LISTEN','BIND','SUBSCRIBE']);
        self::register(AliasDomain::EVENT, 'OFF', ['UNLISTEN','UNBIND','UNSUBSCRIBE']);
        self::register(AliasDomain::EVENT, 'EMIT', ['FIRE','TRIGGER','SEND']);
        self::register(AliasDomain::CHANNEL, 'SEND', ['PUSH','WRITE','EMIT']);
        self::register(AliasDomain::CHANNEL, 'RECEIVE', ['RECV','READ','PULL']);
        self::register(AliasDomain::CHANNEL, 'CLOSE', ['END','SHUT']);
        self::register(AliasDomain::CHART, 'RENDER', ['DRAW','PAINT','SHOW']);
        self::register(AliasDomain::CHART, 'EXPORT', ['SAVE','OUTPUT']);
        self::register(AliasDomain::CHART, 'SELECT', ['PICK','CHOOSE']);
        self::register(AliasDomain::CHART, 'ZOOM', ['MAGNIFY']);
        self::register(AliasDomain::CHART, 'PAN', ['MOVEVIEW','DRAGVIEW']);
        self::register(AliasDomain::HOST, 'OPEN', ['START','CREATE']);
        self::register(AliasDomain::HOST, 'CLOSE', ['STOP','SHUT']);
        self::register(AliasDomain::HOST, 'READ', ['GET','RECEIVE']);
        self::register(AliasDomain::HOST, 'WRITE', ['PUT','SEND']);
        self::register(AliasDomain::WINDOW, 'SHOW', ['OPEN','DISPLAY']);
        self::register(AliasDomain::WINDOW, 'HIDE', ['CLOSE']);
        self::register(AliasDomain::WINDOW, 'MOVE', ['POSITION']);
        self::register(AliasDomain::WINDOW, 'RESIZE', ['SIZE']);

        self::register(AliasDomain::SQL, 'PREPARE', ['COMPILE']);
        self::register(AliasDomain::SQL, 'QUERY', ['SELECT','READ','FETCH']);
        self::register(AliasDomain::SQL, 'EXECUTE', ['EXEC','RUN']);
        self::register(AliasDomain::SQL, 'BEGIN', ['START','BEGINTRANSACTION']);
        self::register(AliasDomain::SQL, 'COMMIT', ['SAVE','COMMITTRANSACTION']);
        self::register(AliasDomain::SQL, 'ROLLBACK', ['UNDO','ABORT']);
        self::register(AliasDomain::SQL, 'SAVEPOINT', ['MARK']);

        self::register(AliasDomain::PASL, 'RETURN', ['RET']);
        self::register(AliasDomain::PASL, 'JUMP', ['JMP','GOTO']);
        self::register(AliasDomain::PASM, 'JMP', ['JUMP','GOTO']);
        self::register(AliasDomain::PASM, 'JZ', ['JE','JUMPZERO']);
        self::register(AliasDomain::PASM, 'JNZ', ['JNE','JUMPNONZERO']);
        self::register(AliasDomain::PASM, 'MOVI', ['LOADI','MOVEI']);
        self::register(AliasDomain::PASM, 'MOVR', ['MOVE','MOVEREG']);
    }
}
