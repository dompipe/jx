<?php declare(strict_types=1);
/** core plugin — loads main jx runtime mass */
namespace jx\plugins\core;

$root = require __DIR__ . '/runtime-root.php';
require_once $root . '/jx.php';

return [
    'id' => 'core',
    'ok' => class_exists(\jx\Bag::class),
];
