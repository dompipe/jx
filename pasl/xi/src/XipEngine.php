<?php declare(strict_types=1);
/** XipEngine entry — decodes part1+part2 b64 once into XipEngine.body.php */
$__body = __DIR__ . '/XipEngine.body.php';
if (!is_file($__body)) {
    $b = base64_decode(
        (string)@file_get_contents(__DIR__ . '/XipEngine.part1.b64')
        . (string)@file_get_contents(__DIR__ . '/XipEngine.part2.b64')
    );
    if ($b === false || $b === '') {
        throw new RuntimeException('xi: missing XipEngine.part1/part2.b64');
    }
    file_put_contents($__body, $b);
}
require $__body;
