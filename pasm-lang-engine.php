<?php declare(strict_types=1);
namespace pasm\lang;

require_once __DIR__ . '/pasm-iterator-abi.php';

/** PASL execution engine with prelinked collection-loop bindings. */
final class Engine
{
    /** @var array<string,iterable> */
    private array $collections = [];
    /** @var list<array{slot:int,collection:string,value_reg:int,key_reg:?int,reverse:bool}> */
    private array $lastIteratorBindings = [];

    public function __construct(
        private bool $optimize = true,
        private bool $verbose = false,
    ) {}

    /** Bind a source-visible collection name before compiling foreach/reveach. */
    public function bindCollection(string $name, iterable $collection): self
    {
        $name = $this->norm($name);
        $this->collections[$name] = $collection;
        return $this;
    }

    public function unbindCollection(string $name): self
    {
        unset($this->collections[$this->norm($name)]);
        return $this;
    }

    public function compile(string $source): string
    {
        $compiler = new PASMFusedCompiler(
            $this->optimize,
            $this->verbose,
            PASMLoopSpace::DEFAULT_MAX_DEPTH,
            array_keys($this->collections),
        );
        $code = $compiler->compileToBytecode($source);
        $this->lastIteratorBindings = $compiler->iteratorBindings();
        return $code;
    }

    public function compileFile(string $source, string $outPath): void
    {
        $compiler = new PASMFusedCompiler(
            $this->optimize,
            $this->verbose,
            PASMLoopSpace::DEFAULT_MAX_DEPTH,
            array_keys($this->collections),
        );
        $compiler->compileToFile($source, $outPath);
        $this->lastIteratorBindings = $compiler->iteratorBindings();
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
        $iterators = $this->buildIteratorTable();
        $vm = $this->optimize
            ? new \pasm\PASMOptimizedBytecodeVM($rt, 1_000_000, null, null, $iterators)
            : new \pasm\PASMBytecodeVM($rt, 1_000_000, null, null, $iterators);
        return $vm->run($code);
    }

    /** @return list<array{slot:int,collection:string,value_reg:int,key_reg:?int,reverse:bool}> */
    public function iteratorBindings(): array { return $this->lastIteratorBindings; }

    private function buildIteratorTable(): ?\pasm\PASMIteratorTable
    {
        if ($this->lastIteratorBindings === []) return null;
        $table = new \pasm\PASMIteratorTable();
        foreach ($this->lastIteratorBindings as $binding) {
            $collection = $this->collections[$binding['collection']] ?? null;
            if ($collection === null) {
                throw new LangException('Collection ' . $binding['collection'] . ' is no longer bound', 'foreach-bind');
            }
            $snapshot = is_array($collection) ? $collection : iterator_to_array($collection, true);
            $keys = array_keys($snapshot);
            $values = array_values($snapshot);
            $descriptor = new \pasm\PASMIteratorDescriptor(
                $binding['slot'],
                count($values),
                static fn(int $i): mixed => $values[$i],
                static fn(int $i): mixed => $keys[$i],
            );
            $descriptor->targets($binding['value_reg'], $binding['key_reg']);
            $table->replace($descriptor);
        }
        return $table;
    }

    private function norm(string $name): string
    {
        $name = ltrim(trim($name), '$');
        if (!preg_match('/^[A-Za-z_]\w*$/', $name)) throw new LangException("Bad collection name {$name}", 'foreach-bind');
        return strtolower($name);
    }
}
