#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * jx.exe compiler / interpreter frontend
 *
 *   jx.exe [--print|-v] [--report[=compact|verbose|json]|--quiet]
 *          [-O0|-O1] [-o out.pbc] [-c 'src'] [file.jx|file.pasl|file.pbc]
 *
 * The Windows jx.exe launcher delegates here, so compiler messages emitted by
 * this file are the authoritative jx.exe CLI contract on every host.
 */
namespace jx;

$root = __DIR__;
require_once $root . '/jx-lang.php';
require_once $root . '/jx-bytecode-page-report.php';

use pasm\lang\Engine as PaslEngine;
use pasm\lang\LangException;
use pasm\lang\PbcFile;
use Throwable;

$verbose = false;
$print = false;
$optimize = true;
$outFile = null;
$sourceArg = null;
$pbcArg = null;
$inline = null;
$reportMode = JxCompilerOutput::COMPACT;
$reportEnabled = true;

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

while ($argv !== []) {
    $a = array_shift($argv);
    if ($a === '-v' || $a === '--verbose') {
        $verbose = true;
        $print = true;
        $reportMode = JxCompilerOutput::VERBOSE;
    } elseif ($a === '--print') {
        $print = true;
    } elseif ($a === '--quiet' || $a === '-q') {
        $reportEnabled = false;
    } elseif ($a === '--report') {
        $reportEnabled = true;
    } elseif (is_string($a) && str_starts_with($a, '--report=')) {
        $reportEnabled = true;
        $value = strtolower(substr($a, 9));
        if (!in_array($value, [JxCompilerOutput::COMPACT, JxCompilerOutput::VERBOSE, JxCompilerOutput::JSON], true)) {
            fwrite(STDERR, "jx.exe: --report must be compact, verbose, or json\n");
            exit(2);
        }
        $reportMode = $value;
    } elseif ($a === '-O0') {
        $optimize = false;
    } elseif ($a === '-O1' || $a === '-O') {
        $optimize = true;
    } elseif ($a === '-o') {
        $outFile = array_shift($argv);
    } elseif ($a === '-c') {
        $inline = array_shift($argv);
    } elseif ($a === '-h' || $a === '--help') {
        fwrite(STDOUT, "Usage: jx.exe [--print|-v] [--report[=compact|verbose|json]|--quiet] [-O0|-O1] [-o out.pbc] [-c 'src'] [file.jx|file.pasl|file.pbc]\n");
        exit(0);
    } elseif (is_string($a) && str_ends_with($a, '.pbc')) {
        $pbcArg = $a;
    } else {
        $sourceArg = $a;
    }
}

/** Emit one official jx.exe bytecode page message. */
$emitPage = static function(
    string $code,
    bool $optimized,
    PaslEngine $pasl,
    ?string $source,
    ?string $output,
) use (&$reportMode, &$reportEnabled): void {
    if (!$reportEnabled) return;
    $iter = $pasl->iteratorBindings();
    $report = new JxBytecodePageReport(
        page: 1,
        bytecode: $code,
        optimized: $optimized,
        fused: false,
        reactive: false,
        target: JxBytecodePageReport::TARGET_PASM,
        source: $source,
        shadow: null,
        dependencies: [],
        registers: [],
        iteratorSlots: count($iter),
        output: $output,
    );
    fwrite(STDERR, JxCompilerOutput::render($report, $reportMode) . "\n");
};

/** Compile one PASL-lowerable page and write PBC while retaining report data. */
$compilePage = static function(
    PaslEngine $pasl,
    string $src,
    string $outFile,
    bool $optimized,
    ?string $sourceName,
) use ($emitPage): void {
    $code = $pasl->compile($src);
    $flags = $optimized ? PbcFile::FLAG_OPTIMIZED : 0;
    PbcFile::write($outFile, $code, $flags);
    $emitPage($code, $optimized, $pasl, $sourceName, $outFile);
};

try {
    $jx = new JxEngine($optimize, $verbose);
    $pasl = new PaslEngine($optimize, $verbose);

    if ($inline !== null) {
        $isJx = (bool)preg_match('/\b(Bag|Task|Book|delivery|underwrite|\.sign\s*\(|\.commit\s*\()/i', $inline);
        if ($outFile !== null && !$isJx) {
            $compilePage($pasl, $inline, $outFile, $optimize, '<inline>');
            exit(0);
        }
        $result = $isJx ? $jx->runSource($inline) : $pasl->runSource($inline);
        if ($print) {
            echo is_scalar($result) || $result === null ? (string)$result : json_encode($result), "\n";
        }
        exit(0);
    }

    if ($pbcArg !== null) {
        $result = $pasl->runFile($pbcArg);
        if ($print) echo $result, "\n";
        exit(0);
    }

    if ($sourceArg !== null) {
        $src = file_get_contents($sourceArg);
        if ($src === false) {
            fwrite(STDERR, "jx.exe: cannot read {$sourceArg}\n");
            exit(1);
        }
        $isJx = str_ends_with($sourceArg, '.jx')
            || (bool)preg_match('/\b(Bag|Task|Book|delivery|underwrite)\b/i', $src);

        if ($outFile !== null) {
            if ($isJx) {
                fwrite(STDERR, "jx.exe: full JX page contains host/canonical operations; compiling PASL-lowerable bytecode page.\n");
            }
            $compilePage($pasl, $src, $outFile, $optimize, $sourceArg);
            exit(0);
        }

        $result = $isJx ? $jx->runSource($src) : $pasl->runSource($src);
        if ($print) {
            if (is_object($result) && method_exists($result, '__toString')) {
                echo $result, "\n";
            } elseif (is_scalar($result) || $result === null) {
                echo $result, "\n";
            } else {
                echo json_encode($result), "\n";
            }
        }
        exit(0);
    }

    fwrite(STDERR, "jx.exe: nothing to run. Use -h for help.\n");
    exit(1);
} catch (JxException $e) {
    fwrite(STDERR, "jx.exe: " . $e->getMessage() . "\n");
    exit(1);
} catch (LangException $e) {
    fwrite(STDERR, "jx.exe: " . $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "jx.exe: " . $e->getMessage() . "\n");
    exit(1);
}
