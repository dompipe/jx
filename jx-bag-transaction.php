<?php declare(strict_types=1);

namespace jx\memory;

final class BagWriteException extends \RuntimeException {}

/**
 * Process-local RefSign authority.
 *
 * A RefSign is not a raw Bag ID. It authenticates bag + subject + generation +
 * nonce with HMAC-SHA256. Hosts may exchange the opaque token, but cannot mint
 * a valid different reference without the authority secret.
 */
final class RefAuthority
{
    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 32) throw new BagWriteException('RefAuthority secret must be at least 32 bytes');
    }

    public static function random(): self
    {
        return new self(random_bytes(32));
    }

    public function sign(string $bagId, string $subject, int $generation): string
    {
        if ($bagId === '' || $subject === '' || $generation < 0) throw new BagWriteException('Invalid RefSign identity');
        $payload = json_encode([
            'v' => 1,
            'bag' => $bagId,
            'subject' => $subject,
            'generation' => $generation,
            'nonce' => bin2hex(random_bytes(12)),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $mac = hash_hmac('sha256', $payload, $this->secret, true);
        return self::b64($payload . $mac);
    }

    /** @return array{v:int,bag:string,subject:string,generation:int,nonce:string} */
    public function verify(string $token): array
    {
        $raw = self::unb64($token);
        if (strlen($raw) <= 32) throw new BagWriteException('Malformed RefSign');
        $payload = substr($raw, 0, -32);
        $mac = substr($raw, -32);
        $expected = hash_hmac('sha256', $payload, $this->secret, true);
        if (!hash_equals($expected, $mac)) throw new BagWriteException('Invalid RefSign MAC');
        $data = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['v'] ?? null) !== 1) throw new BagWriteException('Unsupported RefSign version');
        foreach (['bag','subject','nonce'] as $key) if (!is_string($data[$key] ?? null) || $data[$key] === '') throw new BagWriteException('Malformed RefSign payload');
        if (!is_int($data['generation'] ?? null) || $data['generation'] < 0) throw new BagWriteException('Malformed RefSign generation');
        /** @var array{v:int,bag:string,subject:string,generation:int,nonce:string} $data */
        return $data;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $value): string
    {
        $pad = strlen($value) % 4;
        if ($pad !== 0) $value .= str_repeat('=', 4 - $pad);
        $raw = base64_decode(strtr($value, '-_', '+/'), true);
        if ($raw === false) throw new BagWriteException('Malformed RefSign encoding');
        return $raw;
    }
}

interface BagWriteTarget
{
    public function bagId(): string;
    public function generation(): int;
    public function reserve(string $subject, int $expectedGeneration): void;
    public function commit(string $subject, string $path, mixed $value, int $expectedGeneration): int;
    public function release(string $subject): void;
}

/** Small canonical target useful to hosts/tests and as the transaction reference implementation. */
final class MemoryBag implements BagWriteTarget
{
    private int $generation = 0;
    private ?string $reservedBy = null;
    /** @var array<string,mixed> */ private array $values = [];

    public function __construct(private readonly string $id)
    {
        if ($id === '') throw new BagWriteException('Bag ID must be non-empty');
    }

    public function bagId(): string { return $this->id; }
    public function generation(): int { return $this->generation; }
    public function values(): array { return $this->values; }

    public function reserve(string $subject, int $expectedGeneration): void
    {
        if ($expectedGeneration !== $this->generation) throw new BagWriteException('Stale Bag generation');
        if ($this->reservedBy !== null && $this->reservedBy !== $subject) throw new BagWriteException('Bag write already reserved');
        $this->reservedBy = $subject;
    }

    public function commit(string $subject, string $path, mixed $value, int $expectedGeneration): int
    {
        if ($this->reservedBy !== $subject) throw new BagWriteException('Bag write is not reserved by subject');
        if ($expectedGeneration !== $this->generation) throw new BagWriteException('Stale Bag generation at commit');
        if ($path === '') throw new BagWriteException('Bag write path must be non-empty');
        $this->values[$path] = $value;
        $this->generation++;
        $this->reservedBy = null;
        return $this->generation;
    }

    public function release(string $subject): void
    {
        if ($this->reservedBy === $subject) $this->reservedBy = null;
    }
}

final class BagWriteTransaction
{
    public const NEW = 'new';
    public const SIGNED = 'signed';
    public const AUTHORIZED = 'authorized';
    public const RESERVED = 'reserved';
    public const WRITTEN = 'written';
    public const COMMITTED = 'committed';
    public const ABORTED = 'aborted';

    private string $state = self::NEW;
    private ?string $refSign = null;
    private mixed $pendingValue = null;
    private ?string $pendingPath = null;

    public function __construct(
        private readonly RefAuthority $authority,
        private readonly BagWriteTarget $target,
        private readonly string $subject,
    ) {
        if ($subject === '') throw new BagWriteException('Write subject must be non-empty');
    }

    public function state(): string { return $this->state; }

    public function sign(): string
    {
        $this->expect(self::NEW);
        $this->refSign = $this->authority->sign($this->target->bagId(), $this->subject, $this->target->generation());
        $this->state = self::SIGNED;
        return $this->refSign;
    }

    /** @param callable(string,string):bool $authorizer receives subject, capability */
    public function authorize(callable $authorizer, string $capability = 'bag.write'): self
    {
        $this->expect(self::SIGNED);
        $ref = $this->verifiedRef();
        if ($ref['generation'] !== $this->target->generation()) throw new BagWriteException('RefSign generation is stale');
        if (!$authorizer($this->subject, $capability)) throw new BagWriteException("Subject lacks {$capability}");
        $this->state = self::AUTHORIZED;
        return $this;
    }

    public function reserve(): self
    {
        $this->expect(self::AUTHORIZED);
        $ref = $this->verifiedRef();
        $this->target->reserve($this->subject, $ref['generation']);
        $this->state = self::RESERVED;
        return $this;
    }

    public function write(string $path, mixed $value): self
    {
        $this->expect(self::RESERVED);
        if ($path === '') throw new BagWriteException('Bag write path must be non-empty');
        $this->pendingPath = $path;
        $this->pendingValue = $value;
        $this->state = self::WRITTEN;
        return $this;
    }

    public function commit(): int
    {
        $this->expect(self::WRITTEN);
        $ref = $this->verifiedRef();
        try {
            $generation = $this->target->commit($this->subject, (string)$this->pendingPath, $this->pendingValue, $ref['generation']);
        } catch (\Throwable $e) {
            $this->target->release($this->subject);
            $this->state = self::ABORTED;
            throw $e;
        }
        if ($generation !== $ref['generation'] + 1) {
            $this->state = self::ABORTED;
            throw new BagWriteException('Bag commit must advance generation exactly once');
        }
        $this->state = self::COMMITTED;
        return $generation;
    }

    public function abort(): void
    {
        if ($this->state === self::COMMITTED || $this->state === self::ABORTED) return;
        $this->target->release($this->subject);
        $this->state = self::ABORTED;
    }

    /** @return array{v:int,bag:string,subject:string,generation:int,nonce:string} */
    private function verifiedRef(): array
    {
        if ($this->refSign === null) throw new BagWriteException('Transaction has no RefSign');
        $ref = $this->authority->verify($this->refSign);
        if ($ref['bag'] !== $this->target->bagId() || $ref['subject'] !== $this->subject) throw new BagWriteException('RefSign identity mismatch');
        return $ref;
    }

    private function expect(string $state): void
    {
        if ($this->state !== $state) throw new BagWriteException("Expected transaction state {$state}; got {$this->state}");
    }
}
