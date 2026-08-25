<?php declare(strict_types=1);
$root = dirname(__DIR__);
require $root . '/src/Bag.php';
require $root . '/src/ChannelBus.php';
require $root . '/src/Binding.php';
require $root . '/src/SegmentPipe.php';
require $root . '/src/Ladder.php';
require $root . '/src/Book.php';
require $root . '/src/XipEngine.php';

$engine = new XipEngine($root . '/books', $root . '/data', ['default_book' => 'cover']);
$r = $engine->handle(['method' => 'GET', 'path' => '/', 'get' => ['book' => 'cover'], 'post' => []]);
echo ($r['status'] === 200 && str_contains($r['body'], 'xi-root')) ? "smoke OK GET\n" : "smoke FAIL GET\n";

$r2 = $engine->handle([
    'method' => 'POST',
    'path' => '/',
    'get' => [],
    'post' => ['book' => 'cover', 'protocol' => 'home.save', 'name' => 'Ada'],
]);
echo ($r2['status'] === 200 && str_contains($r2['body'], 'Ada')) ? "smoke OK POST\n" : "smoke FAIL POST\n";

$r3 = $engine->handle([
    'method' => 'POST',
    'path' => '/',
    'post' => ['book' => 'cover', 'protocol' => 'book.turn', 'dir' => 'forward'],
]);
echo ($r3['status'] === 200 && str_contains($r3['body'], 'About')) ? "smoke OK TURN\n" : "smoke FAIL TURN\n";
