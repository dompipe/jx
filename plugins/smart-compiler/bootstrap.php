<?php declare(strict_types=1);
/** Smart compiler plugin — SmartTable + optional PASM master-table bridge */
namespace jx\plugins\smartcompiler;

$root = dirname(__DIR__, 2);
require_once $root . '/jx.php';

$table = \jx\Jx::table();
// Register decimal + install-related rows
// (SmartTable seed is in jx.php; this plugin confirms extrude path)

return [
    'id' => 'smart-compiler',
    'ok' => $table->get('bag.set') !== null,
    'extrude_sample' => $table->extrude('bag.set'),
];
