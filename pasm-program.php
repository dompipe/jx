<?php declare(strict_types=1);
/**
 * PASM whole-program builder
 *
 * Layers:
 *   - canonical blocks
 *   - OOP containers (integer scalars lower into bytecode prelude)
 *   - ASM frame
 *   - expr() — PHP-like integer assignments/operators → PASM bytecode
 *   - PHP frame (arbitrary PHP, not compiled)
 *   - named kernels
 */

namespace pasm;

require_once __DIR__ . '/pasm-runtime.php';
require_once __DIR__ . '/pasm-canonical.php';
require_once __DIR__ . '/pasm-oop-containers.php';
require_once __DIR__ . '/pasm-bytecode.php';
require_once __DIR__ . '/pasm-bytecode-optimized.php';
require_once __DIR__ . '/pasm-expr.php';

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PASMProgramException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $phase = 'general',
        public readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $ctx = $this->context === [] ? '' : ' ' . json_encode($this->context, JSON_UNESCAPED_SLASHES);
        parent::__construct("[PASMProgram:{$this->phase}] {$message}{$ctx}", $code, $previous);
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
    /** @var string[] expr source chunks lowered into the unified stream */
    private array $exprChunks = [];
    private PASMExprCompiler $exprCompiler;

    private int $nextP = 0;
    private bool $optimize;
    private int $preludeValueLimit = 256;

    public function __construct(?PASMRuntime $runtime = null, bool $optimize = true)
    {
        try {
            $this->runtime  = $runtime ?? new PASMRuntime();
            $this->table    = new PASMCanonicalTable();
            $this->frames   = new PASMFramePool();
            $this->frame    = $this->frames->create('program');
            $this->optimize = $optimize;
            $this->exprCompiler = new PASMExprCompiler();
        } catch (Throwable $e) {
            throw new PASMProgramException('Failed to initialize program', 'init', [], 0, $e);
        }
    }

    public function runtime(): PASMRuntime { return $this->runtime; }
    public function frame(): PASMRegisterFrame { return $this->frame; }
    public function table(): PASMCanonicalTable { return $this->table; }
    public function exprVars(): array { return $this->exprCompiler->vars(); }

    public function setPreludeValueLimit(int $n): self
    {
        if ($n < 0) {
            throw new InvalidArgumentException('preludeValueLimit must be >= 0');
        }
        $this->preludeValueLimit = $n;
        return $this;
    }

    /* ---------- blocks / containers ---------- */

    public function block(string $name, array $commands, array $schema = []): PASMCanonicalBlock
    {
        if ($name === '' || $commands === []) {
            throw new InvalidArgumentException('Block name and commands required');
        }
        try {
            $b = $this->table->define($name, $commands, $schema);
            $this->blocks[$name] = $b;
            return $b;
        } catch (Throwable $e) {
            throw new PASMProgramException("Failed to define block {$name}", 'block', ['name' => $name], 0, $e);
        }
    }

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

    /**
     * Lower PHP-like integer statements to PASM assembly and queue them
     * into the unified bytecode stream.
     *
     * Examples:
     *   $prog->expr('$addedto = 0; $addedto = $addedto + 1; $addedto++; $addedto += 1;');
     */
    public function expr(string $source): self
    {
        if (trim($source) === '') {
            throw new InvalidArgumentException('expr source must be non-empty');
        }
        try {
            // compile for validation + register allocation; store source for unified build
            $this->exprCompiler->compile($source);
            $this->exprChunks[] = $source;
            return $this;
        } catch (PASMExprException $e) {
            throw new PASMAssembleException($e->getMessage(), ['expr' => $source], $e);
        }
    }

    /** Compile expr source alone → bytecode (does not mutate program unified stream). */
    public function compileExpr(string $source): string
    {
        try {
            $c = new PASMExprCompiler($this->exprCompiler->vars());
            return $c->compileToBytecode($source, $this->optimize);
        } catch (PASMExprException $e) {
            throw new PASMAssembleException($e->getMessage(), [], $e);
        }
    }

    public function runExpr(string $source): mixed
    {
        try {
            $code = $this->compileExpr($source);
            $vm = new PASMOptimizedBytecodeVM($this->runtime);
            return $vm->run($code);
        } catch (PASMProgramException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PASMExecuteException('expr execution failed', [], $e);
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

    private function assemble(string $source, string $label): string
    {
        if (trim($source) === '') {
            throw new PASMAssembleException("Empty assembly for {$label}", ['label' => $label]);
        }
        try {
            $assembler = new PASMOptimizingAssembler($this->optimize);
            return $assembler->compile($source);
        } catch (Throwable $e) {
            throw new PASMAssembleException("Failed to assemble {$label}: {$e->getMessage()}", ['label' => $label], $e);
        }
    }

    private function buildContainerPreludeAsm(array $bindings): array
    {
        $lines = ['; --- auto prelude: container scalars → arena ---'];
        $meta = ['bases' => [], 'counts' => [], 'skipped_non_int' => 0];
        $first = false;

        foreach ($bindings as $reg => $m) {
            $flat = array_values($m['values'] ?? []);
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
            try {
                $base = $this->runtime->alloc(max(8, $n * 4));
            } catch (Throwable $e) {
                throw new PASMFinalizeException('Arena alloc failed', ['reg' => $reg], $e);
            }
            foreach ($ints as $i => $v) {
                $this->runtime->store32($base + $i * 4, $v);
            }
            $meta['bases'][$reg] = $base;
            $meta['counts'][$reg] = $n;
            $lines[] = "; container {$reg} kind={$m['kind']} id={$m['containerId']} base={$base} count={$n}";
            if (!$first) {
                $lines[] = "        MOVI  ecx  {$base}";
                $lines[] = "        MOVI  ah   {$n}";
                $lines[] = "        MOVI  adx  {$m['containerId']}";
                $first = true;
            }
        }
        if (!$first) {
            $lines[] = '; (no integer container values)';
            $lines[] = '        MOVI  ecx  0';
            $lines[] = '        MOVI  ah   0';
            $lines[] = '        MOVI  adx  0';
        }
        return [implode("\n", $lines), $meta];
    }

    private function buildExprAsm(): string
    {
        if ($this->exprChunks === []) {
            return '';
        }
        // One compiler instance so variable→register map is stable across chunks
        $c = new PASMExprCompiler($this->exprCompiler->vars());
        $parts = [];
        foreach ($this->exprChunks as $src) {
            $parts[] = $c->compile($src);
        }
        // Drop intermediate RETs except the last compile's RET — re-join without duplicate RET
        // compile() always ends with RET; strip all but keep final
        $asm = implode("\n", $parts);
        // Leave as-is: multiple RET means early exit of unified stream if expr is last — OK for expr-only.
        // When mixed with user ASM after expr, strip trailing RET from all but last expr chunk.
        if (count($parts) > 1) {
            $fixed = [];
            foreach ($parts as $i => $p) {
                if ($i < count($parts) - 1) {
                    $p = preg_replace('/^\s*RET\s+\w+\s*$/m', '; (expr chunk end)', $p) ?? $p;
                }
                $fixed[] = $p;
            }
            $asm = implode("\n", $fixed);
        }
        return "; --- expr chunks ---\n" . $asm;
    }

    public function buildUnifiedAsm(array $bindings = []): string
    {
        if ($bindings === [] && $this->containers !== []) {
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
        $exprAsm = $this->buildExprAsm();
        $user = trim($this->asmFrame);

        $parts = [$prelude];
        if ($exprAsm !== '') {
            $parts[] = $exprAsm;
        }
        if ($user !== '') {
            $parts[] = "; --- user ASM frame ---\n" . $user;
        } elseif ($exprAsm === '') {
            $parts[] = <<<'ASM'
; default body: sum mem[ecx ..]
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
        // If we have expr (with RET) and no user ASM, expr provides RET.
        // If user ASM follows expr, strip final RET from expr section.
        if ($exprAsm !== '' && $user !== '') {
            $parts[1] = preg_replace('/^\s*RET\s+\w+\s*$/m', '; (fall through to user ASM)', $parts[1]) ?? $parts[1];
        }

        return implode("\n", $parts);
    }

    public function compileUnified(): string
    {
        return $this->assemble($this->buildUnifiedAsm(), 'unified');
    }

    public function runUnified(): mixed
    {
        try {
            $vm = new PASMOptimizedBytecodeVM($this->runtime);
            return $vm->run($this->compileUnified());
        } catch (PASMProgramException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new PASMExecuteException('Unified bytecode execution failed', [], $e);
        }
    }

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

            $exprBytecode = null;
            if ($this->exprChunks !== []) {
                try {
                    $exprBytecode = (new PASMExprCompiler())->compileToBytecode(
                        implode("\n", $this->exprChunks),
                        $this->optimize
                    );
                } catch (Throwable $e) {
                    throw new PASMAssembleException('expr compile failed', [], $e);
                }
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
                exprBytecode:     $exprBytecode,
                exprVars:         $this->exprCompiler->vars(),
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
        public readonly ?string $exprBytecode,
        public readonly array $exprVars,
        public readonly array $phpStages,
    ) {}

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

    public function runExpr(): mixed
    {
        if ($this->exprBytecode === null) {
            throw new PASMExecuteException('No expr() chunks were registered');
        }
        try {
            $vm = new PASMOptimizedBytecodeVM($this->runtime);
            return $vm->run($this->exprBytecode);
        } catch (Throwable $e) {
            throw new PASMExecuteException('expr bytecode run failed', [], $e);
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
        $lines[] = 'Canonical blocks: ' . ($this->blocks === [] ? '(none)' : implode(', ', array_keys($this->blocks)));
        $lines[] = 'Container bindings:';
        foreach ($this->bindings as $reg => $m) {
            $lines[] = sprintf(
                '  %s  %-7s  id=%d  values=%s',
                $reg,
                $m['kind'],
                $m['containerId'],
                json_encode($m['values'])
            );
        }
        $lines[] = 'Expr vars → regs: ' . ($this->exprVars === [] ? '(none)' : json_encode($this->exprVars));
        $lines[] = 'Unified bytecode: ' . strlen($this->unifiedBytecode) . ' bytes';
        $lines[] = '  hex: ' . bin2hex($this->unifiedBytecode);
        if ($this->exprBytecode !== null) {
            $lines[] = 'Expr-only bytecode: ' . strlen($this->exprBytecode) . ' bytes';
        }
        $lines[] = 'Kernels: ' . ($this->kernelBytecode === [] ? '(none)' : implode(', ', array_keys($this->kernelBytecode)));
        $lines[] = 'PHP stages: ' . ($this->phpStages === [] ? '(none)' : implode(', ', array_keys($this->phpStages)));
        return implode("\n", $lines);
    }
}
