<?php declare(strict_types=1);

/**
 * Canonical JX v0.1 bootstrap.
 *
 * Server adapters and applications include this boundary once rather than
 * repeatedly including individual runtime files from Pages.
 */

require_once dirname(__DIR__) . '/jx.php';
require_once __DIR__ . '/Flow.php';
require_once __DIR__ . '/SQL.php';
require_once __DIR__ . '/BindingCoercion.php';

$control = dirname(__DIR__) . '/pasl/xi/src/Control.php';
if (is_file($control)) require_once $control;
