<?php declare(strict_types=1);
/**
 * Hook ladder: in → PHP page → out (monitor I/O).
 */
final class Ladder
{
    /** @param list<callable(array): void> $before */
    /** @param list<callable(array, array): void> $after */
    public function __construct(
        private array $before = [],
        private array $after = [],
    ) {}

    /** @param callable(array): array $page */
    public function run(array $in, callable $page): array
    {
        foreach ($this->before as $h) {
            $h($in);
        }
        $out = $page($in);
        foreach ($this->after as $h) {
            $h($in, $out);
        }
        return $out;
    }
}
