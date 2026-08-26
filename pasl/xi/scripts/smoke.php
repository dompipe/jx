<?php declare(strict_types=1);
$root = dirname(__DIR__);
require $root . '/src/Bag.php';
require $root . '/src/ChannelBus.php';
require $root . '/src/Binding.php';
require $root . '/src/SegmentPipe.php';
require $root . '/src/Ladder.php';
require $root . '/src/Book.php';
require $root . '/src/Control.php';
require $root . '/src/HostProtocol.php';
require $root . '/src/XipEngine.php';

$data = sys_get_temp_dir() . '/jx-xip-smoke-' . getmypid();
$engine = new XipEngine($root . '/books', $data, ['default_book' => 'cover']);
$r = $engine->handle(['method' => 'GET', 'path' => '/', 'get' => ['book' => 'cover'], 'post' => []]);
echo ($r['status'] === 200 && str_contains($r['body'], 'xi-root')) ? "smoke OK GET\n" : "smoke FAIL GET\n";
echo (str_contains($r['body'], 'application/jx-pasl') && str_contains($r['body'], 'pasl-vm.js')) ? "smoke OK BROWSER PASL\n" : "smoke FAIL BROWSER PASL\n";

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

$r4 = $engine->handle([
    'method' => 'POST',
    'path' => '/jx/drop',
    'get' => ['book' => 'cover'],
    'post' => [],
    'json' => [
        'version' => 'jx.host/1', 'type' => 'pasl.result', 'host' => 'browser',
        'window' => 'cover-main', 'book' => 'cover', 'leaf' => 'home',
        'sequence' => 1, 'payload' => ['result' => '15'],
    ],
]);
echo ($r4['status'] === 202 && str_contains($r4['body'], 'accepted')) ? "smoke OK HOST DROP\n" : "smoke FAIL HOST DROP\n";
