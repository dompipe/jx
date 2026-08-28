<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxb.php';

use jx\semantic\JxbBook;
use jx\semantic\SemanticException;

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

$dir = sys_get_temp_dir() . '/jxb-' . bin2hex(random_bytes(6));
if (!mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('cannot create JXB test directory');

try {
    $sourcePath = $dir . '/sample.jx';
    file_put_contents($sourcePath, $source);

    $compiled = JxbBook::compileFile($sourcePath);
    assert($compiled['path'] === $dir . '/sample.jxb');
    assert(is_file($compiled['path']));
    assert(pathinfo($compiled['path'], PATHINFO_EXTENSION) === 'jxb');

    $loaded = JxbBook::loadFile($compiled['path']);
    assert(($loaded['manifest']['format'] ?? null) === JxbBook::INTERNAL_FORMAT);
    assert(substr($loaded['entries']['JX64/header.bin'], 0, 8) === JxbBook::INTERNAL_MAGIC);
    assert(isset($loaded['entries'][JxbBook::CODE_PATH]));
    assert(isset($loaded['entries'][JxbBook::PREPARED_PATH]));
    assert(!isset($loaded['entries']['SOURCE/program.jx']));

    // 0+3+6+9+12 = 30. This proves the admitted Book executes its prepared JXL.
    assert(JxbBook::runFile($compiled['path']) === 30);

    // Admission consumes prepared type IDs once and refuses representation drift.
    $bad = $loaded;
    $prepared = json_decode($bad['entries'][JxbBook::PREPARED_PATH], true, flags: JSON_THROW_ON_ERROR);
    $prepared['type_ids']['int'] = 99;
    $bad['entries'][JxbBook::PREPARED_PATH] = json_encode($prepared, JSON_THROW_ON_ERROR);
    $rejected = false;
    try { JxbBook::admit($bad); } catch (SemanticException $e) { $rejected = $e->phase === 'jxb-admission'; }
    assert($rejected);

    // Public extension is conventional, not trusted: admitted bytes still identify the Book.
    $renamed = $dir . '/sample.bin';
    copy($compiled['path'], $renamed);
    assert(JxbBook::runFile($renamed) === 30);

    echo "PASS JXB .jx -> .jxb -> typed admission -> JXL execution\n";
} finally {
    foreach (glob($dir . '/*') ?: [] as $file) @unlink($file);
    @rmdir($dir);
}
