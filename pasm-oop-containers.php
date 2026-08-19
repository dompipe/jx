<?php declare(strict_types=1);

namespace pasm;

require_once __DIR__ . '/pasm-double-digit.php';
require_once __DIR__ . '/pasm-canonical-segmented.php';

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use OutOfBoundsException;
use RuntimeException;
use Traversable;
use UnderflowException;
use UnexpectedValueException;
use WeakReference;

/**
 * PASM OOP containers, hot-path optimized.
 *
 * Design rule: normal container methods operate only on frame-local PHP state.
 * Canonical segmented storage is a write-back checkpoint image and is touched
 * only at explicit boundaries (dirtySegments/clearDirty/defrag/register export,
 * remote sync, persistence). This keeps PASM frame isolation without making
 * every push/get/pop pay the segment/codec tax.
 */
interface PASMContainerContract extends Countable, IteratorAggregate, JsonSerializable
{
    public function clear(): static;
    public function isEmpty(): bool;
    public function toArray(): array;
    public function frame(): PASMRegisterFrame;
    public function containerId(): int;
    public function segmentIds(): array;
    public function dirtySegments(): array;
    public function clearDirty(): static;
    public function flush(): static;
    public function loadRegister(string $register = 'P0'): static;
    public function fromRegister(string $register = 'P0'): static;
    public function loadPasmStack(bool $replace = false): static;
    public function fromPasmStack(): static;
}

/** Mixed values referenced by integer PASM cells only when a checkpoint is built. */
final class PASMFrameValuePool implements JsonSerializable
{
    private array $values = [];
    private array $refs = [];
    private array $dirty = [];
    private int $next = 1;

    public function store(mixed $value): int
    {
        $id = $this->next++;
        $this->values[$id] = $value;
        $this->refs[$id] = 1;
        $this->dirty[$id] = true;
        return $id;
    }
    public function get(int $id): mixed
    {
        if (!array_key_exists($id, $this->values)) throw new RuntimeException("Unknown PASM pooled value {$id}");
        return $this->values[$id];
    }
    public function retain(int $id): void { if (isset($this->refs[$id])) $this->refs[$id]++; }
    public function release(int $id): void
    {
        if (!isset($this->refs[$id])) return;
        if (--$this->refs[$id] <= 0) unset($this->refs[$id], $this->values[$id], $this->dirty[$id]);
    }
    public function dirtyValues(): array
    {
        $out = [];
        foreach ($this->dirty as $id => $_) if (array_key_exists($id, $this->values)) $out[$id] = $this->values[$id];
        return $out;
    }
    public function clearDirty(): void { $this->dirty = []; }
    public function import(int $id, mixed $value, int $refs = 1): void
    {
        if ($id < 1) throw new InvalidArgumentException('Pool id must be positive');
        $this->values[$id] = $value;
        $this->refs[$id] = max(1, $refs);
        $this->next = max($this->next, $id + 1);
    }
    public function jsonSerialize(): array { return ['next'=>$this->next,'values'=>$this->values,'refs'=>$this->refs]; }
}

/** Cell codec is deliberately used only by PASMContainerSnapshot::sync(). */
final class PASMCellCodec
{
    private const TAG_MASK=0x3, TAG_POOL=0x0, TAG_INT=0x1, TAG_BOOL=0x2, TAG_NULL=0x3;
    private const MIN_INLINE=-2305843009213693952;
    private const MAX_INLINE= 2305843009213693951;
    public function __construct(public readonly PASMFrameValuePool $pool) {}
    public function encode(mixed $value): int
    {
        if ($value === null) return self::TAG_NULL;
        if (is_bool($value)) return (($value ? 1 : 0) << 2) | self::TAG_BOOL;
        if (is_int($value) && $value >= self::MIN_INLINE && $value <= self::MAX_INLINE) return ($value << 2) | self::TAG_INT;
        return $this->pool->store($value) << 2;
    }
    public function decode(int $cell): mixed
    {
        if ($cell === 0) return null;
        return match($cell & self::TAG_MASK) {
            self::TAG_INT => $cell >> 2,
            self::TAG_BOOL => (($cell >> 2) & 1) === 1,
            self::TAG_NULL => null,
            default => $this->pool->get($cell >> 2),
        };
    }
    public function release(int $cell): void
    {
        if ($cell !== 0 && ($cell & self::TAG_MASK) === self::TAG_POOL) $this->pool->release($cell >> 2);
    }
}

final class PASMContainerContext
{
    private static ?PASMFramePool $frames = null;
    private static ?PASMSegmentRegistry $segments = null;
    private static array $pools = [];
    private static ?PASMRegisterFrame $defaultFrame = null;
    public static function frame(): PASMRegisterFrame
    {
        self::$frames ??= new PASMFramePool();
        return self::$defaultFrame ??= self::$frames->create('oop-default');
    }
    public static function segments(): PASMSegmentRegistry { return self::$segments ??= new PASMSegmentRegistry(); }
    public static function pool(PASMRegisterFrame $frame): PASMFrameValuePool { return self::$pools[$frame->id] ??= new PASMFrameValuePool(); }
}

final class PASMContainerDirectory
{
    private static array $next=[];
    private static array $objects=[];
    public static function register(PASMRegisterFrame $frame, object $container): int
    {
        $id=self::$next[$frame->id]??1;
        self::$next[$frame->id]=$id+1;
        self::$objects[$frame->id][$id]=WeakReference::create($container);
        return $id;
    }
    public static function get(PASMRegisterFrame $frame,int $id): ?object
    {
        $ref=self::$objects[$frame->id][$id]??null;
        if(!$ref)return null;
        $obj=$ref->get();
        if(!$obj)unset(self::$objects[$frame->id][$id]);
        return $obj;
    }
}

/**
 * One canonical segment per container. Hot values live outside it; sync builds
 * a compact logical checkpoint image and imports only pages whose encoded image
 * differs from the previous checkpoint.
 */
final class PASMContainerSnapshot
{
    private int $segment;
    private int $capacity;
    private array $values=[]; // previous logical mixed image
    private array $cells=[];  // previous encoded image

    public function __construct(
        private readonly PASMSegmentArena $arena,
        private readonly PASMCellCodec $codec,
        int $capacity=32,
        private readonly string $name='container.snapshot',
    ) {
        $this->capacity=max(32,$capacity);
        $this->segment=$arena->allocate($this->capacity,$name);
        $arena->clearDirty($this->segment);
    }

    public function segmentId(): int { return $this->segment; }

    private function grow(int $required): void
    {
        if($required <= $this->capacity)return;
        $newCap=$this->capacity;
        while($newCap < $required)$newCap <<= 1;
        $old=$this->segment;
        $this->segment=$this->arena->allocate($newCap,$this->name.'.grow');
        $this->arena->clearDirty($this->segment);
        $this->capacity=$newCap;
        $this->arena->free($old);
        // New physical backing is zero: import every page containing the current image.
        $pageSize=$this->arena->pageSize;
        $last=$this->cells===[]?-1:intdiv(max(array_keys($this->cells)),$pageSize);
        for($p=0;$p<=$last;$p++)$this->importPage($p);
    }

    public function sync(array $flat): void
    {
        $n=count($flat);
        $this->grow(max(1,$n));
        $oldN=count($this->values);
        $max=max($oldN,$n);
        $pages=[];
        $pageSize=$this->arena->pageSize;

        for($i=0;$i<$max;$i++){
            $newExists=$i<$n;
            $oldExists=$i<$oldN;
            if($newExists && $oldExists && $flat[$i] === $this->values[$i]) continue;

            if($oldExists){
                $oldCell=$this->cells[$i]??0;
                if($oldCell!==0)$this->codec->release($oldCell);
            }
            if($newExists){
                $this->values[$i]=$flat[$i];
                $cell=$this->codec->encode($flat[$i]);
                if($cell===0)unset($this->cells[$i]); else $this->cells[$i]=$cell;
            }else{
                unset($this->values[$i],$this->cells[$i]);
            }
            $pages[intdiv($i,$pageSize)]=true;
        }
        if($n < $oldN) $this->values=array_values($this->values);
        foreach(array_keys($pages) as $page)$this->importPage((int)$page);
    }

    private function importPage(int $page): void
    {
        $pageSize=$this->arena->pageSize;
        $base=$page*$pageSize;
        if($base >= $this->capacity)return;
        $vals=[];
        for($i=0;$i<$pageSize;$i++){
            $idx=$base+$i;
            $vals[]=$idx<$this->capacity?($this->cells[$idx]??0):0;
        }
        $this->arena->importPage($this->segment,$page,$vals,true);
    }

    public function clearDirty(): void { $this->arena->clearDirty($this->segment); }
    public function dirtyPages(): array { return $this->arena->dirtyPages($this->segment); }
    public function releaseAndFree(): void
    {
        foreach($this->cells as $cell)$this->codec->release((int)$cell);
        $this->values=[];$this->cells=[];
        $this->arena->free($this->segment);
    }
}

abstract class PASMContainer implements PASMContainerContract
{
    protected readonly PASMRegisterFrame $frameRef;
    protected readonly PASMSegmentRegistry $segmentRegistry;
    protected readonly PASMSegmentArena $arena;
    protected readonly PASMFrameValuePool $valuePool;
    protected readonly PASMCellCodec $codec;
    protected readonly int $id;
    private PASMContainerSnapshot $snapshot;
    protected bool $changed=false;

    public function __construct(iterable $items=[],?PASMRegisterFrame $frame=null,?PASMSegmentRegistry $segments=null,?PASMFrameValuePool $pool=null)
    {
        $this->frameRef=$frame??PASMContainerContext::frame();
        $this->segmentRegistry=$segments??PASMContainerContext::segments();
        $this->arena=$this->segmentRegistry->forFrame($this->frameRef);
        $this->valuePool=$pool??PASMContainerContext::pool($this->frameRef);
        $this->codec=new PASMCellCodec($this->valuePool);
        $this->id=PASMContainerDirectory::register($this->frameRef,$this);
        foreach($items as $key=>$value)$this->import($key,$value);
        $initial=$this->snapshotData();
        $this->snapshot=new PASMContainerSnapshot($this->arena,$this->codec,max(32,count($initial)),"f{$this->frameRef->id}.container{$this->id}");
        $this->snapshot->sync($initial);
        $this->snapshot->clearDirty();
        $this->valuePool->clearDirty();
        $this->changed=false;
    }

    public function __destruct(){ try{$this->snapshot->releaseAndFree();}catch(\Throwable){} }
    protected function touch(): void { $this->changed=true; }
    abstract protected function import(mixed $key,mixed $value): void;
    abstract protected function snapshotData(): array;

    public static function forFrame(PASMRegisterFrame $frame,PASMSegmentRegistry $segments,iterable $items=[],?PASMFrameValuePool $pool=null): static
    { return new static($items,$frame,$segments,$pool); }
    public function frame(): PASMRegisterFrame{return $this->frameRef;}
    public function containerId(): int{return $this->id;}
    public function segmentIds(): array{return[$this->snapshot->segmentId()];}
    public function isEmpty(): bool{return $this->count()===0;}
    public function getIterator(): Traversable{return new ArrayIterator($this->toArray());}
    public function jsonSerialize(): mixed{return $this->toArray();}

    public function flush(): static
    {
        if($this->changed){$this->snapshot->sync($this->snapshotData());$this->changed=false;}
        return $this;
    }
    public function dirtySegments(): array
    {
        $this->flush();$pages=$this->snapshot->dirtyPages();return $pages===[]?[]:[$this->snapshot->segmentId()=>$pages];
    }
    public function clearDirty(): static
    {
        $this->flush();$this->snapshot->clearDirty();$this->valuePool->clearDirty();return $this;
    }
    public function dirtyValuePool(): array{return $this->valuePool->dirtyValues();}
    public function defrag(): array{$this->flush();return $this->arena->defrag();}

    public function loadRegister(string $register='P0'): static
    {
        if(preg_match('/^[RTPM]\d+$/i',$register)){$this->flush();$this->frameRef->set($register,$this->id);return $this;}
        if(class_exists(PASM::class)&&property_exists(PASM::class,$register)){PASM::${$register}=$this->toArray();return $this;}
        throw new OutOfBoundsException("Unknown PASM register {$register}");
    }
    public function fromRegister(string $register='P0'): static
    {
        if(preg_match('/^[RTPM]\d+$/i',$register)){
            $other=PASMContainerDirectory::get($this->frameRef,$this->frameRef->get($register));
            if(!$other instanceof PASMContainerContract)throw new UnexpectedValueException("Canonical register {$register} does not reference a container in this frame");
            return $this->replaceFromIterable($other->toArray());
        }
        if(class_exists(PASM::class)&&property_exists(PASM::class,$register)){
            $value=PASM::${$register};if(!is_iterable($value))throw new UnexpectedValueException("PASM register {$register} is not iterable");
            return $this->replaceFromIterable($value);
        }
        throw new OutOfBoundsException("Unknown PASM register {$register}");
    }
    public function loadCountRegister(string $register='R0'): static{$this->frameRef->set($register,$this->count());return $this;}
    public function loadPasmStack(bool $replace=false): static
    {
        if(!class_exists(PASM::class))return $this;
        if($replace)PASM::$stack=[];
        foreach($this->toArray() as $value)PASM::$stack[]=$value;
        PASM::$ST0=PASM::$stack===[]?null:PASM::$stack[array_key_last(PASM::$stack)];PASM::$sp=PASM::$ST0;return $this;
    }
    public function fromPasmStack(): static
    {
        if(!class_exists(PASM::class))throw new RuntimeException('Legacy PASM stack is unavailable');
        return $this->replaceFromIterable(PASM::$stack);
    }
    protected function replaceFromIterable(iterable $items): static
    {
        $this->clear();foreach($items as $key=>$value)$this->import($key,$value);return $this;
    }
}

/** Ordered indexed sequence. Hot path is a plain PHP packed array. */
class PASMList extends PASMContainer implements ArrayAccess
{
    protected array $items=[];
    protected function import(mixed $key,mixed $value): void{$this->items[]=$value;$this->changed=true;}
    protected function snapshotData(): array{return array_merge([count($this->items)],$this->items);}
    public function count(): int{return count($this->items);}
    public function add(mixed $value): static{$this->items[]=$value;$this->changed=true;return $this;}
    public function insert(int $index,mixed $value): static
    {
        $n=count($this->items);if($index<0||$index>$n)throw new OutOfBoundsException("Index {$index} out of bounds");
        array_splice($this->items,$index,0,[$value]);$this->changed=true;return $this;
    }
    public function get(int $index): mixed
    { if(!array_key_exists($index,$this->items))throw new OutOfBoundsException("Index {$index} out of bounds");return $this->items[$index]; }
    public function set(int $index,mixed $value): static
    { if(!array_key_exists($index,$this->items))throw new OutOfBoundsException("Index {$index} out of bounds");$this->items[$index]=$value;$this->changed=true;return $this; }
    public function removeAt(int $index): mixed
    { if(!array_key_exists($index,$this->items))throw new OutOfBoundsException("Index {$index} out of bounds");$v=array_splice($this->items,$index,1)[0];$this->changed=true;return $v; }
    public function first(): mixed{if($this->items===[])throw new UnderflowException('List is empty');return $this->items[0];}
    public function last(): mixed{if($this->items===[])throw new UnderflowException('List is empty');return $this->items[array_key_last($this->items)];}
    public function contains(mixed $value,bool $strict=true): bool{return in_array($value,$this->items,$strict);}
    public function clear(): static{$this->items=[];$this->changed=true;return $this;}
    public function toArray(): array{return $this->items;}
    public function offsetExists(mixed $offset): bool{return array_key_exists((int)$offset,$this->items);}
    public function offsetGet(mixed $offset): mixed{return $this->get((int)$offset);}
    public function offsetSet(mixed $offset,mixed $value): void
    { if($offset===null||(int)$offset===count($this->items)){$this->add($value);return;}$this->set((int)$offset,$value); }
    public function offsetUnset(mixed $offset): void{$this->removeAt((int)$offset);}
}

class PASMStack extends PASMList
{
    public function push(mixed $value): static{$this->items[]=$value;$this->changed=true;return $this;}
    public function pop(): mixed
    { if($this->items===[])throw new UnderflowException('Stack is empty');$this->changed=true;return array_pop($this->items); }
    public function peek(): mixed{return $this->last();}
}

/** FIFO queue optimized for its normal direction; periodic compaction only. */
class PASMQueue extends PASMContainer
{
    protected array $items=[];
    protected int $head=0;
    protected function import(mixed $key,mixed $value): void{$this->items[]=$value;$this->changed=true;}
    protected function snapshotData(): array{return array_merge([$this->count()],$this->toArray());}
    public function count(): int{return count($this->items)-$this->head;}
    public function isEmpty(): bool{return $this->head>=count($this->items);}
    public function enqueue(mixed $value): static{$this->items[]=$value;$this->changed=true;return $this;}
    public function dequeue(): mixed
    {
        if($this->isEmpty())throw new UnderflowException('Queue is empty');
        $value=$this->items[$this->head++];$this->changed=true;
        if($this->head>=1024 && $this->head*2>=count($this->items)){$this->items=array_slice($this->items,$this->head);$this->head=0;}
        return $value;
    }
    public function peek(): mixed{if($this->isEmpty())throw new UnderflowException('Queue is empty');return $this->items[$this->head];}
    public function clear(): static{$this->items=[];$this->head=0;$this->changed=true;return $this;}
    public function toArray(): array{return $this->head===0?$this->items:array_slice($this->items,$this->head);}
}

/** True circular deque; power-of-two capacity makes end operations mask-based O(1). */
class PASMDeque extends PASMContainer
{
    private array $ring=[];
    private int $capacity=32;
    private int $mask=31;
    private int $head=0;
    private int $size=0;
    protected function import(mixed $key,mixed $value): void{$this->pushBack($value);}
    protected function snapshotData(): array{return array_merge([$this->size],$this->toArray());}
    public function count(): int{return $this->size;}
    public function isEmpty(): bool{return $this->size===0;}
    private function grow(): void
    {
        $old=$this->ring;$oldHead=$this->head;$oldMask=$this->mask;$new=[];
        for($i=0;$i<$this->size;$i++)$new[$i]=$old[($oldHead+$i)&$oldMask];
        $this->capacity<<=1;$this->mask=$this->capacity-1;$this->ring=$new;$this->head=0;
    }
    public function pushBack(mixed $value): static
    { if($this->size===$this->capacity)$this->grow();$this->ring[($this->head+$this->size)&$this->mask]=$value;$this->size++;$this->changed=true;return $this; }
    public function pushFront(mixed $value): static
    { if($this->size===$this->capacity)$this->grow();$this->head=($this->head-1)&$this->mask;$this->ring[$this->head]=$value;$this->size++;$this->changed=true;return $this; }
    public function popFront(): mixed
    { if(!$this->size)throw new UnderflowException('Deque is empty');$slot=$this->head;$v=$this->ring[$slot];unset($this->ring[$slot]);$this->head=($this->head+1)&$this->mask;$this->size--;if(!$this->size)$this->head=0;$this->changed=true;return $v; }
    public function popBack(): mixed
    { if(!$this->size)throw new UnderflowException('Deque is empty');$slot=($this->head+$this->size-1)&$this->mask;$v=$this->ring[$slot];unset($this->ring[$slot]);$this->size--;if(!$this->size)$this->head=0;$this->changed=true;return $v; }
    public function peekFront(): mixed{if(!$this->size)throw new UnderflowException('Deque is empty');return $this->ring[$this->head];}
    public function peekBack(): mixed{if(!$this->size)throw new UnderflowException('Deque is empty');return $this->ring[($this->head+$this->size-1)&$this->mask];}
    public function enqueue(mixed $value): static{return $this->pushBack($value);}
    public function dequeue(): mixed{return $this->popFront();}
    public function peek(): mixed{return $this->peekFront();}
    public function clear(): static{$this->ring=[];$this->head=0;$this->size=0;$this->changed=true;return $this;}
    public function toArray(): array{$out=[];for($i=0;$i<$this->size;$i++)$out[]=$this->ring[($this->head+$i)&$this->mask];return $out;}
}

class PASMMap extends PASMContainer implements ArrayAccess
{
    private array $items=[];
    protected function import(mixed $key,mixed $value): void
    { if(!is_int($key)&&!is_string($key))throw new InvalidArgumentException('PASMMap keys must be int|string');$this->items[$key]=$value;$this->changed=true; }
    protected function snapshotData(): array
    {
        $out=[count($this->items)];foreach($this->items as $k=>$v){$out[]=$k;$out[]=$v;}return $out;
    }
    public function count(): int{return count($this->items);}
    public function put(int|string $key,mixed $value): static{$this->items[$key]=$value;$this->changed=true;return $this;}
    public function get(int|string $key,mixed $default=null): mixed{return array_key_exists($key,$this->items)?$this->items[$key]:$default;}
    public function has(int|string $key): bool{return array_key_exists($key,$this->items);}
    public function remove(int|string $key): mixed
    { if(!array_key_exists($key,$this->items))return null;$v=$this->items[$key];unset($this->items[$key]);$this->changed=true;return $v; }
    public function keys(): array{return array_keys($this->items);}
    public function values(): array{return array_values($this->items);}
    public function clear(): static{$this->items=[];$this->changed=true;return $this;}
    public function toArray(): array{return $this->items;}
    public function offsetExists(mixed $offset): bool{return (is_int($offset)||is_string($offset))&&array_key_exists($offset,$this->items);}
    public function offsetGet(mixed $offset): mixed{return (is_int($offset)||is_string($offset))?$this->get($offset):null;}
    public function offsetSet(mixed $offset,mixed $value): void{if(!is_int($offset)&&!is_string($offset))throw new InvalidArgumentException('PASMMap requires an explicit int|string key');$this->put($offset,$value);}
    public function offsetUnset(mixed $offset): void{if(is_int($offset)||is_string($offset))$this->remove($offset);}
}

class PASMSet extends PASMContainer
{
    private array $items=[];
    private array $index=[];
    protected function import(mixed $key,mixed $value): void{$this->add($value);}
    protected function snapshotData(): array{return array_merge([count($this->items)],$this->items);}
    private static function signature(mixed $value): string
    {
        return match(true){
            is_int($value)=>'i'.$value,
            is_string($value)=>'s'.$value,
            is_bool($value)=>$value?'b1':'b0',
            $value===null=>'n',
            is_float($value)=>'f'.pack('d',$value),
            is_object($value)=>'o'.spl_object_id($value),
            is_resource($value)=>'r'.get_resource_id($value),
            default=>'x'.serialize($value),
        };
    }
    public function count(): int{return count($this->index);}
    public function add(mixed $value): static
    {
        $sig=self::signature($value);if(isset($this->index[$sig]))return $this;
        $this->index[$sig]=count($this->items);$this->items[]=$value;$this->changed=true;return $this;
    }
    public function has(mixed $value): bool{return isset($this->index[self::signature($value)]);}
    public function remove(mixed $value): bool
    {
        $sig=self::signature($value);if(!isset($this->index[$sig]))return false;
        $idx=$this->index[$sig];array_splice($this->items,$idx,1);unset($this->index[$sig]);
        // Removal is colder than has/add; rebuild indexes only after the removed slot.
        for($i=$idx,$n=count($this->items);$i<$n;$i++)$this->index[self::signature($this->items[$i])]=$i;
        $this->changed=true;return true;
    }
    public function clear(): static{$this->items=[];$this->index=[];$this->changed=true;return $this;}
    public function toArray(): array{return $this->items;}
    public function union(PASMSet $other): PASMSet{$r=new PASMSet($this->items,$this->frameRef,$this->segmentRegistry,$this->valuePool);foreach($other as $v)$r->add($v);return $r;}
    public function intersect(PASMSet $other): PASMSet{$r=new PASMSet([],$this->frameRef,$this->segmentRegistry,$this->valuePool);foreach($this->items as $v)if($other->has($v))$r->add($v);return $r;}
    public function difference(PASMSet $other): PASMSet{$r=new PASMSet([],$this->frameRef,$this->segmentRegistry,$this->valuePool);foreach($this->items as $v)if(!$other->has($v))$r->add($v);return $r;}
}

class Vector extends PASMList {}
class Stack extends PASMStack {}
class Queue extends PASMQueue {}
class Deque extends PASMDeque {}
class Map extends PASMMap {}
class Set extends PASMSet {}
