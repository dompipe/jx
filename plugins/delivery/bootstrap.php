<?php declare(strict_types=1);
namespace jx\plugins\delivery;
$root = require __DIR__ . '/runtime-root.php';
require_once $root . '/jx.php';
return ['id' => 'delivery', 'ok' => class_exists(\jx\Delivery::class)];
