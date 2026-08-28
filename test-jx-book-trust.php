<?php declare(strict_types=1);

require_once __DIR__ . '/jx-book-trust.php';

use jx\semantic\BookTrust;
use jx\semantic\JxlBook64;
use jx\semantic\SemanticException;

$source = <<<'JX'
int $x = 2;
repeat (3) {
    $x += 4;
}
JX;

$book = JxlBook64::compile($source, 'trust-fixture');
$caps = BookTrust::normalizeCapabilities(['bag.write', 'clock.read', 'bag.write']);
assert($caps === ['bag.write', 'clock.read']);

if (!BookTrust::sodiumAvailable()) {
    echo "PASS Book trust capability law; Ed25519 unavailable on this PHP build\n";
    exit(0);
}

$key = BookTrust::keypair('jx-book-trust-test-seed');
$envelope = BookTrust::sign($book['bytes'], $caps, 'jx-ci', $key['key_id'], $key['secret']);
assert($envelope['format'] === BookTrust::FORMAT);
assert($envelope['algorithm'] === BookTrust::ALGORITHM);
assert($envelope['content_sha256'] === $book['content_sha256']);
assert($envelope['file_sha256'] === $book['file_sha256']);
assert($envelope['capabilities'] === ['bag.write', 'clock.read']);
assert(BookTrust::verify($book['bytes'], $envelope, $key['public'], ['bag.write']) === true);

$bad = $envelope;
$bad['issuer'] = 'attacker';
assert(BookTrust::verify($book['bytes'], $bad, $key['public']) === false);

$threw = false;
try {
    BookTrust::verify($book['bytes'], $envelope, $key['public'], ['network.connect']);
} catch (SemanticException $e) {
    $threw = str_contains($e->getMessage(), 'lacks required capability');
}
assert($threw);

$tampered = $book['bytes'];
$tampered[20] = chr(ord($tampered[20]) ^ 1);
$threw = false;
try {
    BookTrust::verify($tampered, $envelope, $key['public']);
} catch (Throwable) {
    $threw = true;
}
assert($threw);

echo "PASS Book Ed25519 trust/capability envelope\n";
