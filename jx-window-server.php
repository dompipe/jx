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
  jx-window-server.php open [book] [host:port] [config.json]

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
    $config = (string)($argv[3] ?? $defaultConfig);
    $status = jx_ws_php($xi, [$hostport, 'status']);
    if ($status !== 0) {
        $started = jx_ws_start($xi, $hostport, $config, false);
        if ($started !== 0) {
            exit($started);
        }
        usleep(250000);
    }
    $url = "http://{$hostport}/?book={$book}";
    jx_ws_open_url($url);
    echo "jx: window server opened Book {$book} at {$url}\n";
    exit(0);
}

jx_ws_usage();
