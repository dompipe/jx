<?php declare(strict_types=1);

namespace pasm\lang;

use InvalidArgumentException;

/**
 * Canonical collection-loop surface vocabulary.
 *
 * `foreach` is forward traversal.
 * `reveach` is reverse traversal.
 * `forif` is forward traversal with an inline predicate.
 * `revif` is reverse traversal with an inline predicate.
 *
 * Filtered loops deliberately reuse the same iterator controller. The filter
 * is a body-entry predicate, so a rejected value advances to the next element
 * rather than creating a second iterator or container walk.
 *
 * The repeated executable operation remains the compact iterator ABI:
 *   foreach / forif -> ITERF <slot>
 *   reveach / revif -> ITERR <slot>
 *
 * The collection, iterator descriptor, destination register, and optional key
 * target are prelinked outside the repeated hot path. The repeated call carries
 * only the one-byte iterator slot after its opcode.
 */
final class PASMForeachSurface
{
    public const FOREACH = 'foreach';
    public const REVEACH = 'reveach';
    public const FORIF = 'forif';
    public const REVIF = 'revif';

    public static function iteratorOpcode(string $keyword): string
    {
        return match (strtolower(trim($keyword))) {
            self::FOREACH, self::FORIF => 'ITERF',
            self::REVEACH, self::REVIF => 'ITERR',
            default => throw new InvalidArgumentException("Unknown collection loop {$keyword}"),
        };
    }

    public static function reverse(string $keyword): bool
    {
        $keyword = strtolower(trim($keyword));
        return $keyword === self::REVEACH || $keyword === self::REVIF;
    }

    public static function filtered(string $keyword): bool
    {
        $keyword = strtolower(trim($keyword));
        return $keyword === self::FORIF || $keyword === self::REVIF;
    }

    public static function keywords(): array
    {
        return [self::FOREACH, self::REVEACH, self::FORIF, self::REVIF];
    }
}
