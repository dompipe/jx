<?php declare(strict_types=1);
namespace jx\plugins\constbox;
$root = require __DIR__ . '/runtime-root.php';
require_once $root . '/jx.php';
return ['id' => 'const', 'ok' => function_exists('jx\\jx_const') || class_exists(\jx\ConstBox::class)];
