<?php declare(strict_types=1);

namespace jx;

/**
 * Desktop specialization of the base awake-state register model.
 *
 * Bags remember; registers react.
 *
 * A WindowBag register resolves a canonical Bag name once. Individual hot
 * references are a packed 16-bit [slot:shadow] pair shared with HotRef.
 */
final class DesktopWindowRegister
{
    public const VERSION = 'jx.window-register/2';
    public const MAX_REGISTERS = HotRegisterBank::MAX_REGISTERS;
    public const MAX_SLOT = HotRef::MAX_SLOT;
    public const MAX_SHADOW = HotRef::MAX_SHADOW;

    private HotRegisterBank $bank;

    public function __construct()
    {
        $this->bank = new HotRegisterBank('window-bag');
    }

    public function intern(string $bag): int
    {
        return $this->bank->intern(Desktop::name($bag, 'window Bag'));
    }

    public function bag(int $register): string
    {
        return $this->bank->target($register);
    }

    public static function pack(int $slot, int $shadow = 0): int
    {
        return HotRef::pack($slot, $shadow);
    }

    /** @return array{slot:int,shadow:int} */
    public static function unpack(int $packed): array
    {
        return HotRef::unpack($packed);
    }

    public static function canonical(int $packed): string
    {
        return HotRef::canonical($packed);
    }

    public static function parse(string $source): int
    {
        return HotRef::parse($source);
    }

    /** @return array<int,string> */
    public function table(): array { return $this->bank->table(); }

    public function clearAwakeState(): void { $this->bank->clearAwakeState(); }
}
