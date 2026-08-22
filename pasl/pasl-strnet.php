<?php declare(strict_types=1);
$_p = __DIR__ . '/.pasl-strnet.body.php';
if (!is_file($_p)) {
    require_once __DIR__ . '/pasl-strnet.payload.php';
    file_put_contents($_p, pasl_strnet_body());
}
require_once $_p;
