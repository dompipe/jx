<?php declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/jx/bootstrap.php';

use jx\Desktop;
use jx\DesktopIcon;
use jx\DesktopLaunch;

return Desktop::windowManager('main', [
    'background' => '#20242b',
    'workspaces' => 1,
])
    ->icon(new DesktopIcon('terminal', 'Terminal', '/icons/terminal.png', new DesktopLaunch('xterm'), 28, 28))
    ->icon(new DesktopIcon('files', 'Files', '/icons/files.png', new DesktopLaunch('thunar'), 28, 132))
    ->icon(new DesktopIcon('browser', 'Browser', '/icons/browser.png', new DesktopLaunch('firefox'), 28, 236));
