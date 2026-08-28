<?php declare(strict_types=1);

namespace jx\semantic;

require_once __DIR__ . '/jx-jxl-book64.php';

/**
 * Trust/capability envelope for deterministic compiled Books.
 *
 * The envelope is deliberately detached from the .64B bytes. Signing an
 * envelope therefore does not change the deterministic Book identity. A
 * distributor may attach/store the JSON next to the Book or in a registry.
 */
final class BookTrust
{
    public const FORMAT = 'jx.book-trust/1';
    public const ALGORITHM = 'ed25519';

    /** @param list<string> $capabilities */
    public static function payload(string $bookBytes, array $capabilities, string $issuer, string $keyId): array
    {
        $validated = JxlBook64::validate($bookBytes);
        $caps = self::normalizeCapabilities($capabilities);
        return [
            'format' => self::FORMAT,
            'algorithm' => self::ALGORITHM,
            'issuer' => trim($issuer),
            'key_id' => trim($keyId),
            'book_format' => (string)$validated['manifest']['format'],
            'book_name' => (string)($validated['manifest']['book'] ?? ''),
            'content_sha256' => $validated['content_sha256'],
            'file_sha256' => $validated['file_sha256'],
            'capabilities' => $caps,
        ];
    }

    /** @param list<string> $capabilities */
    public static function sign(string $bookBytes, array $capabilities, string $issuer, string $keyId, string $secretKey): array
    {
        self::requireSodium();
        if (strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new SemanticException('Invalid Ed25519 secret key length', 'trust');
        }
        $payload = self::payload($bookBytes, $capabilities, $issuer, $keyId);
        if ($payload['issuer'] === '' || $payload['key_id'] === '') {
            throw new SemanticException('Trust issuer and key_id must be non-empty', 'trust');
        }
        $canonical = self::canonicalJson($payload);
        $signature = sodium_crypto_sign_detached($canonical, $secretKey);
        return $payload + ['signature' => base64_encode($signature)];
    }

    /**
     * @param array<string,mixed> $envelope
     * @param list<string> $requiredCapabilities
     */
    public static function verify(string $bookBytes, array $envelope, string $publicKey, array $requiredCapabilities = []): bool
    {
        self::requireSodium();
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new SemanticException('Invalid Ed25519 public key length', 'trust');
        }
        $signature64 = $envelope['signature'] ?? null;
        if (!is_string($signature64)) throw new SemanticException('Missing Book trust signature', 'trust');
        $signature = base64_decode($signature64, true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new SemanticException('Invalid Book trust signature encoding', 'trust');
        }
        $payload = $envelope;
        unset($payload['signature']);
        if (($payload['format'] ?? null) !== self::FORMAT || ($payload['algorithm'] ?? null) !== self::ALGORITHM) {
            throw new SemanticException('Unsupported Book trust envelope', 'trust');
        }

        $expected = self::payload(
            $bookBytes,
            is_array($payload['capabilities'] ?? null) ? array_values($payload['capabilities']) : [],
            (string)($payload['issuer'] ?? ''),
            (string)($payload['key_id'] ?? '')
        );
        if ($expected !== $payload) throw new SemanticException('Book trust envelope does not match Book bytes', 'trust');

        $available = array_fill_keys($expected['capabilities'], true);
        foreach (self::normalizeCapabilities($requiredCapabilities) as $cap) {
            if (!isset($available[$cap])) throw new SemanticException("Book lacks required capability {$cap}", 'trust');
        }

        return sodium_crypto_sign_verify_detached($signature, self::canonicalJson($payload), $publicKey);
    }

    /** @return array{public:string,secret:string,key_id:string} */
    public static function keypair(?string $seed = null): array
    {
        self::requireSodium();
        if ($seed !== null) {
            $raw = hash('sha256', $seed, true);
            $pair = sodium_crypto_sign_seed_keypair($raw);
        } else {
            $pair = sodium_crypto_sign_keypair();
        }
        $public = sodium_crypto_sign_publickey($pair);
        $secret = sodium_crypto_sign_secretkey($pair);
        return [
            'public' => $public,
            'secret' => $secret,
            'key_id' => substr(hash('sha256', $public), 0, 24),
        ];
    }

    /** @param list<string> $capabilities @return list<string> */
    public static function normalizeCapabilities(array $capabilities): array
    {
        $out = [];
        foreach ($capabilities as $cap) {
            $cap = strtolower(trim((string)$cap));
            if ($cap === '') continue;
            if (!preg_match('/^[a-z][a-z0-9._:-]{0,95}$/', $cap)) {
                throw new SemanticException("Invalid capability {$cap}", 'trust');
            }
            $out[$cap] = true;
        }
        $caps = array_keys($out);
        sort($caps, SORT_STRING);
        return $caps;
    }

    /** @param array<string,mixed> $value */
    public static function canonicalJson(array $value): string
    {
        $sorted = self::sortRecursive($value);
        return json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function sortRecursive(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map([self::class, 'sortRecursive'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $k => $v) $value[$k] = self::sortRecursive($v);
        return $value;
    }

    public static function sodiumAvailable(): bool
    {
        return function_exists('sodium_crypto_sign_detached')
            && defined('SODIUM_CRYPTO_SIGN_SECRETKEYBYTES');
    }

    private static function requireSodium(): void
    {
        if (!self::sodiumAvailable()) {
            throw new SemanticException('Ed25519 Book trust requires PHP sodium support', 'trust');
        }
    }
}
