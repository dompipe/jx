<?php declare(strict_types=1);

namespace jx\semantic;

require_once __DIR__ . '/jx-book-trust.php';

final class BookGenerationException extends \RuntimeException {}

final readonly class AdmittedBook
{
    /** @param list<string> $capabilities */
    public function __construct(
        public int $generation,
        public string $bytes,
        public array $manifest,
        public string $contentSha256,
        public string $fileSha256,
        public array $capabilities,
        public ?array $trustEnvelope,
    ) {}
}

/** Admission binds validation, signature/capability policy and generation identity. */
final class BookAdmissionPolicy
{
    /**
     * @param list<string> $requiredCapabilities
     * @param null|callable(string):?string $publicKeyResolver key_id -> raw Ed25519 public key
     */
    public function __construct(
        private readonly array $requiredCapabilities = [],
        private readonly mixed $publicKeyResolver = null,
        private readonly bool $requireSignature = false,
    ) {}

    /** @param array<string,mixed>|null $trustEnvelope */
    public function admit(int $generation, string $bytes, ?array $trustEnvelope = null): AdmittedBook
    {
        if ($generation < 1) throw new BookGenerationException('Book generation must be >= 1');
        $validated = JxlBook64::validate($bytes);
        $caps = [];

        if ($trustEnvelope === null) {
            if ($this->requireSignature) throw new BookGenerationException('Signed Book trust envelope required');
            if ($this->requiredCapabilities !== []) throw new BookGenerationException('Unsigned Book cannot satisfy required capabilities');
        } else {
            $keyId = $trustEnvelope['key_id'] ?? null;
            if (!is_string($keyId) || $keyId === '') throw new BookGenerationException('Trust envelope has no key_id');
            if (!is_callable($this->publicKeyResolver)) throw new BookGenerationException('No Book trust key resolver configured');
            $public = ($this->publicKeyResolver)($keyId);
            if (!is_string($public) || $public === '') throw new BookGenerationException("Unknown Book trust key {$keyId}");
            try {
                $ok = BookTrust::verify($bytes, $trustEnvelope, $public, $this->requiredCapabilities);
            } catch (\Throwable $e) {
                throw new BookGenerationException('Book trust verification failed: ' . $e->getMessage(), 0, $e);
            }
            if (!$ok) throw new BookGenerationException('Book trust signature invalid');
            $caps = BookTrust::normalizeCapabilities(is_array($trustEnvelope['capabilities'] ?? null) ? array_values($trustEnvelope['capabilities']) : []);
        }

        return new AdmittedBook(
            $generation,
            $bytes,
            $validated['manifest'],
            $validated['content_sha256'],
            $validated['file_sha256'],
            $caps,
            $trustEnvelope,
        );
    }
}

/**
 * Transactional live-generation coordinator.
 *
 * State is deliberately represented as host-neutral arrays here. Production
 * Bag stores can map snapshot/restore/migrate onto their own persistent pages.
 * Candidate code never replaces active code until migration and activation
 * checks have succeeded.
 */
final class BookGenerationManager
{
    private ?AdmittedBook $active = null;
    private ?AdmittedBook $candidate = null;
    /** @var array<string,mixed> */ private array $state = [];
    /** @var list<array{book:AdmittedBook,state:array<string,mixed>}> */ private array $history = [];

    public function __construct(private readonly BookAdmissionPolicy $policy) {}

    public function active(): ?AdmittedBook { return $this->active; }
    public function candidate(): ?AdmittedBook { return $this->candidate; }
    /** @return array<string,mixed> */ public function state(): array { return $this->state; }

    /** @param array<string,mixed> $state */
    public function seedState(array $state): void
    {
        if ($this->active !== null) throw new BookGenerationException('Cannot seed state after activation');
        $this->state = self::copy($state);
    }

    /** @param array<string,mixed>|null $trustEnvelope */
    public function stage(string $bytes, ?array $trustEnvelope = null): AdmittedBook
    {
        $next = ($this->active?->generation ?? 0) + 1;
        $candidate = $this->policy->admit($next, $bytes, $trustEnvelope);
        if ($this->active !== null && hash_equals($this->active->fileSha256, $candidate->fileSha256)) {
            throw new BookGenerationException('Candidate Book is identical to active generation');
        }
        $this->candidate = $candidate;
        return $candidate;
    }

    /**
     * @param callable(array<string,mixed>,?AdmittedBook,AdmittedBook):array<string,mixed> $migrate
     * @param null|callable(AdmittedBook,array<string,mixed>):void $preActivate
     */
    public function activate(callable $migrate, ?callable $preActivate = null): AdmittedBook
    {
        if ($this->candidate === null) throw new BookGenerationException('No candidate Book staged');
        $candidate = $this->candidate;
        $oldBook = $this->active;
        $oldState = self::copy($this->state);

        try {
            $newState = $migrate(self::copy($oldState), $oldBook, $candidate);
            if (!is_array($newState)) throw new BookGenerationException('Book migration must return state array');
            if ($preActivate !== null) $preActivate($candidate, self::copy($newState));
        } catch (\Throwable $e) {
            $this->candidate = null;
            $this->active = $oldBook;
            $this->state = $oldState;
            if ($e instanceof BookGenerationException) throw $e;
            throw new BookGenerationException('Candidate activation failed: ' . $e->getMessage(), 0, $e);
        }

        if ($oldBook !== null) $this->history[] = ['book' => $oldBook, 'state' => $oldState];
        $this->state = self::copy($newState);
        $this->active = $candidate;
        $this->candidate = null;
        return $candidate;
    }

    public function discardCandidate(): void { $this->candidate = null; }

    public function rollback(): AdmittedBook
    {
        $previous = array_pop($this->history);
        if ($previous === null) throw new BookGenerationException('No prior Book generation to roll back to');
        $this->candidate = null;
        $this->active = $previous['book'];
        $this->state = self::copy($previous['state']);
        return $this->active;
    }

    public function historyDepth(): int { return count($this->history); }

    /** @param array<string,mixed> $value @return array<string,mixed> */
    private static function copy(array $value): array
    {
        // State admitted to this reference coordinator must be serializable
        // semantic data, not PHP resources/pointers/closures.
        $serialized = serialize($value);
        $copy = unserialize($serialized, ['allowed_classes' => false]);
        if (!is_array($copy)) throw new BookGenerationException('State snapshot is not serializable semantic data');
        return $copy;
    }
}
