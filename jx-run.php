#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * jx.exe compiler / interpreter frontend
 *
 *   jx.exe [--print|-v] [--report[=compact|verbose|json]|--quiet]
 *          [--semantic|--jxl|--64b] [-O0|-O1] [-o output] [-c 'src']
 *          [file.jx|file.pasl|file.pbc|file.jxl|file.8B|file.64B]
 *   jx.exe --applied-runtime -o CODE/applied-bus.bin
 *
 * PHP is the authoring/compiler host. --jxl and --64b move canonical numeric
 * JX into prepared execution so the repeat path no longer reparses source.
 */
namespace jx;

$root = __DIR__;
require_once $root . '/jx-lang.php';
require_once $root . '/jx-semantic.php';
require_once $root . '/jx-jxl-book64.php';
require_once $root . '/jx-bytecode-page-report.php';
require_once $root . '/jx/AppliedBytecode.php';

use jx\semantic\Compiler as SemanticCompiler;
use jx\semantic\JxlBook64;
use jx\semantic\JxlVm;
use jx\semantic\SemanticException;
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
$appliedRuntime = false;
$semanticMode = false;
$jxlMode = false;
$book64Mode = false;
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
    } elseif ($a === '--semantic') {
        $semanticMode = true;
    } elseif ($a === '--jxl') {
        $semanticMode = true;
        $jxlMode = true;
    } elseif ($a === '--64b') {
        $semanticMode = true;
        $book64Mode = true;
    } elseif ($a === '--applied-runtime') {
        $appliedRuntime = true;
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
        fwrite(STDOUT, "Usage: jx.exe [--print|-v] [--report[=compact|verbose|json]|--quiet] [--semantic|--jxl|--64b] [-O0|-O1] [-o output] [-c 'src'] [file]\n");
        fwrite(STDOUT, "       --semantic  parse/run typed canonical JX through the semantic IR\n");
        fwrite(STDOUT, "       --jxl       emit prepared JXL (requires -o)\n");
        fwrite(STDOUT, "       --64b       emit deterministic JXL compiled Book (requires -o)\n");
        fwrite(STDOUT, "       .jxl/.8B and .64B inputs execute prepared code directly\n");
        fwrite(STDOUT, "       jx.exe --applied-runtime -o CODE/applied-bus.bin\n");
        exit(0);
    } elseif (is_string($a) && str_ends_with(strtolower($a), '.pbc')) {
        $pbcArg = $a;
    } else {
        $sourceArg = $a;
    }
}

if ($jxlMode && $book64Mode) {
    fwrite(STDERR, "jx.exe: choose either --jxl or --64b\n");
    exit(2);
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

$looksSemantic = static function(string $src): bool {
    return (bool)preg_match('/\b(function|class|foreach|repeat|try|catch|finally|throw|namespace|import)\b|\bdo\s*\{|(^|[;{}]\s*)(int|float|bool|string|list|map|any|void)\s+\$?[A-Za-z_]\w*/mi', $src);
};
$looksHostJx = static function(string $src): bool {
    return (bool)preg_match('/\b(Bag|Task|Book|delivery|underwrite|\.sign\s*\(|\.commit\s*\(|\.tell\s*\()/i', $src);
};
$writeExact = static function(string $path, string $bytes): void {
    $dir = dirname($path);
    if ($dir !== '.' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new SemanticException("Cannot create output directory {$dir}", 'io');
    }
    if (file_put_contents($path, $bytes) !== strlen($bytes)) {
        throw new SemanticException("Cannot write {$path}", 'io');
    }
};

try {
    if ($appliedRuntime) {
        if (!is_string($outFile) || trim($outFile) === '') {
            throw new JxException('--applied-runtime requires -o output', 'compile');
        }
        if ($inline !== null || $sourceArg !== null || $pbcArg !== null) {
            throw new JxException('--applied-runtime is a compiler build page, not source execution', 'compile');
        }
        $compiler = new AppliedBytecodeCompiler();
        $bytes = AppliedBytecode::runtimeBusPage();
        $dir = dirname($outFile);
        if ($dir !== '.' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new JxException('Cannot create applied bytecode output directory', 'compile');
        }
        if (file_put_contents($outFile, $bytes) !== strlen($bytes)) {
            throw new JxException('Cannot write applied runtime bytecode page', 'compile');
        }
        if ($reportEnabled) {
            $page = $compiler->page(1, ['idle.tick', 'idle.collect'], '<jx.exe-runtime>', $outFile);
            fwrite(STDERR, JxCompilerOutput::render($page, $reportMode) . "\n");
        }
        if ($print) fwrite(STDOUT, bin2hex($bytes) . "\n");
        exit(0);
    }

    $jx = new JxEngine($optimize, $verbose);
    $pasl = new PaslEngine($optimize, $verbose);
    $semantic = new SemanticCompiler();

    if ($inline !== null) {
        if ($jxlMode || $book64Mode) {
            if (!is_string($outFile) || $outFile === '') throw new SemanticException(($jxlMode ? '--jxl' : '--64b') . ' requires -o output', 'compile');
            if ($book64Mode) {
                $r = JxlBook64::compile($inline, 'inline');
                $writeExact($outFile, $r['bytes']);
                if ($print) echo $r['file_sha256'], "\n";
            } else {
                $bytes = $semantic->emitJxl($inline);
                $writeExact($outFile, $bytes);
                if ($print) echo bin2hex($bytes), "\n";
            }
            exit(0);
        }
        $isHostJx = $looksHostJx($inline);
        $isSemantic = $semanticMode || (!$isHostJx && $looksSemantic($inline));
        if ($outFile !== null && !$isHostJx && !$isSemantic) {
            $compilePage($pasl, $inline, $outFile, $optimize, '<inline>');
            exit(0);
        }
        $result = $isSemantic ? $semantic->run($inline) : ($isHostJx ? $jx->runSource($inline) : $pasl->runSource($inline));
        if ($print) echo is_scalar($result) || $result === null ? (string)$result . "\n" : json_encode($result) . "\n";
        exit(0);
    }

    if ($pbcArg !== null) {
        if ($semanticMode || $jxlMode || $book64Mode) throw new SemanticException('PBC input cannot be combined with semantic/JXL/64B compiler modes', 'compile');
        $result = $pasl->runFile($pbcArg);
        if ($print) echo $result, "\n";
        exit(0);
    }

    if ($sourceArg !== null) {
        $lowerName = strtolower($sourceArg);
        if (str_ends_with($lowerName, '.jxl') || str_ends_with($lowerName, '.8b')) {
            $bytes = file_get_contents($sourceArg);
            if ($bytes === false) throw new SemanticException("Cannot read {$sourceArg}", 'io');
            $result = (new JxlVm())->run($bytes);
            if ($print) echo $result, "\n";
            exit(0);
        }
        if (str_ends_with($lowerName, '.64b')) {
            $bytes = file_get_contents($sourceArg);
            if ($bytes === false) throw new SemanticException("Cannot read {$sourceArg}", 'io');
            $book = JxlBook64::validate($bytes);
            $code = $book['entries'][JxlBook64::CODE_PATH] ?? null;
            if (!is_string($code)) throw new SemanticException('64B has no prepared JXL program section', '64B');
            $result = (new JxlVm())->run($code);
            if ($print) echo $result, "\n";
            exit(0);
        }

        $src = file_get_contents($sourceArg);
        if ($src === false) {
            fwrite(STDERR, "jx.exe: cannot read {$sourceArg}\n");
            exit(1);
        }
        $isHostJx = $looksHostJx($src);
        $isSemantic = $semanticMode || (!$isHostJx && $looksSemantic($src));
        $isJx = str_ends_with($lowerName, '.jx') || $isHostJx || $isSemantic;

        if ($jxlMode || $book64Mode) {
            if (!is_string($outFile) || $outFile === '') throw new SemanticException(($jxlMode ? '--jxl' : '--64b') . ' requires -o output', 'compile');
            if ($isHostJx) throw new SemanticException('Bag/Task/Book host operations are not yet in the compact numeric JXL subset', 'jxl');
            if ($book64Mode) {
                $r = JxlBook64::compile($src, pathinfo($sourceArg, PATHINFO_FILENAME));
                $writeExact($outFile, $r['bytes']);
                if ($print) echo $r['file_sha256'], "\n";
            } else {
                $bytes = $semantic->emitJxl($src);
                $writeExact($outFile, $bytes);
                if ($print) echo bin2hex($bytes), "\n";
            }
            exit(0);
        }

        if ($outFile !== null) {
            if ($isSemantic) throw new SemanticException('Typed semantic JX requires --jxl or --64b when compiling to a file', 'compile');
            if ($isHostJx) fwrite(STDERR, "jx.exe: full JX page contains host/canonical operations; compiling PASL-lowerable bytecode page.\n");
            $compilePage($pasl, $src, $outFile, $optimize, $sourceArg);
            exit(0);
        }

        $result = $isSemantic ? $semantic->run($src) : ($isJx ? $jx->runSource($src) : $pasl->runSource($src));
        if ($print) {
            if (is_object($result) && method_exists($result, '__toString')) echo $result, "\n";
            elseif (is_scalar($result) || $result === null) echo $result, "\n";
            else echo json_encode($result), "\n";
        }
        exit(0);
    }

    fwrite(STDERR, "jx.exe: nothing to run. Use -h for help.\n");
    exit(1);
} catch (JxException|LangException|SemanticException $e) {
    fwrite(STDERR, "jx.exe: " . $e->getMessage() . "\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "jx.exe: " . $e->getMessage() . "\n");
    exit(1);
}
