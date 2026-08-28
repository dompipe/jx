<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\NativeBook64;

$dir = sys_get_temp_dir() . '/jx64-' . bin2hex(random_bytes(6));
if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
    throw new RuntimeException('cannot create JX64 test directory');
}

try {
    $sections = [
        'BOOK/pages.bin' => "\x01\x02\x03pages",
        'CODE/native.bin' => "\x7fELFjx-native-code",
        'HOT/reactions.bin' => "\x03\x0c\x01\x03",
        'HOT/registers.bin' => "W0\0desktop-windows\0W3\0controls\0",
        'BAG/schema.bin' => "windows\0controls\0",
    ];
    $meta = [
        'book' => 'desktop',
        'arch' => 'x86_64',
        'target' => 'linux-elf',
        'compiler' => 'jx-test',
    ];

    $a = $dir . '/desktop.jxb';
    $b = $dir . '/renamed.anything';
    $c = $dir . '/desktop-second.jxb';

    $buildA = NativeBook64::build($a, $sections, $meta);
    $buildC = NativeBook64::build($c, array_reverse($sections, true), array_reverse($meta, true));

    if ($buildA['content_sha256'] !== $buildC['content_sha256']) {
        throw new RuntimeException('JX64 canonical content hash is not deterministic');
    }
    if ($buildA['file_sha256'] !== $buildC['file_sha256']) {
        throw new RuntimeException('JX64 outer archive is not byte-deterministic');
    }
    if (file_get_contents($a) !== file_get_contents($c)) {
        throw new RuntimeException('JX64 deterministic builds differ byte-for-byte');
    }

    if (!copy($a, $b)) throw new RuntimeException('cannot rename/copy JX64 test package');
    if (!NativeBook64::recognizes($b)) {
        throw new RuntimeException('JX64 recognition incorrectly depends on file extension');
    }

    $loaded = NativeBook64::load($b);
    if (($loaded['manifest']['format'] ?? null) !== NativeBook64::VERSION ||
        ($loaded['manifest']['book'] ?? null) !== 'desktop' ||
        ($loaded['manifest']['target'] ?? null) !== 'linux-elf') {
        throw new RuntimeException('JX64 manifest identity mismatch');
    }
    if ($loaded['content_sha256'] !== $buildA['content_sha256'] ||
        $loaded['file_sha256'] !== $buildA['file_sha256']) {
        throw new RuntimeException('JX64 checksum verification mismatch');
    }
    foreach ($sections as $name => $bytes) {
        if (($loaded['sections'][$name] ?? null) !== $bytes) {
            throw new RuntimeException("JX64 section mismatch: {$name}");
        }
    }

    $fake = $dir . '/fake.jxb';
    file_put_contents($fake, 'not a compiled JX book');
    if (NativeBook64::recognizes($fake)) {
        throw new RuntimeException('JX64 loader trusted .jxb extension instead of package identity');
    }

    echo 'jx-native-book64 (.jxb public extension): ok content=', $buildA['content_sha256'],
         ' file=', $buildA['file_sha256'], "\n";
} finally {
    foreach (glob($dir . '/*') ?: [] as $file) @unlink($file);
    @rmdir($dir);
}
