<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxl-book64.php';

use jx\semantic\JxlBook64;
use jx\semantic\JxlVm;

$source = <<<'JX'
function twice(int $x): int {
    return $x * 2;
}
int $i = 0;
int $sum = 0;
for ($i = 0; $i < 8; $i++) {
    $sum += twice($i);
}
$sum;
JX;

$a = JxlBook64::compile($source, 'book-test');
$b = JxlBook64::compile($source, 'book-test');
assert($a['bytes'] === $b['bytes']);
assert($a['file_sha256'] === $b['file_sha256']);
assert(substr($a['bytes'], 0, 4) === "PK\x03\x04");

$v = JxlBook64::validate($a['bytes']);
assert($v['manifest']['format'] === 'jx.64B/1');
assert($v['manifest']['native_target'] === 'jxl');
assert(isset($v['entries']['CODE/program.jxl']));
assert(!isset($v['entries']['SOURCE/program.jx']));
assert(substr($v['entries']['JX64/header.bin'], 0, 8) === 'JX64B001');
assert($v['content_sha256'] === $a['content_sha256']);
assert($v['file_sha256'] === $a['file_sha256']);

$result = (new JxlVm())->run($v['entries']['CODE/program.jxl']);
assert($result === 56);

$tmp = sys_get_temp_dir() . '/jx-book-' . bin2hex(random_bytes(4)) . '.not64b';
file_put_contents($tmp, $a['bytes']);
$bytes = file_get_contents($tmp);
unlink($tmp);
assert(is_string($bytes));
assert(JxlBook64::validate($bytes)['manifest']['book'] === 'book-test');

echo "jx canonical -> JXL -> deterministic .64B: ok\n";
