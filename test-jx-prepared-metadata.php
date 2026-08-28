<?php declare(strict_types=1);

require_once __DIR__ . '/jx-prepared-metadata.php';

use jx\semantic\Compiler;
use jx\semantic\PreparedMetadata;
use jx\semantic\PreparedType;

$source = <<<'JX'
namespace demo.core;

function add(int $a, int $b): int {
    return $a + $b;
}

class Counter {
    int $value = 0;
    public function bump(int $n): int {
        $value += $n;
        return $value;
    }
}

int $total = add(2, 3);
if ($total > 4) {
    $total += 1;
}
JX;

$program = (new Compiler())->parse($source);
$a = PreparedMetadata::build($program);
$b = PreparedMetadata::build($program);

assert($a === $b);
assert($a['format'] === 'jx.prepared-metadata/1');
assert($a['type_ids']['int'] === PreparedType::INT);
assert($a['type_ids']['bag'] === PreparedType::BAG);
assert($a['type_ids']['handle'] === PreparedType::HANDLE);
assert($a['type_ids']['<user-type>'] === PreparedType::USER);
assert(count($a['functions']) === 1);
assert($a['functions'][0]['name'] === 'add');
assert($a['functions'][0]['return_type_id'] === PreparedType::INT);
assert($a['functions'][0]['params'][0]['type_id'] === PreparedType::INT);
assert(count($a['classes']) === 1);
assert($a['classes'][0]['name'] === 'Counter');
assert($a['classes'][0]['properties'][0]['name'] === 'value');
assert($a['classes'][0]['properties'][0]['type_id'] === PreparedType::INT);
assert(count($a['source_map']) > 5);

$lines = array_column($a['source_map'], 'line');
assert(max($lines) >= 14);
foreach ($a['source_map'] as $entry) {
    assert(is_int($entry['index']));
    assert($entry['index'] >= 0);
    assert(is_string($entry['op']) && $entry['op'] !== '');
    assert(is_int($entry['type_id']));
    assert(is_int($entry['line']));
}

$json1 = PreparedMetadata::json($program);
$json2 = PreparedMetadata::json($program);
assert($json1 === $json2);

echo "PASS prepared metadata type IDs/source map\n";
