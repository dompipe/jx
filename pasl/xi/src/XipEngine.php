<?php declare(strict_types=1);
$__p = __DIR__ . '/XipEngine.assembled.php';
if (!is_file($__p)) {
    file_put_contents($__p, file_get_contents(__DIR__.'/XipEngine.h1.php').file_get_contents(__DIR__.'/XipEngine.h2.php'));
}
require $__p;
