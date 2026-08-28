<?php declare(strict_types=1);

namespace jx\runtime;

final readonly class ServiceMutation
{
    public function __construct(
        public bool $mutated,
        public ?string $bagId = null,
        public ?int $generation = null,
        public bool $dirtySurface = false,
    ) {}

    public static function none(): self { return new self(false); }
}

/**
 * Host-neutral event -> execute -> Bag -> dirty -> present service law.
 *
 * Foreground is preferential, never exclusive. Each turn services up to the
 * foreground quantum first, then one event for every other listener in stable
 * PID order. Hosts provide mechanism callbacks; JX owns the ordering meaning.
 */
final class ServiceTurn
{
    public function __construct(private readonly int $foregroundQuantum = 4)
    {
        if ($foregroundQuantum < 1 || $foregroundQuantum > 64) {
            throw new \InvalidArgumentException('foregroundQuantum must be 1..64');
        }
    }

    /**
     * @param callable():?int $primaryListener
     * @param callable():array<int> $listeners
     * @param callable(int):mixed $popEvent null means empty
     * @param callable(int):void $wake
     * @param callable(int,mixed):ServiceMutation $execute
     * @param callable(string,int):void $publishBag
     * @param callable(int):void $markDirty listener PID
     * @param callable():void $present
     * @return array{events:int,foreground_events:int,background_events:int,mutations:int,presents:int,route:list<int>}
     */
    public function run(
        callable $primaryListener,
        callable $listeners,
        callable $popEvent,
        callable $wake,
        callable $execute,
        callable $publishBag,
        callable $markDirty,
        callable $present,
    ): array {
        $all = array_values(array_unique(array_map('intval', $listeners())));
        $all = array_values(array_filter($all, static fn(int $pid): bool => $pid >= 0));
        sort($all, SORT_NUMERIC);

        $primary = $primaryListener();
        if ($primary !== null && !in_array($primary, $all, true)) $primary = null;

        $route = [];
        if ($primary !== null) $route[] = $primary;
        foreach ($all as $pid) if ($pid !== $primary) $route[] = $pid;

        $stats = [
            'events' => 0,
            'foreground_events' => 0,
            'background_events' => 0,
            'mutations' => 0,
            'presents' => 0,
            'route' => $route,
        ];
        $needPresent = false;

        foreach ($route as $index => $pid) {
            $budget = ($index === 0 && $primary !== null) ? $this->foregroundQuantum : 1;
            for ($n = 0; $n < $budget; $n++) {
                $event = $popEvent($pid);
                if ($event === null) break;
                $wake($pid);
                $mutation = $execute($pid, $event);
                if (!$mutation instanceof ServiceMutation) {
                    throw new \RuntimeException('Service executor must return ServiceMutation');
                }
                $stats['events']++;
                if ($pid === $primary) $stats['foreground_events']++;
                else $stats['background_events']++;

                if ($mutation->mutated) {
                    if ($mutation->bagId === null || $mutation->generation === null || $mutation->generation < 0) {
                        throw new \RuntimeException('Mutating service result requires Bag ID and generation');
                    }
                    $publishBag($mutation->bagId, $mutation->generation);
                    $stats['mutations']++;
                }
                if ($mutation->dirtySurface) {
                    $markDirty($pid);
                    $needPresent = true;
                }
            }
        }

        // One coalesced present per service turn. This prevents repainting stale
        // pixels between multiple foreground mutations while still presenting
        // immediately after the bounded turn completes.
        if ($needPresent) {
            $present();
            $stats['presents'] = 1;
        }
        return $stats;
    }
}
