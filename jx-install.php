#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * jx installer — plugins from one source dir; one-at-a-time; dual backups.
 * Allow gate: windows + mac + linux + web (jx). Non-portable = hard reject (not possible in this version).
 * All gate errors are collected and written to jxerr.log (not stop-on-first).
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
$errLog = $root . '/jxerr.log';

const JX_REQUIRED_TARGETS = ['windows', 'mac', 'linux', 'web'];

/** @var list<string> */
$JX_ERROR_BUFFER = [];

function jx_err(string $message): void
{
    global $JX_ERROR_BUFFER;
    $JX_ERROR_BUFFER[] = $message;
}

/** Flush all collected errors to jxerr.log and return condensed text. */
function jx_err_flush(string $errLog, string $context = ''): string
{
    global $JX_ERROR_BUFFER;
    if ($JX_ERROR_BUFFER === []) {
        return '';
    }
    $ts = date('c');
    $block = "==== jxerr {$ts}" . ($context !== '' ? " [{$context}]" : '') . " ====\n";
    foreach ($JX_ERROR_BUFFER as $i => $msg) {
        $block .= ($i + 1) . '. ' . $msg . "\n";
    }
    $block .= "==== end (" . count($JX_ERROR_BUFFER) . " errors) ====\n\n";
    file_put_contents($errLog, $block, FILE_APPEND | LOCK_EX);
    $condensed = count($JX_ERROR_BUFFER) . " error(s)";
    if ($context !== '') {
        $condensed .= " [{$context}]";
    }
    $condensed .= ":\n  - " . implode("\n  - ", $JX_ERROR_BUFFER);
    $JX_ERROR_BUFFER = [];
    return $condensed;
}

function jx_err_count(): int
{
    global $JX_ERROR_BUFFER;
    return count($JX_ERROR_BUFFER);
}

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
 * Collect ALL portability/target failures (never stop at the first).
 * Non-portable material is outside this version — not possible — hard reject.
 *
 * @return array{allowed:bool,targets:array<string,bool>}
 */
function check_plugin_targets(string $id, array $catalog, string $pluginRoot): array
{
    $required = $catalog['required_targets'] ?? JX_REQUIRED_TARGETS;
    $byId = [];
    foreach ($catalog['plugins'] as $p) {
        $byId[$p['id']] = $p;
    }

    $targetOk = array_fill_keys($required, false);

    if (!isset($byId[$id])) {
        jx_err("Unknown plugin '{$id}' — not in catalog");
        return ['allowed' => false, 'targets' => $targetOk];
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
    } else {
        jx_err("Plugin '{$id}': missing plugin.json at {$meta['path']}/plugin.json");
    }

    foreach ($required as $t) {
        if (in_array($t, $declared, true)) {
            $targetOk[$t] = true;
        } else {
            $targetOk[$t] = false;
            jx_err("Plugin '{$id}': missing required target '{$t}' (windows/mac/linux/web all mandatory)");
        }
    }

    $src = $pluginRoot . '/' . $meta['path'];
    if (!is_dir($src)) {
        jx_err("Plugin '{$id}': source directory missing: {$meta['path']}");
        foreach ($required as $t) {
            $targetOk[$t] = false;
        }
        return ['allowed' => false, 'targets' => $targetOk];
    }

    $entryName = $pj['entry'] ?? 'bootstrap.php';
    $entry = $src . '/' . $entryName;
    if (!is_file($entry)) {
        jx_err("Plugin '{$id}': entry file not found: {$meta['path']}/{$entryName}");
        foreach ($required as $t) {
            $targetOk[$t] = false;
        }
    } else {
        $php = PHP_BINARY ?: 'php';
        $lint = @shell_exec(escapeshellarg($php) . ' -l ' . escapeshellarg($entry) . ' 2>&1');
        if (is_string($lint) && $lint !== '' && !str_contains($lint, 'No syntax errors')) {
            jx_err("Plugin '{$id}': php -l failed on {$entryName}: " . trim(preg_replace('/\s+/', ' ', $lint)));
            foreach ($required as $t) {
                $targetOk[$t] = false;
            }
        } elseif (!is_string($lint) || $lint === '') {
            $code = (string)file_get_contents($entry);
            if ($code !== '') {
                $tokens = @token_get_all($code);
                if ($tokens === [] || $tokens === false) {
                    jx_err("Plugin '{$id}': cannot parse {$entryName} (token_get_all)");
                    foreach ($required as $t) {
                        $targetOk[$t] = false;
                    }
                }
            }
        }
    }

    // Scan every PHP file — collect every portability violation
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile() || !str_ends_with(strtolower($file->getFilename()), '.php')) {
            continue;
        }
        $rel = $file->getFilename();
        $body = (string)file_get_contents($file->getPathname());

        if (preg_match('/\bdl\s*\(/', $body)) {
            jx_err("Plugin '{$id}' [{$rel}]: dl() is not portable — outside this version; cannot use");
            foreach ($required as $t) {
                $targetOk[$t] = false;
            }
        }
        if (preg_match('/\bcom_\w+\s*\(/i', $body) || preg_match('/\bCOM\s*\(/', $body)) {
            jx_err("Plugin '{$id}' [{$rel}]: COM/Windows-only API — not portable; hard reject");
            $targetOk['mac'] = false;
            $targetOk['linux'] = false;
            $targetOk['web'] = false;
        }
        if (preg_match('/\\+[A-Za-z]/|\b[A-Z]:\\/', $body) && !preg_match('/DIRECTORY_SEPARATOR|php_uname|PHP_OS/', $body)) {
            jx_err("Plugin '{$id}' [{$rel}]: hardcoded Windows path separators without portable fallback");
            $targetOk['mac'] = false;
            $targetOk['linux'] = false;
            $targetOk['web'] = false;
        }
        if (preg_match('/php_sapi_name\s*\(\s*\)\s*===\s*[\'"]cli[\'"]/', $body)
            && !preg_match('/(web|fallback|else\b|!==\s*[\'"]cli[\'"])/i', $body)) {
            jx_err("Plugin '{$id}' [{$rel}]: CLI-only SAPI gate without web (jx) fallback — web target impossible");
            $targetOk['web'] = false;
        }
        if (preg_match('/\b(curl_init|mysqli_connect|pg_connect)\s*\(/', $body)
            && preg_match('/extension_loaded\s*\(/', $body) === 0
            && preg_match('/function_exists\s*\(/', $body) === 0) {
            // soft collect — optional dependency without guard is a portability risk for web hosts
            jx_err("Plugin '{$id}' [{$rel}]: extension-backed call without function_exists/extension_loaded guard");
            // do not auto-clear all targets; still recorded in jxerr.log
        }
    }

    $allowed = true;
    foreach ($required as $t) {
        if (empty($targetOk[$t])) {
            $allowed = false;
        }
    }
    if (!$allowed) {
        jx_err("Plugin '{$id}': HARD REJECT — non-portable or incomplete targets. Not possible in this jx version; next version may support it, this one cannot use it.");
    }

    return ['allowed' => $allowed, 'targets' => $targetOk];
}

function print_target_report(string $id, array $report): void
{
    echo "Plugin: {$id}\n";
    foreach ($report['targets'] as $t => $ok) {
        echo '  ' . ($ok ? 'OK ' : 'FAIL') . "  {$t}\n";
    }
    if (!$report['allowed']) {
        echo "HARD REJECT — not portable for this jx version (not possible)\n";
    } else {
        echo "ALLOWED\n";
    }
}

function install_plugin(
    string $id,
    array $catalog,
    string $pluginRoot,
    string $modulesDir,
    string $stateFile,
    string $backupPre,
    string $errLog,
): void {
    $byId = [];
    foreach ($catalog['plugins'] as $p) {
        $byId[$p['id']] = $p;
    }
    if (!isset($byId[$id])) {
        jx_err("Unknown plugin {$id}");
        $text = jx_err_flush($errLog, "install:{$id}");
        fwrite(STDERR, $text . "\n");
        fwrite(STDERR, "Logged to jxerr.log\n");
        exit(1);
    }
    $meta = $byId[$id];
    $state = load_state($stateFile);
    if (in_array($id, $state['order'], true)) {
        echo "Already installed: {$id}\n";
        return;
    }

    $report = check_plugin_targets($id, $catalog, $pluginRoot);
    print_target_report($id, $report);

    if (!$report['allowed']) {
        $text = jx_err_flush($errLog, "install:{$id}");
        fwrite(STDERR, "\nHARD REJECT: plugin '{$id}' is outside the portability requests of this jx version.\n");
        fwrite(STDERR, "It is not possible to use it here. A future version might; this one does not.\n\n");
        fwrite(STDERR, $text . "\n");
        fwrite(STDERR, "All errors appended to jxerr.log\n");
        exit(1);
    }

    // Flush any soft notes collected during a passing check
    if (jx_err_count() > 0) {
        jx_err_flush($errLog, "install-notes:{$id}");
    }

    $pluginJson = $pluginRoot . '/' . $meta['path'] . '/plugin.json';
    $pj = [];
    if (is_file($pluginJson)) {
        $pj = json_decode((string)file_get_contents($pluginJson), true) ?: [];
        foreach ($pj['depends'] ?? [] as $dep) {
            if (!in_array($dep, $state['order'], true)) {
                jx_err("Dependency not installed: {$dep} (needed by {$id})");
                $text = jx_err_flush($errLog, "install:{$id}");
                fwrite(STDERR, $text . "\n");
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
            jx_err("Plugin bootstrap reported failure: {$id}");
            $text = jx_err_flush($errLog, "bootstrap:{$id}");
            fwrite(STDERR, $text . "\n");
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
            // list must not leave errors only in memory — log them per plugin
            if (!$report['allowed'] || jx_err_count() > 0) {
                jx_err_flush($errLog, 'list:' . $p['id']);
            }
            $gate = $report['allowed'] ? 'PASS' : 'REJECT';
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
            if (jx_err_count() > 0) {
                $text = jx_err_flush($errLog, 'check-targets:' . $p['id']);
                echo $text . "\n";
            }
            echo "\n";
            if (!$report['allowed']) {
                $anyFail = true;
            }
        }
        if ($anyFail) {
            fwrite(STDERR, "One or more plugins HARD REJECTED. See jxerr.log for the full multi-error report.\n");
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
        echo "Error log: {$errLog}\n";
        break;

    case 'install-required':
        foreach ($catalog['plugins'] as $p) {
            if (!empty($p['required'])) {
                install_plugin($p['id'], $catalog, $pluginRoot, $modulesDir, $stateFile, $backupPre, $errLog);
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
        install_plugin($id, $catalog, $pluginRoot, $modulesDir, $stateFile, $backupPre, $errLog);
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
        echo "Allow gate: windows + mac + linux + web (jx). Non-portable = HARD REJECT.\n";
        echo "All gate errors accumulate into jxerr.log (multi-error, not stop-on-first).\n";
        break;

    default:
        fwrite(STDERR, "Unknown command {$cmd}\n");
        exit(1);
}

exit(0);
