<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxb.php';

use jx\semantic\BookTrust;
use jx\semantic\JxbBook;
use jx\semantic\SemanticException;

/*
 * Legacy compiled-Book regression.
 *
 * The implementation class retains its historical JxbBook name, but persisted
 * regression artifacts are explicit .64B so this test cannot redefine modern
 * .jxb, which is now the indexed compressed resource archive.
 */
$source = <<<'JX'
function triple(int $x): int {
    return $x * 3;
}
int $i = 0;
int $sum = 0;
for ($i = 0; $i < 5; $i++) {
    $sum += triple($i);
}
$sum;
JX;

$dir = sys_get_temp_dir() . '/jx64-' . bin2hex(random_bytes(6));
if (!mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('cannot create legacy Book test directory');

try {
    $sourcePath = $dir . '/sample.jx';
    $legacyPath = $dir . '/sample.64B';
    file_put_contents($sourcePath, $source);

    $compiled = JxbBook::compileFile($sourcePath, $legacyPath);
    assert($compiled['path'] === $legacyPath);
    assert(is_file($compiled['path']));
    assert(strtolower(pathinfo($compiled['path'], PATHINFO_EXTENSION)) === '64b');

    $loaded = JxbBook::loadFile($compiled['path']);
    assert(($loaded['manifest']['format'] ?? null) === JxbBook::INTERNAL_FORMAT);
    assert(substr($loaded['entries']['JX64/header.bin'], 0, 8) === JxbBook::INTERNAL_MAGIC);
    assert(isset($loaded['entries'][JxbBook::CODE_PATH]));
    assert(isset($loaded['entries'][JxbBook::PREPARED_PATH]));
    assert(!isset($loaded['entries']['SOURCE/program.jx']));

    // 0+3+6+9+12 = 30. This proves legacy admitted Book execution still works.
    assert(JxbBook::runFile($compiled['path']) === 30);

    // Admission consumes prepared type IDs once and refuses representation drift.
    $bad = $loaded;
    $prepared = json_decode($bad['entries'][JxbBook::PREPARED_PATH], true, flags: JSON_THROW_ON_ERROR);
    $prepared['type_ids']['int'] = 99;
    $bad['entries'][JxbBook::PREPARED_PATH] = json_encode($prepared, JSON_THROW_ON_ERROR);
    $rejected = false;
    try { JxbBook::admit($bad); } catch (SemanticException $e) { $rejected = $e->phase === 'jxb-admission'; }
    assert($rejected);

    $badFunction = $loaded;
    $prepared = json_decode($badFunction['entries'][JxbBook::PREPARED_PATH], true, flags: JSON_THROW_ON_ERROR);
    $prepared['functions'][0]['return_type_id'] = 99;
    $badFunction['entries'][JxbBook::PREPARED_PATH] = json_encode($prepared, JSON_THROW_ON_ERROR);
    $rejected = false;
    try { JxbBook::admit($badFunction); } catch (SemanticException $e) { $rejected = $e->phase === 'jxb-admission'; }
    assert($rejected);

    if (BookTrust::sodiumAvailable()) {
        $keys = BookTrust::keypair('jx64-regression');
        $envelope = BookTrust::sign($compiled['bytes'], ['bag.read','ui.present'], 'jx64-test', $keys['key_id'], $keys['secret']);
        assert(JxbBook::runTrusted($compiled['bytes'], $envelope, $keys['public'], ['bag.read']) === 30);

        $denied = false;
        try {
            JxbBook::runTrusted($compiled['bytes'], $envelope, $keys['public'], ['sql.write']);
        } catch (SemanticException $e) {
            $denied = $e->phase === 'trust';
        }
        assert($denied);
    }

    // Package identity is byte-based, not suffix-based, on the legacy path.
    $renamed = $dir . '/sample.bin';
    copy($compiled['path'], $renamed);
    assert(JxbBook::runFile($renamed) === 30);

    echo "PASS legacy .jx -> .64B -> typed/trusted prepared Book admission\n";
} finally {
    foreach (glob($dir . '/*') ?: [] as $file) @unlink($file);
    @rmdir($dir);
}
