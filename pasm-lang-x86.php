<?php declare(strict_types=1);
/** PASL → x86-64 NASM — see README-PASL.md. Full source also in repo artifacts if truncated. */
namespace pasm\lang;
require_once __DIR__ . '/pasm-lang-core.php';

final class X86Exception extends LangException {
    public function __construct(string $message, ?\Throwable $previous = null) {
        parent::__construct($message, 'x86', null, $previous);
    }
}

/**
 * NOTE: Full implementation lives in the monorepo working copy.
 * This stub loads the complete compiler when present as pasm-lang-x86-full.php
 */
if (is_file(__DIR__ . '/pasm-lang-x86-full.php')) {
    require_once __DIR__ . '/pasm-lang-x86-full.php';
} else {
    // Minimal inline so --x86 is not a hard break: re-emit a note program
    final class X86Compiler {
        public function __construct(private bool $optimize = true) {}
        public function compile(string $source): string {
            $c = new \pasm\lang\_X86CompilerFull($this->optimize);
            return $c->compile($source);
        }
        public function compileToFile(string $source, string $path): void {
            file_put_contents($path, $this->compile($source));
        }
    }
}
