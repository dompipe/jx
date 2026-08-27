<?php declare(strict_types=1);

require_once __DIR__ . '/pasl/xi/src/Book.php';

$store = __DIR__ . '/examples/store';
$build = $store . '/build';

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($store . '/build-store.php') . ' 2>&1';
exec($cmd, $lines, $status);
if ($status !== 0) {
    throw new RuntimeException("store build failed: " . implode("\n", $lines));
}

$booksRoot = $store . '/books';
foreach ([
    'books' => ['home', 'catalog', 'specials'],
    'turtles' => ['home', 'habitats', 'care'],
] as $bookId => $spine) {
    $book = Book::load($booksRoot, $bookId);
    if (!$book instanceof Book) throw new RuntimeException("Book {$bookId} did not load");
    if ($book->spine() !== $spine) throw new RuntimeException("Book {$bookId} spine mismatch");
    foreach ($spine as $page) {
        $path = $book->paslPath($page);
        if ($path === null || !str_ends_with($path, '.jx')) throw new RuntimeException("{$bookId}/{$page} JX leaf missing");
    }
}

$expected = [
    'books/home' => 96,
    'books/catalog' => 60,
    'books/specials' => 84,
    'turtles/home' => 16,
    'turtles/habitats' => 387,
    'turtles/care' => 113,
];

$node = trim((string)shell_exec('command -v node 2>/dev/null'));
if ($node !== '') {
    foreach ($expected as $page => $value) {
        $pasm = $build . '/browser/' . str_replace('/', '-', $page) . '.pasm';
        $run = escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/benchmark-target-browser.js') . ' ' . escapeshellarg($pasm) . ' 1 ' . $value;
        $out = []; $code = 0; exec($run . ' 2>&1', $out, $code);
        if ($code !== 0) throw new RuntimeException("browser {$page} failed: " . implode("\n", $out));
    }
}

$cc = trim((string)shell_exec('command -v cc 2>/dev/null'));
if ($cc !== '') {
    $exe = $build . '/jx-store-linux';
    $compile = escapeshellarg($cc)
        . ' -O2 -no-pie -I' . escapeshellarg(__DIR__ . '/host/common')
        . ' -o ' . escapeshellarg($exe)
        . ' ' . escapeshellarg($build . '/store.c')
        . ' ' . escapeshellarg($build . '/store-linux.s')
        . ' ' . escapeshellarg(__DIR__ . '/host/common/jx64-probe.c')
        . ' 2>&1';
    $out = []; $code = 0; exec($compile, $out, $code);
    if ($code !== 0) throw new RuntimeException("Linux native store link failed: " . implode("\n", $out));
    foreach ($expected as $page => $value) {
        $out = []; $code = 0; exec(escapeshellarg($exe) . ' ' . escapeshellarg($page) . ' 2>&1', $out, $code);
        $actual = trim(implode("\n", $out));
        $want = $page . '=' . $value;
        if ($code !== 0 || $actual !== $want) throw new RuntimeException("Linux {$page} mismatch {$actual} != {$want}");
    }

    if (class_exists(ZipArchive::class)) {
        $package = $build . '/jx-store-test.64B';
        $renamed = $build . '/jx-store-test.payload';
        $pack = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($store . '/package-native.php')
            . ' linux-elf ' . escapeshellarg($exe) . ' ' . escapeshellarg($package) . ' 2>&1';
        $out = []; $code = 0; exec($pack, $out, $code);
        if ($code !== 0 || !is_file($package)) throw new RuntimeException("Linux 64B package failed: " . implode("\n", $out));
        if (!copy($package, $renamed)) throw new RuntimeException('Linux 64B rename fixture failed');
        $out = []; $code = 0; exec(escapeshellarg($exe) . ' --probe ' . escapeshellarg($renamed) . ' 2>&1', $out, $code);
        $actual = trim(implode("\n", $out));
        if ($code !== 0 || !preg_match('/^JX64\/1\.0 sections=4$/', $actual)) {
            throw new RuntimeException("Linux native renamed 64B probe failed: {$actual}");
        }
        @unlink($package);
        @unlink($renamed);
    }
    @unlink($exe);
}

$windowsAsm = (string)file_get_contents($build . '/store-windows.s');
if (str_contains($windowsAsm, '.type ') || str_contains($windowsAsm, '.size ')) {
    throw new RuntimeException('Windows COFF assembly still contains ELF-only directives');
}
foreach (array_keys($expected) as $page) {
    $symbol = 'jx_' . str_replace('/', '_', $page);
    if (!str_contains($windowsAsm, '.globl ' . $symbol)) throw new RuntimeException("Windows symbol {$symbol} missing");
}

echo "PASS JX store 2 Books 6 pages same-source browser Linux-native Windows-COFF native-64B\n";
