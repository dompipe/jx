<?php declare(strict_types=1);
namespace jx\plugins\complex;
$root = dirname(__DIR__, 2);
require_once $root . '/jx.php';
return ['id' => 'complex', 'ok' => class_exists(\jx\Complex::class)];
