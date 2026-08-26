<?php declare(strict_types=1);

namespace pasm;

use InvalidArgumentException;

/**
 * JX/PASM Bag Hot Operations.
 *
 * Canonical mnemonics are deliberately few. Human aliases are resolved during
 * parsing/linking and never survive into bytecode/native execution.
 *
 * BPUSH / BPOP are discipline-aware:
 *   record: rejected (use fixed field/slot lowering)
 *   vector: append / pop-back
 *   stack:  push / pop
 *   queue:  enqueue / dequeue
 *   deque:  push-back / pop-front (default queue-like discipline)
 *
 * Explicit deque-end operations use BPUSHF/BPUSHB/BPOPF/BPOPB.
 * BEMPLACE inserts into a contiguous Bag by calculating one insertion address,
 * opening one overlap-safe bulk gap, then storing the inserted value.
 */
final class PASMBagHotOp
{
    public const BPUSH  = 'BPUSH';
    public const BPOP   = 'BPOP';
    public const BPUSHF = 'BPUSHF';
    public const BPUSHB = 'BPUSHB';
    public const BPOPF  = 'BPOPF';
    public const BPOPB  = 'BPOPB';
    public const BEMPLACE = 'BEMPLACE';
    public const BPEEK  = 'BPEEK';
    public const BRESERVE = 'BRESERVE';
    public const BDIRTY = 'BDIRTY';
    public const BSYNC  = 'BSYNC';

    /** @var array<string,string> alias => canonical mnemonic */
    private const ALIASES = [
        // Discipline-aware insertion.
        'BPUSH' => self::BPUSH,
        'PUSH' => self::BPUSH,
        'APPEND' => self::BPUSH,
        'ADD' => self::BPUSH,
        'ENQUEUE' => self::BPUSH,
        'ENQ' => self::BPUSH,
        'QPUSH' => self::BPUSH,
        'SPUSH' => self::BPUSH,
        'VAPPEND' => self::BPUSH,

        // Discipline-aware removal.
        'BPOP' => self::BPOP,
        'POP' => self::BPOP,
        'TAKE' => self::BPOP,
        'DEQUEUE' => self::BPOP,
        'DEQ' => self::BPOP,
        'QPOP' => self::BPOP,
        'SPOP' => self::BPOP,
        'VPOP' => self::BPOP,

        // Explicit double-ended forms.
        'BPUSHF' => self::BPUSHF,
        'PUSHF' => self::BPUSHF,
        'PUSHFRONT' => self::BPUSHF,
        'UNSHIFT' => self::BPUSHF,
        'DPUSHF' => self::BPUSHF,

        'BPUSHB' => self::BPUSHB,
        'PUSHB' => self::BPUSHB,
        'PUSHBACK' => self::BPUSHB,
        'DPUSHB' => self::BPUSHB,

        'BPOPF' => self::BPOPF,
        'POPF' => self::BPOPF,
        'POPFRONT' => self::BPOPF,
        'SHIFT' => self::BPOPF,
        'DPOPF' => self::BPOPF,

        'BPOPB' => self::BPOPB,
        'POPB' => self::BPOPB,
        'POPBACK' => self::BPOPB,
        'DPOPB' => self::BPOPB,

        // Indexed contiguous insertion. One address calculation, one bulk move,
        // one store; aliases disappear before execution.
        'BEMPLACE' => self::BEMPLACE,
        'EMPLACE' => self::BEMPLACE,
        'INSERT' => self::BEMPLACE,
        'BINSERT' => self::BEMPLACE,
        'PACKIN' => self::BEMPLACE,

        // Support operations.
        'BPEEK' => self::BPEEK,
        'PEEK' => self::BPEEK,
        'TOP' => self::BPEEK,
        'FRONT' => self::BPEEK,

        'BRESERVE' => self::BRESERVE,
        'RESERVE' => self::BRESERVE,
        'ENSURE' => self::BRESERVE,

        'BDIRTY' => self::BDIRTY,
        'DIRTY' => self::BDIRTY,

        'BSYNC' => self::BSYNC,
        'SYNC' => self::BSYNC,
        'CHECKPOINT' => self::BSYNC,
        'COMMITBAG' => self::BSYNC,
    ];

    public static function canonical(string $name): string
    {
        $key = strtoupper(trim($name));
        return self::ALIASES[$key]
            ?? throw new InvalidArgumentException("Unknown Bag hot operation: {$name}");
    }

    public static function isAlias(string $name): bool
    {
        return isset(self::ALIASES[strtoupper(trim($name))]);
    }

    /** @return array<string,string> */
    public static function aliases(): array
    {
        return self::ALIASES;
    }

    /**
     * Returns semantic lowering class. Actual machine registers/offsets are
     * allocated by the native backend and are not encoded into canonical JX.
     *
     * BEMPLACE operands are conceptually:
     *   base, index, width, cursor, value
     * where tail_bytes = cursor - insert. The native backend may inline the
     * overlap-safe move (REP MOVS / vector copy) or use its target memmove
     * intrinsic. The canonical hot operation remains one superinstruction.
     */
    public static function lowering(string $op, string $discipline): array
    {
        $op = self::canonical($op);
        $discipline = strtolower($discipline);

        if ($discipline === 'record' && in_array($op, [self::BPUSH,self::BPOP,self::BPUSHF,self::BPUSHB,self::BPOPF,self::BPOPB,self::BEMPLACE], true)) {
            throw new InvalidArgumentException('Record Bags lower through fixed slots/offsets, not cursor insertion');
        }

        return match ($op) {
            self::BPUSH => match ($discipline) {
                'vector','stack' => ['kind'=>'cursor-write-inc','asm'=>['mov [cursor], value','add cursor, width']],
                'queue','deque' => ['kind'=>'tail-write-inc','asm'=>['mov [tail], value','add tail, width']],
                default => throw new InvalidArgumentException("BPUSH unsupported for {$discipline}"),
            },
            self::BPOP => match ($discipline) {
                'vector','stack' => ['kind'=>'cursor-dec-read','asm'=>['sub cursor, width','mov value, [cursor]']],
                'queue','deque' => ['kind'=>'head-read-inc','asm'=>['mov value, [head]','add head, width']],
                default => throw new InvalidArgumentException("BPOP unsupported for {$discipline}"),
            },
            self::BPUSHF => ['kind'=>'head-dec-write','asm'=>['sub head, width','mov [head], value']],
            self::BPUSHB => ['kind'=>'tail-write-inc','asm'=>['mov [tail], value','add tail, width']],
            self::BPOPF => ['kind'=>'head-read-inc','asm'=>['mov value, [head]','add head, width']],
            self::BPOPB => ['kind'=>'tail-dec-read','asm'=>['sub tail, width','mov value, [tail]']],
            self::BEMPLACE => match ($discipline) {
                'vector','stack' => [
                    'kind'=>'address-gap-pack-store',
                    'asm'=>[
                        'lea insert, [base+index*width]',
                        'memmove [insert+width], [insert], cursor-insert',
                        'mov [insert], value',
                    ],
                    'post'=>['add cursor, width'],
                    'overlap_safe'=>true,
                    'bulk_move'=>true,
                ],
                default => throw new InvalidArgumentException("BEMPLACE requires contiguous vector/stack discipline; {$discipline} uses ring/keyed insertion"),
            },
            self::BPEEK => ['kind'=>'peek','asm'=>['mov value, [cursor-width]']],
            self::BRESERVE => ['kind'=>'region-reserve','asm'=>['lea tmp, [cursor+bytes]','cmp tmp, end','ja .bag_grow']],
            self::BDIRTY => ['kind'=>'dirty-once','asm'=>['or flags, BAG_DIRTY']],
            self::BSYNC => ['kind'=>'canonical-boundary','asm'=>[]],
        };
    }
}
