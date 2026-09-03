<?php declare(strict_types=1);

namespace jx;

use InvalidArgumentException;

/** Canonical compiler message emitted by jx.exe for each bytecode page. */
final class JxBytecodePageReport
{
    public const EXECUTABLE = 'jx.exe';
    public const TARGET_PASM = 'PASM';
    public const TARGET_JXL = 'JXL';

    /** @param list<string> $dependencies @param array<string,string> $registers */
    public function __construct(
        public readonly int $page,
        public readonly string $bytecode,
        public readonly bool $optimized = true,
        public readonly bool $fused = false,
        public readonly bool $reactive = false,
        public readonly string $target = self::TARGET_PASM,
        public readonly ?string $source = null,
        public readonly ?string $shadow = null,
        public readonly array $dependencies = [],
        public readonly array $registers = [],
        public readonly int $iteratorSlots = 0,
        public readonly ?string $output = null,
    ) {
        if ($page < 0) throw new InvalidArgumentException('Bytecode page cannot be negative');
        if (trim($target) === '') throw new InvalidArgumentException('Bytecode target cannot be empty');
    }

    public function bytes(): int { return strlen($this->bytecode); }
    public function id(): string { return sprintf('%03d', $this->page); }

    public function mode(): string
    {
        $parts = [$this->optimized ? 'O1' : 'O0'];
        if ($this->fused) $parts[] = 'FUSED';
        if ($this->reactive) $parts[] = 'REACTIVE';
        return implode('+', $parts);
    }

    /** The normal jx.exe build line: deliberately one line and grep-friendly. */
    public function compact(): string
    {
        return sprintf(
            '%s PAGE %s  OK  %dB  %s  deps:%d  regs:%d  iter:%d  target:%s',
            self::EXECUTABLE,
            $this->id(),
            $this->bytes(),
            $this->mode(),
            count($this->dependencies),
            count($this->registers),
            $this->iteratorSlots,
            strtoupper($this->target),
        );
    }

    /** Expanded compiler output used by jx.exe -v. */
    public function verbose(): string
    {
        $lines = [$this->compact()];
        if ($this->source !== null) $lines[] = '  source     : ' . $this->source;
        if ($this->shadow !== null) $lines[] = '  shadow     : ' . $this->shadow;
        if ($this->output !== null) $lines[] = '  output     : ' . $this->output;
        $lines[] = '  bytecode   : ' . $this->bytes() . ' bytes';
        $lines[] = '  mode       : ' . $this->mode();
        $lines[] = '  target     : ' . strtoupper($this->target);
        $lines[] = '  dependency : ' . ($this->dependencies === [] ? 'none' : implode(', ', $this->dependencies));
        if ($this->registers === []) {
            $lines[] = '  registers  : none reported';
        } else {
            $pairs = [];
            foreach ($this->registers as $name=>$reg) $pairs[] = $name . '->' . $reg;
            $lines[] = '  registers  : ' . implode(', ', $pairs);
        }
        $lines[] = '  iterators  : ' . $this->iteratorSlots;
        return implode("\n", $lines);
    }

    public function data(): array
    {
        return [
            'executable'=>self::EXECUTABLE,
            'event'=>'bytecode.page',
            'status'=>'ok',
            'page'=>$this->page,
            'page_id'=>$this->id(),
            'bytes'=>$this->bytes(),
            'mode'=>$this->mode(),
            'optimized'=>$this->optimized,
            'fused'=>$this->fused,
            'reactive'=>$this->reactive,
            'target'=>strtoupper($this->target),
            'source'=>$this->source,
            'shadow'=>$this->shadow,
            'output'=>$this->output,
            'dependencies'=>array_values($this->dependencies),
            'registers'=>$this->registers,
            'iterator_slots'=>$this->iteratorSlots,
        ];
    }

    public function json(): string
    {
        return json_encode($this->data(), JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    }
}

final class JxCompilerOutput
{
    public const COMPACT = 'compact';
    public const VERBOSE = 'verbose';
    public const JSON = 'json';

    public static function render(JxBytecodePageReport $report, string $mode=self::COMPACT): string
    {
        return match ($mode) {
            self::COMPACT => $report->compact(),
            self::VERBOSE => $report->verbose(),
            self::JSON => $report->json(),
            default => throw new InvalidArgumentException("Unknown JX compiler output mode {$mode}"),
        };
    }
}