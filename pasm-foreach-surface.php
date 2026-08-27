<?php declare(strict_types=1);

namespace pasm\lang;

use InvalidArgumentException;

/**
 * Canonical collection-loop surface vocabulary.
 *
 * `foreach` is forward traversal.
 * `reveach` is reverse traversal.
 *
 * The repeated executable operation remains the compact iterator ABI:
 *   foreach  -> ITERF <slot>
 *   reveach  -> ITERR <slot>
 *
 * The collection, iterator descriptor, destination register, and optional key
 * target are prelinked outside the repeated hot path. The repeated call carries
 * only the one-byte iterator slot after its opcode.
 */
final class PASMForeachSurface
{
    public const FOREACH = 'foreach';
    public const REVEACH = 'reveach';

    public static function iteratorOpcode(string $keyword): string
    {
        return match (strtolower(trim($keyword))) {
            self::FOREACH => 'ITERF',
            self::REVEACH => 'ITERR',
            default => throw new InvalidArgumentException("Unknown collection loop {$keyword}"),
        };
    }

    public static function reverse(string $keyword): bool
    {
        return strtolower(trim($keyword)) === self::REVEACH;
    }

    public static function keywords(): array
    {
        return [self::FOREACH, self::REVEACH];
    }
}
