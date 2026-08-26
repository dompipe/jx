<?php declare(strict_types=1);
namespace jx\plugins\intro;
$root = require __DIR__ . '/runtime-root.php';
$doc = $root . '/jx/INTRO.md';
return ['id' => 'intro', 'ok' => is_file($doc), 'doc' => $doc];
