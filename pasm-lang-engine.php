<?php declare(strict_types=1);
namespace pasm\lang;

final class Engine
{
    public function __construct(
        private bool $optimize = true,
        private bool $verbose = false,
    ) {}

    public function compile(string $source): string
    {
        return (new PASMFusedCompiler($this->optimize, $this->verbose))->compileToBytecode($source);
    }

    public function compileFile(string $source, string $outPath): void
    {
        (new PASMFusedCompiler($this->optimize, $this->verbose))->compileToFile($source, $outPath);
    }

    public function runSource(string $source): mixed
    {
        $code = $this->compile($source);
        return $this->runCode($code);
    }

    public function runFile(string $path): mixed
    {
        $pbc = PbcFile::read($path);
        return $this->runCode($pbc['code']);
    }

    public function runCode(string $code): mixed
    {
        $rt = new \pasm\PASMRuntime();
        $vm = $this->optimize
            ? new \pasm\PASMOptimizedBytecodeVM($rt)
            : new \pasm\PASMBytecodeVM($rt);
        return $vm->run($code);
    }
}
