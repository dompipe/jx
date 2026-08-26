<?php declare(strict_types=1);
namespace jx\plugins\constbox;
$root = dirname(__DIR__, 2);
require_once $root . '/jx.php';
return ['id' => 'const', 'ok' => function_exists('jx\\jx_const') || class_exists(\jx\ConstBox::class)];
