<?php declare(strict_types=1);

namespace jx;

/**
 * Universal awake-state JX hot-event address.
 *
 * The routing identity is exactly 24 bits:
 *   register:8 | slot:8 | shadow:8
 *
 * Canonical Bags/descriptors remain authoritative. This address exists only
 * while the program/host is awake and is rebuilt from canonical state.
 */
final class HotAddress
{
    public const VERSION = 'jx.hot-event/1';
    public const MAX_REGISTER = 255;
    public const MAX_ADDRESS = 0xffffff;

    public static function pack(int $register, int $slot, int $shadow = 0): int
    {
        if ($register < 0 || $register > self::MAX_REGISTER) {
            throw new JxException('Hot address register must be uint8', 'hot-event', true,
                ['register'=>$register]);
        }
        $ref = HotRef::pack($slot, $shadow);
        return (($register & 0xff) << 16) | $ref;
    }

    /** @return array{register:int,slot:int,shadow:int,ref:int} */
    public static function unpack(int $address): array
    {
        if ($address < 0 || $address > self::MAX_ADDRESS) {
            throw new JxException('Hot address must be uint24', 'hot-event', true,
                ['address'=>$address]);
        }
        $register = ($address >> 16) & 0xff;
        $ref = $address & 0xffff;
        $parts = HotRef::unpack($ref);
        return [
            'register'=>$register,
            'slot'=>$parts['slot'],
            'shadow'=>$parts['shadow'],
            'ref'=>$ref,
        ];
    }

    public static function canonical(int $address): string
    {
        $v = self::unpack($address);
        return 'W'.$v['register'].':['.$v['slot'].':'.$v['shadow'].']';
    }

    public static function parse(string $source): int
    {
        $source = trim($source);
        if (!preg_match('/^W(\d{1,3}):\[(\d{1,3}):(\d{1,3})\]$/i', $source, $m)) {
            throw new JxException('Invalid canonical hot address', 'hot-event', true,
                ['source'=>$source]);
        }
        return self::pack((int)$m[1], (int)$m[2], (int)$m[3]);
    }

    /** @return array{0:int,1:int,2:int} */
    public static function bytes(int $address): array
    {
        $v = self::unpack($address);
        return [$v['register'], $v['slot'], $v['shadow']];
    }
}

/**
 * Compile-time delivery semantics for hot events.
 *
 * Hosts should not improvise loss/coalescing policy at runtime. The compiler
 * assigns one of these policies to each reactive shadow when it wakes.
 */
final class HotDelivery
{
    public const LATEST = 'latest';       // keep only newest value in a quantum
    public const QUEUE = 'queue';         // preserve every event in order
    public const ONCE = 'once';           // one occurrence per quantum
    public const COUNT = 'count';         // count occurrences per quantum
    public const ACCUMULATE = 'accumulate'; // combine numeric/delta payloads

    /** @return list<string> */
    public static function values(): array
    {
        return [self::LATEST, self::QUEUE, self::ONCE, self::COUNT, self::ACCUMULATE];
    }

    public static function normalize(string $policy): string
    {
        $policy = strtolower(trim($policy));
        if (!in_array($policy, self::values(), true)) {
            throw new JxException('Unsupported hot-event delivery policy', 'hot-event', true,
                ['policy'=>$policy]);
        }
        return $policy;
    }
}

/** Compile-time defaults for common input/control event families. */
final class HotInputPolicy
{
    /** @var array<string,string> */
    private const DEFAULTS = [
        'pointer-move' => HotDelivery::LATEST,
        'pointer-enter' => HotDelivery::ONCE,
        'pointer-leave' => HotDelivery::ONCE,
        'drag-move' => HotDelivery::LATEST,
        'resize' => HotDelivery::LATEST,
        'slider-move' => HotDelivery::LATEST,
        'slider-change' => HotDelivery::LATEST,
        'hover' => HotDelivery::LATEST,
        'wheel' => HotDelivery::ACCUMULATE,
        'scroll' => HotDelivery::ACCUMULATE,
        'device-orientation' => HotDelivery::LATEST,
        'click' => HotDelivery::COUNT,
        'double-click' => HotDelivery::COUNT,
        'button-down' => HotDelivery::QUEUE,
        'button-up' => HotDelivery::QUEUE,
        'key-down' => HotDelivery::QUEUE,
        'key-up' => HotDelivery::QUEUE,
        'submit' => HotDelivery::QUEUE,
        'toggle' => HotDelivery::QUEUE,
        'select' => HotDelivery::QUEUE,
        'close' => HotDelivery::QUEUE,
        'commit' => HotDelivery::QUEUE,
    ];

    public static function forEvent(string $event, ?string $override = null): string
    {
        if ($override !== null) return HotDelivery::normalize($override);
        $event = strtolower(trim($event));
        return self::DEFAULTS[$event] ?? HotDelivery::QUEUE;
    }

    /** @return array<string,string> */
    public static function defaults(): array { return self::DEFAULTS; }
}

/**
 * Compiler-facing hot reaction descriptor. It joins a canonical target name to
 * an awake address and a compile-time delivery policy.
 */
final readonly class HotReaction
{
    public int $address;
    public string $delivery;

    public function __construct(
        public string $canonicalTarget,
        public int $register,
        public int $slot,
        public int $shadow,
        string $delivery = HotDelivery::QUEUE,
    ) {
        $target = trim($canonicalTarget);
        if ($target === '' || strlen($target) > 4096 || str_contains($target, "\0")) {
            throw new JxException('Invalid hot reaction canonical target', 'hot-event', true);
        }
        $this->address = HotAddress::pack($register, $slot, $shadow);
        $this->delivery = HotDelivery::normalize($delivery);
    }

    /** @return array{target:string,address:int,canonical:string,register:int,slot:int,shadow:int,delivery:string} */
    public function descriptor(): array
    {
        return [
            'target'=>$this->canonicalTarget,
            'address'=>$this->address,
            'canonical'=>HotAddress::canonical($this->address),
            'register'=>$this->register,
            'slot'=>$this->slot,
            'shadow'=>$this->shadow,
            'delivery'=>$this->delivery,
        ];
    }
}
