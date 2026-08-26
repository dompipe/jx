<?php declare(strict_types=1);
/**
 * Book binding — spine order, cursor, history, channel bus, and serializable
 * persistence listeners. Back/forth and state are not lost.
 */
final class Binding
{
    /** @param list<string> $spine */
    /** @param list<int> $history */
    /** @param array<string, array<string, mixed>> $leafMeta */
    /** @param list<array<string,mixed>> $listeners */
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
        $this->listeners = $this->normalizeListeners($this->listeners);
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
     * Declare a SQL dependency without retaining a live SQL/PDO object.
     *
     * page -> source -> listener -> destination Bag -> node -> behavior
     *
     * The host resolves source/listener names when the Page is active.
     */
    public function listen(
        string $page,
        string $source,
        string $listener,
        string $into,
        string $at = '_default',
        string $mode = 'auto',
    ): string {
        $record = self::listenerRecord($page, $source, $listener, $into, $at, $mode);
        $this->listeners[$record['id']] = $record;
        return $record['id'];
    }

    /** Remove one declared listener by its stable binding id. */
    public function unlisten(string $id): bool
    {
        if (!isset($this->listeners[$id])) {
            return false;
        }
        unset($this->listeners[$id]);
        return true;
    }

    /** @return list<array<string,mixed>> */
    public function listeners(?string $page = null): array
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

    /** Listeners whose lifetime belongs to the current Page. */
    public function activeListeners(): array
    {
        return $this->listeners($this->here());
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
            'listeners' => array_values($this->listeners),
        ];
    }

    /** @param array<string, mixed> $snap */
    public static function restore(array $snap, ChannelBus $bus): self
    {
        return new self(
            (string)($snap['bookId'] ?? 'cover'),
            array_values(array_map('strval', $snap['spine'] ?? ['home'])),
            $bus,
            (int)($snap['cursor'] ?? 0),
            array_values(array_map('intval', $snap['history'] ?? [])),
            is_array($snap['leafMeta'] ?? null) ? $snap['leafMeta'] : [],
            is_array($snap['tables'] ?? null) ? $snap['tables'] : [],
            is_array($snap['listeners'] ?? null) ? array_values($snap['listeners']) : [],
        );
    }

    /** @param list<array<string,mixed>> $records
     *  @return array<string,array<string,mixed>>
     */
    private function normalizeListeners(array $records): array
    {
        $out = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }
            try {
                $normalized = self::listenerRecord(
                    (string)($record['page'] ?? ''),
                    (string)($record['source'] ?? ''),
                    (string)($record['listener'] ?? ''),
                    (string)($record['into'] ?? ''),
                    (string)($record['at'] ?? '_default'),
                    (string)($record['mode'] ?? 'auto'),
                );
            } catch (InvalidArgumentException) {
                continue;
            }
            $out[$normalized['id']] = $normalized;
        }
        return $out;
    }

    /** @return array{id:string,kind:string,page:string,source:string,listener:string,into:string,at:string,mode:string} */
    private static function listenerRecord(
        string $page,
        string $source,
        string $listener,
        string $into,
        string $at,
        string $mode,
    ): array {
        $page = self::name($page, 'page');
        $source = self::name($source, 'source');
        $listener = self::name($listener, 'listener');
        $into = self::name($into, 'Bag');
        $at = self::name($at, 'Bag node');

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['auto', 'poll', 'notify', 'manual'], true)) {
            throw new InvalidArgumentException('Unsupported SQL listener mode');
        }

        $id = substr(hash('sha256', implode("\0", [
            'sql', $page, $source, $listener, $into, $at, $mode,
        ])), 0, 24);

        return [
            'id' => $id,
            'kind' => 'sql',
            'page' => $page,
            'source' => $source,
            'listener' => $listener,
            'into' => $into,
            'at' => $at,
            'mode' => $mode,
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
