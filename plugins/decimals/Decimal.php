<?php declare(strict_types=1);
/**
 * jx Decimal — fixed-precision decimal numbers (not binary float).
 * Scale is digits after the radix point (default 8).
 */
namespace jx;

if (!class_exists(JxException::class, false)) {
    $root = require __DIR__ . '/runtime-root.php';
    require_once $root . '/jx.php';
}

final class Decimal
{
    /** Value stored as integer of smallest units (value * 10^scale). */
    private string $units;
    private int $scale;

    private function __construct(string $units, int $scale)
    {
        if ($scale < 0 || $scale > 18) {
            throw new JxException('Decimal scale must be 0..18', 'decimal');
        }
        $this->units = $units;
        $this->scale = $scale;
    }

    public static function of(int|float|string $value, int $scale = 8): self
    {
        if (is_int($value)) {
            return new self((string)($value * (10 ** $scale)), $scale);
        }
        if (is_float($value)) {
            return self::parse(sprintf('%.' . $scale . 'F', $value), $scale);
        }
        return self::parse((string)$value, $scale);
    }

    public static function parse(string $s, int $scale = 8): self
    {
        $s = trim($s);
        if ($s === '' || !preg_match('/^[+-]?\d+(\.\d+)?$/', $s)) {
            throw new JxException("Invalid decimal: {$s}", 'decimal');
        }
        $neg = str_starts_with($s, '-');
        $s = ltrim($s, '+-');
        if (str_contains($s, '.')) {
            [$whole, $frac] = explode('.', $s, 2);
            $frac = substr(str_pad($frac, $scale, '0'), 0, $scale);
        } else {
            $whole = $s;
            $frac = str_repeat('0', $scale);
        }
        $units = ltrim($whole, '0');
        if ($units === '') {
            $units = '0';
        }
        $units .= $frac;
        $units = ltrim($units, '0');
        if ($units === '') {
            $units = '0';
        }
        if ($neg && $units !== '0') {
            $units = '-' . $units;
        }
        return new self($units, $scale);
    }

    public function scale(): int
    {
        return $this->scale;
    }

    public function add(self $o): self
    {
        $this->assertSameScale($o);
        return new self((string)((int)$this->units + (int)$o->units), $this->scale);
    }

    public function sub(self $o): self
    {
        $this->assertSameScale($o);
        return new self((string)((int)$this->units - (int)$o->units), $this->scale);
    }

    public function mul(self $o): self
    {
        $this->assertSameScale($o);
        $prod = (int)$this->units * (int)$o->units;
        // result scale = 2*scale; normalize back to scale
        $div = 10 ** $this->scale;
        return new self((string)intdiv($prod, $div), $this->scale);
    }

    public function toFloat(): float
    {
        return (int)$this->units / (10 ** $this->scale);
    }

    public function __toString(): string
    {
        $neg = str_starts_with($this->units, '-');
        $u = ltrim($this->units, '-');
        $u = str_pad($u, $this->scale + 1, '0', STR_PAD_LEFT);
        $whole = substr($u, 0, -$this->scale) ?: '0';
        $frac = substr($u, -$this->scale);
        return ($neg ? '-' : '') . $whole . '.' . $frac;
    }

    private function assertSameScale(self $o): void
    {
        if ($o->scale !== $this->scale) {
            throw new JxException('Decimal scale mismatch', 'decimal', true);
        }
    }
}
