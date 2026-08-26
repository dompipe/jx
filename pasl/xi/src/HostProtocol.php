<?php declare(strict_types=1);

final class JxHostProtocol
{
    public const VERSION = 'jx.host/1';
    public const HOSTS = ['browser', 'win32', 'x11'];

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public static function drop(array $input, string $fallbackBook): array
    {
        $version = (string)($input['version'] ?? self::VERSION);
        if ($version !== self::VERSION) {
            throw new InvalidArgumentException('Unsupported host protocol version');
        }

        $host = strtolower((string)($input['host'] ?? 'browser'));
        if (!in_array($host, self::HOSTS, true)) {
            throw new InvalidArgumentException('Unsupported window host');
        }

        $type = self::name((string)($input['type'] ?? 'window.event'), 'window.event');
        $window = self::name((string)($input['window'] ?? ''), 'main');
        $book = self::name((string)($input['book'] ?? $fallbackBook), $fallbackBook);
        $leaf = self::name((string)($input['leaf'] ?? 'home'), 'home');
        $payload = $input['payload'] ?? [];
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Drop payload must be an object');
        }

        return [
            'version' => self::VERSION,
            'type' => $type,
            'host' => $host,
            'window' => $window,
            'book' => $book,
            'leaf' => $leaf,
            'sequence' => max(0, (int)($input['sequence'] ?? 0)),
            'payload' => $payload,
        ];
    }

    /** @return array<string, mixed> */
    public static function openWindow(Book $book, Binding $binding): array
    {
        $window = $book->window();
        return [
            'version' => self::VERSION,
            'type' => 'window.open',
            'window' => self::name((string)($window['id'] ?? $book->id()), $book->id()),
            'book' => $book->id(),
            'leaf' => $binding->here(),
            'title' => (string)($window['title'] ?? $book->config()['title'] ?? $book->id()),
            'bounds' => [
                'x' => (int)($window['x'] ?? 80),
                'y' => (int)($window['y'] ?? 80),
                'width' => max(240, (int)($window['width'] ?? 960)),
                'height' => max(160, (int)($window['height'] ?? 720)),
            ],
        ];
    }

    private static function name(string $value, string $fallback): string
    {
        $value = preg_replace('/[^a-z0-9._-]/i', '', $value) ?? '';
        return $value !== '' ? substr($value, 0, 96) : $fallback;
    }
}
