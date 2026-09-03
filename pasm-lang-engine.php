<?php declare(strict_types=1);
namespace pasm\lang;

require_once __DIR__ . '/pasm-iterator-abi.php';
require_once __DIR__ . '/pasm-jxl.php';

/**
 * PASL execution/development engine.
 *
 * compile()/runCode() retain the proven in-memory prepared PASM representation
 * for compatibility and tests. Public .jxl files are native Jinx executable
 * images and must be produced by the native encoder pipeline.
 */
final class Engine
{
    /** @var array<string,array{kind:'vector'|'map',keys:list<int|string>,values:list<mixed>}> */
    private array $collections = [];
    /** @var list<array{slot:int,collection:string,value_reg:int,key_reg:?int,reverse:bool}> */
    private array $lastIteratorBindings = [];

    public function __construct(
        private bool $optimize = true,
        private bool $verbose = false,
    ) {}

    /**
     * Bind a source-visible collection name before compiling collection loops.
     * Rich tuple/row source syntax is normalized by the PHP front end before
     * PASM preparation; PASM iteration itself remains scalar + optional key.
     */
    public function bindCollection(string $name, iterable $collection): self
    {
        $name = $this->norm($name);
        $snapshot = is_array($collection) ? $collection : iterator_to_array($collection, true);

        if (array_is_list($snapshot)) {
            $values = array_values($snapshot);
            $this->collections[$name] = [
                'kind' => 'vector',
                'keys' => array_keys($values),
                'values' => $values,
            ];
        } else {
            $this->collections[$name] = [
                'kind' => 'map',
                'keys' => array_keys($snapshot),
                'values' => array_values($snapshot),
            ];
        }
        return $this;
    }

    public function unbindCollection(string $name): self
    {
        unset($this->collections[$this->norm($name)]);
        return $this;
    }

    public function collectionKind(string $name): ?string
    {
        return $this->collections[$this->norm($name)]['kind'] ?? null;
    }

    /** Return the internal prepared PASM stream used by compatibility tests/runtime. */
    public function compile(string $source): string
    {
        $compiler = new PASMFusedCompiler($this->optimize,$this->verbose,PASMLoopSpace::DEFAULT_MAX_DEPTH,array_keys($this->collections));
        $code = $compiler->compileToJxl($source); // historical internal method name
        $this->lastIteratorBindings = $compiler->iteratorBindings();
        return $code;
    }

    public function compilePrepared(string $source): string { return $this->compile($source); }

    /** @deprecated Historical API name. This does not define the public .jxl file contract. */
    public function compileJxl(string $source): string { return $this->compile($source); }

    public function compilePbc(string $source): string
    {
        $compiler = new PASMFusedCompiler($this->optimize,$this->verbose,PASMLoopSpace::DEFAULT_MAX_DEPTH,array_keys($this->collections));
        $code = $compiler->compileToBytecode($source);
        $this->lastIteratorBindings = $compiler->iteratorBindings();
        return $code;
    }

    /**
     * File output from the PASL development engine is PBC. Native .jxl/.jll and
     * resource .jxb outputs belong to their dedicated compiler/packer tools.
     */
    public function compileFile(string $source, string $outPath): void
    {
        $ext = strtolower(pathinfo($outPath,PATHINFO_EXTENSION));
        if ($ext === '') { $outPath .= '.pbc'; $ext = 'pbc'; }
        if (in_array($ext,['jxl','jll','jxb'],true)) {
            throw new LangException(".{$ext} is not a PASL prepared-file target; use the native JX compiler/resource packer",'io');
        }
        if ($ext !== 'pbc') throw new LangException('PASL file output must use .pbc','io');

        $compiler = new PASMFusedCompiler($this->optimize,$this->verbose,PASMLoopSpace::DEFAULT_MAX_DEPTH,array_keys($this->collections));
        $compiler->compileToFile($source,$outPath);
        $this->lastIteratorBindings = $compiler->iteratorBindings();
    }

    public function runSource(string $source): mixed { return $this->runCode($this->compile($source)); }

    public function runFile(string $path): mixed
    {
        $bytes=file_get_contents($path);if($bytes===false)throw new LangException("Cannot read {$path}",'io');
        // Historical prepared streams remain byte-detectable for compatibility,
        // but the ordinary persisted PASL file contract is .pbc.
        if(\pasm\PASMJxlCompiler::isJxl($bytes))return$this->runCode($bytes);
        $pbc=PbcFile::read($path);return$this->runPasmCode($pbc['code']);
    }

    public function runCode(string $code): mixed
    {
        if(\pasm\PASMJxlCompiler::isJxl($code)){
            $asm=(new \pasm\PASMJxlCompiler())->toPasmAssembly($code);
            $assembler=$this->optimize?new \pasm\PASMOptimizingAssembler(true):new \pasm\PASMAssembler();
            $code=$assembler->compile($asm);
        }
        return$this->runPasmCode($code);
    }

    private function runPasmCode(string $code): mixed
    {
        $rt=new \pasm\PASMRuntime();$iterators=$this->buildIteratorTable();
        $vm=$this->optimize?new \pasm\PASMOptimizedBytecodeVM($rt,1_000_000,null,null,$iterators):new \pasm\PASMBytecodeVM($rt,1_000_000,null,null,$iterators);
        return$vm->run($code);
    }

    public function iteratorBindings(): array { return $this->lastIteratorBindings; }

    private function buildIteratorTable(): ?\pasm\PASMIteratorTable
    {
        if($this->lastIteratorBindings===[])return null;$table=new \pasm\PASMIteratorTable();
        foreach($this->lastIteratorBindings as $binding){
            $collection=$this->collections[$binding['collection']]??null;
            if($collection===null)throw new LangException('Collection '.$binding['collection'].' is no longer bound','foreach-bind');
            $keys=$collection['keys'];$values=$collection['values'];
            $descriptor=new \pasm\PASMIteratorDescriptor($binding['slot'],count($values),static fn(int $i):mixed=>$values[$i],static fn(int $i):mixed=>$keys[$i]);
            $descriptor->targets($binding['value_reg'],$binding['key_reg']);$table->replace($descriptor);
        }
        return$table;
    }

    private function norm(string $name): string
    {
        $name=ltrim(trim($name),'$');if(!preg_match('/^[A-Za-z_]\w*$/',$name))throw new LangException("Bad collection name {$name}",'foreach-bind');return strtolower($name);
    }
}
