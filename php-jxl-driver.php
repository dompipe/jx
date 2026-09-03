<?php declare(strict_types=1);

namespace jx;

require_once __DIR__ . '/pasm-expr.php';
require_once __DIR__ . '/pasm-native-encoder.php';
require_once __DIR__ . '/jx-native-image.php';

use pasm\PASMExprCompiler;
use pasm\PASMNativeEncoder;
use RuntimeException;

/**
 * Restricted PHP source driver for JX.
 *
 * PHP is a cold source/compiler host here, not the hot runtime:
 *
 *   PHP-like source -> PASM -> direct x86-64 CODE -> JxNativeImage -> .jxl
 *
 * The accepted surface is deliberately the PASMExprCompiler subset. Unsupported
 * PHP remains a source-driver error rather than silently falling back to eval.
 */
final class PhpJxlDriver
{
    public function __construct(
        private readonly PASMNativeEncoder $encoder = new PASMNativeEncoder(),
    ) {}

    /** @return array{source:string,pasm:string,code:string,jxl:string,variables:array<string,string>,architecture:string} */
    public function compileDetailed(string $phpSource): array
    {
        $source = self::stripPhpTags($phpSource);
        $compiler = new PASMExprCompiler();
        $pasm = $compiler->compile($source);
        $code = $this->encoder->compileCode($pasm);

        if ($code === '') {
            throw new RuntimeException('PHP JXL driver produced an empty native CODE section');
        }

        $jxl = JxNativeImage::executable($code, 0, JxNativeImage::ARCH_X86_64_SYSV)->encode();

        return [
            'source' => $source,
            'pasm' => $pasm,
            'code' => $code,
            'jxl' => $jxl,
            'variables' => $compiler->vars(),
            'architecture' => PASMNativeEncoder::ARCH,
        ];
    }

    public function compile(string $phpSource): string
    {
        return $this->compileDetailed($phpSource)['jxl'];
    }

    /** @return array{source:string,pasm:string,code:string,jxl:string,variables:array<string,string>,architecture:string} */
    public function compileFile(string $inputPath, string $outputPath): array
    {
        $source = file_get_contents($inputPath);
        if ($source === false) {
            throw new RuntimeException("Cannot read {$inputPath}");
        }

        $compiled = $this->compileDetailed($source);
        if (pathinfo($outputPath, PATHINFO_EXTENSION) === '') {
            $outputPath .= '.jxl';
        }
        if (strtolower(pathinfo($outputPath, PATHINFO_EXTENSION)) !== 'jxl') {
            throw new RuntimeException('PhpJxlDriver writes native executable .jxl images only');
        }
        $dir = dirname($outputPath);
        if ($dir !== '.' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create {$dir}");
        }
        if (file_put_contents($outputPath, $compiled['jxl']) !== strlen($compiled['jxl'])) {
            throw new RuntimeException("Cannot write {$outputPath}");
        }
        return $compiled;
    }

    private static function stripPhpTags(string $source): string
    {
        $source = preg_replace('/^\xEF\xBB\xBF/', '', $source) ?? $source;
        $source = preg_replace('/^\s*<\?(?:php)?/i', '', $source, 1) ?? $source;
        $source = preg_replace('/\?>\s*$/', '', $source, 1) ?? $source;
        return trim($source);
    }
}
