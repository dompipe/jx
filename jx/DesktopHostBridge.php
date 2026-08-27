<?php declare(strict_types=1);

namespace jx;

/**
 * Applies normalized native-desktop events to a canonical Bag.
 *
 * Host handles may appear only as opaque host_id strings. Hot native identity
 * may also be supplied as a packed 16-bit [slot:shadow] window_ref.
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
        $clean = $kind === 'window-open'
            ? self::normalizeOpenRow($row, $hostId)
            : array_merge($previous, self::normalizePatchRow($row, $hostId));

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
    private static function normalizeOpenRow(array $row, string $hostId): array
    {
        return array_merge([
            'host_id' => $hostId,
            'window_ref' => null,
            'slot' => null,
            'shadow' => null,
            'pid' => null,
            'title' => '',
            'class' => '',
            'x' => 0,
            'y' => 0,
            'width' => 0,
            'height' => 0,
            'focused' => false,
            'mapped' => true,
            'workspace' => 0,
        ], self::normalizePatchRow($row, $hostId));
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function normalizePatchRow(array $row, string $hostId): array
    {
        $out = ['host_id' => $hostId];

        if (array_key_exists('window_ref', $row)) {
            $packed = is_string($row['window_ref'])
                ? DesktopWindowRegister::parse($row['window_ref'])
                : (int)$row['window_ref'];
            $parts = DesktopWindowRegister::unpack($packed);
            $out['window_ref'] = $packed;
            $out['slot'] = $parts['slot'];
            $out['shadow'] = $parts['shadow'];
        }
        if (array_key_exists('pid', $row)) $out['pid'] = $row['pid'] === null ? null : (int)$row['pid'];
        if (array_key_exists('title', $row)) $out['title'] = substr((string)$row['title'], 0, 1024);
        if (array_key_exists('class', $row)) $out['class'] = substr((string)$row['class'], 0, 256);
        if (array_key_exists('x', $row)) $out['x'] = (int)$row['x'];
        if (array_key_exists('y', $row)) $out['y'] = (int)$row['y'];
        if (array_key_exists('width', $row)) $out['width'] = max(0, (int)$row['width']);
        if (array_key_exists('height', $row)) $out['height'] = max(0, (int)$row['height']);
        if (array_key_exists('focused', $row)) $out['focused'] = (bool)$row['focused'];
        if (array_key_exists('mapped', $row)) $out['mapped'] = (bool)$row['mapped'];
        if (array_key_exists('workspace', $row)) $out['workspace'] = max(0, (int)$row['workspace']);

        return $out;
    }
}
