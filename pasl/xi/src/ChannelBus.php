<?php declare(strict_types=1);
/**
 * Named bags on disk — separate data channels per book.
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
        if (is_file($path)) {
            $bag = Bag::fromJson((string)file_get_contents($path));
        } else {
            $bag = Bag::empty();
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
            file_put_contents($path, $this->cache[$n]->toJson());
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
}
