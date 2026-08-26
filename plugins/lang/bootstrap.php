<?php declare(strict_types=1);
namespace jx\plugins\lang;
$root = require __DIR__ . '/runtime-root.php';
require_once $root . '/jx-lang.php';
return ['id' => 'lang', 'ok' => class_exists(\jx\JxEngine::class)];
