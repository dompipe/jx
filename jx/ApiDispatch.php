<?php declare(strict_types=1);

namespace jx;

/**
 * Canonical API definitions compile into awake W:slot:shadow routes.
 *
 * Shadow convention is fixed so hosts never resolve method names on the hot path:
 *   0 request
 *   1 success
 *   2 error
 *   3 stream/chunk
 *   4 cancel
 */
final class ApiShadow
{
    public const REQUEST = 0;
    public const SUCCESS = 1;
    public const ERROR = 2;
    public const STREAM = 3;
    public const CANCEL = 4;

    public static function valid(int $shadow): bool
    {
        return $shadow >= self::REQUEST && $shadow <= self::CANCEL;
    }
}

/** Compile-time transport choice. The hot route itself is transport-neutral. */
final class ApiTransport
{
    public const DIRECT = 'direct';       // same process / compiled service table
    public const NATIVE = 'native';       // native library or OS service thunk
    public const UNIX = 'unix';           // local AF_UNIX datagram/stream adapter
    public const UDP = 'udp';             // explicit datagram boundary
    public const HTTP = 'http';           // clear HTTP adapter; no TLS implied
    public const HTTPS = 'https';         // TLS required; verify peer and hostname
    public const SSH = 'ssh';             // OpenSSH-style authenticated session adapter
    public const DEVICE = 'device';       // host/device adapter

    /** @return list<string> */
    public static function values(): array
    {
        return [
            self::DIRECT,self::NATIVE,self::UNIX,self::UDP,
            self::HTTP,self::HTTPS,self::SSH,self::DEVICE,
        ];
    }

    public static function normalize(string $transport): string
    {
        $transport = strtolower(trim($transport));
        if (!in_array($transport, self::values(), true)) {
            throw new JxException('Unsupported API transport', 'api-dispatch', true, ['transport'=>$transport]);
        }
        return $transport;
    }

    public static function isSecureRemote(string $transport): bool
    {
        $transport = self::normalize($transport);
        return $transport === self::HTTPS || $transport === self::SSH;
    }
}

/** One canonical API endpoint after compiler slot allocation. */
final readonly class ApiEndpoint
{
    public function __construct(
        public string $name,
        public int $register,
        public int $slot,
        public string $transport = ApiTransport::DIRECT,
        public string $delivery = HotDelivery::QUEUE,
        public bool $idempotent = false,
        public int $timeoutMs = 30000,
        public ?string $capability = null,
    ) {
        $name = trim($name);
        if ($name === '' || strlen($name) > 256 || str_contains($name, "\0")) {
            throw new JxException('Invalid API endpoint name', 'api-dispatch', true);
        }
        HotAddress::pack($register, $slot, ApiShadow::REQUEST);
        ApiTransport::normalize($transport);
        HotDelivery::normalize($delivery);
        if ($timeoutMs < 0 || $timeoutMs > 86_400_000) {
            throw new JxException('API timeout must be between 0 and 86400000ms', 'api-dispatch', true,
                ['timeout_ms'=>$timeoutMs]);
        }
        if ($capability !== null && (trim($capability) === '' || strlen($capability) > 256 || str_contains($capability, "\0"))) {
            throw new JxException('Invalid API capability', 'api-dispatch', true);
        }
        if ($transport === ApiTransport::SSH && $capability !== null && $capability === 'network.ssh') {
            throw new JxException('SSH capability must be operation-scoped, not generic network.ssh', 'api-dispatch', true,
                ['capability'=>$capability]);
        }
    }

    public function address(int $shadow = ApiShadow::REQUEST): int
    {
        if (!ApiShadow::valid($shadow)) {
            throw new JxException('Invalid API shadow', 'api-dispatch', true, ['shadow'=>$shadow]);
        }
        return HotAddress::pack($this->register, $this->slot, $shadow);
    }

    /** @return array<string,mixed> */
    public function descriptor(): array
    {
        return [
            'name'=>$this->name,
            'transport'=>ApiTransport::normalize($this->transport),
            'register'=>$this->register,
            'slot'=>$this->slot,
            'request'=>HotAddress::canonical($this->address(ApiShadow::REQUEST)),
            'success'=>HotAddress::canonical($this->address(ApiShadow::SUCCESS)),
            'error'=>HotAddress::canonical($this->address(ApiShadow::ERROR)),
            'stream'=>HotAddress::canonical($this->address(ApiShadow::STREAM)),
            'cancel'=>HotAddress::canonical($this->address(ApiShadow::CANCEL)),
            'delivery'=>HotDelivery::normalize($this->delivery),
            'idempotent'=>$this->idempotent,
            'timeout_ms'=>$this->timeoutMs,
            'capability'=>$this->capability,
        ];
    }
}

/**
 * Compiler-facing API table. Endpoint names disappear from normal dispatch once
 * this table is materialized into a .64B HOT/API section.
 */
final class ApiDispatchTable
{
    public const VERSION = 'jx.api-dispatch/1';
    public const MAX_ENDPOINTS = 256;

    /** @var array<string,ApiEndpoint> */
    private array $byName = [];
    /** @var array<int,ApiEndpoint> */
    private array $bySlot = [];

    public function __construct(public readonly int $register)
    {
        HotAddress::pack($register, 0, 0);
    }

    public function add(
        string $name,
        string $transport = ApiTransport::DIRECT,
        string $delivery = HotDelivery::QUEUE,
        bool $idempotent = false,
        int $timeoutMs = 30000,
        ?string $capability = null,
    ): ApiEndpoint {
        $key = trim($name);
        if (isset($this->byName[$key])) return $this->byName[$key];
        $slot = count($this->bySlot);
        if ($slot >= self::MAX_ENDPOINTS) {
            throw new JxException('API dispatch register is full', 'api-dispatch', true,
                ['register'=>$this->register]);
        }
        $endpoint = new ApiEndpoint($key, $this->register, $slot, $transport, $delivery,
            $idempotent, $timeoutMs, $capability);
        $this->byName[$key] = $endpoint;
        $this->bySlot[$slot] = $endpoint;
        return $endpoint;
    }

    public function byName(string $name): ?ApiEndpoint { return $this->byName[trim($name)] ?? null; }
    public function bySlot(int $slot): ?ApiEndpoint { return $this->bySlot[$slot] ?? null; }
    public function count(): int { return count($this->bySlot); }

    /** @return list<array<string,mixed>> */
    public function descriptor(): array
    {
        return array_map(static fn(ApiEndpoint $e): array => $e->descriptor(), array_values($this->bySlot));
    }
}

/**
 * API payload header carried inside HotPacket.
 *
 * Bytes 0..3  call id, network byte order
 * Bytes 4..5  status/code, uint16 (0 for request/success-without-status)
 * Byte  6     content type code
 * Byte  7     API flags
 * Bytes 8..   body
 */
final class ApiPacket
{
    public const VERSION = 'jx.api-packet/1';
    public const HEADER_BYTES = 8;
    public const CONTENT_BINARY = 0;
    public const CONTENT_JSON = 1;
    public const CONTENT_UTF8 = 2;

    public static function encode(
        ApiEndpoint $endpoint,
        int $callId,
        string $body = '',
        int $shadow = ApiShadow::REQUEST,
        int $status = 0,
        int $contentType = self::CONTENT_BINARY,
        int $apiFlags = 0,
    ): string {
        if ($callId < 0 || $callId > 0xffffffff) throw new JxException('API call id must be uint32', 'api-dispatch', true);
        if ($status < 0 || $status > 0xffff) throw new JxException('API status must be uint16', 'api-dispatch', true);
        if ($contentType < 0 || $contentType > 255 || $apiFlags < 0 || $apiFlags > 255) {
            throw new JxException('API content type/flags must be uint8', 'api-dispatch', true);
        }
        $payload = pack('NnCC', $callId, $status, $contentType, $apiFlags).$body;
        return HotPacket::encode($endpoint->address($shadow), $payload, $endpoint->delivery);
    }

    /** @return array<string,mixed> */
    public static function decode(string $packet): array
    {
        $hot = HotPacket::decode($packet);
        if (strlen($hot['payload']) < self::HEADER_BYTES) {
            throw new JxException('API packet payload is truncated', 'api-dispatch', true);
        }
        $h = unpack('Ncall_id/nstatus/Ccontent_type/Capi_flags', substr($hot['payload'], 0, self::HEADER_BYTES));
        if (!is_array($h)) throw new JxException('Cannot decode API packet header', 'api-dispatch', true);
        return $hot + [
            'call_id'=>(int)$h['call_id'],
            'status'=>(int)$h['status'],
            'content_type'=>(int)$h['content_type'],
            'api_flags'=>(int)$h['api_flags'],
            'body'=>substr($hot['payload'], self::HEADER_BYTES),
        ];
    }
}
