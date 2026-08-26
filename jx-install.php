#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * jx installer — plugins from one source dir; one-at-a-time; dual backups.
 * Allow gate: windows + mac + linux + web (jx). Non-portable = hard reject.
 * Errors collect into jxerr.log (multi-error); condensed summary on stderr.
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

/** @var list<array{code:string,plugin:string,file:?string,message:string}> */
$JX_ERROR_BUFFER = [];

/**
 * Record one error (never aborts by itself).
 */
function jx_err(string $code, string $plugin, string $message, ?string $file = null): void
{
    global $JX_ERROR_BUFFER;
    $JX_ERROR_BUFFER[] = [
        'code' => strtoupper($code),
        'plugin' => $plugin,
        'file' => $file,
        'message' => trim($message),
    ];
}

function jx_err_count(): int
{
    global $JX_ERROR_BUFFER;
    return count($JX_ERROR_BUFFER);
}

function jx_err_clear(): void
{
    global $JX_ERROR_BUFFER;
    $JX_ERROR_BUFFER = [];
}

/**
 * Format one error line for log / stderr.
 */
function jx_err_format_line(array $e, int $n): string
{
    $loc = $e['file'] !== null && $e['file'] !== '' ? " @ {$e['file']}" : '';
    return sprintf('%2d. [%s] %s%s — %s', $n, $e['code'], $e['plugin'], $loc, $e['message']);
}

/**
 * Write buffer to jxerr.log; return condensed multi-error text for stderr.
 * Clears the buffer.
 */
function jx_err_flush(string $errLog, string $context = ''): string
{
    global $JX_ERROR_BUFFER;
    if ($JX_ERROR_BUFFER === []) {
        return '';
    }

    $n = count($JX_ERROR_BUFFER);
    $ts = date('Y-m-d H:i:s T');
    $ctx = $context !== '' ? $context : 'jx';

    // --- jxerr.log block ---
    $log = [];
    $log[] = str_repeat('=', 64);
    $log[] = "jxerr  {$ts}";
    $log[] = "context: {$ctx}";
    $log[] = "count:   {$n}";
    $log[] = str_repeat('-', 64);
    foreach ($JX_ERROR_BUFFER as $i => $e) {
        $log[] = jx_err_format_line($e, $i + 1);
    }
    $log[] = str_repeat('=', 64);
    $log[] = '';
    file_put_contents($errLog, implode("\n", $log) . "\n", FILE_APPEND | LOCK_EX);

    // --- condensed stderr ---
    $out = [];
    $out[] = "jx: {$n} error" . ($n === 1 ? '' : 's') . " ({$ctx})";
    foreach ($JX_ERROR_BUFFER as $i => $e) {
        $out[] = jx_err_format_line($e, $i + 1);
    }
    $out[] = "log: {$errLog}";

    $JX_ERROR_BUFFER = [];
    return implode("\n", $out);
}

/** Standard hard-reject banner + flushed errors. */
function jx_hard_reject(string $errLog, string $plugin, string $context): void
{
    $body = jx_err_flush($errLog, $context);
    $banner = [
        '',
        '┌──────────────────────────────────────────────────────────┐',
        '│  HARD REJECT                                             │',
        '├──────────────────────────────────────────────────────────┤',
        '│  Plugin is not portable for this jx version.             │',
        '│  Not possible here. A later version might support it;    │',
        '│  this one cannot use it.                                 │',
        '└──────────────────────────────────────────────────────────┘',
        "plugin: {$plugin}",
        '',
        $body,
        '',
    ];
    fwrite(STDERR, implode("\n", $banner));
    exit(1);
}

function ensure_dirs(string ...$dirs): void
{
    foreach ($dirs as $d) {
        if (!is_dir($d) && !@mkdir($d, 0770, true)) {
            fwrite(STDERR, "jx: cannot create directory {$d}\n");
            exit(1);
        }
    }
}

function load_catalog(string $pluginRoot): array
{
    $path = $pluginRoot . '/catalog.json';
    if (!is_file($path)) {
        fwrite(STDERR, "jx: missing plugins/catalog.json\n");
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
        jx_err('E-CATALOG', $id, 'not in catalog');
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
        jx_err('E-MANIFEST', $id, 'missing plugin.json', $meta['path'] . '/plugin.json');
    }

    foreach ($required as $t) {
        if (in_array($t, $declared, true)) {
            $targetOk[$t] = true;
        } else {
            $targetOk[$t] = false;
            jx_err('E-TARGET', $id, "missing required target '{$t}' (need windows, mac, linux, web)");
        }
    }

    $src = $pluginRoot . '/' . $meta['path'];
    if (!is_dir($src)) {
        jx_err('E-SOURCE', $id, 'source directory missing', $meta['path']);
        foreach ($required as $t) {
            $targetOk[$t] = false;
        }
        return ['allowed' => false, 'targets' => $targetOk];
    }

    $entryName = $pj['entry'] ?? 'bootstrap.php';
    $entry = $src . '/' . $entryName;
    $entryRel = $meta['path'] . '/' . $entryName;

    if (!is_file($entry)) {
        jx_err('E-ENTRY', $id, 'entry file not found', $entryRel);
        foreach ($required as $t) {
            $targetOk[$t] = false;
        }
    } else {
        $php = PHP_BINARY ?: 'php';
        $lint = @shell_exec(escapeshellarg($php) . ' -l ' . escapeshellarg($entry) . ' 2>&1');
        if (is_string($lint) && $lint !== '' && !str_contains($lint, 'No syntax errors')) {
            $detail = trim(preg_replace('/\s+/', ' ', $lint) ?? $lint);
            jx_err('E-SYNTAX', $id, $detail, $entryRel);
            foreach ($required as $t) {
                $targetOk[$t] = false;
            }
        } elseif (!is_string($lint) || $lint === '') {
            $code = (string)file_get_contents($entry);
            if ($code !== '') {
                $tokens = @token_get_all($code);
                if ($tokens === [] || $tokens === false) {
                    jx_err('E-SYNTAX', $id, 'parse failed (token_get_all)', $entryRel);
                    foreach ($required as $t) {
                        $targetOk[$t] = false;
                    }
                }
            }
        }
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile() || !str_ends_with(strtolower($file->getFilename()), '.php')) {
            continue;
        }
        $rel = $meta['path'] . '/' . $file->getFilename();
        $body = (string)file_get_contents($file->getPathname());

        if (preg_match('/\bdl\s*\(/', $body)) {
            jx_err('E-PORTABLE', $id, 'dl() is not portable — outside this version', $rel);
            foreach ($required as $t) {
                $targetOk[$t] = false;
            }
        }
        if (preg_match('/\bcom_\w+\s*\(/i', $body) || preg_match('/\bCOM\s*\(/', $body)) {
            jx_err('E-PORTABLE', $id, 'COM/Windows-only API — not portable', $rel);
            $targetOk['mac'] = false;
            $targetOk['linux'] = false;
            $targetOk['web'] = false;
        }
        if (preg_match('/\\+[A-Za-z]/|\b[A-Z]:\\/', $body) && !preg_match('/DIRECTORY_SEPARATOR|php_uname|PHP_OS/', $body)) {
            jx_err('E-PORTABLE', $id, 'hardcoded Windows paths without portable fallback', $rel);
            $targetOk['mac'] = false;
            $targetOk['linux'] = false;
            $targetOk['web'] = false;
        }
        if (preg_match('/php_sapi_name\s*\(\s*\)\s*===\s*[\'"]cli[\'"]/', $body)
            && !preg_match('/(web|fallback|else\b|!==\s*[\'"]cli[\'"])/i', $body)) {
            jx_err('E-WEB', $id, 'CLI-only SAPI gate without web (jx) fallback', $rel);
            $targetOk['web'] = false;
        }
        if (preg_match('/\b(curl_init|mysqli_connect|pg_connect)\s*\(/', $body)
            && preg_match('/extension_loaded\s*\(/', $body) === 0
            && preg_match('/function_exists\s*\(/', $body) === 0) {
            jx_err('E-GUARD', $id, 'extension call without function_exists/extension_loaded guard', $rel);
        }
    }

    $allowed = true;
    foreach ($required as $t) {
        if (empty($targetOk[$t])) {
            $allowed = false;
        }
    }
    if (!$allowed) {
        jx_err(
            'E-REJECT',
            $id,
            'non-portable or incomplete targets — not possible in this jx version'
        );
    }

    return ['allowed' => $allowed, 'targets' => $targetOk];
}

function print_target_report(string $id, array $report): void
{
    echo "plugin  {$id}\n";
    foreach ($report['targets'] as $t => $ok) {
        echo '  ' . ($ok ? 'ok  ' : 'fail') . "  {$t}\n";
    }
    echo $report['allowed'] ? "result  ALLOWED\n" : "result  HARD REJECT\n";
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
        jx_err('E-CATALOG', $id, 'unknown plugin');
        jx_hard_reject($errLog, $id, "install:{$id}");
    }
    $meta = $byId[$id];
    $state = load_state($stateFile);
    if (in_array($id, $state['order'], true)) {
        echo "jx: already installed: {$id}\n";
        return;
    }

    $report = check_plugin_targets($id, $catalog, $pluginRoot);
    print_target_report($id, $report);

    if (!$report['allowed']) {
        jx_hard_reject($errLog, $id, "install:{$id}");
    }

    if (jx_err_count() > 0) {
        // soft notes only
        $notes = jx_err_flush($errLog, "install-notes:{$id}");
        fwrite(STDERR, $notes . "\n");
    }

    $pluginJson = $pluginRoot . '/' . $meta['path'] . '/plugin.json';
    $pj = [];
    if (is_file($pluginJson)) {
        $pj = json_decode((string)file_get_contents($pluginJson), true) ?: [];
        foreach ($pj['depends'] ?? [] as $dep) {
            if (!in_array($dep, $state['order'], true)) {
                jx_err('E-DEPEND', $id, "dependency not installed: {$dep}");
                $text = jx_err_flush($errLog, "install:{$id}");
                fwrite(STDERR, $text . "\n");
                exit(1);
            }
        }
    }

    $preId = backup_pre($modulesDir, $stateFile, $backupPre);
    echo "jx: pre-install backup {$preId}\n";

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
            jx_err('E-BOOT', $id, 'bootstrap reported failure');
            $text = jx_err_flush($errLog, "bootstrap:{$id}");
            fwrite(STDERR, $text . "\n");
            exit(1);
        }
        echo "jx: bootstrapped {$id}\n";
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
    echo "jx: installed {$id} (#" . count($state['order']) . ")\n";
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
        echo "catalog  plugins/   targets: windows mac linux web\n";
        foreach ($catalog['plugins'] as $p) {
            $on = in_array($p['id'], $state['order'], true) ? 'on ' : 'off';
            $req = !empty($p['required']) ? 'req' : 'opt';
            $report = check_plugin_targets($p['id'], $catalog, $pluginRoot);
            if (!$report['allowed'] || jx_err_count() > 0) {
                jx_err_flush($errLog, 'list:' . $p['id']);
            }
            $gate = $report['allowed'] ? 'pass' : 'reject';
            echo sprintf("  [%s] %-16s  %s  %s\n", $on, $p['id'], $req, $gate);
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
                echo jx_err_flush($errLog, 'check-targets:' . $p['id']) . "\n";
            }
            echo "\n";
            if (!$report['allowed']) {
                $anyFail = true;
            }
        }
        if ($anyFail) {
            fwrite(STDERR, "jx: one or more plugins HARD REJECTED — see jxerr.log\n");
        }
        exit($anyFail ? 1 : 0);

    case 'status':
        $state = load_state($stateFile);
        echo "installed:\n";
        if ($state['order'] === []) {
            echo "  (none)\n";
        }
        foreach ($state['order'] as $i => $id) {
            $t = $state['installed'][$id]['targets'] ?? [];
            $tstr = $t === [] ? '' : ' [' . implode(' ', array_keys(array_filter($t))) . ']';
            echo '  ' . ($i + 1) . ". {$id}{$tstr}\n";
        }
        echo 'backups  pre=' . (is_dir($backupPre) ? count(glob($backupPre . '/*', GLOB_ONLYDIR) ?: []) : 0);
        echo '  full=' . (is_dir($backupFull) ? count(glob($backupFull . '/*', GLOB_ONLYDIR) ?: []) : 0) . "\n";
        echo "log      {$errLog}\n";
        break;

    case 'install-required':
        foreach ($catalog['plugins'] as $p) {
            if (!empty($p['required'])) {
                install_plugin($p['id'], $catalog, $pluginRoot, $modulesDir, $stateFile, $backupPre, $errLog);
            }
        }
        $fullId = backup_full($hostRoot, $backupFull);
        echo "jx: full backup {$fullId}\n";
        break;

    case 'install':
        $id = $argv[1] ?? '';
        if ($id === '') {
            fwrite(STDERR, "jx: usage: jx-install.php install <plugin-id>\n");
            exit(1);
        }
        install_plugin($id, $catalog, $pluginRoot, $modulesDir, $stateFile, $backupPre, $errLog);
        break;

    case 'backup-full':
        $fullId = backup_full($hostRoot, $backupFull);
        echo "jx: full backup {$fullId}\n";
        echo "     {$backupFull}/{$fullId}\n";
        break;

    case 'restore-full':
        $id = $argv[1] ?? '';
        $src = $backupFull . '/' . $id;
        if ($id === '' || !is_dir($src)) {
            fwrite(STDERR, "jx: usage: jx-install.php restore-full <timestamp>\n");
            exit(1);
        }
        $preId = backup_pre($modulesDir, $stateFile, $backupPre);
        echo "jx: safety pre-backup {$preId}\n";
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
        echo "jx: restored full backup {$id}\n";
        break;

    case 'help':
    case '-h':
    case '--help':
        echo "jx-install.php list|status|check-targets [id]|install-required|install <id>|backup-full|restore-full <ts>\n";
        echo "gate: windows mac linux web — non-portable = HARD REJECT\n";
        echo "errors: multi-collect → jxerr.log + condensed stderr\n";
        break;

    default:
        fwrite(STDERR, "jx: unknown command {$cmd}\n");
        exit(1);
}

exit(0);
