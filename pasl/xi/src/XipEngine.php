<?php declare(strict_types=1);
require_once __DIR__ . '/HostProtocol.php';
$__p = __DIR__ . '/XipEngine.assembled.php';
$__body = (string)file_get_contents(__DIR__.'/XipEngine.h1.php') . (string)file_get_contents(__DIR__.'/XipEngine.h2.php');
if (!is_file($__p) || file_get_contents($__p) !== $__body) {
    file_put_contents($__p, $__body, LOCK_EX);
}
unset($__body);
require $__p;
