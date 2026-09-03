<?php declare(strict_types=1);

namespace jx;

use InvalidArgumentException;

/**
 * Cold PHP lowering for JX filtered collection syntax.
 *
 * This layer understands rich authoring forms such as:
 *
 *   _, no1, no2, no3 = forif ($value in $values if no1 < _)
 *
 * and turns them into a canonical plan before JXL/PASM encoding begins.
 * PASM does not own tuple semantics. JXL is free to redesign the normalized
 * plan into compact target-specific operations later.
 */
final class ForIfPlan
{
    /** @param list<string> $targets */
    public function __construct(
        public readonly string $direction,
        public readonly string $collection,
        public readonly array $targets,
        public readonly ?string $sourceValue,
        public readonly ?string $key,
        public readonly string $predicate,
    ) {}

    public function reverse(): bool { return $this->direction === 'revif'; }
    public function width(): int { return count($this->targets); }

    /** Position zero is always the canonical current value `_`. */
    public function current(): string { return $this->targets[0]; }

    /** @return array<string,int> */
    public function positions(): array
    {
        $out=[];
        foreach($this->targets as $i=>$name)$out[$name]=$i;
        return $out;
    }

    /** Canonical cold IR passed toward JXL preparation. */
    public function toArray(): array
    {
        return [
            'op' => 'FORIF_ROW',
            'direction' => $this->direction,
            'reverse' => $this->reverse(),
            'collection' => $this->collection,
            'targets' => $this->targets,
            'positions' => $this->positions(),
            'current' => '_',
            'source_value' => $this->sourceValue,
            'key' => $this->key,
            'predicate' => $this->predicate,
        ];
    }
}

final class ForIfLowering
{
    /**
     * Parse the canonical destructuring form.
     *
     * Examples:
     *   _, no1 = forif ($value in $values if no1 < _)
     *   _, x, y = revif ($rows as $row if y >= _)
     */
    public static function parse(string $line): ForIfPlan
    {
        $line=trim(rtrim($line,';'));
        if(!preg_match('/^(.*?)\s*=\s*(forif|revif)\s*\((.*)\)$/is',$line,$m))
            throw new InvalidArgumentException('Expected: _, ... = forif|revif (...)');

        $targets=self::targets($m[1]);
        if($targets[0]!=='_')throw new InvalidArgumentException('Position zero must be _');

        $direction=strtolower($m[2]);
        [$iter,$predicate]=self::splitPredicate(trim($m[3]));
        [$collection,$key,$sourceValue]=self::parseIterator($iter);

        return new ForIfPlan($direction,$collection,$targets,$sourceValue,$key,$predicate);
    }

    /** @return list<string> */
    private static function targets(string $lhs): array
    {
        $out=[];
        foreach(explode(',',$lhs) as $raw){
            $name=ltrim(trim($raw),'$');
            if(!preg_match('/^[A-Za-z_]\w*$/',$name))throw new InvalidArgumentException("Bad forif target {$name}");
            $name=strtolower($name);
            if(in_array($name,$out,true))throw new InvalidArgumentException("Duplicate forif target {$name}");
            $out[]=$name;
        }
        if(count($out)<1)throw new InvalidArgumentException('forif needs at least _');
        return $out;
    }

    /** @return array{0:string,1:string} */
    private static function splitPredicate(string $header): array
    {
        if(!preg_match('/^(.*?)\s+if\s+(.+)$/is',$header,$m))
            throw new InvalidArgumentException('forif/revif requires inline if condition');
        $iter=trim($m[1]);$predicate=trim($m[2]);
        if($iter===''||$predicate==='')throw new InvalidArgumentException('Incomplete forif/revif header');
        return[$iter,$predicate];
    }

    /** @return array{0:string,1:?string,2:?string} */
    private static function parseIterator(string $iter): array
    {
        if(preg_match('/^\$?([A-Za-z_]\w*)\s+in\s+\$?([A-Za-z_]\w*)$/i',$iter,$m))
            return[strtolower($m[2]),null,strtolower($m[1])];

        if(preg_match('/^\$?([A-Za-z_]\w*)\s+as\s+(?:(?:\$?([A-Za-z_]\w*))\s*=>\s*)?\$?([A-Za-z_]\w*)$/i',$iter,$m))
            return[strtolower($m[1]),isset($m[2])&&$m[2]!==''?strtolower($m[2]):null,strtolower($m[3])];

        throw new InvalidArgumentException('Expected $value in $collection or $collection as [$key =>] $value');
    }
}
