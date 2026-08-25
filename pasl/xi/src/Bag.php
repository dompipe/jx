<?php declare(strict_types=1);
/**
 * Institutional bag — userland key/value payload only.
 * Secrets never belong here.
 */
final class Bag
{
    /** @param array<string, mixed> $data */
    public function __construct(private array $data = []) {}

    public static function empty(): self
    {
        return new self();
    }

    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        $b = new self();
        foreach ($data as $k => $v) {
            $b->set((string)$k, $v);
        }
        return $b;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        if (preg_match('/secret|password|token|xi_/i', $key)) {
            return;
        }
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    /** @param list<string> $keys */
    public function dilate(array $keys): self
    {
        $out = self::empty();
        foreach ($keys as $k) {
            $out->set($k, $this->get($k));
        }
        return $out;
    }

    /** @param array<string, mixed> $other */
    public function merge(array $other, ?array $allowKeys = null): void
    {
        foreach ($other as $k => $v) {
            $k = (string)$k;
            if ($allowKeys !== null && !in_array($k, $allowKeys, true)) {
                continue;
            }
            $this->set($k, $v);
        }
    }

    public function toJson(): string
    {
        return json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    public static function fromJson(string $json): self
    {
        $d = json_decode($json, true);
        return is_array($d) ? self::from($d) : self::empty();
    }
}
