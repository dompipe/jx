<?php declare(strict_types=1);

namespace pasm;

use InvalidArgumentException;

require_once __DIR__ . '/jx-alias.php';

use jx\AliasDomain;
use jx\JxAlias;

/**
 * JX/PASM Bag Hot Operations.
 *
 * Canonical mnemonics are deliberately few. All public aliases live in the
 * language-wide JxAlias registry and disappear during parse/link.
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

    public static function canonical(string $name): string
    {
        return JxAlias::canonical(AliasDomain::BAG_HOT, $name);
    }

    public static function isAlias(string $name): bool
    {
        return JxAlias::has(AliasDomain::BAG_HOT, $name);
    }

    /** @return array<string,string> */
    public static function aliases(): array
    {
        return JxAlias::aliases(AliasDomain::BAG_HOT);
    }

    /**
     * Returns a semantic native-lowering recipe. Actual registers, offsets,
     * array helpers and relocation targets are assigned by the native backend.
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
                'map','set' => throw new InvalidArgumentException("{$discipline} uses BEMPLACE for ordered insert-if-absent semantics"),
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

            // BEMPLACE is discipline-aware. Map is a keyed Vector of complete
            // [key,value] entries; Set is the ordered one-word keyed form.
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
                    'insert_if_absent'=>false,
                ],
                'map' => [
                    'kind'=>'ordered-keyed-vector-emplace',
                    'asm'=>[
                        'call keyed_vector_find_key',
                        'jc .exists',
                        'memmove entries[index+1], entries[index], tail-entry-bytes',
                        'mov entries[index].key, key',
                        'mov entries[index].value, value',
                    ],
                    'find_once'=>true,
                    'layout'=>['Entry[]','Entry=[key,value]'],
                    'entry_width'=>'key_width + value_width',
                    'insert_if_absent'=>true,
                    'returns_existing'=>true,
                    'hashing'=>false,
                ],
                'set' => [
                    'kind'=>'ordered-unique-array-emplace',
                    'asm'=>[
                        'call sorted_find_key',
                        'jc .exists',
                        'memmove keys[index+1], keys[index], tail-bytes',
                        'mov keys[index], key',
                    ],
                    'find_once'=>true,
                    'layout'=>['keys[]'],
                    'insert_if_absent'=>true,
                    'returns_existing'=>true,
                    'hashing'=>false,
                ],
                default => throw new InvalidArgumentException("BEMPLACE unsupported for {$discipline}"),
            },

            self::BPEEK => ['kind'=>'peek','asm'=>['mov value, [cursor-width]']],
            self::BRESERVE => ['kind'=>'region-reserve','asm'=>['lea tmp, [cursor+bytes]','cmp tmp, end','ja .bag_grow']],
            self::BDIRTY => ['kind'=>'dirty-once','asm'=>['or flags, BAG_DIRTY']],
            self::BSYNC => ['kind'=>'canonical-boundary','asm'=>[]],
        };
    }
}
