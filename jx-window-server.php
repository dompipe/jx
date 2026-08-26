#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * jx window server control.
 *
 * This is the JX-owned window-server surface. XI remains the current Book
 * rendering engine, but callers should treat this file as the parent display
 * server contract: start/status/stop the host, and open Books into windows.
 */

$root = __DIR__;
$xi = $root . '/pasl/xi/xi.php';
$defaultConfig = $root . '/pasl/xi/config.json';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "jx-window-server: CLI only\n");
    exit(1);
}

$argv = array_values(array_slice($_SERVER['argv'] ?? [], 1));

function jx_ws_usage(): void
{
    fwrite(STDERR, <<<TXT
jx window server

  jx-window-server.php start [host:port] [config.json] [--foreground]
  jx-window-server.php stop [host:port]
  jx-window-server.php status [host:port]
  jx-window-server.php open [book] [host:port] [config.json] [--native|--browser]

TXT);
    exit(2);
}

function jx_ws_php(string $script, array $args): int
{
    $cmd = array_merge([PHP_BINARY, $script], $args);
    $escaped = array_map('escapeshellarg', $cmd);
    passthru(implode(' ', $escaped), $code);
    return (int)$code;
}

function jx_ws_start(string $xi, string $hostport, string $config, bool $foreground): int
{
    if ($foreground) {
        return jx_ws_php($xi, [$hostport, 'start', $config, '--foreground']);
    }

    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        $args = [
            $xi,
            $hostport,
            'start',
            $config,
            '--foreground',
        ];
        $argList = implode(',', array_map(
            fn(string $arg): string => "'" . str_replace("'", "''", $arg) . "'",
            $args
        ));
        $cmd = 'powershell -NoProfile -Command "Start-Process -WindowStyle Hidden -FilePath '
            . escapeshellarg(PHP_BINARY)
            . ' -ArgumentList @(' . $argList . ')"';
        exec($cmd, $out, $code);
        usleep(400000);
        return jx_ws_php($xi, [$hostport, 'status']) === 0 ? 0 : ($code ?: 1);
    }

    $cmd = implode(' ', array_map('escapeshellarg', [PHP_BINARY, $xi, $hostport, 'start', $config]));
    exec($cmd . ' >/dev/null 2>&1', $out, $code);
    return (int)$code;
}

function jx_ws_open_url(string $url): void
{
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        pclose(popen('start "" ' . escapeshellarg($url), 'r'));
        return;
    }

    foreach (['wslview', 'xdg-open'] as $cmd) {
        exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null', $out, $code);
        if ($code === 0) {
            exec($cmd . ' ' . escapeshellarg($url) . ' >/dev/null 2>&1 &');
            return;
        }
    }

    exec('command -v powershell.exe 2>/dev/null', $out, $code);
    if ($code === 0) {
        exec('powershell.exe -NoProfile -Command Start-Process ' . escapeshellarg($url) . ' >/dev/null 2>&1 &');
    }
}

function jx_ws_plain_book(string $url): string
{
    $html = @file_get_contents($url);
    if ($html === false) {
        return "JX native window could not read {$url}";
    }
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html;
    $html = preg_replace('#</(h[1-6]|p|div|section|article|li|tr|form)>#i', "\n", $html) ?? $html;
    $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
    $text = preg_replace("/(\r?\n\s*){3,}/", "\n\n", $text) ?? $text;
    return trim($text);
}

function jx_ws_open_native(string $root, string $url, string $book): bool
{
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        $exe = $root . '/build/windows/jx-native-window.exe';
        if (!is_file($exe)) {
            fwrite(STDERR, "jx: native Windows host missing; run tools\\windows\\build-windows.ps1\n");
            return false;
        }
        $cmd = 'powershell -NoProfile -Command "Start-Process -WindowStyle Normal -FilePath '
            . escapeshellarg($exe)
            . ' -ArgumentList @('
            . "'" . str_replace("'", "''", $url) . "',"
            . "'" . str_replace("'", "''", $book) . "'"
            . ')"';
        exec($cmd, $out, $code);
        return $code === 0;
    }

    exec('command -v xmessage 2>/dev/null', $out, $code);
    if ($code === 0) {
        $tmp = tempnam(sys_get_temp_dir(), 'jx-book-');
        file_put_contents($tmp, jx_ws_plain_book($url));
        exec('xmessage -title ' . escapeshellarg("JX Book {$book}") . ' -file ' . escapeshellarg($tmp) . ' >/dev/null 2>&1 &');
        return true;
    }

    fwrite(STDERR, "jx: native X11 host unavailable; install X11 tools or Xlib host support\n");
    return false;
}

$cmd = strtolower((string)($argv[0] ?? ''));
if ($cmd === '' || $cmd === '-h' || $cmd === '--help') {
    jx_ws_usage();
}

$hostport = (string)($argv[1] ?? 'localhost:8766');

if ($cmd === 'start') {
    $config = (string)($argv[2] ?? $defaultConfig);
    exit(jx_ws_start($xi, $hostport, $config, in_array('--foreground', $argv, true)));
}

if ($cmd === 'stop' || $cmd === 'status') {
    exit(jx_ws_php($xi, [$hostport, $cmd]));
}

if ($cmd === 'open') {
    $book = preg_replace('/[^a-z0-9_-]/i', '', (string)($argv[1] ?? 'cover')) ?: 'cover';
    $hostport = (string)($argv[2] ?? 'localhost:8766');
    $native = in_array('--native', $argv, true) || getenv('JX_WINDOW_HOST') === 'native';
    $browser = in_array('--browser', $argv, true);
    $config = $defaultConfig;
    for ($i = 3; $i < count($argv); $i++) {
        if (is_string($argv[$i]) && !str_starts_with($argv[$i], '-')) {
            $config = $argv[$i];
            break;
        }
    }
    $status = jx_ws_php($xi, [$hostport, 'status']);
    if ($status !== 0) {
        $started = jx_ws_start($xi, $hostport, $config, false);
        if ($started !== 0) {
            exit($started);
        }
        usleep(250000);
    }
    $url = "http://{$hostport}/?book={$book}";
    if ($native && !$browser) {
        if (!jx_ws_open_native($root, $url, $book)) {
            exit(1);
        }
        echo "jx: native window server opened Book {$book} at {$url}\n";
        exit(0);
    }
    jx_ws_open_url($url);
    echo "jx: browser window host opened Book {$book} at {$url}\n";
    exit(0);
}

jx_ws_usage();
