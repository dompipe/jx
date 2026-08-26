#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * jx installer — plugins from one source dir; one-at-a-time; dual backups.
 * Allow gate: windows + mac + linux + web (jx). Non-portable = hard reject.
 * Errors collect into jxerr.log (multi-error) with mandatory line numbers.
 */

$root = __DIR__;
define('JX_ROOT', $root);
$pluginRoot = $root . '/plugins';
$hostRoot = $root . '/host';
$layoutFile = $hostRoot . '/layout.json';
$linksFile = $hostRoot . '/links.json';
$layout = is_file($layoutFile)
    ? (json_decode((string)file_get_contents($layoutFile), true) ?: [])
    : [];
$modulesDir = isset($layout['shared_plugins']) && is_string($layout['shared_plugins'])
    ? $layout['shared_plugins']
    : $hostRoot . '/modules';
$stateFile = $hostRoot . '/state.json';
$backupPre = $hostRoot . '/backups/pre';
$backupFull = $hostRoot . '/backups/full';
$errLog = $root . '/jxerr.log';

const JX_REQUIRED_TARGETS = ['windows', 'mac', 'linux', 'web'];

/** @var list<array{code:string,plugin:string,file:?string,line:?int,message:string}> */
$JX_ERROR_BUFFER = [];

/**
 * Record one error. Line number is required whenever a file is known.
 */
function jx_err(string $code, string $plugin, string $message, ?string $file = null, ?int $line = null): void
{
    global $JX_ERROR_BUFFER;
    $JX_ERROR_BUFFER[] = [
        'code' => strtoupper($code),
        'plugin' => $plugin,
        'file' => $file,
        'line' => $line,
        'message' => trim($message),
    ];
}

function jx_err_count(): int
{
    global $JX_ERROR_BUFFER;
    return count($JX_ERROR_BUFFER);
}

/**
 * Find 1-based line numbers of every match of $pattern in $body.
 * @return list<int>
 */
function jx_match_lines(string $body, string $pattern): array
{
    $lines = [];
    if (preg_match_all($pattern, $body, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $hit) {
            $offset = $hit[1];
            $lines[] = substr_count(substr($body, 0, $offset), "\n") + 1;
        }
    }
    return $lines !== [] ? $lines : [];
}

/** First matching line, or null. */
function jx_first_match_line(string $body, string $pattern): ?int
{
    $lines = jx_match_lines($body, $pattern);
    return $lines[0] ?? null;
}

/** Parse "on line N" from php -l output. */
function jx_parse_lint_line(string $lint): ?int
{
    if (preg_match('/on line\s+(\d+)/i', $lint, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/:(\d+)\s*$/m', $lint, $m)) {
        return (int)$m[1];
    }
    return null;
}

function jx_err_format_line(array $e, int $n): string
{
    $loc = '';
    if ($e['file'] !== null && $e['file'] !== '') {
        $loc = ' @ ' . $e['file'];
        if ($e['line'] !== null && $e['line'] > 0) {
            $loc .= ':' . $e['line'];
        } else {
            $loc .= ':?';
        }
    } elseif ($e['line'] !== null && $e['line'] > 0) {
        $loc = ' @ line ' . $e['line'];
    }
    return sprintf('%2d. [%s] %s%s — %s', $n, $e['code'], $e['plugin'], $loc, $e['message']);
}

function jx_err_flush(string $errLog, string $context = ''): string
{
    global $JX_ERROR_BUFFER;
    if ($JX_ERROR_BUFFER === []) {
        return '';
    }

    $n = count($JX_ERROR_BUFFER);
    $ts = date('Y-m-d H:i:s T');
    $ctx = $context !== '' ? $context : 'jx';

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

    $out = [];
    $out[] = "jx: {$n} error" . ($n === 1 ? '' : 's') . " ({$ctx})";
    foreach ($JX_ERROR_BUFFER as $i => $e) {
        $out[] = jx_err_format_line($e, $i + 1);
    }
    $out[] = "log: {$errLog}";

    $JX_ERROR_BUFFER = [];
    return implode("\n", $out);
}

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

function remove_tree(string $path): void
{
    if (!is_dir($path) || is_link($path)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function ts(): string
{
    return (new DateTimeImmutable())->format('Ymd-His-u');
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

function backup_full(string $modulesDir, string $stateFile, string $backupFull): string
{
    ensure_dirs($backupFull);
    $id = ts();
    $dest = $backupFull . '/' . $id;
    mkdir($dest, 0770, true);
    copy_tree($modulesDir, $dest . '/modules');
    if (is_file($stateFile)) {
        copy($stateFile, $dest . '/state.json');
    }
    file_put_contents($dest . '/README.txt', "Full install backup {$id}\nRedirect host modules here to restore.\n");
    return $id;
}

/**
 * Emit one error per matching line for a portability pattern.
 * @return bool true if any match
 */
function jx_err_each_line(
    string $code,
    string $plugin,
    string $message,
    string $rel,
    string $body,
    string $pattern,
): bool {
    $lines = jx_match_lines($body, $pattern);
    if ($lines === []) {
        return false;
    }
    foreach ($lines as $ln) {
        jx_err($code, $plugin, $message, $rel, $ln);
    }
    return true;
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
        jx_err('E-CATALOG', $id, 'not in catalog', 'plugins/catalog.json', null);
        return ['allowed' => false, 'targets' => $targetOk];
    }

    $meta = $byId[$id];
    $declared = $meta['targets'] ?? [];
    $contextFree = true;
    $pluginJsonPath = $pluginRoot . '/' . $meta['path'] . '/plugin.json';
    $pluginJsonRel = $meta['path'] . '/plugin.json';
    $pj = [];
    if (is_file($pluginJsonPath)) {
        $pj = json_decode((string)file_get_contents($pluginJsonPath), true) ?: [];
        if (!empty($pj['targets']) && is_array($pj['targets'])) {
            $declared = array_values(array_unique(array_merge($declared, $pj['targets'])));
        }
        if (!empty($pj['depends'])) {
            $contextFree = false;
            jx_err(
                'E-CONTEXT',
                $id,
                'plugin packages must not depend on sibling packages',
                $pluginJsonRel,
                jx_first_match_line((string)file_get_contents($pluginJsonPath), '/"depends"/') ?? 1
            );
            foreach ($required as $t) {
                $targetOk[$t] = false;
            }
        }
    } else {
        jx_err('E-MANIFEST', $id, 'missing plugin.json', $pluginJsonRel, 1);
    }

    // catalog line hint: search catalog file for plugin id
    $catalogBody = (string)@file_get_contents($pluginRoot . '/catalog.json');
    $catalogLine = jx_first_match_line($catalogBody, '/"id"\s*:\s*"' . preg_quote($id, '/') . '"/');

    foreach ($required as $t) {
        if (in_array($t, $declared, true)) {
            $targetOk[$t] = true;
        } else {
            $targetOk[$t] = false;
            jx_err(
                'E-TARGET',
                $id,
                "missing required target '{$t}' (need windows, mac, linux, web)",
                'plugins/catalog.json',
                $catalogLine
            );
        }
    }
    if (!$contextFree) {
        foreach ($required as $t) {
            $targetOk[$t] = false;
        }
    }

    $src = $pluginRoot . '/' . $meta['path'];
    if (!is_dir($src)) {
        jx_err('E-SOURCE', $id, 'source directory missing', $meta['path'], 1);
        foreach ($required as $t) {
            $targetOk[$t] = false;
        }
        return ['allowed' => false, 'targets' => $targetOk];
    }

    $entryName = $pj['entry'] ?? 'bootstrap.php';
    $entry = $src . '/' . $entryName;
    $entryRel = $meta['path'] . '/' . $entryName;

    if (!is_file($entry)) {
        jx_err('E-ENTRY', $id, 'entry file not found', $entryRel, 1);
        foreach ($required as $t) {
            $targetOk[$t] = false;
        }
    } else {
        $php = PHP_BINARY ?: 'php';
        $lint = @shell_exec(escapeshellarg($php) . ' -l ' . escapeshellarg($entry) . ' 2>&1');
        if (is_string($lint) && $lint !== '' && !str_contains($lint, 'No syntax errors')) {
            $detail = trim(preg_replace('/\s+/', ' ', $lint) ?? $lint);
            $lintLine = jx_parse_lint_line($lint);
            jx_err('E-SYNTAX', $id, $detail, $entryRel, $lintLine ?? 1);
            foreach ($required as $t) {
                $targetOk[$t] = false;
            }
        } elseif (!is_string($lint) || $lint === '') {
            $code = (string)file_get_contents($entry);
            if ($code !== '') {
                $tokens = @token_get_all($code);
                if ($tokens === [] || $tokens === false) {
                    jx_err('E-SYNTAX', $id, 'parse failed (token_get_all)', $entryRel, 1);
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

        if (jx_err_each_line(
            'E-PORTABLE',
            $id,
            'dl() is not portable — outside this version',
            $rel,
            $body,
            '/\bdl\s*\(/'
        )) {
            foreach ($required as $t) {
                $targetOk[$t] = false;
            }
        }

        if (jx_err_each_line(
            'E-PORTABLE',
            $id,
            'COM/Windows-only API — not portable',
            $rel,
            $body,
            '/\bcom_\w+\s*\(|\bCOM\s*\(/i'
        )) {
            $targetOk['mac'] = false;
            $targetOk['linux'] = false;
            $targetOk['web'] = false;
        }

        if (jx_err_each_line(
            'E-PORTABLE',
            $id,
            'hardcoded Windows paths without portable fallback',
            $rel,
            $body,
            '~\b[A-Z]:[\\\\/]~i'
        )) {
            // only if no portable API nearby is still a hard flag; line is recorded
            if (!preg_match('/DIRECTORY_SEPARATOR|php_uname|PHP_OS/', $body)) {
                $targetOk['mac'] = false;
                $targetOk['linux'] = false;
                $targetOk['web'] = false;
            }
        }

        // CLI-only: report each matching line
        if (preg_match_all(
            '/php_sapi_name\s*\(\s*\)\s*===\s*[\'"]cli[\'"]/',
            $body,
            $mm,
            PREG_OFFSET_CAPTURE
        )) {
            $hasFallback = (bool)preg_match('/(web|fallback|else\b|!==\s*[\'"]cli[\'"])/i', $body);
            if (!$hasFallback) {
                foreach ($mm[0] as $hit) {
                    $ln = substr_count(substr($body, 0, $hit[1]), "\n") + 1;
                    jx_err('E-WEB', $id, 'CLI-only SAPI gate without web (jx) fallback', $rel, $ln);
                    $targetOk['web'] = false;
                }
            }
        }

        if (preg_match_all(
            '/\b(curl_init|mysqli_connect|pg_connect)\s*\(/',
            $body,
            $mm,
            PREG_OFFSET_CAPTURE
        )) {
            $guarded = preg_match('/extension_loaded\s*\(|function_exists\s*\(/', $body) === 1;
            if (!$guarded) {
                foreach ($mm[0] as $hit) {
                    $ln = substr_count(substr($body, 0, $hit[1]), "\n") + 1;
                    jx_err(
                        'E-GUARD',
                        $id,
                        'extension call without function_exists/extension_loaded guard',
                        $rel,
                        $ln
                    );
                }
            }
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
            'non-portable or incomplete targets — not possible in this jx version',
            $pluginJsonRel,
            1
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
        jx_err('E-CATALOG', $id, 'unknown plugin', 'plugins/catalog.json', null);
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
        $notes = jx_err_flush($errLog, "install-notes:{$id}");
        fwrite(STDERR, $notes . "\n");
    }

    $pluginJson = $pluginRoot . '/' . $meta['path'] . '/plugin.json';
    $pj = [];
    if (is_file($pluginJson)) {
        $pj = json_decode((string)file_get_contents($pluginJson), true) ?: [];
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
    file_put_contents($dst . '/.jx-root', JX_ROOT . "\n", LOCK_EX);

    $entry = $dst . '/' . ($pj['entry'] ?? 'bootstrap.php');
    if (is_file($entry)) {
        try {
            $result = require $entry;
            if (is_array($result) && isset($result['ok']) && !$result['ok']) {
                throw new RuntimeException('bootstrap reported failure');
            }
        } catch (Throwable $e) {
            remove_tree($dst);
            jx_err(
                'E-BOOT',
                $id,
                $e->getMessage(),
                $meta['path'] . '/' . ($pj['entry'] ?? 'bootstrap.php'),
                1
            );
            $text = jx_err_flush($errLog, "bootstrap:{$id}");
            fwrite(STDERR, $text . "\n");
            exit(1);
        }
        echo "jx: bootstrapped {$id}\n";
    }

    $hostModules = dirname($stateFile) . '/modules';
    if (realpath($hostModules) !== realpath($modulesDir)) {
        ensure_dirs($hostModules);
        create_directory_link($dst, $hostModules . '/' . $id);
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

/** @return array{platform:string,bin:string,shared_plugins:string,path_file:?string} */
function system_layout(): array
{
    $platform = PHP_OS_FAMILY;
    if ($platform === 'Windows') {
        $local = getenv('LOCALAPPDATA') ?: getenv('USERPROFILE') . '/AppData/Local';
        $programData = getenv('ProgramData') ?: 'C:/ProgramData';
        $layout = [
            'platform' => $platform,
            'bin' => $local . '/jx/bin',
            'shared_plugins' => $programData . '/jx/plugins',
            'path_file' => null,
        ];
    } elseif ($platform === 'Darwin') {
        $layout = [
            'platform' => $platform,
            'bin' => '/usr/local/bin',
            'shared_plugins' => '/usr/local/share/jx/plugins',
            'path_file' => '/etc/paths.d/jx',
        ];
    } else {
        $layout = [
            'platform' => $platform,
            'bin' => '/etc/bin',
            'shared_plugins' => '/etc/jx/plugins',
            'path_file' => '/etc/profile.d/jx.sh',
        ];
    }

    $binOverride = getenv('JX_BIN_DIR');
    $pluginOverride = getenv('JX_SHARED_PLUGIN_DIR');
    $pathOverride = getenv('JX_PATH_FILE');
    if (is_string($binOverride) && $binOverride !== '') {
        $layout['bin'] = $binOverride;
    }
    if (is_string($pluginOverride) && $pluginOverride !== '') {
        $layout['shared_plugins'] = $pluginOverride;
    }
    if (is_string($pathOverride) && $pathOverride !== '') {
        $layout['path_file'] = $pathOverride;
    }
    return $layout;
}

function create_directory_link(string $target, string $link): void
{
    if (is_dir($link) && realpath($link) === realpath($target)) {
        return;
    }
    if (file_exists($link) || is_link($link)) {
        throw new RuntimeException("Cannot link {$link}: path already exists");
    }
    if (PHP_OS_FAMILY !== 'Windows') {
        if (!symlink($target, $link)) {
            throw new RuntimeException("Cannot link {$link} to {$target}");
        }
        return;
    }

    putenv('JX_LINK_PATH=' . $link);
    putenv('JX_LINK_TARGET=' . $target);
    $script = <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
New-Item -ItemType Junction -Path $env:JX_LINK_PATH -Target $env:JX_LINK_TARGET | Out-Null
POWERSHELL;
    $process = proc_open(
        ['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException("Cannot start junction command for {$link}");
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || !is_dir($link)) {
        throw new RuntimeException("Cannot link {$link} to {$target}: " . trim($output));
    }
}

/** @return array{links:list<array{plugin:string,scope:string,context:string,link:string}>} */
function load_links(string $linksFile): array
{
    if (!is_file($linksFile)) {
        return ['links' => []];
    }
    $links = json_decode((string)file_get_contents($linksFile), true, 512, JSON_THROW_ON_ERROR);
    return isset($links['links']) && is_array($links['links']) ? $links : ['links' => []];
}

function save_links(string $linksFile, array $links): void
{
    file_put_contents(
        $linksFile,
        json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    );
}

function save_context_manifest(string $context, string $scope, array $plugins): void
{
    $jxDir = $context . '/.jx';
    ensure_dirs($jxDir);
    file_put_contents(
        $jxDir . '/plugins.json',
        json_encode(
            ['scope' => $scope, 'plugins' => $plugins],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . "\n",
        LOCK_EX
    );
}

function link_plugin_context(
    string $id,
    string $scope,
    string $contextPath,
    string $modulesDir,
    string $linksFile,
): void {
    if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $id) !== 1) {
        throw new InvalidArgumentException("Invalid plugin id: {$id}");
    }
    if (!in_array($scope, ['book', 'library'], true)) {
        throw new InvalidArgumentException('Scope must be book or library');
    }
    $context = realpath($contextPath);
    if ($context === false || !is_dir($context)) {
        throw new InvalidArgumentException("Context directory not found: {$contextPath}");
    }
    $package = $modulesDir . '/' . $id;
    if (!is_dir($package)) {
        throw new RuntimeException("Plugin is not installed: {$id}");
    }

    $pluginDir = $context . '/.jx/plugins';
    ensure_dirs($pluginDir);
    $link = $pluginDir . '/' . $id;
    create_directory_link($package, $link);

    $manifestPath = $context . '/.jx/plugins.json';
    $manifest = is_file($manifestPath)
        ? (json_decode((string)file_get_contents($manifestPath), true) ?: [])
        : [];
    if (isset($manifest['scope']) && $manifest['scope'] !== $scope) {
        throw new RuntimeException(
            "Context is already declared as {$manifest['scope']}, not {$scope}"
        );
    }
    $plugins = isset($manifest['plugins']) && is_array($manifest['plugins'])
        ? $manifest['plugins']
        : [];
    $plugins[$id] = ['path' => $link, 'package' => realpath($package) ?: $package];
    save_context_manifest($context, $scope, $plugins);

    $registry = load_links($linksFile);
    $registry['links'] = array_values(array_filter(
        $registry['links'],
        fn(array $item): bool => !(
            $item['plugin'] === $id
            && $item['scope'] === $scope
            && $item['context'] === $context
        )
    ));
    $registry['links'][] = [
        'plugin' => $id,
        'scope' => $scope,
        'context' => $context,
        'link' => $link,
    ];
    save_links($linksFile, $registry);
    echo "jx: linked {$id} to {$scope} {$context}\n";
}

function unlink_plugin_context(
    string $id,
    string $scope,
    string $contextPath,
    string $modulesDir,
    string $linksFile,
): void {
    if (!in_array($scope, ['book', 'library'], true)) {
        throw new InvalidArgumentException('Scope must be book or library');
    }
    $context = realpath($contextPath) ?: $contextPath;
    $package = $modulesDir . '/' . $id;
    $link = $context . '/.jx/plugins/' . $id;
    remove_host_link($package, $link);

    $manifestPath = $context . '/.jx/plugins.json';
    if (is_file($manifestPath)) {
        $manifest = json_decode((string)file_get_contents($manifestPath), true) ?: [];
        if (isset($manifest['scope']) && $manifest['scope'] !== $scope) {
            throw new RuntimeException(
                "Context is declared as {$manifest['scope']}, not {$scope}"
            );
        }
        $plugins = isset($manifest['plugins']) && is_array($manifest['plugins'])
            ? $manifest['plugins']
            : [];
        unset($plugins[$id]);
        save_context_manifest($context, $scope, $plugins);
    }

    $registry = load_links($linksFile);
    $registry['links'] = array_values(array_filter(
        $registry['links'],
        fn(array $item): bool => !(
            $item['plugin'] === $id
            && $item['scope'] === $scope
            && $item['context'] === $context
        )
    ));
    save_links($linksFile, $registry);
    echo "jx: unlinked {$id} from {$scope} {$context}\n";
}

function uninstall_plugin(
    string $id,
    string $modulesDir,
    string $stateFile,
    string $backupPre,
    string $linksFile,
): void {
    if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $id) !== 1) {
        throw new InvalidArgumentException("Invalid plugin id: {$id}");
    }
    $state = load_state($stateFile);
    if (!in_array($id, $state['order'], true)) {
        echo "jx: not installed: {$id}\n";
        return;
    }
    $preId = backup_pre($modulesDir, $stateFile, $backupPre);
    echo "jx: pre-uninstall backup {$preId}\n";

    $registry = load_links($linksFile);
    foreach ($registry['links'] as $item) {
        if ($item['plugin'] === $id) {
            unlink_plugin_context(
                $id,
                $item['scope'],
                $item['context'],
                $modulesDir,
                $linksFile
            );
        }
    }
    $package = $modulesDir . '/' . $id;
    $hostLink = dirname($stateFile) . '/modules/' . $id;
    if (
        realpath(dirname($hostLink)) !== realpath($modulesDir)
        && realpath($hostLink) === realpath($package)
    ) {
        remove_host_link($package, $hostLink);
    }
    remove_tree($package);
    unset($state['installed'][$id]);
    $state['order'] = array_values(array_filter(
        $state['order'],
        fn(string $installed): bool => $installed !== $id
    ));
    save_state($stateFile, $state);
    echo "jx: uninstalled {$id}\n";
}

function create_command_link(string $target, string $link): void
{
    $body = "#!/bin/sh\nexec php " . escapeshellarg($target) . ' "$@"' . "\n";
    if (is_link($link) && realpath($link) === realpath($target)) {
        unlink($link);
    }
    if (is_file($link) && (string)file_get_contents($link) === $body) {
        return;
    }
    if (file_exists($link) || is_link($link)) {
        throw new RuntimeException("Cannot install command {$link}: path already exists");
    }
    if (file_put_contents($link, $body, LOCK_EX) === false || !chmod($link, 0755)) {
        throw new RuntimeException("Cannot install command {$link}");
    }
}

function update_system_path(array $layout): void
{
    if (getenv('JX_SKIP_PATH_UPDATE') === '1') {
        return;
    }
    if ($layout['platform'] === 'Windows') {
        putenv('JX_BIN_TO_ADD=' . $layout['bin']);
        $script = <<<'POWERSHELL'
$bin = $env:JX_BIN_TO_ADD
$path = [Environment]::GetEnvironmentVariable('Path', 'User')
$parts = @($path -split ';' | Where-Object { $_ -ne '' })
if ($parts -notcontains $bin) {
    [Environment]::SetEnvironmentVariable('Path', (($parts + $bin) -join ';'), 'User')
}
POWERSHELL;
        $process = proc_open(
            ['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start PowerShell to update User PATH');
        }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Cannot update User PATH: ' . trim($output));
        }
        return;
    }

    if ($layout['path_file'] === null) {
        return;
    }
    $body = $layout['platform'] === 'Darwin'
        ? $layout['bin'] . "\n"
        : 'export PATH="' . $layout['bin'] . ':$PATH"' . "\n";
    if (file_put_contents($layout['path_file'], $body, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write PATH file ' . $layout['path_file']);
    }
}

function remove_system_path(array $layout): void
{
    if (getenv('JX_SKIP_PATH_UPDATE') === '1') {
        return;
    }
    if ($layout['platform'] === 'Windows') {
        putenv('JX_BIN_TO_REMOVE=' . $layout['bin']);
        $script = <<<'POWERSHELL'
$bin = $env:JX_BIN_TO_REMOVE
$path = [Environment]::GetEnvironmentVariable('Path', 'User')
$parts = @($path -split ';' | Where-Object { $_ -ne '' -and $_ -ne $bin })
[Environment]::SetEnvironmentVariable('Path', ($parts -join ';'), 'User')
POWERSHELL;
        $process = proc_open(
            ['powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start PowerShell to update User PATH');
        }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Cannot update User PATH: ' . trim($output));
        }
        return;
    }

    $pathFile = $layout['path_file'];
    if ($pathFile === null || !is_file($pathFile)) {
        return;
    }
    $expected = $layout['platform'] === 'Darwin'
        ? $layout['bin'] . "\n"
        : 'export PATH="' . $layout['bin'] . ':$PATH"' . "\n";
    if ((string)file_get_contents($pathFile) !== $expected) {
        throw new RuntimeException("Refusing to remove modified PATH file {$pathFile}");
    }
    unlink($pathFile);
}

function remove_host_link(string $target, string $link): void
{
    if (!is_dir($link) || realpath($link) !== realpath($target)) {
        return;
    }
    if (PHP_OS_FAMILY === 'Windows' && !is_link($link)) {
        if (!rmdir($link)) {
            throw new RuntimeException("Cannot remove junction {$link}");
        }
        return;
    }
    if (!unlink($link)) {
        throw new RuntimeException("Cannot remove link {$link}");
    }
}

function remove_command(string $path, string $target): void
{
    if (is_link($path) && realpath($path) === realpath($target)) {
        unlink($path);
        return;
    }
    $body = "#!/bin/sh\nexec php " . escapeshellarg($target) . ' "$@"' . "\n";
    if (is_file($path) && (string)file_get_contents($path) === $body) {
        unlink($path);
    }
}

function uninstall_system_layout(
    string $root,
    string $hostRoot,
    string $layoutFile,
    string $stateFile,
    string $linksFile,
    bool $keepPlugins,
    bool $dryRun,
): void {
    $layout = is_file($layoutFile)
        ? (json_decode((string)file_get_contents($layoutFile), true) ?: system_layout())
        : system_layout();
    echo "platform        {$layout['platform']}\n";
    echo "bin             {$layout['bin']}\n";
    echo "shared plugins  {$layout['shared_plugins']}\n";
    echo 'plugin data     ' . ($keepPlugins ? 'keep' : 'backup then remove') . "\n";
    if ($dryRun) {
        echo "result          dry run; no changes\n";
        return;
    }

    $shared = $layout['shared_plugins'];
    $packages = glob($shared . '/*', GLOB_ONLYDIR) ?: [];
    $ownsShared = true;
    foreach ($packages as $package) {
        $marker = $package . '/.jx-root';
        if (
            !is_file($marker)
            || realpath(trim((string)file_get_contents($marker))) !== realpath($root)
        ) {
            $ownsShared = false;
            break;
        }
    }
    if (!$keepPlugins && is_dir($shared) && !$ownsShared) {
        throw new RuntimeException("Refusing to remove unowned shared plugin directory {$shared}");
    }

    remove_system_path($layout);
    if ($layout['platform'] === 'Windows') {
        foreach (['jx.cmd', 'jx-install.cmd'] as $name) {
            $path = $layout['bin'] . '/' . $name;
            if (is_file($path) && str_contains((string)file_get_contents($path), $root)) {
                unlink($path);
            }
        }
        @rmdir($layout['bin']);
    } else {
        remove_command($layout['bin'] . '/jx', $root . '/jx-run.php');
        remove_command($layout['bin'] . '/jx-install', $root . '/jx-install.php');
    }

    if (!$keepPlugins) {
        $registry = load_links($linksFile);
        foreach ($registry['links'] as $item) {
            unlink_plugin_context(
                $item['plugin'],
                $item['scope'],
                $item['context'],
                $shared,
                $linksFile
            );
        }
        if (is_file($linksFile)) {
            unlink($linksFile);
        }
    }
    if (!$keepPlugins) {
        if (is_dir($shared)) {
            foreach ($packages as $package) {
                remove_host_link($package, $hostRoot . '/modules/' . basename($package));
            }
            $backup = $hostRoot . '/backups/uninstall/' . ts();
            ensure_dirs($backup);
            copy_tree($shared, $backup . '/modules');
            if (is_file($stateFile)) {
                copy($stateFile, $backup . '/state.json');
                unlink($stateFile);
            }
            remove_tree($shared);
            echo "backup          {$backup}\n";
        }
        if (is_file($layoutFile)) {
            unlink($layoutFile);
        }
        ensure_dirs($hostRoot . '/modules');
    }
    echo "result          uninstalled\n";
}

function install_system_layout(
    string $root,
    string $hostRoot,
    string $layoutFile,
    string $pluginRoot,
    bool $dryRun,
): void {
    $layout = system_layout();
    echo "platform        {$layout['platform']}\n";
    echo "bin             {$layout['bin']}\n";
    echo "shared plugins  {$layout['shared_plugins']}\n";
    echo "host link       {$hostRoot}/modules\n";
    if ($dryRun) {
        echo "result          dry run; no changes\n";
        return;
    }

    ensure_dirs($hostRoot, $layout['bin'], $layout['shared_plugins']);
    $localModules = $hostRoot . '/modules';
    if (is_dir($localModules) && realpath($localModules) === realpath($layout['shared_plugins'])) {
        remove_host_link($layout['shared_plugins'], $localModules);
    }
    ensure_dirs($localModules);
    foreach (glob($localModules . '/*', GLOB_ONLYDIR) ?: [] as $localPlugin) {
        $id = basename($localPlugin);
        $sharedPlugin = $layout['shared_plugins'] . '/' . $id;
        if (is_dir($sharedPlugin) && realpath($localPlugin) === realpath($sharedPlugin)) {
            continue;
        }
        copy_tree($localPlugin, $sharedPlugin);
        file_put_contents($sharedPlugin . '/.jx-root', $root . "\n", LOCK_EX);
        remove_tree($localPlugin);
        create_directory_link($sharedPlugin, $localPlugin);
    }
    foreach (glob($layout['shared_plugins'] . '/*', GLOB_ONLYDIR) ?: [] as $sharedPlugin) {
        $marker = $sharedPlugin . '/.jx-root';
        if (
            !is_file($marker)
            || realpath(trim((string)file_get_contents($marker))) !== realpath($root)
        ) {
            throw new RuntimeException("Shared package is not owned by this JX: {$sharedPlugin}");
        }
        create_directory_link($sharedPlugin, $localModules . '/' . basename($sharedPlugin));
    }

    file_put_contents(
        $layoutFile,
        json_encode($layout, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        LOCK_EX
    );

    if ($layout['platform'] === 'Windows') {
        file_put_contents(
            $layout['bin'] . '/jx.cmd',
            "@echo off\r\nphp \"{$root}\\jx-run.php\" %*\r\n",
            LOCK_EX
        );
        file_put_contents(
            $layout['bin'] . '/jx-install.cmd',
            "@echo off\r\nphp \"{$root}\\jx-install.php\" %*\r\n",
            LOCK_EX
        );
    } else {
        create_command_link($root . '/jx-run.php', $layout['bin'] . '/jx');
        create_command_link($root . '/jx-install.php', $layout['bin'] . '/jx-install');
    }
    update_system_path($layout);
    echo "result          installed\n";
}

// --- CLI ---
$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$cmd = $argv[0] ?? 'status';

if ($cmd === 'install-system') {
    install_system_layout(
        $root,
        $hostRoot,
        $layoutFile,
        $pluginRoot,
        in_array('--dry-run', $argv, true),
    );
    exit(0);
}

if ($cmd === 'uninstall-system') {
    uninstall_system_layout(
        $root,
        $hostRoot,
        $layoutFile,
        $stateFile,
        $linksFile,
        in_array('--keep-plugins', $argv, true),
        in_array('--dry-run', $argv, true),
    );
    exit(0);
}

ensure_dirs($hostRoot, $modulesDir, $backupPre, $backupFull);
$catalog = load_catalog($pluginRoot);

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
        $fullId = backup_full($modulesDir, $stateFile, $backupFull);
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

    case 'uninstall':
        $id = $argv[1] ?? '';
        if ($id === '') {
            fwrite(STDERR, "jx: usage: jx-install.php uninstall <plugin-id>\n");
            exit(1);
        }
        uninstall_plugin($id, $modulesDir, $stateFile, $backupPre, $linksFile);
        break;

    case 'link':
    case 'unlink':
        $id = $argv[1] ?? '';
        $scope = $argv[2] ?? '';
        $context = $argv[3] ?? '';
        if ($id === '' || $scope === '' || $context === '') {
            fwrite(
                STDERR,
                "jx: usage: jx-install.php {$cmd} <plugin-id> <book|library> <path>\n"
            );
            exit(1);
        }
        if ($cmd === 'link') {
            link_plugin_context($id, $scope, $context, $modulesDir, $linksFile);
        } else {
            unlink_plugin_context($id, $scope, $context, $modulesDir, $linksFile);
        }
        break;

    case 'backup-full':
        $fullId = backup_full($modulesDir, $stateFile, $backupFull);
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
        $hostModules = dirname($stateFile) . '/modules';
        $sharedMode = realpath($hostModules) !== realpath($modulesDir);
        if ($sharedMode) {
            $oldState = load_state($stateFile);
            foreach ($oldState['order'] as $oldId) {
                remove_host_link(
                    $modulesDir . '/' . $oldId,
                    $hostModules . '/' . $oldId
                );
            }
        }
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
        if ($sharedMode) {
            ensure_dirs($hostModules);
            $restoredState = load_state($stateFile);
            foreach ($restoredState['order'] as $restoredId) {
                $package = $modulesDir . '/' . $restoredId;
                if (is_dir($package)) {
                    create_directory_link($package, $hostModules . '/' . $restoredId);
                }
            }
        }
        echo "jx: restored full backup {$id}\n";
        break;

    case 'help':
    case '-h':
    case '--help':
        echo "jx-install.php install-system [--dry-run]|uninstall-system [--keep-plugins] [--dry-run]|list|status|check-targets [id]|install-required|install <id>|uninstall <id>|link <id> <book|library> <path>|unlink <id> <book|library> <path>|backup-full|restore-full <ts>\n";
        echo "gate: windows mac linux web — non-portable = HARD REJECT\n";
        echo "errors: multi-collect with file:line → jxerr.log\n";
        break;

    default:
        fwrite(STDERR, "jx: unknown command {$cmd}\n");
        exit(1);
}

exit(0);
