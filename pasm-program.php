<?php declare(strict_types=1);
/**
 * PASM whole-program builder
 *
 * Layers:
 *   - canonical blocks  — immutable algorithmic identity (executor, not binary bytecode)
 *   - OOP containers    — hot data; scalar ints lower into a bytecode prelude
 *   - ASM frame         — free-form assembly
 *   - PHP frame         — arbitrary PHP (runs as PHP)
 *   - named kernels     — extra bytecode routines
 *
 * finalize() builds a unified binary stream when possible:
 *   [container prelude ASM → bytecode] + [main ASM frame → bytecode]
 * Canonical blocks remain on the canonical executor; they are not fused into
 * the binary ISA. PHP stages stay callables.
 */

namespace pasm;

require_once __DIR__ . '/pasm-runtime.php';
require_once __DIR__ . '/pasm-canonical.php';
require_once __DIR__ . '/pasm-oop-containers.php';
require_once __DIR__ . '/pasm-bytecode.php';
require_once __DIR__ . '/pasm-bytecode-optimized.php';

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/* ================================================================== */
/* Errors                                                             */
/* ================================================================== */

class PASMProgramException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $phase = 'general',
        public readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($this->format($message), $code, $previous);
    }

    private function format(string $message): string
    {
        $ctx = $this->context === [] ? '' : ' ' . json_encode($this->context, JSON_UNESCAPED_SLASHES);
        return "[PASMProgram:{$this->phase}] {$message}{$ctx}";
    }
}

class PASMAssembleException extends PASMProgramException
{
    public function __construct(string $message, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 'assemble', $context, 0, $previous);
    }
}

class PASMExecuteException extends PASMProgramException
{
    public function __construct(string $message, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 'execute', $context, 0, $previous);
    }
}

class PASMFinalizeException extends PASMProgramException
{
    public function __construct(string $message, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 'finalize', $context, 0, $previous);
    }
}

/* ================================================================== */

final class PASMTrackedContainer
{
    public function __construct(
        public readonly object $container,
        public readonly string $kind,
        public readonly string $reg,
    ) {}

    public function __call(string $method, array $args): mixed
    {
        if (!method_exists($this->container, $method) && !is_callable([$this->container, $method])) {
            throw new PASMProgramException(
                "Unknown method {$method} on {$this->kind} container",
                'container',
                ['reg' => $this->reg, 'kind' => $this->kind]
            );
        }
        try {
            return $this->container->$method(...$args);
        } catch (Throwable $e) {
            throw new PASMProgramException(
                $e->getMessage(),
                'container',
                ['reg' => $this->reg, 'kind' => $this->kind, 'method' => $method],
                0,
                $e
            );
        }
    }
}

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
    /** @var array<string,string> */
    private array $kernels = [];
    /** @var array<string,callable> */
    private array $phpStages = [];

    private string $asmFrame = '';
    private int $nextP = 0;
    private bool $optimize;
    /** Max integer values auto-emitted into the bytecode prelude per container. */
    private int $preludeValueLimit = 256;

    public function __construct(?PASMRuntime $runtime = null, bool $optimize = true)
    {
        try {
            $this->runtime  = $runtime ?? new PASMRuntime();
            $this->table    = new PASMCanonicalTable();
            $this->frames   = new PASMFramePool();
            $this->frame    = $this->frames->create('program');
            $this->optimize = $optimize;
        } catch (Throwable $e) {
            throw new PASMProgramException('Failed to initialize program', 'init', [], 0, $e);
        }
    }

    public function runtime(): PASMRuntime { return $this->runtime; }
    public function frame(): PASMRegisterFrame { return $this->frame; }
    public function table(): PASMCanonicalTable { return $this->table; }

    public function setPreludeValueLimit(int $n): self
    {
        if ($n < 0) {
            throw new InvalidArgumentException('preludeValueLimit must be >= 0');
        }
        $this->preludeValueLimit = $n;
        return $this;
    }

    /* ---------- canonical blocks ---------- */

    public function block(string $name, array $commands, array $schema = []): PASMCanonicalBlock
    {
        if ($name === '') {
            throw new InvalidArgumentException('Block name must be non-empty');
        }
        if ($commands === []) {
            throw new InvalidArgumentException("Block {$name} has no commands");
        }
        try {
            $b = $this->table->define($name, $commands, $schema);
            $this->blocks[$name] = $b;
            return $b;
        } catch (Throwable $e) {
            throw new PASMProgramException("Failed to define block {$name}", 'block', ['name' => $name], 0, $e);
        }
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

    /* ---------- kernels / ASM / PHP ---------- */

    public function kernel(string $name, string $assembly): self
    {
        if ($name === '' || trim($assembly) === '') {
            throw new InvalidArgumentException('Kernel name and assembly must be non-empty');
        }
        $this->kernels[$name] = $assembly;
        return $this;
    }

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
        return $this->assemble($this->asmFrame, 'asm-frame');
    }

    public function runAsm(): mixed
    {
        try {
            $code = $this->compileAsm();
            $vm = new PASMOptimizedBytecodeVM($this->runtime);
            return $vm->run($code);
        } catch (PASMProgramException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PASMExecuteException('ASM frame execution failed', [], $e);
        }
    }

    public function php(string $name, callable $fn): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('PHP stage name must be non-empty');
        }
        $this->phpStages[$name] = $fn;
        return $this;
    }

    public function runPhp(string $name): mixed
    {
        if (!isset($this->phpStages[$name])) {
            throw new PASMProgramException("Unknown PHP stage {$name}", 'php', ['name' => $name]);
        }
        try {
            return ($this->phpStages[$name])($this);
        } catch (Throwable $e) {
            throw new PASMExecuteException("PHP stage {$name} failed", ['name' => $name], $e);
        }
    }

    public function phpStages(): array
    {
        return array_keys($this->phpStages);
    }

    /* ---------- assembly helper ---------- */

    private function assemble(string $source, string $label): string
    {
        if (trim($source) === '') {
            throw new PASMAssembleException("Empty assembly for {$label}", ['label' => $label]);
        }
        try {
            $assembler = new PASMOptimizingAssembler($this->optimize);
            return $assembler->compile($source);
        } catch (Throwable $e) {
            throw new PASMAssembleException(
                "Failed to assemble {$label}: {$e->getMessage()}",
                ['label' => $label],
                $e
            );
        }
    }

    /**
     * Build ASM that materializes integer scalar values from linear containers
     * into the memory arena and leaves:
     *   ecx = base of first linear dump
     *   ah  = element count of first linear dump
     *   adx = containerId of first linear container (if any)
     *
     * Non-integer / nested values are skipped (not representable in the integer ISA).
     */
    private function buildContainerPreludeAsm(array $bindings): array
    {
        // returns [asm:string, meta:array]
        $lines = [];
        $lines[] = '; --- auto prelude: container scalars → arena ---';
        $meta = ['bases' => [], 'counts' => [], 'skipped_non_int' => 0];

        $firstBaseRegSet = false;
        $arenaCursor = 0;

        foreach ($bindings as $reg => $m) {
            $values = $m['values'] ?? [];
            if (!is_array($values)) {
                continue;
            }
            // Map/Set may be associative; use values list
            $flat = array_values($values);
            $ints = [];
            foreach ($flat as $v) {
                if (is_int($v)) {
                    $ints[] = $v;
                } else {
                    $meta['skipped_non_int']++;
                }
                if (count($ints) >= $this->preludeValueLimit) {
                    break;
                }
            }
            if ($ints === []) {
                continue;
            }

            $n = count($ints);
            $bytes = $n * 4;
            try {
                $base = $this->runtime->alloc(max(8, $bytes));
            } catch (Throwable $e) {
                throw new PASMFinalizeException('Arena alloc failed during prelude', ['reg' => $reg, 'bytes' => $bytes], $e);
            }

            foreach ($ints as $i => $v) {
                $this->runtime->store32($base + $i * 4, $v);
            }

            $meta['bases'][$reg] = $base;
            $meta['counts'][$reg] = $n;

            // Emit MOVI that document the binding for the main frame (optional bookkeeping in comments)
            $lines[] = "; container {$reg} kind={$m['kind']} id={$m['containerId']} base={$base} count={$n}";

            if (!$firstBaseRegSet) {
                $lines[] = "        MOVI  ecx  {$base}";
                $lines[] = "        MOVI  ah   {$n}";
                $lines[] = "        MOVI  adx  {$m['containerId']}";
                $firstBaseRegSet = true;
            }

            $arenaCursor = max($arenaCursor, $base + $bytes);
        }

        if (!$firstBaseRegSet) {
            $lines[] = '; (no integer container values to lower)';
            $lines[] = '        MOVI  ecx  0';
            $lines[] = '        MOVI  ah   0';
            $lines[] = '        MOVI  adx  0';
        }

        return [implode("\n", $lines), $meta];
    }

    /**
     * Unified assembly = container prelude + user ASM frame.
     * This is the closest “all-inclusive” binary path for data + user code.
     */
    public function buildUnifiedAsm(array $bindings = []): string
    {
        if ($bindings === [] && $this->containers !== []) {
            // best-effort snapshot without full finalize side effects
            foreach ($this->containers as $t) {
                try {
                    $t->container->flush();
                } catch (Throwable $e) {
                    throw new PASMFinalizeException('flush failed', ['reg' => $t->reg], $e);
                }
            }
            $bindings = [];
            foreach ($this->containers as $t) {
                $bindings[$t->reg] = [
                    'kind'        => $t->kind,
                    'containerId' => $t->container->containerId(),
                    'segments'    => $t->container->segmentIds(),
                    'values'      => $t->container->toArray(),
                ];
            }
        }

        [$prelude] = $this->buildContainerPreludeAsm($bindings);
        $user = trim($this->asmFrame);
        if ($user === '') {
            // default body: return sum of prelude buffer if ah>0, else RET adx
            $user = <<<'ASM'
; default body: sum mem[ecx .. ecx+4*(ah-1)]
        MOVI  rdx  0
        MOVI  bdx  0
        CMP   ah   0
        JZ    done
loop:   LOAD32 cdx ecx bdx
        ADD    rdx rdx cdx
        ADD    bdx bdx 4
        DEC    ah
        CMP    ah  0
        JNZ    loop
done:   RET    rdx
ASM;
        }

        return $prelude . "\n; --- user ASM frame ---\n" . $user;
    }

    public function compileUnified(): string
    {
        $src = $this->buildUnifiedAsm();
        return $this->assemble($src, 'unified');
    }

    public function runUnified(): mixed
    {
        try {
            $code = $this->compileUnified();
            $vm = new PASMOptimizedBytecodeVM($this->runtime);
            return $vm->run($code);
        } catch (PASMProgramException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PASMExecuteException('Unified bytecode execution failed', [], $e);
        }
    }

    /* ---------- finalize ---------- */

    public function finalize(): PASMProgramPackage
    {
        try {
            $bindings = [];
            $dirty = [];
            foreach ($this->containers as $t) {
                try {
                    $t->container->flush();
                    $t->container->loadRegister($t->reg);
                    $d = $t->container->dirtySegments();
                    $t->container->clearDirty();
                } catch (Throwable $e) {
                    throw new PASMFinalizeException(
                        "Container materialize failed for {$t->reg}",
                        ['reg' => $t->reg, 'kind' => $t->kind],
                        $e
                    );
                }
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

            $unifiedAsm = $this->buildUnifiedAsm($bindings);
            $unifiedBytecode = $this->assemble($unifiedAsm, 'unified');

            $mainBytecode = null;
            if (trim($this->asmFrame) !== '') {
                $mainBytecode = $this->assemble($this->asmFrame, 'asm-frame');
            }

            $kernelBytecode = [];
            foreach ($this->kernels as $name => $src) {
                $kernelBytecode[$name] = $this->assemble($src, "kernel:{$name}");
            }

            return new PASMProgramPackage(
                table:            $this->table,
                blocks:           $this->blocks,
                frame:            $this->frame,
                runtime:          $this->runtime,
                bindings:         $bindings,
                dirty:            $dirty,
                asmSource:        $this->asmFrame,
                unifiedAsmSource: $unifiedAsm,
                unifiedBytecode:  $unifiedBytecode,
                mainBytecode:     $mainBytecode,
                kernelBytecode:   $kernelBytecode,
                phpStages:        $this->phpStages,
            );
        } catch (PASMProgramException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PASMFinalizeException('finalize failed', [], $e);
        }
    }
}

final class PASMProgramPackage
{
    public function __construct(
        public readonly PASMCanonicalTable $table,
        public readonly array $blocks,
        public readonly PASMRegisterFrame $frame,
        public readonly PASMRuntime $runtime,
        public readonly array $bindings,
        public readonly array $dirty,
        public readonly string $asmSource,
        public readonly string $unifiedAsmSource,
        public readonly string $unifiedBytecode,
        public readonly ?string $mainBytecode,
        public readonly array $kernelBytecode,
        public readonly array $phpStages,
    ) {}

    /** Primary all-inclusive binary output (prelude + user ASM). */
    public function toBytecode(): string
    {
        return $this->unifiedBytecode;
    }

    public function toBytecodeHex(): string
    {
        return bin2hex($this->unifiedBytecode);
    }

    public function invoke(string $blockName, int $startPc = 0, ?int $budget = null): array
    {
        if (!isset($this->blocks[$blockName]) && !$this->table->has($blockName)) {
            throw new PASMExecuteException("Unknown block {$blockName}", ['block' => $blockName]);
        }
        try {
            $exec = new PASMCanonicalExecutor($this->table);
            return $exec->invoke($blockName, $this->frame, $startPc, $budget);
        } catch (Throwable $e) {
            throw new PASMExecuteException("Block {$blockName} failed", ['block' => $blockName], $e);
        }
    }

    public function runUnified(): mixed
    {
        try {
            $vm = new PASMOptimizedBytecodeVM($this->runtime);
            return $vm->run($this->unifiedBytecode);
        } catch (Throwable $e) {
            throw new PASMExecuteException('Unified bytecode run failed', [], $e);
        }
    }

    public function runAsm(): mixed
    {
        if ($this->mainBytecode === null) {
            throw new PASMExecuteException('No user ASM frame was provided');
        }
        try {
            $vm = new PASMOptimizedBytecodeVM($this->runtime);
            return $vm->run($this->mainBytecode);
        } catch (Throwable $e) {
            throw new PASMExecuteException('ASM frame run failed', [], $e);
        }
    }

    public function runKernel(string $name): mixed
    {
        if (!isset($this->kernelBytecode[$name])) {
            throw new PASMExecuteException("Unknown kernel {$name}", ['name' => $name]);
        }
        try {
            $vm = new PASMOptimizedBytecodeVM($this->runtime);
            return $vm->run($this->kernelBytecode[$name]);
        } catch (Throwable $e) {
            throw new PASMExecuteException("Kernel {$name} failed", ['name' => $name], $e);
        }
    }

    public function runPhp(string $name): mixed
    {
        if (!isset($this->phpStages[$name])) {
            throw new PASMExecuteException("Unknown PHP stage {$name}", ['name' => $name]);
        }
        try {
            return ($this->phpStages[$name])($this);
        } catch (Throwable $e) {
            throw new PASMExecuteException("PHP stage {$name} failed", ['name' => $name], $e);
        }
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
        $lines[] = 'Unified ASM chars: ' . strlen($this->unifiedAsmSource);
        $lines[] = 'Unified bytecode:  ' . strlen($this->unifiedBytecode) . ' bytes';
        $lines[] = '  hex: ' . bin2hex($this->unifiedBytecode);
        $lines[] = 'User ASM frame: ' . (trim($this->asmSource) === '' ? '(default body)' : strlen($this->asmSource) . ' chars');
        $lines[] = 'Kernels: ' . ($this->kernelBytecode === [] ? '(none)' : implode(', ', array_keys($this->kernelBytecode)));
        $lines[] = 'PHP stages: ' . ($this->phpStages === [] ? '(none)' : implode(', ', array_keys($this->phpStages)));
        return implode("\n", $lines);
    }
}
