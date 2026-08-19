<?php declare(strict_types=1);
namespace pasm;

require_once __DIR__.'/pasm-bytecode-optimized.php';
require_once __DIR__.'/pasm-runtime.php';

use InvalidArgumentException;
use RuntimeException;

final class PASMMasterEntry implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $kind,
        public readonly string $payload,
        public readonly array $dependencies = [],
        public readonly array $meta = [],
    ) {}

    public static function make(string $name, string $kind, string $payload, array $dependencies=[], array $meta=[]): self
    {
        $canon = json_encode([$kind,base64_encode($payload),array_values($dependencies)], JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $id = substr(hash('sha256',$canon),0,24);
        return new self($id,$name,$kind,$payload,array_values($dependencies),$meta);
    }

    public function jsonSerialize(): array
    {
        return ['id'=>$this->id,'name'=>$this->name,'kind'=>$this->kind,'payload'=>base64_encode($this->payload),'dependencies'=>$this->dependencies,'meta'=>$this->meta];
    }

    public static function fromArray(array $v): self
    {
        $payload=base64_decode((string)($v['payload']??''),true);
        if($payload===false) throw new RuntimeException('Invalid master-table payload');
        $e=new self((string)$v['id'],(string)$v['name'],(string)$v['kind'],$payload,(array)($v['dependencies']??[]),(array)($v['meta']??[]));
        $check=self::make($e->name,$e->kind,$e->payload,$e->dependencies,$e->meta);
        if(!hash_equals($check->id,$e->id)) throw new RuntimeException('Master-table entry checksum mismatch');
        return $e;
    }
}

/** Persistent executable vocabulary: primitive schemas, concrete commands, and reusable blocks. */
final class PASMMasterTable
{
    /** @var array<string,PASMMasterEntry> */ private array $entries=[];
    /** @var array<string,string> */ private array $names=[];
    private PASMOptimizingAssembler $assembler;

    public function __construct(private readonly ?string $file=null)
    {
        $this->assembler=new PASMOptimizingAssembler(true);
        if($file!==null && is_file($file)) $this->load();
        $this->installPrimitiveSchemas();
    }

    private function installPrimitiveSchemas(): void
    {
        foreach(['HALT','MOVI','MOVR','ADD','SUB','MUL','DIV','MOD','AND','OR','XOR','SHL','SHR','CMP','JMP','JZ','JNZ','PUSH','POP','INC','DEC','NEG','LOAD32','STORE32','RET'] as $op){
            $name='cmd.'.strtolower($op);
            if(isset($this->names[$name])) continue;
            $entry=PASMMasterEntry::make($name,'primitive',$op,[],['opcode'=>$op]);
            $this->put($entry,false);
        }
    }

    public function defineCommand(string $source, ?string $name=null): PASMMasterEntry
    {
        $name ??= 'command.'.substr(hash('sha256',trim($source)),0,12);
        $code=$this->assembler->compile($source);
        $e=PASMMasterEntry::make($name,'bytecode',$code,[],['source'=>trim($source),'commands'=>1]);
        return $this->put($e);
    }

    public function defineBlock(string|array $source, ?string $name=null): PASMMasterEntry
    {
        $text=is_array($source)?implode("\n",$source):$source;
        $name ??= 'block.'.substr(hash('sha256',trim($text)),0,12);
        $code=$this->assembler->compile($source);
        $commands=count(array_filter(preg_split('/\R/',trim($text)),fn($x)=>trim((string)$x)!==''&&!str_ends_with(trim((string)$x),':')));
        $e=PASMMasterEntry::make($name,'bytecode',$code,[],['source'=>$text,'commands'=>$commands]);
        return $this->put($e);
    }

    /** A block made only of references to already-named blocks. */
    public function defineComposite(array $refs, ?string $name=null, bool $yieldBetween=true): PASMMasterEntry
    {
        $ids=[];
        foreach($refs as $r) $ids[]=$this->resolve((string)$r)->id;
        $name ??= 'set.'.substr(hash('sha256',implode('|',$ids)),0,12);
        $payload=json_encode(['refs'=>$ids,'yield_between'=>$yieldBetween],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        return $this->put(PASMMasterEntry::make($name,'composite',$payload,$ids,['count'=>count($ids),'yield_between'=>$yieldBetween]));
    }

    public function alias(string $alias,string $ref): void
    {
        if($alias==='') throw new InvalidArgumentException('Alias cannot be empty');
        $this->names[$alias]=$this->resolve($ref)->id;
        $this->persist();
    }

    private function put(PASMMasterEntry $e,bool $persist=true): PASMMasterEntry
    {
        $this->entries[$e->id]=$e;
        $this->names[$e->name]=$e->id;
        if($persist)$this->persist();
        return $e;
    }

    public function install(PASMMasterEntry $e): PASMMasterEntry { return $this->put($e); }
    public function has(string $ref): bool { return isset($this->entries[$ref])||isset($this->names[$ref]); }
    public function resolve(string $ref): PASMMasterEntry
    {
        $id=$this->names[$ref]??$ref;
        if(!isset($this->entries[$id])) throw new RuntimeException("Unknown PASM master entry {$ref}");
        return $this->entries[$id];
    }
    public function ids(): array { return array_keys($this->entries); }
    public function names(): array { return $this->names; }
    public function count(): int { return count($this->entries); }

    public function exportPackage(string $ref): array
    {
        $seen=[];$out=[];
        $walk=function(string $r) use (&$walk,&$seen,&$out){
            $e=$this->resolve($r); if(isset($seen[$e->id]))return; $seen[$e->id]=true;
            foreach($e->dependencies as $d)$walk($d);
            $out[]=$e->jsonSerialize();
        };
        $walk($ref);
        return $out;
    }

    public function importPackage(array $package): void
    {
        foreach($package as $raw)$this->put(PASMMasterEntry::fromArray((array)$raw),false);
        $this->persist();
    }

    public function persist(): void
    {
        if($this->file===null)return;
        $dir=dirname($this->file); if(!is_dir($dir))@mkdir($dir,0770,true);
        $data=['v'=>1,'entries'=>array_values(array_map(fn($e)=>$e->jsonSerialize(),$this->entries)),'names'=>$this->names];
        $json=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $tmp=$this->file.'.tmp-'.getmypid();
        if(file_put_contents($tmp,$json,LOCK_EX)===false||!@rename($tmp,$this->file)){@unlink($tmp);throw new RuntimeException('Unable to persist PASM master table');}
    }

    private function load(): void
    {
        $raw=file_get_contents($this->file); if($raw===false)return;
        $v=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        foreach((array)($v['entries']??[]) as $item){$e=PASMMasterEntry::fromArray((array)$item);$this->entries[$e->id]=$e;$this->names[$e->name]=$e->id;}
        foreach((array)($v['names']??[]) as $name=>$id) if(isset($this->entries[$id]))$this->names[$name]=$id;
    }
}

final class PASMMasterExecutor
{
    private PASMOptimizedBytecodeVM $vm;
    public function __construct(public readonly PASMRuntime $runtime, public readonly PASMMasterTable $table)
    { $this->vm=new PASMOptimizedBytecodeVM($runtime); }

    public function invoke(string $ref): mixed
    {
        $e=$this->table->resolve($ref);
        return match($e->kind){
            'bytecode'=>$this->vm->run($e->payload),
            'composite'=>$this->runComposite($e),
            'primitive'=>throw new RuntimeException('Primitive schemas require a concrete command block'),
            default=>throw new RuntimeException("Unsupported master entry kind {$e->kind}"),
        };
    }

    private function runComposite(PASMMasterEntry $e): mixed
    {
        $p=json_decode($e->payload,true,512,JSON_THROW_ON_ERROR);$ret=null;
        foreach((array)$p['refs'] as $r)$ret=$this->invoke((string)$r);
        return $ret;
    }
}

/** Cooperative scheduler. A thread is a sequence of named executable blocks. */
final class PASMCooperativeScheduler
{
    /** @var array<int,array{id:int,name:string,queue:array,pos:int,state:string,result:mixed}> */ private array $tasks=[];
    private int $nextId=1; private int $cursor=0;
    public function __construct(private readonly PASMMasterExecutor $executor){}

    public function spawn(array $refs,string $name='task'): int
    {
        foreach($refs as $r)$this->executor->table->resolve((string)$r);
        $id=$this->nextId++;
        $this->tasks[$id]=['id'=>$id,'name'=>$name,'queue'=>array_values($refs),'pos'=>0,'state'=>'ready','result'=>null];
        return $id;
    }

    /** Execute at most one named block from one runnable task, then yield. */
    public function tick(): ?array
    {
        $ids=array_keys($this->tasks);$n=count($ids);if($n===0)return null;
        for($k=0;$k<$n;$k++){
            $idx=($this->cursor+$k)%$n;$id=$ids[$idx];$t=&$this->tasks[$id];
            if(!in_array($t['state'],['ready','yielded'],true)){unset($t);continue;}
            if($t['pos']>=count($t['queue'])){$t['state']='done';unset($t);continue;}
            $t['state']='running';$ref=(string)$t['queue'][$t['pos']++];
            $t['result']=$this->executor->invoke($ref);
            $t['state']=$t['pos']>=count($t['queue'])?'done':'yielded';
            $event=['task'=>$id,'name'=>$t['name'],'block'=>$ref,'state'=>$t['state'],'result'=>$t['result']];
            unset($t);$this->cursor=($idx+1)%$n;return $event;
        }
        return null;
    }

    public function run(): array { $events=[]; while(($e=$this->tick())!==null)$events[]=$e; return $events; }
    public function task(int $id): array { if(!isset($this->tasks[$id]))throw new RuntimeException('Unknown PASM task');return $this->tasks[$id]; }
    public function tasks(): array { return $this->tasks; }
}
