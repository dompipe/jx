<?php declare(strict_types=1);

namespace jx;

/**
 * JX hot-call identity shared by the compiler, native hosts and OSAura.
 *
 * Executable hot calls are always one byte:
 *   [1][bank:4][shadow:3]
 *
 * Canonical names remain outside this byte. A program resolves canonical
 * meaning once, then carries the bank/shadow opcode while awake.
 */
final class HotShadow
{
    public const VERSION = 'jx.hot-shadow/2';
    public const HOT_BASE = 0x80;
    public const BANKS = 16;
    public const SHADOWS_PER_BANK = 8;
    public const HOT_ENTRIES = 128;

    /* Stable low shadow IDs within a bank. */
    public const STATE    = 0;
    public const TASKBAR  = 1;
    public const TITLE    = 2;
    public const FOCUS    = 3;
    public const GEOMETRY = 4;

    /* Compatibility-only canonical shadow namespace. It is not bytecode. */
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

    public static function opcode(int $bank, int $shadow): int
    {
        if ($bank < 0 || $bank >= self::BANKS || $shadow < 0 || $shadow >= self::SHADOWS_PER_BANK) {
            throw new JxException('Hot opcode requires bank 0..15 and shadow 0..7', 'hot-shadow', true,
                ['bank'=>$bank, 'shadow'=>$shadow]);
        }
        return self::HOT_BASE | (($bank & 0x0f) << 3) | ($shadow & 0x07);
    }

    /** @return array{bank:int,shadow:int} */
    public static function decodeOpcode(int $opcode): array
    {
        if ($opcode < self::HOT_BASE || $opcode > 0xff) {
            throw new JxException('Hot opcode must have MSB=1', 'hot-shadow', true, ['opcode'=>$opcode]);
        }
        return ['bank'=>($opcode >> 3) & 0x0f, 'shadow'=>$opcode & 0x07];
    }

    public static function isHotOpcode(int $opcode): bool
    {
        return $opcode >= self::HOT_BASE && $opcode <= 0xff;
    }

    public static function name(int $shadow): string
    {
        if ($shadow < 0 || $shadow > self::MAX) {
            throw new JxException('Canonical shadow must fit uint8', 'hot-shadow', true, ['shadow'=>$shadow]);
        }
        return self::reserved()[$shadow] ?? ('dynamic-'.$shadow);
    }

    /* Legacy canonical allocator; compilation must lower it to bank/shadow. */
    public static function dynamic(int $ordinal): int
    {
        if ($ordinal < 0) throw new JxException('Dynamic shadow ordinal must be non-negative', 'hot-shadow', true);
        $shadow = self::FIRST_DYNAMIC + $ordinal;
        if ($shadow > self::MAX) {
            throw new JxException('Canonical shadow space exhausted', 'hot-shadow', true, ['ordinal'=>$ordinal]);
        }
        return $shadow;
    }
}
