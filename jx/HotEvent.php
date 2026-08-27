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
    public const LATEST = 'latest';
    public const QUEUE = 'queue';
    public const ONCE = 'once';
    public const COUNT = 'count';
    public const ACCUMULATE = 'accumulate';

    private const CODES = [
        self::LATEST => 0,
        self::QUEUE => 1,
        self::ONCE => 2,
        self::COUNT => 3,
        self::ACCUMULATE => 4,
    ];

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::CODES);
    }

    public static function normalize(string $policy): string
    {
        $policy = strtolower(trim($policy));
        if (!isset(self::CODES[$policy])) {
            throw new JxException('Unsupported hot-event delivery policy', 'hot-event', true,
                ['policy'=>$policy]);
        }
        return $policy;
    }

    public static function code(string $policy): int
    {
        return self::CODES[self::normalize($policy)];
    }

    public static function fromCode(int $code): string
    {
        $policy = array_search($code, self::CODES, true);
        if ($policy === false) {
            throw new JxException('Unsupported hot-event delivery code', 'hot-event', true,
                ['code'=>$code]);
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
 * Binary datagram envelope for hot input/reactions.
 *
 * Byte layout:
 *   0 version
 *   1 register
 *   2 slot
 *   3 shadow
 *   4 delivery code
 *   5 flags
 *   6..7 payload length (network byte order)
 *   8.. payload bytes
 *
 * The routing address itself is always the three bytes at offsets 1..3.
 */
final class HotPacket
{
    public const VERSION = 1;
    public const HEADER_BYTES = 8;
    public const MAX_PAYLOAD = 65535;

    public static function encode(
        int $address,
        string $payload = '',
        string $delivery = HotDelivery::QUEUE,
        int $flags = 0,
    ): string {
        if (strlen($payload) > self::MAX_PAYLOAD) {
            throw new JxException('Hot packet payload exceeds uint16 length', 'hot-event', true);
        }
        if ($flags < 0 || $flags > 255) {
            throw new JxException('Hot packet flags must be uint8', 'hot-event', true, ['flags'=>$flags]);
        }
        $v = HotAddress::unpack($address);
        return pack('CCCCCCn',
            self::VERSION,
            $v['register'],
            $v['slot'],
            $v['shadow'],
            HotDelivery::code($delivery),
            $flags,
            strlen($payload),
        ).$payload;
    }

    /** @return array{address:int,canonical:string,register:int,slot:int,shadow:int,delivery:string,flags:int,payload:string} */
    public static function decode(string $packet): array
    {
        if (strlen($packet) < self::HEADER_BYTES) {
            throw new JxException('Hot packet is truncated', 'hot-event', true);
        }
        $h = unpack('Cversion/Cregister/Cslot/Cshadow/Cdelivery/Cflags/nlength', substr($packet, 0, self::HEADER_BYTES));
        if (!is_array($h) || ($h['version'] ?? 0) !== self::VERSION) {
            throw new JxException('Unsupported hot packet version', 'hot-event', true);
        }
        $length = (int)$h['length'];
        if (strlen($packet) !== self::HEADER_BYTES + $length) {
            throw new JxException('Hot packet payload length mismatch', 'hot-event', true,
                ['declared'=>$length, 'actual'=>strlen($packet) - self::HEADER_BYTES]);
        }
        $address = HotAddress::pack((int)$h['register'], (int)$h['slot'], (int)$h['shadow']);
        return [
            'address'=>$address,
            'canonical'=>HotAddress::canonical($address),
            'register'=>(int)$h['register'],
            'slot'=>(int)$h['slot'],
            'shadow'=>(int)$h['shadow'],
            'delivery'=>HotDelivery::fromCode((int)$h['delivery']),
            'flags'=>(int)$h['flags'],
            'payload'=>substr($packet, self::HEADER_BYTES),
        ];
    }
}

/** Compiler-facing canonical-target -> awake-route descriptor. */
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
