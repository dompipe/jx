<?php declare(strict_types=1);
$_p = __DIR__ . '/.pasl-front.payload.body.php';
if (!is_file($_p)) {
    require_once __DIR__ . '/pasl-front.payload.php';
    file_put_contents($_p, pasl_front_body());
}
require_once $_p;
