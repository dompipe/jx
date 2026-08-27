<?php declare(strict_types=1);

namespace jx;

/**
 * Applies normalized native-desktop events to a canonical Bag.
 *
 * Host handles (XID/HWND) may appear only as opaque host_id strings. The bridge
 * owns the mutable index and publishes a data-shaped list into one Bag node.
 */
final class DesktopHostBridge
{
    /** @var array<string,array<string,mixed>> */
    private array $windows = [];

    public function __construct(
        private Bag $bag,
        private string $node = 'windows',
    ) {}

    /** @param array<string,mixed> $event */
    public function apply(array $event): void
    {
        $kind = strtolower(trim((string)($event['event'] ?? '')));
        $row = is_array($event['window'] ?? null) ? Boundary::import($event['window']) : [];
        $hostId = trim((string)($row['host_id'] ?? $event['host_id'] ?? ''));

        if ($kind === 'reset') {
            $this->windows = [];
            $this->publish();
            return;
        }
        if ($hostId === '' || strlen($hostId) > 128 || str_contains($hostId, "\0")) {
            throw new JxException('Desktop host event requires a safe host_id', 'desktop.bridge', true);
        }
        if (in_array($kind, ['window-close', 'window-unmap'], true)) {
            unset($this->windows[$hostId]);
            $this->publish();
            return;
        }
        if (!in_array($kind, ['window-open','window-update','window-focus'], true)) {
            throw new JxException('Unsupported desktop host event', 'desktop.bridge', true, ['event'=>$kind]);
        }

        $previous = $this->windows[$hostId] ?? [];
        $clean = array_merge($previous, self::normalizeRow($row, $hostId));
        if ($kind === 'window-focus') {
            foreach ($this->windows as &$other) $other['focused'] = false;
            unset($other);
            $clean['focused'] = true;
        }
        $this->windows[$hostId] = $clean;
        $this->publish();
    }

    /** @return list<array<string,mixed>> */
    public function rows(): array { return array_values($this->windows); }

    private function publish(): void
    {
        $this->bag->write($this->node, array_values($this->windows));
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function normalizeRow(array $row, string $hostId): array
    {
        $title = substr((string)($row['title'] ?? ''), 0, 1024);
        $class = substr((string)($row['class'] ?? ''), 0, 256);
        return [
            'host_id' => $hostId,
            'pid' => isset($row['pid']) ? (int)$row['pid'] : null,
            'title' => $title,
            'class' => $class,
            'x' => (int)($row['x'] ?? 0),
            'y' => (int)($row['y'] ?? 0),
            'width' => max(0, (int)($row['width'] ?? 0)),
            'height' => max(0, (int)($row['height'] ?? 0)),
            'focused' => (bool)($row['focused'] ?? false),
            'mapped' => (bool)($row['mapped'] ?? true),
            'workspace' => max(0, (int)($row['workspace'] ?? 0)),
        ];
    }
}
