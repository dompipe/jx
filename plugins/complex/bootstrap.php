<?php declare(strict_types=1);
namespace jx\plugins\complex;
$root = require __DIR__ . '/runtime-root.php';
require_once $root . '/jx.php';
return ['id' => 'complex', 'ok' => class_exists(\jx\Complex::class)];
