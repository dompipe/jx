<?php declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/jx/bootstrap.php';

use jx\NativeBook64;

if ($argc !== 4) {
    fwrite(STDERR, "usage: php examples/store/package-native.php <target> <native-binary> <output.jxb>\n");
    exit(2);
}

[$script, $target, $binaryPath, $output] = $argv;
$target = strtolower(trim($target));
if (!in_array($target, ['linux-elf', 'windows-pe'], true)) {
    throw new RuntimeException("Unsupported native store target: {$target}");
}

$native = file_get_contents($binaryPath);
if ($native === false || $native === '') {
    throw new RuntimeException("Cannot read compiled native binary: {$binaryPath}");
}
$manifestPath = __DIR__ . '/build/manifest.json';
$manifest = file_get_contents($manifestPath);
if ($manifest === false) {
    throw new RuntimeException('Store compiler manifest is missing');
}

$codeName = $target === 'linux-elf' ? 'CODE/program.elf' : 'CODE/program.pe';
// NativeBook64 is the internal v1 JX64B001 packer; .jxb is the public Book name.
$result = NativeBook64::build($output, [
    'BOOK/pages.json' => $manifest,
    $codeName => $native,
    // The first real Store package has no dynamic UI registers yet; these
    // zero-version tables reserve stable compiled sections for the common ABI.
    'HOT/registers.bin' => "JXREG\x00\x01\x00",
    'HOT/reactions.bin' => "JXREACT\x00\x01\x00",
], [
    'book'=>'jx-store',
    'arch'=>'x86_64',
    'target'=>$target,
    'compiler'=>'jx-store-build',
    'entry'=>$codeName,
]);

printf("jx-store: packaged %s content=%s file=%s bytes=%d\n",
    $output, $result['content_sha256'], $result['file_sha256'], $result['bytes']);
