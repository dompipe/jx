<?php declare(strict_types=1);

namespace jx;

require_once __DIR__ . '/pasm-expr.php';
require_once __DIR__ . '/pasm-native-jxl.php';

use pasm\PASMExprCompiler;
use pasm\PASMNativeJxlEncoder;
use RuntimeException;

/**
 * Restricted PHP source driver for JX.
 *
 * PHP is treated as a source language here, not as the execution runtime:
 *
 *   PHP-like source -> PASM -> direct x86-64 encoding -> native JXL bytes
 *
 * The accepted surface is deliberately the PASMExprCompiler subset. Unsupported
 * PHP remains a source-driver error rather than silently falling back to eval.
 */
final class PhpJxlDriver
{
    public function __construct(
        private readonly PASMNativeJxlEncoder $encoder = new PASMNativeJxlEncoder(),
    ) {}

    /** @return array{source:string,pasm:string,jxl:string,variables:array<string,string>,architecture:string} */
    public function compileDetailed(string $phpSource): array
    {
        $source = self::stripPhpTags($phpSource);
        $compiler = new PASMExprCompiler();
        $pasm = $compiler->compile($source);
        $jxl = $this->encoder->compile($pasm);

        if ($jxl === '') {
            throw new RuntimeException('PHP JXL driver produced an empty native stream');
        }

        return [
            'source' => $source,
            'pasm' => $pasm,
            'jxl' => $jxl,
            'variables' => $compiler->vars(),
            'architecture' => PASMNativeJxlEncoder::ARCH,
        ];
    }

    public function compile(string $phpSource): string
    {
        return $this->compileDetailed($phpSource)['jxl'];
    }

    /** @return array{source:string,pasm:string,jxl:string,variables:array<string,string>,architecture:string} */
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
