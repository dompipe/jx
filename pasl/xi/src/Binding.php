<?php declare(strict_types=1);
/**
 * Book binding — spine order, cursor, history, channel bus, and Page-to-Bag
 * usage. External data-source details belong to the Bag itself.
 */
final class Binding
{
    /** @param list<string> $spine */
    /** @param list<int> $history */
    /** @param array<string, array<string, mixed>> $leafMeta */
    /** @param list<array<string,mixed>> $listeners Legacy/new Page-to-Bag records */
    public function __construct(
        private string $bookId,
        private array $spine,
        private ChannelBus $channels,
        private int $cursor = 0,
        private array $history = [],
        private array $leafMeta = [],
        private array $tables = [],
        private array $listeners = [],
    ) {
        if ($this->cursor < 0 || $this->cursor >= count($this->spine)) {
            $this->cursor = 0;
        }
        $this->listeners = $this->normalizeUses($this->listeners);
    }

    public function bookId(): string
    {
        return $this->bookId;
    }

    public function here(): string
    {
        return $this->spine[$this->cursor] ?? ($this->spine[0] ?? 'home');
    }

    /** @return list<string> */
    public function spine(): array
    {
        return $this->spine;
    }

    public function channel(string $name): Bag
    {
        return $this->channels->channel($name);
    }

    public function bus(): ChannelBus
    {
        return $this->channels;
    }

    public function forward(): string
    {
        if ($this->cursor < count($this->spine) - 1) {
            $this->history[] = $this->cursor;
            $this->cursor++;
        }
        return $this->here();
    }

    public function back(): string
    {
        while ($this->history !== []) {
            $previous = (int)array_pop($this->history);
            if ($previous !== $this->cursor) {
                $this->cursor = $previous;
                return $this->here();
            }
        }
        if ($this->cursor > 0) {
            $this->cursor--;
        }
        return $this->here();
    }

    public function open(string $pageId): string
    {
        $i = array_search($pageId, $this->spine, true);
        if ($i === false) {
            return $this->here();
        }
        if ($i !== $this->cursor) {
            $this->history[] = $this->cursor;
        }
        $this->cursor = (int)$i;
        return $this->here();
    }

    /**
     * Preferred form: say which Bag a Page uses. The Bag itself owns any
     * SQL/NoSQL/source bindings.
     */
    public function useBag(string $page, string $bag): string
    {
        $record = self::useRecord($page, $bag);
        $this->listeners[$record['id']] = $record;
        return $record['id'];
    }

    public function releaseBag(string $id): bool
    {
        if (!isset($this->listeners[$id])) {
            return false;
        }
        unset($this->listeners[$id]);
        return true;
    }

    /** @return list<array{id:string,page:string,bag:string}> */
    public function bagUses(?string $page = null): array
    {
        $records = array_values($this->listeners);
        if ($page === null) {
            return $records;
        }
        return array_values(array_filter(
            $records,
            static fn(array $record): bool => ($record['page'] ?? null) === $page,
        ));
    }

    /** @return list<array{id:string,page:string,bag:string}> */
    public function activeBags(): array
    {
        return $this->bagUses($this->here());
    }

    /**
     * Compatibility form from the earlier SQL-listener design.
     *
     * The SQL details are now moved immediately onto the destination Bag and
     * Binding only remembers that the Page uses that Bag.
     */
    public function listen(
        string $page,
        string $source,
        string $listener,
        string $into,
        string $at = '_default',
        string $mode = 'auto',
    ): string {
        $page = self::name($page, 'page');
        $into = self::name($into, 'Bag');
        $bag = $this->channels->channel($into);
        $bag->bind($source, $listener, $at, $mode);
        return $this->useBag($page, $into);
    }

    /** Compatibility alias: this releases Page use, not the Bag's own source. */
    public function unlisten(string $id): bool
    {
        return $this->releaseBag($id);
    }

    /** Compatibility view: active Page uses expanded with the Bag bindings. */
    public function activeListeners(): array
    {
        $out = [];
        foreach ($this->activeBags() as $use) {
            $bag = $this->channels->channel($use['bag']);
            foreach ($bag->bindings() as $binding) {
                $out[] = [
                    'page' => $use['page'],
                    'bag' => $use['bag'],
                    'use' => $use['id'],
                    'binding' => $binding,
                ];
            }
        }
        return $out;
    }

    public function leafMode(string $pageId): string
    {
        $m = $this->leafMeta[$pageId]['mode'] ?? 'state-ready';
        return $m === 'normalized' ? 'normalized' : 'state-ready';
    }

    /** @return array<string, mixed>|null */
    public function tableMeta(string $tableId): ?array
    {
        return $this->tables[$tableId] ?? null;
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'bookId'    => $this->bookId,
            'spine'     => $this->spine,
            'cursor'    => $this->cursor,
            'history'   => $this->history,
            'leafMeta'  => $this->leafMeta,
            'tables'    => $this->tables,
            'bagUses'   => array_values($this->listeners),
        ];
    }

    /** @param array<string, mixed> $snap */
    public static function restore(array $snap, ChannelBus $bus): self
    {
        $uses = is_array($snap['bagUses'] ?? null)
            ? array_values($snap['bagUses'])
            : (is_array($snap['listeners'] ?? null) ? array_values($snap['listeners']) : []);

        return new self(
            (string)($snap['bookId'] ?? 'cover'),
            array_values(array_map('strval', $snap['spine'] ?? ['home'])),
            $bus,
            (int)($snap['cursor'] ?? 0),
            array_values(array_map('intval', $snap['history'] ?? [])),
            is_array($snap['leafMeta'] ?? null) ? $snap['leafMeta'] : [],
            is_array($snap['tables'] ?? null) ? $snap['tables'] : [],
            $uses,
        );
    }

    /** @param list<array<string,mixed>> $records
     *  @return array<string,array{id:string,page:string,bag:string}>
     */
    private function normalizeUses(array $records): array
    {
        $out = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            try {
                if (isset($record['bag'])) {
                    $use = self::useRecord(
                        (string)($record['page'] ?? ''),
                        (string)$record['bag'],
                    );
                } elseif (isset($record['into'], $record['source'], $record['listener'])) {
                    // Migrate the earlier serialized SQL listener shape.
                    $page = self::name((string)($record['page'] ?? ''), 'page');
                    $bagName = self::name((string)$record['into'], 'Bag');
                    $bag = $this->channels->channel($bagName);
                    $bag->bind(
                        (string)$record['source'],
                        (string)$record['listener'],
                        (string)($record['at'] ?? '_default'),
                        (string)($record['mode'] ?? 'auto'),
                    );
                    $use = self::useRecord($page, $bagName);
                } else {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }
            $out[$use['id']] = $use;
        }
        return $out;
    }

    /** @return array{id:string,page:string,bag:string} */
    private static function useRecord(string $page, string $bag): array
    {
        $page = self::name($page, 'page');
        $bag = self::name($bag, 'Bag');
        return [
            'id' => substr(hash('sha256', "bag-use\0{$page}\0{$bag}"), 0, 24),
            'page' => $page,
            'bag' => $bag,
        ];
    }

    private static function name(string $value, string $what): string
    {
        $value = trim($value);
        if (
            $value === ''
            || strlen($value) > 256
            || str_contains($value, "\0")
            || preg_match('/[^a-z0-9._-]/i', $value)
        ) {
            throw new InvalidArgumentException("Invalid {$what} name");
        }
        return $value;
    }
}
