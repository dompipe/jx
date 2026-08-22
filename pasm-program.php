<?php declare(strict_types=1);
/**
 * PASM whole-program builder (engineering-aligned)
 *
 * Layers:
 *   - canonical blocks  — immutable algorithmic identity
 *   - OOP containers    — hot data, checkpointed at finalize
 *   - ASM frame         — free-form assembly → binary bytecode
 *   - PHP frame         — arbitrary PHP callables (run as PHP)
 *   - named kernels     — extra bytecode routines
 *
 * There is no general PHP → bytecode compiler. Arbitrary PHP runs as PHP.
 * At finalize(), containers materialize into canonical artifacts; ASM/kernels
 * become bytecode; PHP stages remain callables invoked on the package.
 */

namespace pasm;

require_once __DIR__ . '/pasm-runtime.php';
require_once __DIR__ . '/pasm-canonical.php';
require_once __DIR__ . '/pasm-oop-containers.php';
require_once __DIR__ . '/pasm-bytecode.php';
require_once __DIR__ . '/pasm-bytecode-optimized.php';

use InvalidArgumentException;
use RuntimeException;

/** Thin wrapper so container mutations stay on the real hot-path object. */
final class PASMTrackedContainer
{
    public function __construct(
        public readonly object $container,
        public readonly string $kind,
        public readonly string $reg,
    ) {}

    public function __call(string $method, array $args): mixed
    {
        return $this->container->$method(...$args);
    }
}

/**
 * Program builder — assemble blocks, containers, ASM, and arbitrary PHP,
 * then finalize into a runnable package.
 */
final class PASMProgram
{
    private PASMCanonicalTable $table;
    private PASMFramePool $frames;
    private PASMRegisterFrame $frame;
    private PASMRuntime $runtime;

    /** @var array<string,PASMCanonicalBlock> */
    private array $blocks = [];
    /** @var PASMTrackedContainer[] */
    private array $containers = [];
    /** @var array<string,string> name → assembly source */
    private array $kernels = [];
    /** @var array<string,callable> name → PHP callable */
    private array $phpStages = [];

    private string $asmFrame = '';
    private int $nextP = 0;
    private bool $optimize;

    public function __construct(?PASMRuntime $runtime = null, bool $optimize = true)
    {
        $this->runtime  = $runtime ?? new PASMRuntime();
        $this->table    = new PASMCanonicalTable();
        $this->frames   = new PASMFramePool();
        $this->frame    = $this->frames->create('program');
        $this->optimize = $optimize;
    }

    public function runtime(): PASMRuntime { return $this->runtime; }
    public function frame(): PASMRegisterFrame { return $this->frame; }
    public function table(): PASMCanonicalTable { return $this->table; }

    /* ---------- canonical blocks ---------- */

    public function block(string $name, array $commands, array $schema = []): PASMCanonicalBlock
    {
        $b = $this->table->define($name, $commands, $schema);
        $this->blocks[$name] = $b;
        return $b;
    }

    /* ---------- OOP containers ---------- */

    public function vector(iterable $items = []): PASMTrackedContainer
    {
        return $this->track('vector', Vector::forFrame($this->frame, null, $items));
    }

    public function stack(iterable $items = []): PASMTrackedContainer
    {
        return $this->track('stack', Stack::forFrame($this->frame, null, $items));
    }

    public function queue(iterable $items = []): PASMTrackedContainer
    {
        return $this->track('queue', Queue::forFrame($this->frame, null, $items));
    }

    public function deque(iterable $items = []): PASMTrackedContainer
    {
        return $this->track('deque', Deque::forFrame($this->frame, null, $items));
    }

    public function map(iterable $items = []): PASMTrackedContainer
    {
        return $this->track('map', Map::forFrame($this->frame, null, $items));
    }

    public function set(iterable $items = []): PASMTrackedContainer
    {
        return $this->track('set', Set::forFrame($this->frame, null, $items));
    }

    private function track(string $kind, object $c): PASMTrackedContainer
    {
        $reg = 'P' . $this->nextP++;
        $t = new PASMTrackedContainer($c, $kind, $reg);
        $this->containers[] = $t;
        return $t;
    }

    /* ---------- named bytecode kernels ---------- */

    public function kernel(string $name, string $assembly): self
    {
        $this->kernels[$name] = $assembly;
        return $this;
    }

    /* ---------- ASM frame (arbitrary assembly) ---------- */

    public function asm(string $source): self
    {
        $this->asmFrame = $source;
        return $this;
    }

    public function asmAppend(string $source): self
    {
        $this->asmFrame .= ($this->asmFrame === '' ? '' : "\n") . $source;
        return $this;
    }

    public function asmSource(): string
    {
        return $this->asmFrame;
    }

    public function compileAsm(): string
    {
        if (trim($this->asmFrame) === '') {
            throw new RuntimeException('ASM frame is empty — call asm() or asmAppend() first');
        }
        $assembler = new PASMOptimizingAssembler($this->optimize);
        return $assembler->compile($this->asmFrame);
    }

    public function runAsm(): mixed
    {
        $code = $this->compileAsm();
        $vm = new PASMOptimizedBytecodeVM($this->runtime);
        return $vm->run($code);
    }

    /* ---------- PHP frame (arbitrary PHP) ---------- */

    /**
     * Register arbitrary PHP under a stage name.
     * The callable receives the ProgramPackage (after finalize) or
     * the live PASMProgram before finalize when run via runPhp().
     *
     * Signature: function (PASMProgram|PASMProgramPackage $ctx): mixed
     */
    public function php(string $name, callable $fn): self
    {
        $this->phpStages[$name] = $fn;
        return $this;
    }

    /**
     * Run a named PHP stage against the live builder (before finalize).
     * Useful for setup that mutates containers or prepares arena/registers.
     */
    public function runPhp(string $name): mixed
    {
        if (!isset($this->phpStages[$name])) {
            throw new RuntimeException("Unknown PHP stage {$name}");
        }
        return ($this->phpStages[$name])($this);
    }

    /** List registered PHP stage names. */
    public function phpStages(): array
    {
        return array_keys($this->phpStages);
    }

    /* ---------- finalize → complete package ---------- */

    public function finalize(): PASMProgramPackage
    {
        $bindings = [];
        $dirty = [];
        foreach ($this->containers as $t) {
            $t->container->flush();
            $t->container->loadRegister($t->reg);
            $d = $t->container->dirtySegments();
            $t->container->clearDirty();
            $bindings[$t->reg] = [
                'kind'        => $t->kind,
                'containerId' => $t->container->containerId(),
                'segments'    => $t->container->segmentIds(),
                'values'      => $t->container->toArray(),
            ];
            if ($d !== []) {
                $dirty[$t->reg] = $d;
            }
        }

        $assembler = new PASMOptimizingAssembler($this->optimize);

        $mainBytecode = null;
        if (trim($this->asmFrame) !== '') {
            $mainBytecode = $assembler->compile($this->asmFrame);
        }

        $kernelBytecode = [];
        foreach ($this->kernels as $name => $src) {
            $kernelBytecode[$name] = $assembler->compile($src);
        }

        return new PASMProgramPackage(
            table:          $this->table,
            blocks:         $this->blocks,
            frame:          $this->frame,
            runtime:        $this->runtime,
            bindings:       $bindings,
            dirty:          $dirty,
            asmSource:      $this->asmFrame,
            mainBytecode:   $mainBytecode,
            kernelBytecode: $kernelBytecode,
            phpStages:      $this->phpStages,
        );
    }
}

/** Completed program: blocks + bound containers + bytecode + PHP stages. */
final class PASMProgramPackage
{
    public function __construct(
        public readonly PASMCanonicalTable $table,
        /** @var array<string,PASMCanonicalBlock> */
        public readonly array $blocks,
        public readonly PASMRegisterFrame $frame,
        public readonly PASMRuntime $runtime,
        public readonly array $bindings,
        public readonly array $dirty,
        public readonly string $asmSource,
        public readonly ?string $mainBytecode,
        public readonly array $kernelBytecode,
        /** @var array<string,callable> */
        public readonly array $phpStages,
    ) {}

    public function invoke(string $blockName, int $startPc = 0, ?int $budget = null): array
    {
        $exec = new PASMCanonicalExecutor($this->table);
        return $exec->invoke($blockName, $this->frame, $startPc, $budget);
    }

    public function runAsm(): mixed
    {
        if ($this->mainBytecode === null) {
            throw new RuntimeException('No ASM frame was provided');
        }
        $vm = new PASMOptimizedBytecodeVM($this->runtime);
        return $vm->run($this->mainBytecode);
    }

    public function runKernel(string $name): mixed
    {
        if (!isset($this->kernelBytecode[$name])) {
            throw new RuntimeException("Unknown kernel {$name}");
        }
        $vm = new PASMOptimizedBytecodeVM($this->runtime);
        return $vm->run($this->kernelBytecode[$name]);
    }

    /** Run a named arbitrary-PHP stage against this package. */
    public function runPhp(string $name): mixed
    {
        if (!isset($this->phpStages[$name])) {
            throw new RuntimeException("Unknown PHP stage {$name}");
        }
        return ($this->phpStages[$name])($this);
    }

    public function describe(): string
    {
        $lines = ['=== PASM Program Package ==='];
        $lines[] = 'Canonical blocks:';
        foreach ($this->blocks as $name => $b) {
            $lines[] = "  - {$name}  id={$b->id}  cmds=" . count($b->commands);
        }
        $lines[] = 'Container bindings:';
        foreach ($this->bindings as $reg => $m) {
            $lines[] = sprintf(
                '  %s  %-7s  id=%d  segs=%s  values=%s',
                $reg,
                $m['kind'],
                $m['containerId'],
                implode(',', $m['segments']),
                json_encode($m['values'])
            );
        }
        $lines[] = 'ASM frame: ' . (trim($this->asmSource) === '' ? '(empty)' : strlen($this->asmSource) . ' chars');
        if ($this->mainBytecode !== null) {
            $lines[] = '  main bytecode = ' . strlen($this->mainBytecode) . ' bytes';
        }
        $lines[] = 'Kernels: ' . ($this->kernelBytecode === [] ? '(none)' : implode(', ', array_keys($this->kernelBytecode)));
        $lines[] = 'PHP stages: ' . ($this->phpStages === [] ? '(none)' : implode(', ', array_keys($this->phpStages)));
        return implode("\n", $lines);
    }
}
