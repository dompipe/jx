<?php declare(strict_types=1);

$candidates = [];
if (defined('JX_ROOT')) {
    $candidates[] = (string)constant('JX_ROOT');
}

$configured = getenv('JX_ROOT');
if (is_string($configured) && $configured !== '') {
    $candidates[] = $configured;
}

$marker = __DIR__ . '/.jx-root';
if (is_file($marker)) {
    $candidates[] = trim((string)file_get_contents($marker));
}

$candidates[] = dirname(__DIR__, 2);
foreach (array_unique($candidates) as $candidate) {
    $root = realpath($candidate);
    if ($root !== false && is_file($root . '/jx.php')) {
        return $root;
    }
}

throw new RuntimeException('Cannot locate the JX runtime root from ' . __DIR__);
