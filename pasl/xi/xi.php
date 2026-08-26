<?php declare(strict_types=1);
/**
 * xi — XIP book server control
 *
 *   php xi.php localhost:8765 start config.json
 *   php xi.php localhost:8765 stop
 *   php xi.php localhost:8765 status
 *   php xi.php localhost:8765 start config.json --foreground
 */

$root = __DIR__;
require $root . '/src/Bag.php';
require $root . '/src/ChannelBus.php';
require $root . '/src/Binding.php';
require $root . '/src/SegmentPipe.php';
require $root . '/src/Ladder.php';
require $root . '/src/Book.php';
require $root . '/src/Control.php';
require $root . '/src/HostProtocol.php';
require $root . '/src/XipEngine.php';
require $root . '/src/Server.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "xi: CLI only\n");
    exit(1);
}

$argv = array_values(array_slice($argv, 1));

function xi_usage(): void
{
    fwrite(STDERR, <<<TXT
xi — XIP institutional book server

  xi <host:port> start [config.json] [--foreground]
  xi <host:port> stop
  xi <host:port> status

TXT);
    exit(2);
}

function xi_hostport(string $s): array
{
    if (!preg_match('/^([\w.\-]+|localhost):(\d+)$/', $s, $m)) {
        fwrite(STDERR, "xi: need host:port\n");
        exit(2);
    }
    $host = $m[1] === 'localhost' ? '127.0.0.1' : $m[1];
    return [$host, (int)$m[2]];
}

function xi_pid_path(string $host, int $port): string
{
    $d = sys_get_temp_dir() . '/pasl-xi';
    if (!is_dir($d)) {
        mkdir($d, 0777, true);
    }
    return "{$d}/{$host}_{$port}.pid";
}

function xi_running(string $pidFile): bool
{
    if (!is_file($pidFile)) {
        return false;
    }
    $pid = (int)trim((string)file_get_contents($pidFile));
    if ($pid <= 0) {
        return false;
    }
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        exec('tasklist /FI "PID eq ' . $pid . '" 2>NUL', $out);
        return str_contains(implode("\n", $out), (string)$pid);
    }
    return function_exists('posix_kill') && @posix_kill($pid, 0);
}

function xi_load_config(?string $file, string $root): array
{
    $cfg = [
        'books_root'   => $root . '/books',
        'data_root'    => $root . '/data',
        'default_book' => 'cover',
        'ssl'          => false,
        'dry'          => true,
        'allow_input'  => true,
        'allow_output' => true,
    ];
    if ($file === null || $file === '') {
        return $cfg;
    }
    if (!is_file($file)) {
        fwrite(STDERR, "xi: config not found: {$file}\n");
        exit(1);
    }
    $j = json_decode((string)file_get_contents($file), true);
    if (!is_array($j)) {
        fwrite(STDERR, "xi: bad JSON config\n");
        exit(1);
    }
    return array_merge($cfg, $j);
}

if (count($argv) < 2) {
    xi_usage();
}

[$host, $port] = xi_hostport($argv[0]);
$cmd = strtolower($argv[1] ?? '');
$pidFile = xi_pid_path($host, $port);

if ($cmd === 'stop') {
    if (!xi_running($pidFile)) {
        echo "xi: not running on {$host}:{$port}\n";
        @unlink($pidFile);
        exit(0);
    }
    $pid = (int)trim((string)file_get_contents($pidFile));
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        exec('taskkill /PID ' . $pid . ' /F 2>NUL');
    } else {
        posix_kill($pid, SIGTERM);
        usleep(200000);
        if (@posix_kill($pid, 0)) {
            posix_kill($pid, SIGKILL);
        }
    }
    @unlink($pidFile);
    echo "xi: stopped {$host}:{$port} (pid {$pid})\n";
    exit(0);
}

if ($cmd === 'status') {
    if (xi_running($pidFile)) {
        echo 'xi: running ' . $host . ':' . $port . ' pid=' . trim((string)file_get_contents($pidFile)) . "\n";
        exit(0);
    }
    echo "xi: stopped {$host}:{$port}\n";
    exit(1);
}

if ($cmd !== 'start') {
    xi_usage();
}

$cfgFile = null;
$foreground = false;
for ($i = 2; $i < count($argv); $i++) {
    if ($argv[$i] === '--foreground') {
        $foreground = true;
    } elseif (!str_starts_with($argv[$i], '-')) {
        $cfgFile = $argv[$i];
    }
}

$cfg = xi_load_config($cfgFile, $root);
$booksRoot = $cfg['books_root'];
$dataRoot = $cfg['data_root'];
if (!str_starts_with($booksRoot, '/') && !preg_match('#^[A-Za-z]:#', $booksRoot)) {
    $booksRoot = $root . '/' . ltrim($booksRoot, './');
}
if (!str_starts_with($dataRoot, '/') && !preg_match('#^[A-Za-z]:#', $dataRoot)) {
    $dataRoot = $root . '/' . ltrim($dataRoot, './');
}

if (xi_running($pidFile)) {
    fwrite(STDERR, "xi: already running on {$host}:{$port}\n");
    exit(1);
}

$engine = new XipEngine($booksRoot, $dataRoot, $cfg);
$server = new Server(
    $engine,
    $host,
    $port,
    !empty($cfg['ssl']),
    $cfg['cert'] ?? null,
    $cfg['key'] ?? null,
);

if (!$foreground && strncasecmp(PHP_OS, 'WIN', 3) !== 0 && function_exists('pcntl_fork')) {
    $pid = pcntl_fork();
    if ($pid < 0) {
        exit(1);
    }
    if ($pid > 0) {
        file_put_contents($pidFile, (string)$pid);
        echo "xi: started {$host}:{$port} pid={$pid}\n";
        exit(0);
    }
    if (function_exists('posix_setsid')) {
        posix_setsid();
    }
    file_put_contents($pidFile, (string)getmypid());
} else {
    file_put_contents($pidFile, (string)getmypid());
    echo "xi: foreground http://{$host}:{$port}/\n";
}

$server->serve();
