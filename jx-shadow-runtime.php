<?php declare(strict_types=1);

namespace jx;

require_once __DIR__ . '/jx-reactive.php';
require_once __DIR__ . '/pasm-runtime.php';
require_once __DIR__ . '/pasm-bytecode.php';

use InvalidArgumentException;
use RuntimeException;
use pasm\PASM;
use pasm\PASMAssembler;
use pasm\PASMBytecodeVM;
use pasm\PASMRuntime;

/**
 * One compiled execution shadow. Canonical source remains elsewhere; this
 * object carries only executable form plus the source identities that can
 * invalidate it.
 */
interface ExecutableShadow
{
    public function id(): string;
    /** @return list<string> */
    public function dependencies(): array;
    public function run(ReactiveShadowRuntime $runtime): mixed;
    public function runs(): int;
    public function lastResult(): mixed;
    public function dirty(): bool;
    public function markDirty(string $sourceId): void;
    public function clearDirty(): void;
}

abstract class AbstractExecutableShadow implements ExecutableShadow
{
    /** @var array<string,true> */
    private array $dependencySet = [];
    private bool $isDirty = true;
    private int $runCount = 0;
    private mixed $result = null;
    /** @var array<string,true> */
    private array $invalidatedBy = [];

    /** @param list<string> $dependencies */
    public function __construct(private readonly string $shadowId, array $dependencies)
    {
        if (trim($shadowId) === '') throw new InvalidArgumentException('Shadow id cannot be empty');
        foreach ($dependencies as $id) {
            $id = (string)$id;
            if ($id === '') throw new InvalidArgumentException('Shadow dependency id cannot be empty');
            $this->dependencySet[$id] = true;
        }
        if ($this->dependencySet === []) throw new InvalidArgumentException('Executable shadow needs at least one reactive dependency');
    }

    public function id(): string { return $this->shadowId; }
    public function dependencies(): array { return array_keys($this->dependencySet); }
    public function runs(): int { return $this->runCount; }
    public function lastResult(): mixed { return $this->result; }
    public function dirty(): bool { return $this->isDirty; }
    public function invalidatedBy(): array { return array_keys($this->invalidatedBy); }
    public function markDirty(string $sourceId): void
    {
        if (!isset($this->dependencySet[$sourceId])) return;
        $this->invalidatedBy[$sourceId] = true;
        $this->isDirty = true;
    }
    public function clearDirty(): void { $this->isDirty = false; $this->invalidatedBy = []; }

    final public function run(ReactiveShadowRuntime $runtime): mixed
    {
        if (!$this->isDirty && $this->runCount > 0) return $this->result;
        $this->result = $this->execute($runtime);
        $this->runCount++;
        $this->clearDirty();
        return $this->result;
    }

    abstract protected function execute(ReactiveShadowRuntime $runtime): mixed;
}

/** Host/fallback shadow. Kept for operations whose native/PASM lowering is not yet available. */
final class CallbackExecutableShadow extends AbstractExecutableShadow
{
    /** @param list<string> $dependencies */
    public function __construct(string $id, array $dependencies, private readonly \Closure $executor)
    {
        parent::__construct($id, $dependencies);
    }

    protected function execute(ReactiveShadowRuntime $runtime): mixed
    {
        return ($this->executor)($runtime, $this);
    }
}

/**
 * PASM execution shadow. Source values are prelinked to hot registers once by
 * source identity. The repeated dispatch path does not parse JX/PHP-ish code.
 */
final class PASMExecutableShadow extends AbstractExecutableShadow
{
    /** @var array<string,string> source-id => PASM register name */
    private array $inputRegisters;
    private readonly string $bytecode;
    private PASMRuntime $pasmRuntime;

    /**
     * @param array<string,string> $inputRegisters source-id => register
     */
    public function __construct(
        string $id,
        array $inputRegisters,
        string $assemblyOrBytecode,
        bool $isBytecode = false,
        ?PASMRuntime $runtime = null,
    ) {
        if ($inputRegisters === []) throw new InvalidArgumentException('PASM shadow needs at least one prelinked input');
        foreach ($inputRegisters as $sourceId=>$register) {
            if (!array_key_exists(strtolower($register), \pasm\PASMBC::REG)) {
                throw new InvalidArgumentException("Unsupported PASM shadow register {$register}");
            }
            $inputRegisters[(string)$sourceId] = strtolower($register);
        }
        $this->inputRegisters = $inputRegisters;
        parent::__construct($id, array_keys($inputRegisters));
        $this->bytecode = $isBytecode ? $assemblyOrBytecode : (new PASMAssembler())->compile($assemblyOrBytecode);
        $this->pasmRuntime = $runtime ?? new PASMRuntime();
    }

    public function bytecodeBytes(): int { return strlen($this->bytecode); }
    public function inputRegisters(): array { return $this->inputRegisters; }

    protected function execute(ReactiveShadowRuntime $runtime): mixed
    {
        foreach ($this->inputRegisters as $sourceId=>$register) {
            $value = $runtime->source($sourceId)->value();
            if (is_bool($value)) $value = $value ? 1 : 0;
            if (!is_int($value)) throw new RuntimeException("PASM scalar shadow input {$sourceId} must currently be int/bool");
            PASM::${$register} = $value;
        }
        return (new PASMBytecodeVM($this->pasmRuntime))->run($this->bytecode);
    }
}

/**
 * Dependency index and dispatch engine.
 *
 * source revision -> indexed shadows -> execute only affected shadows
 */
final class ReactiveShadowRuntime
{
    /** @var array<string,ReactiveSource> */
    private array $sources = [];
    /** @var array<string,int> source-id => subscription */
    private array $sourceSubscriptions = [];
    /** @var array<string,ExecutableShadow> */
    private array $shadows = [];
    /** @var array<string,array<string,true>> source-id => shadow-id set */
    private array $dependents = [];
    private bool $autoDispatch = true;
    private int $dispatches = 0;

    public function __construct(bool $autoDispatch = true) { $this->autoDispatch = $autoDispatch; }

    public function addSource(ReactiveSource $source): self
    {
        $id = $source->id();
        if (isset($this->sources[$id]) && $this->sources[$id] !== $source) {
            throw new RuntimeException("Reactive source identity collision {$id}");
        }
        if (!isset($this->sources[$id])) {
            $this->sources[$id] = $source;
            $this->sourceSubscriptions[$id] = $source->subscribe(function(ReactiveSource $changed): void {
                $this->invalidateSource($changed->id());
                if ($this->autoDispatch) $this->dispatchSource($changed->id());
            });
        }
        return $this;
    }

    public function source(string $id): ReactiveSource
    {
        return $this->sources[$id] ?? throw new RuntimeException("Unknown reactive source {$id}");
    }

    public function addShadow(ExecutableShadow $shadow, bool $runInitial = true): self
    {
        if (isset($this->shadows[$shadow->id()])) throw new RuntimeException("Duplicate shadow id {$shadow->id()}");
        foreach ($shadow->dependencies() as $sourceId) {
            if (!isset($this->sources[$sourceId])) throw new RuntimeException("Shadow {$shadow->id()} depends on unregistered source {$sourceId}");
            $this->dependents[$sourceId][$shadow->id()] = true;
        }
        $this->shadows[$shadow->id()] = $shadow;
        if ($runInitial) $shadow->run($this);
        return $this;
    }

    public function shadow(string $id): ExecutableShadow
    {
        return $this->shadows[$id] ?? throw new RuntimeException("Unknown executable shadow {$id}");
    }

    public function invalidateSource(string $sourceId): int
    {
        $n = 0;
        foreach (array_keys($this->dependents[$sourceId] ?? []) as $shadowId) {
            $shadow = $this->shadows[$shadowId];
            $before = $shadow->dirty();
            $shadow->markDirty($sourceId);
            if (!$before && $shadow->dirty()) $n++;
        }
        return $n;
    }

    /** Execute only shadows directly indexed by this changed source. */
    public function dispatchSource(string $sourceId): int
    {
        $ran = 0;
        foreach (array_keys($this->dependents[$sourceId] ?? []) as $shadowId) {
            $shadow = $this->shadows[$shadowId];
            if (!$shadow->dirty()) continue;
            $shadow->run($this);
            $ran++;
            $this->dispatches++;
        }
        return $ran;
    }

    /** Execute every dirty shadow; useful after batched source updates. */
    public function settle(): int
    {
        $ran = 0;
        foreach ($this->shadows as $shadow) {
            if (!$shadow->dirty()) continue;
            $shadow->run($this);
            $ran++;
            $this->dispatches++;
        }
        return $ran;
    }

    public function dispatches(): int { return $this->dispatches; }

    /** @return array<string,list<string>> */
    public function dependencyIndex(): array
    {
        $out = [];
        foreach ($this->dependents as $sourceId=>$set) $out[$sourceId] = array_keys($set);
        return $out;
    }

    public function status(): array
    {
        $sources=[];$shadows=[];
        foreach($this->sources as $id=>$s)$sources[$id]=['revision'=>$s->revision(),'type'=>$s::class];
        foreach($this->shadows as $id=>$s)$shadows[$id]=['runs'=>$s->runs(),'dirty'=>$s->dirty(),'dependencies'=>$s->dependencies(),'last_result'=>$s->lastResult()];
        return ['sources'=>$sources,'shadows'=>$shadows,'dispatches'=>$this->dispatches];
    }
}
