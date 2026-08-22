<?php declare(strict_types=1);
function pasl_back_body(): string {
    $a = file_get_contents(__DIR__ . '/pasl-back.p0.b64');
    $b = file_get_contents(__DIR__ . '/pasl-back.p1.b64');
    $c = file_get_contents(__DIR__ . '/pasl-back.p2.b64');
    return zlib_decode(base64_decode($a.$b.$c));
}
