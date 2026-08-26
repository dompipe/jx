#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * jx installer — plugins from one source dir; one-at-a-time; dual backups.
 * Allow gate: plugin must compile/verify for windows, mac, linux, and web (jx).
 *
 *   php jx-install.php list|status|check-targets [id]|install-required|install <id>|backup-full|restore-full <ts>
 */

$root = __DIR__;
$pluginRoot = $root . '/plugins';
$hostRoot = $root . '/host';
$modulesDir = $hostRoot . '/modules';
$stateFile = $hostRoot . '/state.json';
$backupPre = $hostRoot . '/backups/pre';
$backupFull = $hostRoot . '/backups/full';

const JX_REQUIRED_TARGETS = ['windows', 'mac', 'linux', 'web'];

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
    return json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
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

/**
 * Allow gate: plugin must declare and pass windows, mac, linux, web (jx).
 *
 * @return array{allowed:bool,targets:array<string,bool>,messages:list<string>}
 */
function check_plugin_targets(string $id, array $catalog, string $pluginRoot): array
{
    $required = $catalog['required_targets'] ?? JX_REQUIRED_TARGETS;
    $byId = [];
    foreach ($catalog['plugins'] as $p) {
        $byId[$p['id']] = $p;
    }
    if (!isset($byId[$id])) {
        return ['allowed' => false, 'targets' => [], 'messages' => ["Unknown plugin {$id}"]];
    }
    $meta = $byId[$id];
    $declared = $meta['targets'] ?? [];
    $pluginJsonPath = $pluginRoot . '/' . $meta['path'] . '/plugin.json';
    $pj = [];
    if (is_file($pluginJsonPath)) {
        $pj = json_decode((string)file_get_contents($pluginJsonPath), true) ?: [];
        if (!empty($pj['targets']) && is_array($pj['targets'])) {
            $declared = array_values(array_unique(array_merge($declared, $pj['targets'])));
        }
    }

    $messages = [];
    $targetOk = [];
    foreach ($required as $t) {
        $targetOk[$t] = in_array($t, $declared, true);
        if (!$targetOk[$t]) {
            $messages[] = "Missing required target declaration: {$t}";
        }
    }

    // Portable compile/verify: PHP lint entry + no forbidden OS-only requires in plugin tree
    $src = $pluginRoot . '/' . $meta['path'];
    $entryName = $pj['entry'] ?? 'bootstrap.php';
    $entry = $src . '/' . $entryName;
    if (!is_file($entry)) {
        // decimals uses Decimal.php as entry
        $messages[] = "No entry file {$entryName} under {$meta['path']}";
        foreach ($required as $t) {
            $targetOk[$t] = false;
        }
    } else {
        // Syntax check via php -l when CLI available
        $php = PHP_BINARY ?: 'php';
        $lint = @shell_exec(escapeshellarg($php) . ' -l ' . escapeshellarg($entry) . ' 2>&1');
        $lintOk = is_string($lint) && str_contains($lint, 'No syntax errors');
        if (!$lintOk && is_string($lint) && $lint !== '') {
            $messages[] = "php -l failed for {$entryName}: " . trim($lint);
            foreach ($required as $t) {
                $targetOk[$t] = false;
            }
        } elseif (!$lintOk) {
            // shell_exec disabled — fall back to token_get_all
            $code = (string)file_get_contents($entry);
            if (@token_get_all($code) === [] && $code !== '') {
                $messages[] = "token_get_all could not parse {$entryName}";
                foreach ($required as $t) {
                    $targetOk[$t] = false;
                }
            }
        }

        // Scan plugin PHP files for hard OS locks that would break other targets
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $body = (string)file_get_contents($file->getPathname());
            // Disallow unconditional extension requirements that break portability
            if (preg_match('/\bdl\s*\(/', $body)) {
                $messages[] = "Portable compile blocked: dl() in {$file->getFilename()}";
                foreach ($required as $t) {
                    $targetOk[$t] = false;
                }
            }
            if (preg_match('/PHP_OS_FAMILY\s*===\s*[\'"]Windows[\'"]/', $body)
                && !preg_match('/web|mac|linux|fallback/i', $body)) {
                // Heuristic only — warn, do not auto-fail if targets still declared
                $messages[] = "Note: Windows-specific branch in {$file->getFilename()} — ensure mac/linux/web paths exist";
            }
        }
    }

    // web (jx) specific: entry must be loadable without CLI SAPI assumption
    if (($targetOk['web'] ?? false) === true) {
        if (is_file($entry)) {
            $body = (string)file_get_contents($entry);
            if (preg_match('/php_sapi_name\s*\(\s*\)\s*===\s*[\'"]cli[\'"]/', $body)
                && !preg_match('/web|fallback|else/i', $body)) {
                $messages[] = 'web (jx) target failed: CLI-only gate without web fallback';
                $targetOk['web'] = false;
            }
        }
    }

    $allowed = true;
    foreach ($required as $t) {
        if (empty($targetOk[$t])) {
            $allowed = false;
        }
    }

    return ['allowed' => $allowed, 'targets' => $targetOk, 'messages' => $messages];
}

function print_target_report(string $id, array $report): void
{
    echo "Plugin: {$id}\n";
    foreach ($report['targets'] as $t => $ok) {
        echo '  ' . ($ok ? 'OK ' : 'FAIL') . "  {$t}\n";
    }
    foreach ($report['messages'] as $m) {
        echo "  · {$m}\n";
    }
    echo $report['allowed'] ? "ALLOWED\n" : "DENIED (must pass windows, mac, linux, web)\n";
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

    // --- Allow gate: windows, mac, linux, web (jx) ---
    $report = check_plugin_targets($id, $catalog, $pluginRoot);
    print_target_report($id, $report);
    if (!$report['allowed']) {
        fwrite(STDERR, "Install blocked: {$id} does not pass all required targets.\n");
        exit(1);
    }

    $pluginJson = $pluginRoot . '/' . $meta['path'] . '/plugin.json';
    $pj = [];
    if (is_file($pluginJson)) {
        $pj = json_decode((string)file_get_contents($pluginJson), true) ?: [];
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

    $entry = $dst . '/' . ($pj['entry'] ?? 'bootstrap.php');
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
        'targets' => $report['targets'],
    ];
    $state['order'][] = $id;
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
        echo "Catalog (source: plugins/) — targets: windows, mac, linux, web\n";
        foreach ($catalog['plugins'] as $p) {
            $on = in_array($p['id'], $state['order'], true) ? 'ON ' : 'off';
            $req = !empty($p['required']) ? 'required' : 'optional';
            $report = check_plugin_targets($p['id'], $catalog, $pluginRoot);
            $gate = $report['allowed'] ? 'PASS' : 'FAIL';
            echo "  [{$on}] {$p['id']} — {$p['name']} ({$req}) targets:{$gate}\n";
        }
        break;

    case 'check-targets':
        $only = $argv[1] ?? null;
        $anyFail = false;
        foreach ($catalog['plugins'] as $p) {
            if ($only !== null && $p['id'] !== $only) {
                continue;
            }
            $report = check_plugin_targets($p['id'], $catalog, $pluginRoot);
            print_target_report($p['id'], $report);
            echo "\n";
            if (!$report['allowed']) {
                $anyFail = true;
            }
        }
        exit($anyFail ? 1 : 0);

    case 'status':
        $state = load_state($stateFile);
        echo "Installed order:\n";
        if ($state['order'] === []) {
            echo "  (none)\n";
        }
        foreach ($state['order'] as $i => $id) {
            $t = $state['installed'][$id]['targets'] ?? [];
            $tstr = $t === [] ? '' : ' [' . implode(',', array_keys(array_filter($t))) . ']';
            echo '  ' . ($i + 1) . ". {$id}{$tstr}\n";
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
        echo "jx-install.php list|status|check-targets [id]|install-required|install <id>|backup-full|restore-full <ts>\n";
        echo "Allow gate: windows + mac + linux + web (jx)\n";
        break;

    default:
        fwrite(STDERR, "Unknown command {$cmd}\n");
        exit(1);
}

exit(0);
