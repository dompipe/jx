<?php declare(strict_types=1);

require_once __DIR__ . '/jx-lang.php';

use jx\JxEngine;

$engine = new JxEngine(true, false);
$result = $engine->runSource(<<<'JX'
book = Book.load("alias-demo");
bag = Bag.allocate(4096);
ref = bag.authorize("value");
bag.write(42).commit(ref);
free = bag.free();
JX);

if ($result !== 4088) {
    fwrite(STDERR, "FAIL alias execution: expected quotient 4088 got " . var_export($result,true) . "\n");
    exit(1);
}

$trace = $engine->aliasProvenance();
if (count($trace) < 5) {
    fwrite(STDERR, "FAIL alias provenance: expected >=5 canonicalized statements, got " . count($trace) . "\n");
    exit(1);
}

$joined = json_encode($trace, JSON_UNESCAPED_SLASHES);
foreach (['Book.load','Book.open','Bag.allocate','Bag.underwrite','authorize','sign','write','set','free','quotient'] as $needle) {
    if (!str_contains((string)$joined, $needle)) {
        fwrite(STDERR, "FAIL alias provenance missing {$needle}: {$joined}\n");
        exit(1);
    }
}

echo "PASS JX language aliases trace=" . count($trace) . "\n";
