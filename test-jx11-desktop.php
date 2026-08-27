<?php declare(strict_types=1);

$root = __DIR__;
$tmp = sys_get_temp_dir() . '/jx11-' . getmypid() . '.manifest';
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/host/linux/jx11-compile.php') . ' ' . escapeshellarg($root . '/examples/jx11/desktop.php');
exec($cmd . ' > ' . escapeshellarg($tmp), $output, $status);
if ($status !== 0) throw new RuntimeException('jx11 manifest compiler failed');
$text = file_get_contents($tmp);
@unlink($tmp);
if ($text === false) throw new RuntimeException('jx11 manifest missing');

$needles = [
    "JX11/1\n",
    "background=#20242b\n",
    "taskbar=1\n",
    "taskbar-height=34\n",
    "window-bag=desktop-windows\n",
    "icon=terminal|Terminal|/icons/terminal.png|xterm|28|28\n",
    "icon=files|Files|/icons/files.png|thunar|28|132\n",
    "icon=browser|Browser|/icons/browser.png|firefox|28|236\n",
];
foreach ($needles as $needle) {
    if (!str_contains($text, $needle)) throw new RuntimeException('jx11 manifest missing: ' . trim($needle));
}

echo "jx11-desktop: ok\n";
