<?php declare(strict_types=1);
/**
 * One website section = one book (multi-part Docker-like unit).
 */
final class Book
{
    public function __construct(
        private string $id,
        private string $root,
        private array $config,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function root(): string
    {
        return $this->root;
    }

    /** @return list<string> */
    public function spine(): array
    {
        $s = $this->config['spine'] ?? ['home'];
        return array_values(array_map('strval', is_array($s) ? $s : ['home']));
    }

    /** @return array<string, array<string, mixed>> */
    public function leafMeta(): array
    {
        $l = $this->config['leaves'] ?? [];
        return is_array($l) ? $l : [];
    }

    /** @return array<string, array<string, mixed>> */
    public function tables(): array
    {
        $t = $this->config['tables'] ?? [];
        return is_array($t) ? $t : [];
    }

    public function dropsEnabled(): bool
    {
        return !empty($this->config['drops']['enabled']);
    }

    public function dropChannel(): string
    {
        return (string)($this->config['drops']['channel'] ?? 'drop');
    }

    /** @return array<string, mixed> */
    public function window(): array
    {
        $window = $this->config['window'] ?? [];
        return is_array($window) ? $window : [];
    }

    public function paslPath(string $leafId): ?string
    {
        $meta = $this->leafMeta()[$leafId] ?? [];
        $relative = is_array($meta) ? (string)($meta['pasl'] ?? '') : '';
        if ($relative === '' || str_contains($relative, "\0")) {
            return null;
        }
        $file = $this->root . '/' . ltrim(str_replace('\\', '/', $relative), '/');
        return $this->jailed($file, $this->root) && is_file($file) ? $file : null;
    }

    public function pagePath(string $leafId): ?string
    {
        $leafId = preg_replace('/[^a-z0-9_-]/i', '', $leafId) ?? '';
        if ($leafId === '') {
            return null;
        }
        $file = $this->root . '/pages/' . $leafId . '.php';
        return $this->jailed($file, $this->root . '/pages') ? $file : null;
    }

    public function protocolPath(string $name): ?string
    {
        $name = preg_replace('/[^a-z0-9._-]/i', '', $name) ?? '';
        if ($name === '') {
            return null;
        }
        $file = $this->root . '/protocol/' . $name . '.php';
        return $this->jailed($file, $this->root . '/protocol') ? $file : null;
    }

    public function dataDir(string $siteData): string
    {
        return rtrim($siteData, '/\\') . '/books/' . $this->id;
    }

    public function inboxDir(string $siteData): string
    {
        return $this->dataDir($siteData) . '/inbox';
    }

    public function channelsDir(string $siteData): string
    {
        return $this->dataDir($siteData) . '/channels';
    }

    public function bindingPath(string $siteData): string
    {
        return $this->dataDir($siteData) . '/binding.json';
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return $this->config;
    }

    private function jailed(string $file, string $root): bool
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            return false;
        }
        if (!is_file($file)) {
            $parent = realpath(dirname($file));
            return $parent !== false && $this->inside($parent, $rootReal);
        }
        $real = realpath($file);
        return $real !== false && $this->inside($real, $rootReal);
    }

    private function inside(string $path, string $root): bool
    {
        $path = rtrim($path, '/\\');
        $root = rtrim($root, '/\\');
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    public static function load(string $booksRoot, string $id): ?self
    {
        $id = preg_replace('/[^a-z0-9_-]/i', '', $id) ?? '';
        if ($id === '') {
            return null;
        }
        $root = rtrim($booksRoot, '/\\') . '/' . $id;
        $cfgFile = $root . '/book.json';
        if (!is_file($cfgFile)) {
            return null;
        }
        $cfg = json_decode((string)file_get_contents($cfgFile), true);
        if (!is_array($cfg)) {
            return null;
        }
        return new self($id, $root, $cfg);
    }
}
