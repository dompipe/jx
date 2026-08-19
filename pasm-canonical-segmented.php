<?php declare(strict_types=1);
namespace pasm;

require_once __DIR__.'/pasm-canonical.php';

use InvalidArgumentException;
use RuntimeException;

/**
 * Page-aligned segmented store for one canonical frame.
 * Segment IDs are logical/stable. Physical start offsets may move during defrag.
 * This is a logical PASM arena; PHP itself still owns the underlying process memory.
 */
final class PASMSegmentArena implements \JsonSerializable
{
    /** @var array<int,array{id:int,name:string,start:int,length:int,span:int,pages:int,dirty:array<int,true>}> */
    private array $segments=[];
    /** @var array<int,int> Sparse physical cells. Unset == zero. */
    private array $cells=[];
    private int $nextId=1;

    public function __construct(
        public readonly int $pageSize=32,
        public readonly int $maxCells=1048576,
    ) {
        if($pageSize<1 || ($pageSize & ($pageSize-1))!==0) throw new InvalidArgumentException('Segment pageSize must be a power of two');
        if($maxCells<$pageSize) throw new InvalidArgumentException('Segment maxCells is too small');
    }

    public function allocate(int $length,string $name='segment',int $fill=0): int
    {
        if($length<1) throw new InvalidArgumentException('Segment length must be positive');
        $span=$this->align($length);
        $start=$this->findGap($span);
        if($start===null){
            $start=$this->highWater();
            if($start+$span>$this->maxCells){
                if($this->freeCells()>=$span){$this->defrag();$start=$this->highWater();}
                if($start+$span>$this->maxCells) throw new RuntimeException('Segment arena exhausted');
            }
        }
        $id=$this->nextId++;
        $pages=intdiv($span,$this->pageSize);
        $dirty=[];for($p=0;$p<$pages;$p++)$dirty[$p]=true;
        $this->segments[$id]=['id'=>$id,'name'=>$name,'start'=>$start,'length'=>$length,'span'=>$span,'pages'=>$pages,'dirty'=>$dirty];
        if($fill!==0){for($i=0;$i<$length;$i++)$this->cells[$start+$i]=$fill;}
        return $id;
    }

    public function free(int $id): void
    {
        $s=$this->segment($id);for($i=$s['start'];$i<$s['start']+$s['span'];$i++)unset($this->cells[$i]);unset($this->segments[$id]);
    }

    public function read(int $id,int $offset): int
    {
        $s=$this->segment($id);$this->checkOffset($s,$offset);return $this->cells[$s['start']+$offset]??0;
    }

    public function write(int $id,int $offset,int $value,bool $dirty=true): int
    {
        $s=$this->segment($id);$this->checkOffset($s,$offset);$at=$s['start']+$offset;
        if($value===0)unset($this->cells[$at]);else $this->cells[$at]=$value;
        if($dirty)$this->markDirty($id,$offset,1);
        return $value;
    }

    public function fill(int $id,int $offset,int $length,int $value): void
    {
        $s=$this->segment($id);if($length<0||$offset<0||$offset+$length>$s['length'])throw new InvalidArgumentException('Segment fill range out of bounds');
        for($i=0;$i<$length;$i++)$this->write($id,$offset+$i,$value,false);$this->markDirty($id,$offset,$length);
    }

    public function copy(int $dst,int $dstOffset,int $src,int $srcOffset,int $length): void
    {
        if($length<0)throw new InvalidArgumentException('Segment copy length cannot be negative');
        $ds=$this->segment($dst);$ss=$this->segment($src);
        if($dstOffset<0||$dstOffset+$length>$ds['length']||$srcOffset<0||$srcOffset+$length>$ss['length'])throw new InvalidArgumentException('Segment copy range out of bounds');
        $tmp=[];for($i=0;$i<$length;$i++)$tmp[]=$this->read($src,$srcOffset+$i);
        foreach($tmp as $i=>$v)$this->write($dst,$dstOffset+$i,$v,false);$this->markDirty($dst,$dstOffset,$length);
    }

    /** Compact all live segments toward zero. Logical IDs remain unchanged. */
    public function defrag(): array
    {
        $before=$this->stats();$ordered=$this->segments;uasort($ordered,fn($a,$b)=>$a['start']<=>$b['start']);$next=0;$newCells=[];$movedSegments=0;$movedCells=0;
        foreach($ordered as $id=>$s){
            if($s['start']!==$next){$movedSegments++;$movedCells+=$s['span'];}
            for($i=0;$i<$s['length'];$i++){ $v=$this->cells[$s['start']+$i]??0;if($v!==0)$newCells[$next+$i]=$v; }
            $this->segments[$id]['start']=$next;$next+=$s['span'];
        }
        $this->cells=$newCells;$after=$this->stats();
        return ['moved_segments'=>$movedSegments,'moved_cells'=>$movedCells,'before_high_water'=>$before['high_water'],'after_high_water'=>$after['high_water'],'reclaimed_cells'=>$before['high_water']-$after['high_water'],'fragmentation_before'=>$before['fragmentation'],'fragmentation_after'=>$after['fragmentation']];
    }

    public function stats(): array
    {
        $used=0;foreach($this->segments as $s)$used+=$s['span'];$high=$this->highWater();$holes=max(0,$high-$used);
        return ['segments'=>count($this->segments),'page_size'=>$this->pageSize,'used_cells'=>$used,'high_water'=>$high,'fragmented_cells'=>$holes,'free_cells'=>$this->maxCells-$used,'fragmentation'=>$high>0?$holes/$high:0.0];
    }

    public function descriptor(int $id): array { $s=$this->segment($id);unset($s['dirty']);$s['dirty_pages']=array_keys($this->segments[$id]['dirty']);return $s; }

    /** @return array<int,array{page:int,values:array<int,int>}> */
    public function dirtyPages(int $id): array
    {
        $s=$this->segment($id);$out=[];foreach(array_keys($s['dirty']) as $p){$values=[];$base=$p*$this->pageSize;for($i=0;$i<$this->pageSize;$i++){ $off=$base+$i;$values[]=$off<$s['length']?$this->read($id,$off):0; }$out[]=['page'=>$p,'values'=>$values];}return $out;
    }
    public function clearDirty(int $id): void { $this->segment($id);$this->segments[$id]['dirty']=[]; }

    public function importPage(int $id,int $page,array $values,bool $markDirty=false): void
    {
        $s=$this->segment($id);if($page<0||$page>=$s['pages'])throw new InvalidArgumentException('Segment page out of range');if(count($values)!==$this->pageSize)throw new InvalidArgumentException('Segment page has wrong cell count');
        $base=$page*$this->pageSize;foreach($values as $i=>$v){$off=$base+$i;if($off<$s['length'])$this->write($id,$off,(int)$v,false);}if($markDirty)$this->segments[$id]['dirty'][$page]=true;
    }

    public function jsonSerialize(): array { return ['page_size'=>$this->pageSize,'max_cells'=>$this->maxCells,'stats'=>$this->stats(),'segments'=>array_map(fn($s)=>$this->descriptor((int)$s['id']),array_values($this->segments))]; }

    private function segment(int $id): array { return $this->segments[$id]??throw new RuntimeException("Unknown PASM segment {$id}"); }
    private function align(int $n): int { return ($n+$this->pageSize-1)&~($this->pageSize-1); }
    private function checkOffset(array $s,int $offset): void { if($offset<0||$offset>=$s['length'])throw new InvalidArgumentException('Segment offset out of bounds'); }
    private function highWater(): int { $high=0;foreach($this->segments as $s)$high=max($high,$s['start']+$s['span']);return $high; }
    private function freeCells(): int { $used=0;foreach($this->segments as $s)$used+=$s['span'];return $this->maxCells-$used; }
    private function findGap(int $span): ?int
    {
        if(!$this->segments)return 0;$ordered=$this->segments;uasort($ordered,fn($a,$b)=>$a['start']<=>$b['start']);$at=0;foreach($ordered as $s){if($s['start']-$at>=$span)return $at;$at=max($at,$s['start']+$s['span']);}return null;
    }
    private function markDirty(int $id,int $offset,int $length): void
    {
        if($length<=0)return;$first=intdiv($offset,$this->pageSize);$last=intdiv($offset+$length-1,$this->pageSize);for($p=$first;$p<=$last;$p++)$this->segments[$id]['dirty'][$p]=true;
    }
}

/** One segment arena per frame; segment handles are frame-local. */
final class PASMSegmentRegistry
{
    /** @var array<int,PASMSegmentArena> */ private array $arenas=[];
    public function __construct(private readonly int $pageSize=32,private readonly int $maxCells=1048576){}
    public function forFrame(PASMRegisterFrame|int $frame): PASMSegmentArena { $id=$frame instanceof PASMRegisterFrame?$frame->id:$frame;return $this->arenas[$id]??=$this->newArena(); }
    public function dropFrame(int $frame): void { unset($this->arenas[$frame]); }
    private function newArena(): PASMSegmentArena { return new PASMSegmentArena($this->pageSize,$this->maxCells); }
}

/** Canonical interpreter plus frame-owned segmented/page commands. */
final class PASMSegmentedExecutor
{
    public function __construct(public readonly PASMCanonicalTable $table,public readonly PASMSegmentRegistry $segments=new PASMSegmentRegistry()){}
    public function invoke(string $ref,PASMRegisterFrame $f,int $startPc=0,?int $budget=null): array
    {
        $b=$this->table->get($ref);$cmd=$b->commands;$pc=$startPc;$steps=0;$zero=false;$ret=null;$n=count($cmd);$arena=$this->segments->forFrame($f);
        $f->set('M'.PASMCanonicalISA::M_BLOCK,hexdec(substr($b->id,0,7)));$f->set('M'.PASMCanonicalISA::M_STATUS,1);
        while($pc<$n){if($budget!==null&&$steps>=$budget){$f->set('M'.PASMCanonicalISA::M_IP,$pc);$f->set('M'.PASMCanonicalISA::M_STATUS,2);return ['state'=>'yielded','pc'=>$pc,'result'=>$ret,'steps'=>$steps];}
            $ins=(array)$cmd[$pc++];$steps++;$op=strtoupper((string)($ins[0]??''));$val=fn($x)=>is_string($x)&&preg_match('/^[RTPM]\d+$/i',$x)?$f->get($x):(int)$x;
            switch($op){
                case 'MOVI':$f->set((string)$ins[1],(int)$ins[2]);break;case 'MOV':$f->set((string)$ins[1],$f->get((string)$ins[2]));break;
                case 'ADD':$f->set((string)$ins[1],$val($ins[2])+$val($ins[3]));break;case 'SUB':$f->set((string)$ins[1],$val($ins[2])-$val($ins[3]));break;case 'MUL':$f->set((string)$ins[1],$val($ins[2])*$val($ins[3]));break;
                case 'DIV':$d=$val($ins[3]);if($d===0)throw new RuntimeException('Canonical division by zero');$f->set((string)$ins[1],intdiv($val($ins[2]),$d));break;case 'MOD':$d=$val($ins[3]);if($d===0)throw new RuntimeException('Canonical modulo by zero');$f->set((string)$ins[1],$val($ins[2])%$d);break;
                case 'AND':$f->set((string)$ins[1],$val($ins[2])&$val($ins[3]));break;case 'OR':$f->set((string)$ins[1],$val($ins[2])|$val($ins[3]));break;case 'XOR':$f->set((string)$ins[1],$val($ins[2])^$val($ins[3]));break;case 'INC':$f->set((string)$ins[1],$f->get((string)$ins[1])+1);break;case 'DEC':$f->set((string)$ins[1],$f->get((string)$ins[1])-1);break;
                case 'CMP':$zero=$val($ins[1])===$val($ins[2]);$f->set('M'.PASMCanonicalISA::M_FLAGS,$zero?1:0);break;case 'JMP':$pc=(int)$ins[1];break;case 'JZ':if($zero)$pc=(int)$ins[1];break;case 'JNZ':if(!$zero)$pc=(int)$ins[1];break;

                case 'SEGNEW':case 'PAGE_NEW':$id=$arena->allocate($val($ins[2]),(string)($ins[3]??'segment'),isset($ins[4])?$val($ins[4]):0);$f->set((string)$ins[1],$id);break;
                case 'SEGFREE':case 'PAGE_FREE':$arena->free($val($ins[1]));break;
                case 'SEGSET':case 'PAGE_SET':$arena->write($val($ins[1]),$val($ins[2]),$val($ins[3]));break;
                case 'SEGGET':case 'PAGE_GET':$f->set((string)$ins[1],$arena->read($val($ins[2]),$val($ins[3])));break;
                case 'SEGFILL':$arena->fill($val($ins[1]),$val($ins[2]),$val($ins[3]),$val($ins[4]));break;
                case 'SEGCOPY':$arena->copy($val($ins[1]),$val($ins[2]),$val($ins[3]),$val($ins[4]),$val($ins[5]));break;
                case 'SEGDEFRAG':case 'SEGPACK':case 'PACKPAGES':case 'DEFRAG':$d=$arena->defrag();if(isset($ins[1]))$f->set((string)$ins[1],(int)$d['moved_cells']);break;
                case 'SEGSTAT':$s=$arena->stats();$metric=strtolower((string)$ins[2]);$v=match($metric){'segments'=>(int)$s['segments'],'used'=>(int)$s['used_cells'],'high'=>(int)$s['high_water'],'fragmented'=>(int)$s['fragmented_cells'],'free'=>(int)$s['free_cells'],default=>throw new InvalidArgumentException("Unknown SEGSTAT metric {$metric}")};$f->set((string)$ins[1],$v);break;

                case 'RET':$ret=$val($ins[1]);$f->set('P0',$ret);$f->set('M'.PASMCanonicalISA::M_STATUS,0);$f->set('M'.PASMCanonicalISA::M_IP,$pc);return ['state'=>'done','pc'=>$pc,'result'=>$ret,'steps'=>$steps];
                case 'YIELD':$f->set('M'.PASMCanonicalISA::M_IP,$pc);$f->set('M'.PASMCanonicalISA::M_STATUS,2);return ['state'=>'yielded','pc'=>$pc,'result'=>$ret,'steps'=>$steps];
                case 'HALT':$f->set('M'.PASMCanonicalISA::M_STATUS,0);return ['state'=>'done','pc'=>$pc,'result'=>$ret,'steps'=>$steps];
                default:throw new RuntimeException("Unknown segmented canonical opcode {$op}");
            }
        }
        $f->set('M'.PASMCanonicalISA::M_STATUS,0);return ['state'=>'done','pc'=>$pc,'result'=>$ret,'steps'=>$steps];
    }
}

/** Round-robin segmented tasks. Each task owns frame + segment arena state. */
final class PASMSegmentedScheduler
{
    private array $tasks=[];private int $next=1,$cursor=0;
    public function __construct(private readonly PASMSegmentedExecutor $exec,private readonly PASMFramePool $frames,private readonly int $quantum=64){}
    public function spawn(string $block,array $initial=[],string $name='task'): int{$this->exec->table->get($block);$f=$this->frames->create($name);foreach($initial as $r=>$v)$f->set((string)$r,(int)$v);$id=$this->next++;$this->tasks[$id]=['id'=>$id,'name'=>$name,'block'=>$block,'frame'=>$f->id,'pc'=>0,'state'=>'ready','result'=>null];return $id;}
    public function tick(): ?array{$ids=array_keys($this->tasks);$n=count($ids);if(!$n)return null;for($k=0;$k<$n;$k++){ $idx=($this->cursor+$k)%$n;$id=$ids[$idx];$t=&$this->tasks[$id];if($t['state']==='done'){unset($t);continue;}$f=$this->frames->get($t['frame']);$r=$this->exec->invoke($t['block'],$f,$t['pc'],$this->quantum);$t['pc']=$r['pc'];$t['state']=$r['state'];$t['result']=$r['result'];$ev=$t+['steps'=>$r['steps']];unset($t);$this->cursor=($idx+1)%max(1,$n);return $ev;}return null;}
    public function run(): array{$out=[];while(($e=$this->tick())!==null)$out[]=$e;return $out;}
    public function frame(int $task): PASMRegisterFrame{$t=$this->tasks[$task]??throw new RuntimeException('Unknown task');return $this->frames->get($t['frame']);}
    public function arena(int $task): PASMSegmentArena{return $this->exec->segments->forFrame($this->frame($task));}
}
