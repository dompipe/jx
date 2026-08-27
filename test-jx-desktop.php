<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\Desktop;
use jx\DesktopIcon;
use jx\DesktopLaunch;
use jx\DesktopShortcut;
use jx\DesktopWindowState;
use jx\JxException;

function desktop_test(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('desktop test failed: '.$message);
}

$terminal = new DesktopLaunch('xterm', ['-geometry','100x30'], null, [], 'tools', 'book');
$icon = new DesktopIcon('terminal', 'Terminal', '/icons/terminal.png', $terminal, 32, 32);
$shortcut = new DesktopShortcut('open-terminal', 'Super+Enter', 'launch', $terminal);
$desktop = Desktop::windowManager('main', [
    'background'=>'/wallpaper.png',
    'window_bag'=>'desktop.windows',
    'input_bag'=>'desktop.input',
    'workspaces'=>4,
])->icon($icon)->shortcut($shortcut);

$d = $desktop->jsonSerialize();
desktop_test($d['version'] === 'jx.desktop/1', 'desktop ABI version');
desktop_test($d['mode'] === 'window-manager', 'window manager mode');
desktop_test($d['with']['workspaces'] === 4, 'workspace count');
desktop_test(count($d['icons']) === 1, 'desktop icon');
desktop_test($d['icons'][0]['launch']['program'] === 'xterm', 'icon launch program');
desktop_test($d['icons'][0]['launch']['isolation'] === 'book', 'Book launch isolation');
desktop_test(count($d['shortcuts']) === 1, 'desktop shortcut');
desktop_test(in_array('window-open', $d['events'], true), 'window events exposed');

$row = DesktopWindowState::row('0x04200007', 1234, 'Terminal', 'XTerm', 10, 20, 800, 600, true, true, 2);
desktop_test($row['host_id'] === '0x04200007', 'opaque host window id');
desktop_test($row['pid'] === 1234, 'window pid');
desktop_test($row['focused'] === true, 'window focus');
desktop_test($row['workspace'] === 2, 'window workspace');

$failed = false;
try { Desktop::windowManager('bad desktop'); } catch (JxException) { $failed = true; }
desktop_test($failed, 'bad desktop id rejected');

$failed = false;
try { new DesktopLaunch('/bin/sh', [], null, [], null, 'vm'); } catch (JxException) { $failed = true; }
desktop_test($failed, 'unknown isolation rejected');

$failed = false;
try { Desktop::shell('bad', ['token'=>'secret']); } catch (JxException) { $failed = true; }
desktop_test($failed, 'secret-bearing descriptor rejected');

echo "jx-desktop: ok\n";
