<?php declare(strict_types=1);

namespace pasm;

require_once __DIR__ . '/pasm-iterator-abi.php';
require_once __DIR__ . '/jx-bag-containers.php';

use jx\BagContainerContract;

/**
 * One-time Bag-container -> iterator-slot linker.
 *
 * Binding may inspect/copy the rich container shape once. Repeated traversal
 * then uses only ITERF/ITERR + one u8 slot, preserving the 2-byte hot ABI.
 * The snapshot is deliberate: a compiled foreach sees a stable collection
 * image for that binding. Rebind to observe a later container revision.
 */
final class PASMBagIterator
{
    public static function bind(PASMIteratorTable $table, int $slot, BagContainerContract $container): PASMIteratorDescriptor
    {
        $snapshot = $container->toArray();
        $keys = array_keys($snapshot);
        $values = array_values($snapshot);

        $descriptor = new PASMIteratorDescriptor(
            $slot,
            count($values),
            static fn(int $i): mixed => $values[$i],
            static fn(int $i): mixed => $keys[$i],
        );
        $table->replace($descriptor);
        return $descriptor;
    }

    /** @return list<mixed> */
    public static function collectForward(PASMIteratorTable $table, int $slot): array
    {
        $out = [];
        $bc = PASMIterBC::encodeForward($slot);
        while (($item = $table->execute($bc))->valid) $out[] = $item->value;
        return $out;
    }

    /** @return list<mixed> */
    public static function collectReverse(PASMIteratorTable $table, int $slot): array
    {
        $out = [];
        $bc = PASMIterBC::encodeReverse($slot);
        while (($item = $table->execute($bc))->valid) $out[] = $item->value;
        return $out;
    }

    /** @return list<array{key:mixed,value:mixed}> */
    public static function collectPairs(PASMIteratorTable $table, int $slot, bool $reverse=false): array
    {
        $out = [];
        $bc = $reverse ? PASMIterBC::encodeReverse($slot) : PASMIterBC::encodeForward($slot);
        while (($item = $table->execute($bc))->valid) $out[] = ['key'=>$item->key,'value'=>$item->value];
        return $out;
    }
}
