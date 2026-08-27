<?php declare(strict_types=1);

namespace jx;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use OutOfBoundsException;
use Traversable;
use UnderflowException;

if (!class_exists(Bag::class, false)) {
    require_once __DIR__ . '/jx.php';
}

/**
 * Canonical Bag container disciplines.
 *
 * A container is not a second memory system. It is a Bag plus an access law.
 * Hot operations stay in target-native state; checkpoint() is the explicit
 * canonical boundary that commits a snapshot through the Bag handshake.
 */
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
    protected int $checkpointRevision = -1;

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
        if ($this->checkpointRevision === $this->revision) return $this;
        $ref = $this->owner->sign($node);
        try {
            $this->owner->set($this->canonical(), $node)->commit($ref);
            $this->checkpointRevision = $this->revision;
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
        $this->checkpointRevision = $this->revision;
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
    /**
     * Canonical fallback for BEMPLACE. The PHP host uses one array_splice call;
     * native lowering computes one address and performs one overlap-safe tail move.
     */
    public function emplace(int $index, mixed $value): static
    {
        $n = count($this->values);
        if ($index < 0 || $index > $n) throw new OutOfBoundsException("VectorBag emplace index {$index}");
        array_splice($this->values, $index, 0, [$value]);
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

class MapBag extends BagContainer
{
    protected array $values=[];
    public function __construct(Bag $bag, ?string $elementType=null, string $kind=BagDiscipline::MAP){parent::__construct($bag,$kind,$elementType);}
    public function count(): int{return count($this->values);}
    public function put(string|int $key,mixed $value): static{$this->values[$key]=$value;$this->changed();return $this;}
    /** Insert only when absent; return the existing or newly inserted value. */
    public function emplace(string|int $key, mixed $value): mixed
    {
        if (array_key_exists($key, $this->values)) return $this->values[$key];
        $this->values[$key] = $value;
        $this->changed();
        return $value;
    }
    public function get(string|int $key,mixed $default=null): mixed{return $this->values[$key]??$default;}
    public function has(string|int $key): bool{return array_key_exists($key,$this->values);}
    public function remove(string|int $key): bool{if(!array_key_exists($key,$this->values))return false;unset($this->values[$key]);$this->changed();return true;}
    public function clear(): static{if($this->values!==[]){$this->values=[];$this->changed();}return $this;}
    public function toArray(): array{return $this->values;}
    public function nativeLayout(): array{return ['strategy'=>'native-hash','emplace'=>'probe once; insert if absent','element_type'=>$this->elementType];}
    protected function exportPayload(): array{return ['values'=>$this->values];}
    protected function importPayload(array $payload): void{$this->values=$payload['values']??[];}
}

final class SetBag extends MapBag
{
    public function __construct(Bag $bag, ?string $elementType=null){parent::__construct($bag,$elementType,BagDiscipline::SET);}
    private function keyOf(mixed $value): string
    {
        return match(true){
            is_int($value)=>'i:'.$value,
            is_string($value)=>'s:'.$value,
            is_float($value)=>'f:'.sprintf('%.17g',$value),
            is_bool($value)=>'b:'.($value?'1':'0'),
            $value===null=>'n:',
            default=>'x:'.hash('xxh3',serialize($value)),
        };
    }
    /** Insert only when absent; return the existing or newly inserted value. */
    public function emplaceValue(mixed $value): mixed
    {
        $k=$this->keyOf($value);
        if(array_key_exists($k,$this->values)) return $this->values[$k];
        $this->values[$k]=$value;$this->changed();return $value;
    }
    public function add(mixed $value): static{$this->emplaceValue($value);return $this;}
    public function contains(mixed $value): bool{return array_key_exists($this->keyOf($value),$this->values);}
    public function discard(mixed $value): bool{$k=$this->keyOf($value);if(!array_key_exists($k,$this->values))return false;unset($this->values[$k]);$this->changed();return true;}
    public function toArray(): array{return array_values($this->values);}
    public function nativeLayout(): array{return ['strategy'=>'native-hash-set','emplace'=>'probe once; insert key if absent','element_type'=>$this->elementType];}
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
