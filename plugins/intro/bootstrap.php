<?php declare(strict_types=1);
namespace jx\plugins\intro;
$doc = dirname(__DIR__, 2) . '/jx/INTRO.md';
return ['id' => 'intro', 'ok' => is_file($doc), 'doc' => $doc];
