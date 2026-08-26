<?php declare(strict_types=1);
/**
 * Named Bags on disk — separate data channels per Book.
 *
 * Current Bag JSON is a versioned envelope containing both data and declarative
 * source bindings. A legacy `.bindings.json` sidecar is still consumed once so
 * earlier branch data can migrate forward without losing bindings.
 */
final class ChannelBus
{
    /** @var array<string, Bag> */
    private array $cache = [];

    public function __construct(private string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
    }

    public function channel(string $name): Bag
    {
        $name = $this->safeName($name);
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $path = $this->path($name);
        $bag = is_file($path)
            ? Bag::fromJson((string)file_get_contents($path))
            : Bag::empty();

        // Migration path from the earlier sidecar representation.
        $legacyBindingPath = $this->bindingPath($name);
        if (is_file($legacyBindingPath)) {
            $bindings = json_decode((string)file_get_contents($legacyBindingPath), true);
            if (is_array($bindings) && $bindings !== []) {
                $bag->restoreBindings($bindings);
            }
        }

        $this->cache[$name] = $bag;
        return $bag;
    }

    public function save(?string $name = null): void
    {
        $names = $name === null ? array_keys($this->cache) : [$this->safeName($name)];
        foreach ($names as $n) {
            if (!isset($this->cache[$n])) {
                continue;
            }

            $path = $this->path($n);
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($path, $this->cache[$n]->toJson(), LOCK_EX);

            // Once the versioned Bag envelope is saved, the old sidecar is no
            // longer authoritative and can be removed safely.
            $legacyBindingPath = $this->bindingPath($n);
            if (is_file($legacyBindingPath)) {
                @unlink($legacyBindingPath);
            }
        }
    }

    public function ensure(string $name): Bag
    {
        return $this->channel($name);
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[^a-z0-9._-]/i', '', $name) ?? '';
        return $name !== '' ? $name : 'default';
    }

    private function path(string $name): string
    {
        return rtrim($this->dir, '/\\') . DIRECTORY_SEPARATOR . $name . '.json';
    }

    private function bindingPath(string $name): string
    {
        return rtrim($this->dir, '/\\') . DIRECTORY_SEPARATOR . $name . '.bindings.json';
    }
}
