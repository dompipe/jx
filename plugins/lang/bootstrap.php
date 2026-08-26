<?php declare(strict_types=1);
namespace jx\plugins\lang;
$root = dirname(__DIR__, 2);
require_once $root . '/jx-lang.php';
return ['id' => 'lang', 'ok' => class_exists(\jx\JxEngine::class)];
