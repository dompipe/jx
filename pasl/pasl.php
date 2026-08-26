<?php declare(strict_types=1);
/**
 * PASL package entry — single require for the whole language surface.
 *
 *   require 'pasl/pasl.php';
 *
 * Loads numeric core + full surface (strnet) when present.
 *   pasl\Compiler          — multi-target numeric
 *   pasl\strnet\Compiler   — strings / arrays / bags / network → C
 *   pasl\Package           — auto-route toC / compile()
 */
require_once __DIR__ . '/pasl-front.php';
require_once __DIR__ . '/pasl-back.php';

$_pasl_strnet = __DIR__ . '/pasl-strnet.php';
if (is_file($_pasl_strnet)) {
    try {
        require_once $_pasl_strnet;
    } catch (\Throwable) {
        // Numeric PASL remains usable when the optional full-surface payload is absent or damaged.
    }
}

require_once __DIR__ . '/pasl-package.php';
