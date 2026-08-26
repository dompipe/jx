<?php declare(strict_types=1);
namespace jx\plugins\delivery;
$root = dirname(__DIR__, 2);
require_once $root . '/jx.php';
return ['id' => 'delivery', 'ok' => class_exists(\jx\Delivery::class)];
