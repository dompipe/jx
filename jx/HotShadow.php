<?php declare(strict_types=1);

namespace jx;

/**
 * Stable low shadow IDs shared by the base compiler and native hosts.
 *
 * 0..15 are ABI-reserved. 16..255 are available to the compiler for
 * prelinked reactive handlers. The numeric value is awake-state execution
 * identity only; canonical meaning remains in Bags/source provenance.
 */
final class HotShadow
{
    public const VERSION = 'jx.hot-shadow/1';

    public const STATE    = 0;
    public const TASKBAR  = 1;
    public const TITLE    = 2;
    public const FOCUS    = 3;
    public const GEOMETRY = 4;

    public const FIRST_DYNAMIC = 16;
    public const MAX = 255;

    /** @return array<int,string> */
    public static function reserved(): array
    {
        return [
            self::STATE => 'state',
            self::TASKBAR => 'taskbar',
            self::TITLE => 'title',
            self::FOCUS => 'focus',
            self::GEOMETRY => 'geometry',
        ];
    }

    public static function name(int $shadow): string
    {
        if ($shadow < 0 || $shadow > self::MAX) {
            throw new JxException('Hot shadow must fit uint8', 'hot-shadow', true, ['shadow'=>$shadow]);
        }
        return self::reserved()[$shadow] ?? ('dynamic-'.$shadow);
    }

    public static function dynamic(int $ordinal): int
    {
        if ($ordinal < 0) throw new JxException('Dynamic shadow ordinal must be non-negative', 'hot-shadow', true);
        $shadow = self::FIRST_DYNAMIC + $ordinal;
        if ($shadow > self::MAX) {
            throw new JxException('Hot shadow space exhausted', 'hot-shadow', true, ['ordinal'=>$ordinal]);
        }
        return $shadow;
    }
}
