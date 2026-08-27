<?php declare(strict_types=1);

/**
 * Compile a canonical Desktop descriptor array into the tiny JX11/1 execution
 * shadow consumed by host/linux/jx11.c.
 *
 * Usage:
 *   php jx11-compile.php desktop.php > desktop.jx11
 *
 * The input PHP file must return either a jx\Desktop or its serialized array.
 */
require_once dirname(__DIR__, 2) . '/jx/bootstrap.php';

use jx\Desktop;

if ($argc < 2) {
    fwrite(STDERR, "usage: php jx11-compile.php <desktop.php>\n");
    exit(2);
}

$source = $argv[1];
if (!is_file($source)) {
    fwrite(STDERR, "jx11-compile: input not found: {$source}\n");
    exit(2);
}

$value = require $source;
if ($value instanceof Desktop) $value = $value->jsonSerialize();
if (!is_array($value) || ($value['kind'] ?? null) !== 'desktop') {
    fwrite(STDERR, "jx11-compile: input must return Desktop or desktop descriptor array\n");
    exit(2);
}

function jx11_field(mixed $value, string $what): string
{
    $text = (string)$value;
    if ($text === '' || str_contains($text, "|") || str_contains($text, "\n") || str_contains($text, "\r") || str_contains($text, "\0")) {
        throw new RuntimeException("jx11-compile: invalid {$what} for JX11/1 shadow");
    }
    return $text;
}

$out = ["JX11/1"];
$background = (string)($value['with']['background'] ?? '#181a1f');
if (preg_match('/^#[0-9a-fA-F]{6}$/', $background)) $out[] = 'background=' . strtolower($background);
else $out[] = 'background=#181a1f';

foreach ((array)($value['icons'] ?? []) as $icon) {
    if (!is_array($icon)) continue;
    $launch = is_array($icon['launch'] ?? null) ? $icon['launch'] : [];
    if (($launch['isolation'] ?? 'host') !== 'host') {
        throw new RuntimeException('jx11-compile: book/sandbox launch isolation is reserved but not executable yet');
    }
    $args = array_values((array)($launch['args'] ?? []));
    if ($args !== []) {
        throw new RuntimeException('jx11-compile: JX11/1 native shadow currently supports program-only icon launches');
    }
    $out[] = implode('|', [
        jx11_field($icon['id'] ?? '', 'icon id'),
        jx11_field($icon['label'] ?? '', 'icon label'),
        jx11_field($launch['program'] ?? '', 'launch program'),
        (string)(int)($icon['x'] ?? 0),
        (string)(int)($icon['y'] ?? 0),
    ]);
    $out[array_key_last($out)] = 'icon=' . $out[array_key_last($out)];
}

echo implode("\n", $out), "\n";
