<?php declare(strict_types=1);
function pasm_x86_body(): string {
    $a = file_get_contents(__DIR__ . '/pasm-lang-x86.p0.b64');
    $b = file_get_contents(__DIR__ . '/pasm-lang-x86.p1.b64');
    $c = file_get_contents(__DIR__ . '/pasm-lang-x86.p2.b64');
    return zlib_decode(base64_decode($a.$b.$c));
}
