<?php declare(strict_types=1);

namespace pasm;

use InvalidArgumentException;
use RuntimeException;

/**
 * PASM Media ABI v1.
 *
 * Canonical JX media graphs are resolved once into compact numeric slots. The
 * hot path uses prelinked media/analyser/Bag/chart slots; names and plugin ids
 * remain provenance only and are not repeatedly resolved while samples flow.
 */
final class PASMMediaOp
{
    public const MOPEN=0x40;      // media slot -> host media handle
    public const MANALYZE=0x41;   // media slot, analyser slot
    public const MPUBLISH=0x42;   // analyser slot, Bag slot
    public const MCHART=0x43;     // Bag slot, chart slot
    public const MSYNC=0x44;      // analyser/Bag checkpoint boundary
    public const MCLOSE=0x45;     // media slot

    public const NAMES=[
        self::MOPEN=>'MOPEN', self::MANALYZE=>'MANALYZE', self::MPUBLISH=>'MPUBLISH',
        self::MCHART=>'MCHART', self::MSYNC=>'MSYNC', self::MCLOSE=>'MCLOSE',
    ];
}

final class PASMMediaSlotTable
{
    private array $ids=[];
    private array $entries=[];

    public function intern(string $kind,string $name,array $meta=[]):int
    {
        $kind=strtolower(trim($kind));$name=trim($name);
        if($kind===''||$name==='') throw new InvalidArgumentException('Media slot kind/name cannot be empty');
        $key=$kind."\0".$name;
        if(isset($this->ids[$key])) return $this->ids[$key];
        $id=count($this->entries);
        if($id>255) throw new RuntimeException('PASM media ABI supports at most 256 slots per compiled graph');
        $this->ids[$key]=$id;
        $this->entries[$id]=['id'=>$id,'kind'=>$kind,'name'=>$name,'meta'=>$meta];
        return $id;
    }

    public function entry(int $id):array
    {
        if(!isset($this->entries[$id])) throw new RuntimeException("Unknown PASM media slot {$id}");
        return $this->entries[$id];
    }
    public function all():array{return $this->entries;}
}

final class PASMMediaInstruction
{
    public function __construct(public readonly int $opcode,public readonly array $operands=[])
    {
        if(!isset(PASMMediaOp::NAMES[$opcode])) throw new InvalidArgumentException('Unknown PASM media opcode');
        foreach($operands as $v) if(!is_int($v)||$v<0||$v>255) throw new InvalidArgumentException('PASM media operands must fit one byte');
    }
    public function bytes():string{return chr($this->opcode).implode('',array_map('chr',$this->operands));}
    public function text():string{return PASMMediaOp::NAMES[$this->opcode].($this->operands?' '.implode(',',$this->operands):'');}
}

final class PASMMediaGraph
{
    /** @param list<PASMMediaInstruction> $instructions */
    public function __construct(public readonly PASMMediaSlotTable $slots,public readonly array $instructions,public readonly array $provenance=[]){ }
    public function bytecode():string{return implode('',array_map(static fn(PASMMediaInstruction $i)=>$i->bytes(),$this->instructions));}
    public function listing():string{return implode("\n",array_map(static fn(PASMMediaInstruction $i)=>$i->text(),$this->instructions));}
}

/** Compile host-neutral Media -> Analysis -> Bag -> Chart relationships into a prelinked shadow graph. */
final class PASMMediaGraphCompiler
{
    public function compile(array $media,array $bindings,array $charts=[]):PASMMediaGraph
    {
        $slots=new PASMMediaSlotTable();$ops=[];$prov=[];
        foreach($media as $m){
            if(!is_array($m)||($m['control']??null)!=='media') throw new InvalidArgumentException('Media graph expects serialized Media controls');
            $id=(string)($m['id']??'');$slot=$slots->intern('media',$id,['type'=>$m['type']??null,'mime'=>$m['mime']??null]);
            $ops[]=new PASMMediaInstruction(PASMMediaOp::MOPEN,[$slot]);
            $prov[]=['canonical'=>'Media','name'=>$id,'slot'=>$slot];
        }
        foreach($bindings as $b){
            if(!is_array($b)||($b['kind']??null)!=='binding') throw new InvalidArgumentException('Media graph expects serialized analysis bindings');
            $mediaName=(string)($b['source']['media']??'');
            $mediaSlot=$slots->intern('media',$mediaName);
            $bindingId=(string)($b['id']??'');
            $analysisSlot=$slots->intern('analysis',$bindingId,['binding'=>$b['binding']??null]);
            $targetBag=(string)($b['target']['bag']??'');
            $targetAt=(string)($b['target']['at']??'_default');
            $bagSlot=$slots->intern('bag',$targetBag.'.'.$targetAt,['bag'=>$targetBag,'at'=>$targetAt]);
            $ops[]=new PASMMediaInstruction(PASMMediaOp::MANALYZE,[$mediaSlot,$analysisSlot]);
            $ops[]=new PASMMediaInstruction(PASMMediaOp::MPUBLISH,[$analysisSlot,$bagSlot]);
            $prov[]=['canonical'=>$b['binding']??'analysis','name'=>$bindingId,'slot'=>$analysisSlot,'bag_slot'=>$bagSlot];
        }
        foreach($charts as $c){
            if(!is_array($c)||($c['control']??null)!=='chart') throw new InvalidArgumentException('Media graph expects serialized Chart controls');
            $bag=(string)($c['source']['bag']??'');$at=(string)($c['source']['at']??'_default');
            $bagSlot=$slots->intern('bag',$bag.'.'.$at,['bag'=>$bag,'at'=>$at]);
            $chartId=(string)($c['id']??'');$chartSlot=$slots->intern('chart',$chartId,['type'=>$c['type']??null]);
            $ops[]=new PASMMediaInstruction(PASMMediaOp::MCHART,[$bagSlot,$chartSlot]);
            $prov[]=['canonical'=>'Chart','name'=>$chartId,'slot'=>$chartSlot,'bag_slot'=>$bagSlot];
        }
        foreach($slots->all() as $entry) if($entry['kind']==='bag') $ops[]=new PASMMediaInstruction(PASMMediaOp::MSYNC,[$entry['id']]);
        return new PASMMediaGraph($slots,$ops,$prov);
    }
}

/** Host adapter interface used by native/browser/JIT PASM media shadows. */
interface PASMMediaHost
{
    public function open(array $slot):void;
    public function analyze(array $media,array $analysis):void;
    public function publish(array $analysis,array $bag):void;
    public function chart(array $bag,array $chart):void;
    public function sync(array $slot):void;
    public function close(array $media):void;
}

final class PASMMediaGraphExecutor
{
    public function __construct(private PASMMediaHost $host){}
    public function run(PASMMediaGraph $graph):void
    {
        foreach($graph->instructions as $i){
            $s=$graph->slots;
            switch($i->opcode){
                case PASMMediaOp::MOPEN:$this->host->open($s->entry($i->operands[0]));break;
                case PASMMediaOp::MANALYZE:$this->host->analyze($s->entry($i->operands[0]),$s->entry($i->operands[1]));break;
                case PASMMediaOp::MPUBLISH:$this->host->publish($s->entry($i->operands[0]),$s->entry($i->operands[1]));break;
                case PASMMediaOp::MCHART:$this->host->chart($s->entry($i->operands[0]),$s->entry($i->operands[1]));break;
                case PASMMediaOp::MSYNC:$this->host->sync($s->entry($i->operands[0]));break;
                case PASMMediaOp::MCLOSE:$this->host->close($s->entry($i->operands[0]));break;
            }
        }
    }
}
