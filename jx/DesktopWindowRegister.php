<?php declare(strict_types=1);

namespace jx;

/**
 * Compact desktop window identity for native/JX11 hot paths.
 *
 * A WindowBag register resolves a canonical Bag name once. Individual hot
 * references are then a 16-bit [slot:shadow] pair: high byte window slot,
 * low byte compiled/reactive shadow id.
 */
final class DesktopWindowRegister
{
    public const VERSION = 'jx.window-register/1';
    public const MAX_REGISTERS = 256;
    public const MAX_SLOT = 255;
    public const MAX_SHADOW = 255;

    /** @var array<int,string> */
    private array $bags = [];
    /** @var array<string,int> */
    private array $byName = [];

    public function intern(string $bag): int
    {
        $bag = Desktop::name($bag, 'window Bag');
        if (isset($this->byName[$bag])) return $this->byName[$bag];
        $reg = count($this->bags);
        if ($reg >= self::MAX_REGISTERS) {
            throw new JxException('WindowBag register table exhausted', 'desktop.window-register', true);
        }
        $this->bags[$reg] = $bag;
        $this->byName[$bag] = $reg;
        return $reg;
    }

    public function bag(int $register): string
    {
        if (!isset($this->bags[$register])) {
            throw new JxException('Unknown WindowBag register', 'desktop.window-register', true, ['register'=>$register]);
        }
        return $this->bags[$register];
    }

    /** Pack [slot:shadow] into two bytes. */
    public static function pack(int $slot, int $shadow = 0): int
    {
        if ($slot < 0 || $slot > self::MAX_SLOT || $shadow < 0 || $shadow > self::MAX_SHADOW) {
            throw new JxException('Window reference must fit [8-bit slot:8-bit shadow]', 'desktop.window-register', true,
                ['slot'=>$slot,'shadow'=>$shadow]);
        }
        return (($slot & 0xff) << 8) | ($shadow & 0xff);
    }

    /** @return array{slot:int,shadow:int} */
    public static function unpack(int $packed): array
    {
        if ($packed < 0 || $packed > 0xffff) {
            throw new JxException('Packed window reference must be uint16', 'desktop.window-register', true);
        }
        return ['slot'=>($packed >> 8) & 0xff, 'shadow'=>$packed & 0xff];
    }

    public static function canonical(int $packed): string
    {
        $v = self::unpack($packed);
        return '['.$v['slot'].':'.$v['shadow'].']';
    }

    public static function parse(string $source): int
    {
        $source = trim($source);
        if (!preg_match('/^\[(\d{1,3}):(\d{1,3})\]$/', $source, $m)) {
            throw new JxException('Invalid canonical window reference', 'desktop.window-register', true, ['source'=>$source]);
        }
        return self::pack((int)$m[1], (int)$m[2]);
    }

    /** @return array<int,string> */
    public function table(): array { return $this->bags; }
}
