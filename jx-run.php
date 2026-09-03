#!/usr/bin/env php
<?php declare(strict_types=1);
/**
 * jx.exe compiler / interpreter frontend
 *
 *   jx.exe [--print|-v] [--report[=compact|verbose|json]|--quiet]
 *          [--semantic|--jxl|--64b] [-O0|-O1] [-o output] [-c 'src']
 *          [file.jx|file.pasl|file.pbc|file.8B|file.64B]
 *   jx.exe --applied-runtime -o CODE/applied-bus.bin
 *
 * Canonical artifact meanings:
 *   .jx  = source
 *   .jxl = native JXNI executable image
 *   .jll = native JXNI loadable library
 *   .jxb = indexed compressed resources (not executable)
 *
 * PHP is the cold authoring/compiler host. PASM/PASL and the historical six-byte
 * prepared stream remain compiler/compatibility machinery; they do not define
 * the public .jxl extension. Historical compiled Books remain explicit .64B.
 */
namespace jx;

$root = __DIR__;
require_once $root . '/jx-lang.php';
require_once $root . '/jx-jxl-compiler.php';
require_once $root . '/jx-jxb.php';
require_once $root . '/jx-jxb-archive.php';
require_once $root . '/php-jxl-driver.php';
require_once $root . '/jx-native-image.php';
require_once $root . '/jx-bytecode-page-report.php';
require_once $root . '/jx/AppliedBytecode.php';

use jx\semantic\PreparedCompiler;
use jx\semantic\JxbBook;
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
$legacy64Mode = false;
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
        $jxlMode = true;
    } elseif ($a === '--jxb') {
        fwrite(STDERR, "jx.exe: .jxb is a resource archive; use jxb-compile.php/jxb-resource-pack.php\n");
        exit(2);
    } elseif ($a === '--jll') {
        fwrite(STDERR, "jx.exe: use jll-native-compile.php for loadable libraries and export signatures\n");
        exit(2);
    } elseif ($a === '--64b') {
        $legacy64Mode = true;
        $semanticMode = true;
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
        fwrite(STDOUT, "       --semantic  parse/run typed canonical JX through semantic IR\n");
        fwrite(STDOUT, "       --jxl       emit native JXNI executable image (requires -o)\n");
        fwrite(STDOUT, "       --64b       explicit legacy compiled-Book output (requires -o .64B)\n");
        fwrite(STDOUT, "       .8B/.pbc     historical prepared compatibility inputs\n");
        fwrite(STDOUT, "       .jxl         native executable; launch with the native JXL host\n");
        fwrite(STDOUT, "       .jll         native loadable library; build with jll-native-compile.php\n");
        fwrite(STDOUT, "       .jxb         resources; use jxb-compile.php/jxb-run.php\n");
        fwrite(STDOUT, "       jx.exe --applied-runtime -o CODE/applied-bus.bin\n");
        exit(0);
    } elseif (is_string($a) && str_ends_with(strtolower($a), '.pbc')) {
        $pbcArg = $a;
    } else {
        $sourceArg = $a;
    }
}

if ($jxlMode && $legacy64Mode) {
    fwrite(STDERR, "jx.exe: choose either native --jxl or legacy --64b\n");
    exit(2);
}

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
$normalizeJxlOutput = static function(string $path): string {
    $trimmed = trim($path);
    if ($trimmed === '') throw new SemanticException('--jxl requires a non-empty output path', 'compile');
    if (pathinfo($trimmed, PATHINFO_EXTENSION) === '') return $trimmed . '.jxl';
    if (strtolower(pathinfo($trimmed, PATHINFO_EXTENSION)) !== 'jxl') throw new SemanticException('--jxl output must end in .jxl', 'compile');
    return $trimmed;
};
$normalize64Output = static function(string $path): string {
    $trimmed = trim($path);
    if ($trimmed === '') throw new SemanticException('--64b requires a non-empty output path', 'compile');
    if (pathinfo($trimmed, PATHINFO_EXTENSION) === '') return $trimmed . '.64B';
    if (strtolower(pathinfo($trimmed, PATHINFO_EXTENSION)) !== '64b') throw new SemanticException('--64b output must end in .64B', 'compile');
    return $trimmed;
};

/** Emit one official jx.exe bytecode page message for compatibility/PASM work. */
$emitPage = static function(
    string $code,
    bool $optimized,
    PaslEngine $pasl,
    ?string $source,
    ?string $output,
    string $target,
) use (&$reportMode, &$reportEnabled): void {
    if (!$reportEnabled) return;
    $iter = $pasl->iteratorBindings();
    $report = new JxBytecodePageReport(
        page: 1,
        bytecode: $code,
        optimized: $optimized,
        fused: false,
        reactive: false,
        target: $target,
        source: $source,
        shadow: null,
        dependencies: [],
        registers: [],
        iteratorSlots: count($iter),
        output: $output,
    );
    fwrite(STDERR, JxCompilerOutput::render($report, $reportMode) . "\n");
};

/** Persist one PASL-lowerable compatibility page. Public JXL is never emitted here. */
$compilePage = static function(
    PaslEngine $pasl,
    string $src,
    string $outFile,
    bool $optimized,
    ?string $sourceName,
) use ($emitPage): void {
    $ext = strtolower(pathinfo($outFile, PATHINFO_EXTENSION));
    if ($ext === '') $outFile .= '.pbc';
    elseif ($ext !== 'pbc') throw new SemanticException('Prepared/PASL file output must use .pbc; native executables use --jxl', 'compile');

    $code = $pasl->compilePbc($src);
    $flags = $optimized ? PbcFile::FLAG_OPTIMIZED : 0;
    PbcFile::write($outFile, $code, $flags);
    $emitPage($code, $optimized, $pasl, $sourceName, $outFile, JxBytecodePageReport::TARGET_PASM);
};

$compileNativeJxl = static function(string $src, string $path) use ($writeExact, $normalizeJxlOutput): array {
    $path = $normalizeJxlOutput($path);
    $compiled = (new PhpJxlDriver())->compileDetailed($src);
    $writeExact($path, $compiled['jxl']);
    return ['path'=>$path] + $compiled;
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
    $semantic = new PreparedCompiler();

    if ($inline !== null) {
        if ($jxlMode || $legacy64Mode) {
            if (!is_string($outFile) || trim($outFile) === '') throw new SemanticException(($jxlMode ? '--jxl' : '--64b') . ' requires -o output', 'compile');
            if ($legacy64Mode) {
                $outFile = $normalize64Output($outFile);
                $r = JxbBook::compile($inline, 'inline');
                $writeExact($outFile, $r['bytes']);
                if ($print) echo $r['file_sha256'], "\n";
            } else {
                $r = $compileNativeJxl($inline, $outFile);
                if ($print) echo $r['path'], " ", hash('sha256', $r['jxl']), "\n";
            }
            exit(0);
        }
        $isHostJx = $looksHostJx($inline);
        $isSemantic = $semanticMode || (!$isHostJx && $looksSemantic($inline));
        if ($outFile !== null && !$isHostJx && !$isSemantic) {
            if (strtolower(pathinfo($outFile,PATHINFO_EXTENSION)) === 'jxl') {
                $r = $compileNativeJxl($inline,$outFile);
                if ($print) echo $r['path'], "\n";
            } else {
                $compilePage($pasl, $inline, $outFile, $optimize, '<inline>');
            }
            exit(0);
        }
        $result = $isSemantic ? $semantic->run($inline) : ($isHostJx ? $jx->runSource($inline) : $pasl->runSource($inline));
        if ($print) echo is_scalar($result) || $result === null ? (string)$result . "\n" : json_encode($result) . "\n";
        exit(0);
    }

    if ($pbcArg !== null) {
        if ($semanticMode || $jxlMode || $legacy64Mode) throw new SemanticException('PBC input cannot be combined with semantic/native/legacy compiler modes', 'compile');
        $result = $pasl->runFile($pbcArg);
        if ($print) echo $result, "\n";
        exit(0);
    }

    if ($sourceArg !== null) {
        $lowerName = strtolower($sourceArg);
        if (str_ends_with($lowerName, '.jxl')) {
            $bytes = file_get_contents($sourceArg);
            if ($bytes === false) throw new SemanticException("Cannot read {$sourceArg}", 'io');
            $image = JxNativeImage::decode($bytes);
            if (($image['flags'] & JxNativeImage::FLAG_EXECUTABLE) === 0 || $image['entrypoint'] === null) throw new SemanticException('JXL image has no executable entrypoint', 'jxl');
            fwrite(STDERR, "jx.exe: native .jxl validated; execute with host/linux/jx-jxl-run or host/windows/jx-jxl-run for the matching architecture\n");
            if ($print) echo json_encode(['architecture'=>$image['architecture'],'entrypoint'=>$image['entrypoint'],'code_bytes'=>strlen($image['sections']['CODE'])]), "\n";
            exit(0);
        }
        if (str_ends_with($lowerName, '.jll')) {
            $bytes = file_get_contents($sourceArg);
            if ($bytes === false) throw new SemanticException("Cannot read {$sourceArg}", 'io');
            $image = JxNativeImage::decode($bytes);
            if (($image['flags'] & JxNativeImage::FLAG_LIBRARY) === 0) throw new SemanticException('JLL image is not marked library', 'jll');
            if ($print) echo json_encode(['architecture'=>$image['architecture'],'exports'=>$image['exports']]), "\n";
            exit(0);
        }
        if (str_ends_with($lowerName, '.8b')) {
            $bytes = file_get_contents($sourceArg);
            if ($bytes === false) throw new SemanticException("Cannot read {$sourceArg}", 'io');
            $result = (new JxlVm())->run($bytes);
            if ($print) echo $result, "\n";
            exit(0);
        }
        if (str_ends_with($lowerName, '.jxb')) {
            fwrite(STDERR, "jx.exe: .jxb is a resource archive; use jxb-run.php --list/--get\n");
            exit(2);
        }
        if (str_ends_with($lowerName, '.64b')) {
            $bytes = file_get_contents($sourceArg);
            if ($bytes === false) throw new SemanticException("Cannot read {$sourceArg}", 'io');
            $result = JxbBook::run($bytes);
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

        if ($jxlMode || $legacy64Mode) {
            if (!is_string($outFile) || trim($outFile) === '') throw new SemanticException(($jxlMode ? '--jxl' : '--64b') . ' requires -o output', 'compile');
            if ($legacy64Mode) {
                $outFile = $normalize64Output($outFile);
                $r = JxbBook::compile($src, pathinfo($sourceArg, PATHINFO_FILENAME));
                $writeExact($outFile, $r['bytes']);
                if ($print) echo $r['file_sha256'], "\n";
            } else {
                if ($isHostJx) throw new SemanticException('Host Bag/Task/Book operations are not yet admitted by the direct native encoder', 'jxl');
                $r = $compileNativeJxl($src, $outFile);
                if ($print) echo $r['path'], " ", hash('sha256', $r['jxl']), "\n";
            }
            exit(0);
        }

        if ($outFile !== null) {
            if (strtolower(pathinfo($outFile,PATHINFO_EXTENSION)) === 'jxl') {
                if ($isHostJx) throw new SemanticException('Host Bag/Task/Book operations are not yet admitted by the direct native encoder', 'jxl');
                $r = $compileNativeJxl($src,$outFile);
                if ($print) echo $r['path'], "\n";
                exit(0);
            }
            if ($isSemantic) throw new SemanticException('Typed semantic JX file compilation currently requires the native --jxl admitted subset or an explicit compatibility path', 'compile');
            if ($isHostJx) fwrite(STDERR, "jx.exe: full JX page contains host/canonical operations; persisting PASM-compatible page only.\n");
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
