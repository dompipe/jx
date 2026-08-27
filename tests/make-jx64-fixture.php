<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/jx/bootstrap.php';

use jx\NativeBook64;

$out = $argv[1] ?? '';
if ($out === '') {
    fwrite(STDERR, "usage: php tests/make-jx64-fixture.php <output>\n");
    exit(2);
}

$result = NativeBook64::build($out, [
    'BOOK/pages.bin' => "page-table\x00\x01",
    'CODE/native.bin' => "native-code\x7f",
    'HOT/registers.bin' => "W0\0windows\0",
], [
    'book'=>'probe-fixture',
    'arch'=>'x86_64',
    'target'=>'native-test',
    'compiler'=>'jx-ci',
]);

echo $result['content_sha256'], " ", $result['file_sha256'], "\n";
