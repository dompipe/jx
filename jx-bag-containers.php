<?php declare(strict_types=1);

namespace jx;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use LogicException;
use OutOfBoundsException;
use Traversable;
use UnderflowException;

if (!class_exists(Bag::class, false)) {
    require_once __DIR__ . '/jx.php';
}

final class BagDiscipline
{
    public const RECORD = 'record';
    public const VECTOR = 'vector';
    public const STACK = 'stack';
    public const QUEUE = 'queue';
    public const DEQUE = 'deque';
    public const MAP = 'map';
    public const SET = 'set';

    public static function all(): array
    {
        return [self::RECORD, self::VECTOR, self::STACK, self::QUEUE, self::DEQUE, self::MAP, self::SET];
    }
}

interface BagContainerContract extends Countable, IteratorAggregate, JsonSerializable
{
    public function bag(): Bag;
    public function discipline(): string;
    public function checkpoint(string $node = '_container'): static;
    public function restore(string $node = '_container'): static;
    public function clear(): static;
    public function isEmpty(): bool;
    public function toArray(): array;
    public function canonical(): array;
}

abstract class BagContainer implements BagContainerContract
{
    protected int $revision = 0;
    /** @var array<string,int> */
    protected array $checkpointRevisions = [];

    public function __construct(
        protected readonly Bag $owner,
        protected readonly string $kind,
        protected readonly ?string $elementType = null,
    ) {
        if (!in_array($kind, BagDiscipline::all(), true)) {
            throw new JxException("Unknown Bag discipline: {$kind}", 'bag.container');
        }
    }

    public function bag(): Bag { return $this->owner; }
    public function discipline(): string { return $this->kind; }
    public function isEmpty(): bool { return $this->count() === 0; }
    public function getIterator(): Traversable { return new ArrayIterator($this->toArray()); }
    public function jsonSerialize(): array { return $this->canonical(); }
    final protected function changed(): void { $this->revision++; }

    final public function checkpoint(string $node = '_container'): static
    {
        if (($this->checkpointRevisions[$node] ?? -1) === $this->revision) return $this;
        $ref = $this->owner->sign($node);
        try {
            $this->owner->set($this->canonical(), $node)->commit($ref);
            $this->checkpointRevisions[$node] = $this->revision;
        } finally {
            $this->owner->unsign($ref);
        }
        return $this;
    }

    final public function restore(string $node = '_container'): static
    {
        $snapshot = $this->owner->peek($node);
        if (!is_array($snapshot)) return $this;
        if (($snapshot['discipline'] ?? null) !== $this->kind) {
            throw new JxException('Bag checkpoint discipline mismatch', 'bag.container', true);
        }
        $this->importPayload($snapshot['payload'] ?? []);
        $this->revision = (int)($snapshot['revision'] ?? 0);
        $this->checkpointRevisions[$node] = $this->revision;
        return $this;
    }

    final public function canonical(): array
    {
        return [
            'abi' => 'jx.bag.container/1',
            'bag' => $this->owner->id(),
            'discipline' => $this->kind,
            'element_type' => $this->elementType,
            'revision' => $this->revision,
            'count' => $this->count(),
            'layout' => $this->nativeLayout(),
            'payload' => $this->exportPayload(),
        ];
    }

    abstract public function nativeLayout(): array;
    abstract protected function exportPayload(): array;
    abstract protected function importPayload(array $payload): void;
}

final class RecordBag extends BagContainer
{
    private array $slots = [];
    private array $values = [];
    private array $types = [];

    public function __construct(Bag $bag, array $schema)
    {
        parent::__construct($bag, BagDiscipline::RECORD, null);
        $i = 0;
        foreach ($schema as $name => $spec) {
            $this->slots[(string)$name] = $i++;
            if (is_array($spec)) {
                $this->types[] = (string)($spec['type'] ?? 'mixed');
                $this->values[] = $spec['default'] ?? null;
            } else {
                $this->types[] = (string)$spec;
                $this->values[] = null;
            }
        }
    }

    public function count(): int { return count($this->values); }
    public function slot(string $field): int
    {
        if (!array_key_exists($field, $this->slots)) throw new OutOfBoundsException("Unknown RecordBag field {$field}");
        return $this->slots[$field];
    }
    public function get(string|int $field): mixed
    {
        $slot = is_int($field) ? $field : $this->slot($field);
        if (!array_key_exists($slot, $this->values)) throw new OutOfBoundsException("Unknown RecordBag slot {$slot}");
        return $this->values[$slot];
    }
    public function put(string|int $field, mixed $value): static
    {
        $slot = is_int($field) ? $field : $this->slot($field);
        if (!array_key_exists($slot, $this->values)) throw new OutOfBoundsException("Unknown RecordBag slot {$slot}");
        $this->values[$slot] = $value;
        $this->changed();
        return $this;
    }
    public function clear(): static
    {
        foreach ($this->values as $i => $_) $this->values[$i] = null;
        $this->changed();
        return $this;
    }
    public function toArray(): array
    {
        $out = [];
        foreach ($this->slots as $name => $slot) $out[$name] = $this->values[$slot];
        return $out;
    }
    public function nativeLayout(): array
    {
        $fields = [];
        foreach ($this->slots as $name => $slot) {
            $fields[] = ['name'=>$name, 'slot'=>$slot, 'type'=>$this->types[$slot], 'offset_expr'=>"offsetof(record,{$name})"];
        }
        return ['strategy'=>'fixed-record', 'fields'=>$fields];
    }
    protected function exportPayload(): array { return ['values'=>$this->values]; }
    protected function importPayload(array $payload): void { $this->values = array_values($payload['values'] ?? $this->values); }
}

class VectorBag extends BagContainer
{
    protected array $values = [];
    public function __construct(Bag $bag, ?string $elementType = null, string $kind = BagDiscipline::VECTOR)
    {
        parent::__construct($bag, $kind, $elementType);
    }
    public function count(): int { return count($this->values); }
    public function append(mixed $value): static { $this->values[]=$value; $this->changed(); return $this; }
    public function get(int $index): mixed
    {
        if (!array_key_exists($index,$this->values)) throw new OutOfBoundsException("VectorBag index {$index}");
        return $this->values[$index];
    }
    public function put(int $index,mixed $value): static
    {
        if (!array_key_exists($index,$this->values)) throw new OutOfBoundsException("VectorBag index {$index}");
        $this->values[$index]=$value; $this->changed(); return $this;
    }
    public function emplace(int $index, mixed $value): static
    {
        $n = count($this->values);
        if ($index < 0 || $index > $n) throw new OutOfBoundsException("VectorBag emplace index {$index}");
        if ($index === $n) $this->values[] = $value;
        else array_splice($this->values, $index, 0, [$value]);
        $this->changed();
        return $this;
    }
    public function pop(): mixed
    {
        if ($this->values===[]) throw new UnderflowException('VectorBag is empty');
        $this->changed(); return array_pop($this->values);
    }
    public function clear(): static { if($this->values!==[]){$this->values=[];$this->changed();} return $this; }
    public function toArray(): array { return $this->values; }
    public function nativeLayout(): array { return ['strategy'=>'contiguous-vector','index'=>'base + index * element_size','emplace'=>'address + one bulk tail move + store','element_type'=>$this->elementType]; }
    protected function exportPayload(): array { return ['values'=>$this->values]; }
    protected function importPayload(array $payload): void { $this->values=array_values($payload['values']??[]); }
}

final class StackBag extends VectorBag
{
    public function __construct(Bag $bag, ?string $elementType = null) { parent::__construct($bag,$elementType,BagDiscipline::STACK); }
    public function pushValue(mixed $value): static { return $this->append($value); }
    public function top(): mixed
    {
        if ($this->values===[]) throw new UnderflowException('StackBag is empty');
        return $this->values[array_key_last($this->values)];
    }
    public function nativeLayout(): array { return ['strategy'=>'contiguous-stack','push'=>'data[count++]','pop'=>'data[--count]','emplace'=>'address + one bulk tail move + store','element_type'=>$this->elementType]; }
}

class QueueBag extends BagContainer
{
    protected array $ring = [];
    protected int $head = 0;
    protected int $tail = 0;
    protected int $mask;

    public function __construct(Bag $bag, ?string $elementType = null, int $capacity = 16, string $kind = BagDiscipline::QUEUE)
    {
        parent::__construct($bag,$kind,$elementType);
        $cap = 1;
        while ($cap < max(2,$capacity)) $cap <<= 1;
        $this->mask = $cap - 1;
    }
    public function count(): int { return $this->tail-$this->head; }
    protected function grow(): void
    {
        $old=$this->toArray();
        $cap=($this->mask+1)<<1;
        $this->ring=[];$this->head=0;$this->tail=0;$this->mask=$cap-1;
        foreach($old as $v)$this->ring[$this->tail++ & $this->mask]=$v;
    }
    public function enqueue(mixed $value): static
    {
        if($this->count() >= $this->mask+1)$this->grow();
        $this->ring[$this->tail++ & $this->mask]=$value;$this->changed();return $this;
    }
    public function dequeue(): mixed
    {
        if($this->isEmpty())throw new UnderflowException('QueueBag is empty');
        $slot=$this->head++ & $this->mask;$v=$this->ring[$slot];unset($this->ring[$slot]);$this->changed();return $v;
    }
    public function front(): mixed
    {
        if($this->isEmpty())throw new UnderflowException('QueueBag is empty');
        return $this->ring[$this->head & $this->mask];
    }
    public function clear(): static { if(!$this->isEmpty()){$this->ring=[];$this->head=$this->tail=0;$this->changed();}return $this; }
    public function toArray(): array
    {
        $out=[];for($i=$this->head;$i<$this->tail;$i++)$out[]=$this->ring[$i & $this->mask];return $out;
    }
    public function nativeLayout(): array { return ['strategy'=>'power-of-two-ring','slot'=>'index & mask','capacity'=>$this->mask+1,'element_type'=>$this->elementType]; }
    protected function exportPayload(): array { return ['values'=>$this->toArray(),'capacity'=>$this->mask+1]; }
    protected function importPayload(array $payload): void
    {
        $vals=array_values($payload['values']??[]);$cap=1;while($cap<max(2,(int)($payload['capacity']??count($vals))))$cap<<=1;
        $this->ring=[];$this->head=0;$this->tail=0;$this->mask=$cap-1;foreach($vals as $v)$this->ring[$this->tail++ & $this->mask]=$v;
    }
}

final class DequeBag extends QueueBag
{
    public function __construct(Bag $bag, ?string $elementType = null, int $capacity = 16) { parent::__construct($bag,$elementType,$capacity,BagDiscipline::DEQUE); }
    public function pushBack(mixed $v): static { return $this->enqueue($v); }
    public function popFront(): mixed { return $this->dequeue(); }
    public function pushFront(mixed $v): static
    {
        if($this->count() >= $this->mask+1)$this->grow();
        $this->ring[--$this->head & $this->mask]=$v;$this->changed();return $this;
    }
    public function popBack(): mixed
    {
        if($this->isEmpty())throw new UnderflowException('DequeBag is empty');
        $slot=--$this->tail & $this->mask;$v=$this->ring[$slot];unset($this->ring[$slot]);$this->changed();return $v;
    }
    public function back(): mixed
    {
        if($this->isEmpty())throw new UnderflowException('DequeBag is empty');
        return $this->ring[($this->tail-1)&$this->mask];
    }
    public function nativeLayout(): array { return ['strategy'=>'double-ended-power-of-two-ring','slot'=>'index & mask','capacity'=>$this->mask+1,'element_type'=>$this->elementType]; }
}

/**
 * Map is canonically a two-dimensional ordered array. The PHP host mirrors the
 * native law with synchronized keys[] and values[] dimensions; it never uses an
 * associative array as the Map's internal storage.
 */
class MapBag extends BagContainer
{
    /** @var list<string|int> */
    protected array $keys=[];
    /** @var list<mixed> */
    protected array $values=[];
    protected int $cursor=0;

    public function __construct(Bag $bag, ?string $elementType=null, string $kind=BagDiscipline::MAP)
    {
        parent::__construct($bag,$kind,$elementType);
    }

    public function count(): int{return count($this->keys);}

    private static function compareKey(string|int $a,string|int $b): int
    {
        if (is_int($a) && is_int($b)) return $a <=> $b;
        if (is_int($a)) return -1;
        if (is_int($b)) return 1;
        return strcmp($a,$b);
    }

    /** @return array{0:int,1:bool} lower_bound index + found */
    protected function findPosition(string|int $key): array
    {
        $n=count($this->keys);
        if($n===0){$this->cursor=0;return [0,false];}

        if($this->cursor<$n){
            $cmp=self::compareKey($this->keys[$this->cursor],$key);
            if($cmp===0)return [$this->cursor,true];
            if($cmp<0){
                $next=$this->cursor+1;
                if($next>=$n){$this->cursor=$n;return [$n,false];}
                $cmpNext=self::compareKey($this->keys[$next],$key);
                if($cmpNext===0){$this->cursor=$next;return [$next,true];}
                if($cmpNext>0){$this->cursor=$next;return [$next,false];}
            }
        }

        $lo=0;$hi=$n;
        while($lo<$hi){
            $mid=($lo+$hi)>>1;
            if(self::compareKey($this->keys[$mid],$key)<0)$lo=$mid+1;else$hi=$mid;
        }
        $this->cursor=$lo;
        return [$lo,$lo<$n && self::compareKey($this->keys[$lo],$key)===0];
    }

    private function insertAt(int $i, string|int $key, mixed $value): void
    {
        $n=count($this->keys);
        if($i===$n){
            $this->keys[]=$key;
            $this->values[]=$value;
        }else{
            array_splice($this->keys,$i,0,[$key]);
            array_splice($this->values,$i,0,[$value]);
        }
        $this->cursor=$i;
    }

    public function put(string|int $key,mixed $value): static
    {
        [$i,$found]=$this->findPosition($key);
        if($found)$this->values[$i]=$value;
        else $this->insertAt($i,$key,$value);
        $this->changed();
        return $this;
    }

    /** Map BEMPLACE: find key; insert only when absent. */
    public function emplace(mixed ...$args): mixed
    {
        if(count($args)!==2 || (!is_string($args[0]) && !is_int($args[0]))) {
            throw new \InvalidArgumentException('MapBag::emplace expects (string|int key, mixed value)');
        }
        $key=$args[0];$value=$args[1];
        [$i,$found]=$this->findPosition($key);
        if($found)return $this->values[$i];
        $this->insertAt($i,$key,$value);
        $this->changed();
        return $value;
    }

    public function get(string|int $key,mixed $default=null): mixed
    {
        [$i,$found]=$this->findPosition($key);
        return $found?$this->values[$i]:$default;
    }

    public function has(string|int $key): bool
    {
        [, $found]=$this->findPosition($key);
        return $found;
    }

    public function remove(string|int $key): bool
    {
        [$i,$found]=$this->findPosition($key);
        if(!$found)return false;
        array_splice($this->keys,$i,1);
        array_splice($this->values,$i,1);
        $this->cursor=min($i,count($this->keys));
        $this->changed();
        return true;
    }

    public function clear(): static
    {
        if($this->keys!==[]){$this->keys=[];$this->values=[];$this->cursor=0;$this->changed();}
        return $this;
    }

    public function toArray(): array
    {
        $out=[];
        foreach($this->keys as $i=>$key)$out[$key]=$this->values[$i];
        return $out;
    }

    public function nativeLayout(): array
    {
        return [
            'strategy'=>'ordered-2d-array',
            'dimensions'=>['keys[]','values[]'],
            'find'=>'cursor marquee then lower_bound',
            'put'=>'overwrite value at found index; otherwise insert key/value at lower_bound',
            'element_type'=>$this->elementType,
        ];
    }

    protected function exportPayload(): array{return ['keys'=>$this->keys,'values'=>$this->values];}

    protected function importPayload(array $payload): void
    {
        $keys=$payload['keys']??null;$values=$payload['values']??null;
        if(is_array($keys)&&is_array($values)&&count($keys)===count($values)){
            $pairs=[];$valueList=array_values($values);
            foreach(array_values($keys) as $i=>$key){
                if(!is_int($key)&&!is_string($key))continue;
                $pairs[]=['key'=>$key,'value'=>$valueList[$i]??null];
            }
            usort($pairs,static fn(array $a,array $b):int=>self::compareKey($a['key'],$b['key']));
            $this->keys=[];$this->values=[];
            foreach($pairs as $pair){
                $n=count($this->keys);
                if($n>0 && self::compareKey($this->keys[$n-1],$pair['key'])===0)$this->values[$n-1]=$pair['value'];
                else{$this->keys[]=$pair['key'];$this->values[]=$pair['value'];}
            }
        }else{
            // Backward-compatible restore of the old associative payload. The
            // restored live representation is immediately converted to 2D arrays.
            $legacy=is_array($payload['values']??null)?$payload['values']:[];
            $this->keys=[];$this->values=[];
            foreach($legacy as $key=>$value){$this->keys[]=$key;$this->values[]=$value;}
            $order=array_keys($this->keys);
            usort($order,fn(int $a,int $b):int=>self::compareKey($this->keys[$a],$this->keys[$b]));
            $sortedKeys=[];$sortedValues=[];
            foreach($order as $i){$sortedKeys[]=$this->keys[$i];$sortedValues[]=$this->values[$i];}
            $this->keys=$sortedKeys;$this->values=$sortedValues;
        }
        $this->cursor=0;
    }
}

/** Set is the ordered one-dimensional unique-key form of Map's array law. */
final class SetBag extends BagContainer
{
    /** @var list<mixed> */
    private array $values=[];
    private int $cursor=0;

    public function __construct(Bag $bag, ?string $elementType=null)
    {
        parent::__construct($bag,BagDiscipline::SET,$elementType);
    }

    private function keyOf(mixed $value): string
    {
        return match(true){
            is_int($value)=>'i:'.sprintf('%020d',$value),
            is_string($value)=>'s:'.$value,
            is_float($value)=>'f:'.sprintf('%.17g',$value),
            is_bool($value)=>'b:'.($value?'1':'0'),
            $value===null=>'n:',
            default=>'x:'.hash('xxh3',serialize($value)),
        };
    }

    /** @return array{0:int,1:bool} */
    private function findPosition(mixed $value): array
    {
        $needle=$this->keyOf($value);$n=count($this->values);
        if($n===0){$this->cursor=0;return [0,false];}
        if($this->cursor<$n){
            $cur=strcmp($this->keyOf($this->values[$this->cursor]),$needle);
            if($cur===0)return [$this->cursor,true];
            if($cur<0){
                $next=$this->cursor+1;
                if($next>=$n){$this->cursor=$n;return [$n,false];}
                $cmp=strcmp($this->keyOf($this->values[$next]),$needle);
                if($cmp===0){$this->cursor=$next;return [$next,true];}
                if($cmp>0){$this->cursor=$next;return [$next,false];}
            }
        }
        $lo=0;$hi=$n;
        while($lo<$hi){
            $mid=($lo+$hi)>>1;
            if(strcmp($this->keyOf($this->values[$mid]),$needle)<0)$lo=$mid+1;else$hi=$mid;
        }
        $this->cursor=$lo;
        return [$lo,$lo<$n && strcmp($this->keyOf($this->values[$lo]),$needle)===0];
    }

    public function count(): int{return count($this->values);}

    public function put(string|int $key,mixed $value): static
    {
        throw new LogicException('SetBag does not expose map-key put(); use add() or emplace(value)');
    }

    public function remove(string|int $key): bool
    {
        throw new LogicException('SetBag does not expose map-key remove(); use discard(value)');
    }

    /** Set BEMPLACE: find value; drop duplicate or insert at its ordered position. */
    public function emplace(mixed ...$args): mixed
    {
        if(count($args)!==1)throw new \InvalidArgumentException('SetBag::emplace expects (mixed value)');
        $value=$args[0];[$i,$found]=$this->findPosition($value);
        if($found)return $this->values[$i];
        if($i===count($this->values))$this->values[]=$value;
        else array_splice($this->values,$i,0,[$value]);
        $this->cursor=$i;$this->changed();return $value;
    }

    public function add(mixed $value): static{$this->emplace($value);return $this;}
    public function contains(mixed $value): bool{[, $found]=$this->findPosition($value);return $found;}
    public function discard(mixed $value): bool
    {
        [$i,$found]=$this->findPosition($value);if(!$found)return false;
        array_splice($this->values,$i,1);$this->cursor=min($i,count($this->values));$this->changed();return true;
    }
    public function clear(): static{if($this->values!==[]){$this->values=[];$this->cursor=0;$this->changed();}return $this;}
    public function toArray(): array{return $this->values;}
    public function nativeLayout(): array
    {
        return [
            'strategy'=>'ordered-unique-array',
            'dimensions'=>['keys[]'],
            'find'=>'cursor marquee then lower_bound',
            'emplace'=>'drop if found; otherwise insert at lower_bound',
            'element_type'=>$this->elementType,
        ];
    }
    protected function exportPayload(): array{return ['values'=>$this->values];}
    protected function importPayload(array $payload): void
    {
        $input=array_values($payload['values']??[]);$this->values=[];$this->cursor=0;
        foreach($input as $value){
            [$i,$found]=$this->findPosition($value);
            if(!$found){
                if($i===count($this->values))$this->values[]=$value;
                else array_splice($this->values,$i,0,[$value]);
                $this->cursor=$i;
            }
        }
        $this->cursor=0;
    }
}

final class BagContainers
{
    public static function record(int $bytes,array $schema): RecordBag{return new RecordBag(Bag::underwrite($bytes),$schema);}
    public static function vector(int $bytes,?string $type=null): VectorBag{return new VectorBag(Bag::underwrite($bytes),$type);}
    public static function stack(int $bytes,?string $type=null): StackBag{return new StackBag(Bag::underwrite($bytes),$type);}
    public static function queue(int $bytes,?string $type=null,int $capacity=16): QueueBag{return new QueueBag(Bag::underwrite($bytes),$type,$capacity);}
    public static function deque(int $bytes,?string $type=null,int $capacity=16): DequeBag{return new DequeBag(Bag::underwrite($bytes),$type,$capacity);}
    public static function map(int $bytes,?string $type=null): MapBag{return new MapBag(Bag::underwrite($bytes),$type);}
    public static function set(int $bytes,?string $type=null): SetBag{return new SetBag(Bag::underwrite($bytes),$type);}
}
