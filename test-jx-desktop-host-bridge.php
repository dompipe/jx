<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\Bag;
use jx\DesktopHostBridge;

$bag = Bag::underwrite(65536);
$bridge = new DesktopHostBridge($bag, 'windows');

$bridge->apply([
    'event' => 'window-open',
    'window' => [
        'host_id' => 'x11:1001', 'pid' => 41, 'title' => 'Terminal', 'class' => 'XTerm',
        'x' => 10, 'y' => 20, 'width' => 800, 'height' => 600, 'focused' => true,
    ],
]);
$rows = $bag->read('windows');
if (!is_array($rows) || count($rows) !== 1 || ($rows[0]['title'] ?? null) !== 'Terminal') {
    throw new RuntimeException('desktop host bridge did not publish window-open');
}

$bridge->apply([
    'event' => 'window-open',
    'window' => [
        'host_id' => 'x11:1002', 'pid' => 42, 'title' => 'Browser', 'class' => 'Firefox',
        'x' => 40, 'y' => 50, 'width' => 1024, 'height' => 768,
    ],
]);
$bridge->apply([
    'event' => 'window-focus',
    'window' => ['host_id' => 'x11:1002'],
]);
$rows = $bag->read('windows');
if (count($rows) !== 2) throw new RuntimeException('desktop host bridge lost a window');
$by = [];
foreach ($rows as $row) $by[$row['host_id']] = $row;
if (($by['x11:1002']['focused'] ?? false) !== true || ($by['x11:1001']['focused'] ?? true) !== false) {
    throw new RuntimeException('desktop host bridge focus exclusivity failed');
}
if (($by['x11:1002']['pid'] ?? null) !== 42 || ($by['x11:1002']['class'] ?? '') !== 'Firefox'
    || ($by['x11:1002']['x'] ?? null) !== 40 || ($by['x11:1002']['y'] ?? null) !== 50
    || ($by['x11:1002']['width'] ?? null) !== 1024 || ($by['x11:1002']['height'] ?? null) !== 768) {
    throw new RuntimeException('desktop host bridge focus patch erased existing metadata');
}

$bridge->apply([
    'event' => 'window-update',
    'window' => ['host_id' => 'x11:1002', 'title' => 'JX Browser', 'x' => 60],
]);
$rows = $bag->read('windows');
$by = [];
foreach ($rows as $row) $by[$row['host_id']] = $row;
if (($by['x11:1002']['title'] ?? '') !== 'JX Browser' || ($by['x11:1002']['x'] ?? null) !== 60) {
    throw new RuntimeException('desktop host bridge update failed');
}
if (($by['x11:1002']['pid'] ?? null) !== 42 || ($by['x11:1002']['class'] ?? '') !== 'Firefox'
    || ($by['x11:1002']['y'] ?? null) !== 50 || ($by['x11:1002']['width'] ?? null) !== 1024
    || ($by['x11:1002']['height'] ?? null) !== 768 || ($by['x11:1002']['focused'] ?? false) !== true) {
    throw new RuntimeException('desktop host bridge partial update erased existing metadata');
}

$bridge->apply(['event' => 'window-close', 'host_id' => 'x11:1001']);
$rows = $bag->read('windows');
if (count($rows) !== 1 || ($rows[0]['host_id'] ?? '') !== 'x11:1002') {
    throw new RuntimeException('desktop host bridge close failed');
}

echo "jx-desktop-host-bridge: ok\n";
