<?php declare(strict_types=1);
namespace pasm\lang;
require_once __DIR__ . '/pasm-lang-core.php';
$_p = __DIR__ . '/.pasm-lang-x86.body.php';
if (!is_file($_p)) {
    // Body is written on first load from embedded payload in pasm-lang-x86.payload.php
    require_once __DIR__ . '/pasm-lang-x86.payload.php';
    file_put_contents($_p, pasm_x86_body());
}
require_once $_p;
