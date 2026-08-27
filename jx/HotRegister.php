<?php declare(strict_types=1);

namespace jx;

/**
 * Base-compiler awake-state register primitive.
 *
 * Bags remember; registers react.
 *
 * Canonical objects/names are interned once when a program wakes. Hot work then
 * carries a bounded register byte plus a packed 16-bit [slot:shadow] reference.
 * Register layout is acceleration/provenance only and MUST NOT define canonical
 * program meaning or durable state.
 */
final class HotRef
{
    public const MAX_SLOT = 255;
    public const MAX_SHADOW = 255;

    public static function pack(int $slot, int $shadow = 0): int
    {
        if ($slot < 0 || $slot > self::MAX_SLOT || $shadow < 0 || $shadow > self::MAX_SHADOW) {
            throw new JxException('Hot reference must fit [8-bit slot:8-bit shadow]', 'hot-register', true,
                ['slot'=>$slot, 'shadow'=>$shadow]);
        }
        return (($slot & 0xff) << 8) | ($shadow & 0xff);
    }

    /** @return array{slot:int,shadow:int} */
    public static function unpack(int $packed): array
    {
        if ($packed < 0 || $packed > 0xffff) {
            throw new JxException('Packed hot reference must be uint16', 'hot-register', true,
                ['packed'=>$packed]);
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
            throw new JxException('Invalid canonical hot reference', 'hot-register', true, ['source'=>$source]);
        }
        return self::pack((int)$m[1], (int)$m[2]);
    }
}

/**
 * One bounded byte-addressed register bank for awake-state canonical targets.
 *
 * A compiler/host may create separate banks by domain (Bag, WindowBag, Control,
 * object, Delivery, media, etc.). Names exist only in the cold table; hot code
 * uses the returned register number directly.
 */
final class HotRegisterBank
{
    public const VERSION = 'jx.hot-register/1';
    public const MAX_REGISTERS = 256;

    /** @var array<int,string> */
    private array $targets = [];
    /** @var array<string,int> */
    private array $byName = [];

    public function __construct(private string $domain = 'bag')
    {
        $domain = trim($domain);
        if ($domain === '' || strlen($domain) > 64 || preg_match('/[^a-z0-9._-]/i', $domain)) {
            throw new JxException('Invalid hot-register domain', 'hot-register', true, ['domain'=>$domain]);
        }
        $this->domain = strtolower($domain);
    }

    public function domain(): string { return $this->domain; }

    public function intern(string $canonicalName): int
    {
        $canonicalName = trim($canonicalName);
        if ($canonicalName === '' || strlen($canonicalName) > 4096 || str_contains($canonicalName, "\0")) {
            throw new JxException('Invalid hot-register canonical target', 'hot-register', true);
        }
        if (isset($this->byName[$canonicalName])) return $this->byName[$canonicalName];
        $register = count($this->targets);
        if ($register >= self::MAX_REGISTERS) {
            throw new JxException('Hot-register bank exhausted', 'hot-register', true, ['domain'=>$this->domain]);
        }
        $this->targets[$register] = $canonicalName;
        $this->byName[$canonicalName] = $register;
        return $register;
    }

    public function target(int $register): string
    {
        if ($register < 0 || $register >= self::MAX_REGISTERS || !isset($this->targets[$register])) {
            throw new JxException('Unknown hot register', 'hot-register', true,
                ['domain'=>$this->domain, 'register'=>$register]);
        }
        return $this->targets[$register];
    }

    public function lookup(string $canonicalName): ?int
    {
        return $this->byName[$canonicalName] ?? null;
    }

    /** @return array<int,string> */
    public function table(): array { return $this->targets; }

    public function clearAwakeState(): void
    {
        $this->targets = [];
        $this->byName = [];
    }
}
