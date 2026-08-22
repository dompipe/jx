<?php declare(strict_types=1);
/**
 * Live page updates WITHOUT JavaScript.
 *
 *   php -S 127.0.0.1:8765 -t pasl/live
 *   open http://127.0.0.1:8765/server.php
 *
 * Meta-refresh by default. POST form or curl updates state — no JS required.
 */
header('Cache-Control: no-store');

$stateFile = __DIR__ . '/state.json';
$interval = max(1, (int)($_GET['every'] ?? 3));
$mode = $_GET['mode'] ?? 'refresh';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $raw = file_get_contents('php://input');
    $msg = trim((string)($_POST['msg'] ?? $raw));
    if ($msg === '' && isset($_POST['html'])) {
        $msg = (string)$_POST['html'];
    }
    $state = [
        't' => time(),
        'html' => $msg !== '' ? $msg : ('updated @ ' . date('H:i:s')),
    ];
    file_put_contents($stateFile, json_encode($state, JSON_UNESCAPED_UNICODE));
    if (!isset($_GET['mode']) && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo "ok\n";
    exit;
}

function load_state(string $file): array
{
    if (!is_file($file)) {
        return ['t' => time(), 'html' => 'Waiting for updates…'];
    }
    $j = json_decode((string)file_get_contents($file), true);
    return is_array($j) ? $j : ['t' => time(), 'html' => 'bad state'];
}

function page_html(array $state, int $interval): string
{
    $body = htmlspecialchars((string)($state['html'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $when = date('H:i:s', (int)($state['t'] ?? time()));
    return "<!DOCTYPE html>\n<html lang=\"en\"><head><meta charset=\"utf-8\">"
        . "<meta http-equiv=\"refresh\" content=\"{$interval}\">"
        . "<title>PASL live (no JS)</title></head><body>"
        . "<h1>Live page (no JavaScript)</h1>"
        . "<p>Auto-refresh every {$interval}s · last {$when}</p>"
        . "<div style=\"border:1px solid #ccc;padding:1rem;min-height:4rem;white-space:pre-wrap\">{$body}</div>"
        . "<form method=\"post\" action=\"\">"
        . "<p>Push update (HTML form, still no JS):</p>"
        . "<input type=\"text\" name=\"msg\" placeholder=\"new message\">"
        . "<button type=\"submit\">Update</button></form></body></html>";
}

$state = load_state($stateFile);
if ($mode === 'stream') {
    header('Content-Type: multipart/x-mixed-replace; boundary=PASL');
    while (true) {
        $state = load_state($stateFile);
        echo "--PASL\r\nContent-Type: text/html; charset=utf-8\r\n\r\n";
        echo page_html($state, $interval);
        echo "\r\n";
        @ob_flush(); @flush();
        sleep($interval);
    }
    exit;
}
header('Content-Type: text/html; charset=utf-8');
echo page_html($state, $interval);
