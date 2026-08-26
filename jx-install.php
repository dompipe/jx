#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * jx installer — plugins from one source dir; one-at-a-time; dual backups.
 *
 *   php jx-install.php list|status|install-required|install <id>|backup-full|restore-full <ts>
 */

$root = __DIR__;
$pluginRoot = $root . '/plugins';
$hostRoot = $root . '/host';
$modulesDir = $hostRoot . '/modules';
$stateFile = $hostRoot . '/state.json';
$backupPre = $hostRoot . '/backups/pre';
$backupFull = $hostRoot . '/backups/full';

function ensure_dirs(string ...$dirs): void
{
    foreach ($dirs as $d) {
        if (!is_dir($d) && !@mkdir($d, 0770, true)) {
            fwrite(STDERR, "Cannot create {$d}\n");
            exit(1);
        }
    }
}

function load_catalog(string $pluginRoot): array
{
    $path = $pluginRoot . '/catalog.json';
    if (!is_file($path)) {
        fwrite(STDERR, "Missing plugins/catalog.json\n");
        exit(1);
    }
    $j = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    return $j;
}

function load_state(string $stateFile): array
{
    if (!is_file($stateFile)) {
        return ['installed' => [], 'order' => []];
    }
    return json_decode((string)file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);
}

function save_state(string $stateFile, array $state): void
{
    file_put_contents(
        $stateFile,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    );
}

/** Recursive copy directory. */
function copy_tree(string $src, string $dst): void
{
    if (!is_dir($src)) {
        return;
    }
    if (!is_dir($dst)) {
        mkdir($dst, 0770, true);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $target = $dst . DIRECTORY_SEPARATOR . $it->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0770, true);
            }
        } else {
            copy($item->getPathname(), $target);
        }
    }
}

function ts(): string
{
    return date('Ymd-His');
}

function backup_pre(string $modulesDir, string $stateFile, string $backupPre): string
{
    ensure_dirs($backupPre);
    $id = ts();
    $dest = $backupPre . '/' . $id;
    mkdir($dest, 0770, true);
    if (is_dir($modulesDir)) {
        copy_tree($modulesDir, $dest . '/modules');
    }
    if (is_file($stateFile)) {
        copy($stateFile, $dest . '/state.json');
    }
    file_put_contents($dest . '/README.txt', "Pre-install backup {$id}\n");
    return $id;
}

function backup_full(string $hostRoot, string $backupFull): string
{
    ensure_dirs($backupFull);
    $id = ts();
    $dest = $backupFull . '/' . $id;
    mkdir($dest, 0770, true);
    copy_tree($hostRoot . '/modules', $dest . '/modules');
    if (is_file($hostRoot . '/state.json')) {
        copy($hostRoot . '/state.json', $dest . '/state.json');
    }
    file_put_contents($dest . '/README.txt', "Full install backup {$id}\nRedirect host modules here to restore.\n");
    return $id;
}

function install_plugin(
    string $id,
    array $catalog,
    string $pluginRoot,
    string $modulesDir,
    string $stateFile,
    string $backupPre,
): void {
    $byId = [];
    foreach ($catalog['plugins'] as $p) {
        $byId[$p['id']] = $p;
    }
    if (!isset($byId[$id])) {
        fwrite(STDERR, "Unknown plugin {$id}\n");
        exit(1);
    }
    $meta = $byId[$id];
    $state = load_state($stateFile);
    if (in_array($id, $state['order'], true)) {
        echo "Already installed: {$id}\n";
        return;
    }
    foreach ($meta['depends'] ?? [] as $dep) {
        // depends recorded in plugin.json
    }
    $pluginJson = $pluginRoot . '/' . $meta['path'] . '/plugin.json';
    if (is_file($pluginJson)) {
        $pj = json_decode((string)file_get_contents($pluginJson), true);
        foreach ($pj['depends'] ?? [] as $dep) {
            if (!in_array($dep, $state['order'], true)) {
                fwrite(STDERR, "Dependency not installed: {$dep} (needed by {$id})\n");
                exit(1);
            }
        }
    }

    $preId = backup_pre($modulesDir, $stateFile, $backupPre);
    echo "Pre-install backup: {$preId}\n";

    $src = $pluginRoot . '/' . $meta['path'];
    $dst = $modulesDir . '/' . $id;
    ensure_dirs($modulesDir);
    if (is_dir($dst)) {
        // replace module tree
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dst, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dst);
    }
    copy_tree($src, $dst);

    // Run entry if present
    $entry = $dst . '/' . (($pj['entry'] ?? null) ?: 'bootstrap.php');
    if (!isset($pj) && is_file($dst . '/plugin.json')) {
        $pj = json_decode((string)file_get_contents($dst . '/plugin.json'), true);
        $entry = $dst . '/' . ($pj['entry'] ?? 'bootstrap.php');
    }
    if (is_file($entry)) {
        $result = require $entry;
        if (is_array($result) && isset($result['ok']) && !$result['ok']) {
            fwrite(STDERR, "Plugin bootstrap reported failure: {$id}\n");
            exit(1);
        }
        echo "Bootstrapped {$id}\n";
    }

    $state['installed'][$id] = [
        'id' => $id,
        'path' => $meta['path'],
        'installed_at' => date('c'),
        'pre_backup' => $preId,
    ];
    $state['order'][] = $id; // new installs always last
    save_state($stateFile, $state);
    echo "Installed {$id} (order position " . count($state['order']) . ")\n";
}

// --- CLI ---
ensure_dirs($hostRoot, $modulesDir, $backupPre, $backupFull);
$catalog = load_catalog($pluginRoot);
$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$cmd = $argv[0] ?? 'status';

switch ($cmd) {
    case 'list':
        $state = load_state($stateFile);
        echo "Catalog (source: plugins/):\n";
        foreach ($catalog['plugins'] as $p) {
            $on = in_array($p['id'], $state['order'], true) ? 'ON ' : 'off';
            $req = !empty($p['required']) ? 'required' : 'optional';
            echo "  [{$on}] {$p['id']} — {$p['name']} ({$req})\n";
        }
        break;

    case 'status':
        $state = load_state($stateFile);
        echo "Installed order:\n";
        if ($state['order'] === []) {
            echo "  (none)\n";
        }
        foreach ($state['order'] as $i => $id) {
            echo '  ' . ($i + 1) . ". {$id}\n";
        }
        echo "Pre backups: " . (is_dir($backupPre) ? count(glob($backupPre . '/*', GLOB_ONLYDIR) ?: []) : 0) . "\n";
        echo "Full backups: " . (is_dir($backupFull) ? count(glob($backupFull . '/*', GLOB_ONLYDIR) ?: []) : 0) . "\n";
        break;

    case 'install-required':
        foreach ($catalog['plugins'] as $p) {
            if (!empty($p['required'])) {
                install_plugin($p['id'], $catalog, $pluginRoot, $modulesDir, $stateFile, $backupPre);
            }
        }
        $fullId = backup_full($hostRoot, $backupFull);
        echo "Full backup after required set: {$fullId}\n";
        break;

    case 'install':
        $id = $argv[1] ?? '';
        if ($id === '') {
            fwrite(STDERR, "Usage: jx-install.php install <plugin-id>\n");
            exit(1);
        }
        install_plugin($id, $catalog, $pluginRoot, $modulesDir, $stateFile, $backupPre);
        break;

    case 'backup-full':
        $fullId = backup_full($hostRoot, $backupFull);
        echo "Full backup: {$fullId}\n";
        echo "Path: {$backupFull}/{$fullId}\n";
        break;

    case 'restore-full':
        $id = $argv[1] ?? '';
        $src = $backupFull . '/' . $id;
        if ($id === '' || !is_dir($src)) {
            fwrite(STDERR, "Usage: jx-install.php restore-full <timestamp>\n");
            exit(1);
        }
        $preId = backup_pre($modulesDir, $stateFile, $backupPre);
        echo "Safety pre-backup before restore: {$preId}\n";
        // Clear modules
        if (is_dir($modulesDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($modulesDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
            }
        }
        copy_tree($src . '/modules', $modulesDir);
        if (is_file($src . '/state.json')) {
            copy($src . '/state.json', $stateFile);
        }
        echo "Restored full backup {$id}\n";
        break;

    case 'help':
    case '-h':
    case '--help':
        echo "jx-install.php list|status|install-required|install <id>|backup-full|restore-full <ts>\n";
        break;

    default:
        fwrite(STDERR, "Unknown command {$cmd}\n");
        exit(1);
}

exit(0);
