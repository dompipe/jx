<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/jx-lang.php';
require_once dirname(__DIR__) . '/jx-jxl-compiler.php';
require_once dirname(__DIR__) . '/jx-jxb.php';

use jx\JxEngine;
use jx\semantic\JxbBook;
use jx\semantic\JxlVm;
use jx\semantic\PreparedCompiler;

$mode = strtolower((string)($_GET['mode'] ?? 'php'));
$rows = max(1, min(500, (int)($_GET['rows'] ?? 100)));

$semanticSource = 'int $x=0; repeat (100) { $x += 1; } $x;';
$result = null;

switch ($mode) {
    case 'php':
        $x = 0;
        for ($i = 0; $i < 100; $i++) $x++;
        $result = $x;
        break;

    case 'jx':
        $result = (new PreparedCompiler())->run($semanticSource);
        break;

    case 'jxl':
        $path = getenv('JX_BENCH_JXL') ?: '';
        $bytes = $path !== '' ? @file_get_contents($path) : false;
        if (!is_string($bytes)) {
            http_response_code(500);
            $result = 'missing prepared JXL';
        } else {
            $result = (new JxlVm())->run($bytes);
        }
        break;

    case 'jxb':
        $path = getenv('JX_BENCH_JXB') ?: '';
        $bytes = $path !== '' ? @file_get_contents($path) : false;
        if (!is_string($bytes)) {
            http_response_code(500);
            $result = 'missing compiled JXB';
        } else {
            $result = JxbBook::run($bytes);
        }
        break;

    case 'host':
        $src = 'bag = Bag.underwrite(256); ref = bag.sign("msg"); bag.set("hello-jx").commit(ref); q = bag.quotient();';
        $result = (new JxEngine(true, false))->runSource($src);
        break;

    default:
        http_response_code(400);
        $result = 'unknown mode';
        break;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$escapedMode = htmlspecialchars($mode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$escapedResult = htmlspecialchars(is_scalar($result) || $result === null ? (string)$result : json_encode($result, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>JX no-JS benchmark</title>";
echo "<style>body{font-family:system-ui,sans-serif;margin:2rem}table{border-collapse:collapse}td,th{padding:.25rem .5rem;border:1px solid #bbb}</style>";
echo "</head><body>";
echo "<h1>PHP/JX server-rendered page</h1>";
echo "<p>Mode: {$escapedMode}; result: {$escapedResult}</p>";
echo "<form method=\"get\"><label>Mode <select name=\"mode\"><option>php</option><option>jx</option><option>jxl</option><option>jxb</option><option>host</option></select></label><button type=\"submit\">Run</button></form>";
echo "<table><thead><tr><th>row</th><th>value</th></tr></thead><tbody>";
for ($i = 0; $i < $rows; $i++) {
    $v = $i * 3 + 1;
    echo "<tr><td>{$i}</td><td>{$v}</td></tr>";
}
echo "</tbody></table></body></html>";
