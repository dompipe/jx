<?php declare(strict_types=1);
/**
 * Book binding — spine order, cursor, history, channel bus.
 * Back/forth and state are not lost.
 */
final class Binding
{
    /** @param list<string> $spine */
    /** @param list<int> $history */
    /** @param array<string, array<string, mixed>> $leafMeta */
    public function __construct(
        private string $bookId,
        private array $spine,
        private ChannelBus $channels,
        private int $cursor = 0,
        private array $history = [],
        private array $leafMeta = [],
        private array $tables = [],
    ) {
        if ($this->cursor < 0 || $this->cursor >= count($this->spine)) {
            $this->cursor = 0;
        }
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
            'bookId'  => $this->bookId,
            'spine'   => $this->spine,
            'cursor'  => $this->cursor,
            'history' => $this->history,
            'leafMeta'=> $this->leafMeta,
            'tables'  => $this->tables,
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
        );
    }
}
