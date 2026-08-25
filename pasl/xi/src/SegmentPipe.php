<?php declare(strict_types=1);
/**
 * XIP segment pipe — NOP by default, replace per segment.
 * @phpstan-type SegFn callable(Bag, array): Bag
 */
final class SegmentPipe
{
    /** @var array<string, callable(Bag, array): Bag> */
    private array $segments = [];

    /** @param array<string, callable(Bag, array): Bag> $segments */
    public function __construct(array $segments = [])
    {
        foreach ($segments as $name => $fn) {
            $this->set((string)$name, $fn);
        }
    }

    /** @param callable(Bag, array): Bag $fn */
    public function set(string $name, callable $fn): void
    {
        $this->segments[$name] = $fn;
    }

    public function run(Bag $buffer, array $req): Bag
    {
        foreach ($this->segments as $fn) {
            $buffer = $fn($buffer, $req);
        }
        return $buffer;
    }
}
