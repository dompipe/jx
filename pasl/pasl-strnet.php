<?php declare(strict_types=1);
/**
 * PASL full-surface loader — materializes body from payload parts.
 * Integrated via pasl.php (require once).
 */
$_pasl_strnet_body = __DIR__ . '/.pasl-strnet.body.php';
if (!is_file($_pasl_strnet_body)) {
    require_once __DIR__ . '/pasl-strnet.payload.php';
    $src = pasl_strnet_body();
    if ($src === false || $src === '') {
        throw new RuntimeException('PASL: cannot decode pasl-strnet payload');
    }
    file_put_contents($_pasl_strnet_body, $src);
}
require_once $_pasl_strnet_body;
