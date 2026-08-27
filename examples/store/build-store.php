<?php declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/pasm-lang.php';
require_once dirname(__DIR__, 2) . '/pasm-lang-x86.php';

use pasm\lang\Compiler;
use pasm\lang\X86Compiler;

$root = __DIR__;
$build = $root . '/build';
$browserDir = $build . '/browser';
@mkdir($browserDir, 0777, true);

$pages = [
    'books/home'       => 'books/books/pages/home.jx',
    'books/catalog'    => 'books/books/pages/catalog.jx',
    'books/specials'   => 'books/books/pages/specials.jx',
    'turtles/home'     => 'books/turtles/pages/home.jx',
    'turtles/habitats' => 'books/turtles/pages/habitats.jx',
    'turtles/care'     => 'books/turtles/pages/care.jx',
];

$linuxAsm = [];
$windowsAsm = [];
$manifest = [];
$compiler = new Compiler(true, false);
$x86 = new X86Compiler(true);

$symbolFor = static fn(string $id): string => 'jx_' . str_replace('/', '_', $id);

foreach ($pages as $id => $relative) {
    $path = $root . '/' . $relative;
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException("Cannot read {$relative}");
    }

    $pasm = $compiler->compile($source);
    $browserFile = str_replace('/', '-', $id) . '.pasm';
    file_put_contents($browserDir . '/' . $browserFile, $pasm);

    $symbol = $symbolFor($id);
    $asm = $x86->compile($source);
    $asm = preg_replace('/\bpasl_main\b/', $symbol, $asm) ?? $asm;
    $linuxAsm[] = $asm;

    $coff = preg_replace('/^\s*\.type\s+.*$/m', '', $asm) ?? $asm;
    $coff = preg_replace('/^\s*\.size\s+.*$/m', '', $coff) ?? $coff;
    $windowsAsm[] = trim($coff) . "\n";

    $manifest[$id] = [
        'source' => $relative,
        'browser' => 'build/browser/' . $browserFile,
        'symbol' => $symbol,
    ];
}

file_put_contents($build . '/store-linux.s', implode("\n", $linuxAsm));
file_put_contents($build . '/store-windows.s', implode("\n", $windowsAsm));
file_put_contents($build . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$externs = [];
$routes = [];
foreach (array_keys($pages) as $id) {
    $symbol = $symbolFor($id);
    $externs[] = "extern int64_t {$symbol}(void);";
    $routes[] = "    if (strcmp(page, \"{$id}\") == 0) return print_result(page, {$symbol}());";
}

$c = '#include <inttypes.h>' . "\n"
   . '#include <stdint.h>' . "\n"
   . '#include <stdio.h>' . "\n"
   . '#include <string.h>' . "\n\n"
   . implode("\n", $externs) . "\n\n"
   . "static int print_result(const char *page, int64_t value) {\n"
   . "    printf(\"%s=%\" PRId64 \"\\n\", page, value);\n"
   . "    return 0;\n"
   . "}\n\n"
   . "int main(int argc, char **argv) {\n"
   . "    const char *page = argc > 1 ? argv[1] : \"books/home\";\n"
   . implode("\n", $routes) . "\n"
   . "    fprintf(stderr, \"unknown JX store page: %s\\n\", page);\n"
   . "    return 2;\n"
   . "}\n";
file_put_contents($build . '/store.c', $c);

echo "jx-store: built 6 canonical JX pages for browser, Linux ELF, and Windows PE/COFF\n";
