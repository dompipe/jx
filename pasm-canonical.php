<?php declare(strict_types=1);
namespace pasm;

require_once __DIR__.'/pasm-master-table.php';

use InvalidArgumentException;
use RuntimeException;

/** Canonical PASM ISA/register description. Numeric IDs are stable on the wire. */
final class PASMCanonicalISA
{
    public const VERSION = 1;
    public const GENERAL_COUNT = 256;
    public const TEMP_COUNT = 1024;
    public const PERSISTENT_COUNT = 256;

    public const M_IP=0, M_FLAGS=1, M_SP=2, M_FRAME=3, M_BLOCK=4, M_PEER=5, M_SCHED=6, M_STATUS=7;

    private const BANK = ['R'=>0, 'T'=>1, 'P'=>2, 'M'=>3];
    private const LIMIT = ['R'=>self::GENERAL_COUNT, 'T'=>self::TEMP_COUNT, 'P'=>self::PERSISTENT_COUNT, 'M'=>8];

    /** Pack a canonical register into a stable integer: bank in high 4 bits, index below. */
    public static function id(string $name): int
    {
        $name=strtoupper(trim($name));
        if(!preg_match('/^([RTPM])(\d+)$/',$name,$m)) throw new InvalidArgumentException("Invalid canonical register {$name}");
        $bank=$m[1];$idx=(int)$m[2];
        if($idx<0||$idx>=self::LIMIT[$bank]) throw new InvalidArgumentException("Canonical register out of range {$name}");
        return (self::BANK[$bank]<<12)|$idx;
    }
    public static function name(int $id): string
    {
        $bank=($id>>12)&0xF;$idx=$id&0xFFF;$letters=array_flip(self::BANK);
        if(!isset($letters[$bank])) throw new InvalidArgumentException('Invalid canonical register id');
        $b=$letters[$bank]; if($idx>=self::LIMIT[$b]) throw new InvalidArgumentException('Canonical register id out of range');
        return $b.$idx;
    }
    public static function bank(int $id): string { return self::name($id)[0]; }
    public static function index(int $id): int { return $id&0xFFF; }
}

/** Sparse virtual register frame. Zero-valued slots consume no array entry. */
final class PASMRegisterFrame implements \JsonSerializable
{
    private array $r=[],$t=[],$p=[],$m=[];
    public function __construct(public readonly int $id, public readonly string $name='frame') { $this->m[PASMCanonicalISA::M_FRAME]=$id; }
    public function get(int|string $reg): int
    {
        $id=is_string($reg)?PASMCanonicalISA::id($reg):$reg;$i=PASMCanonicalISA::index($id);
        return match(PASMCanonicalISA::bank($id)){'R'=>$this->r[$i]??0,'T'=>$this->t[$i]??0,'P'=>$this->p[$i]??0,'M'=>$this->m[$i]??0};
    }
    public function set(int|string $reg,int $value): int
    {
        $id=is_string($reg)?PASMCanonicalISA::id($reg):$reg;$i=PASMCanonicalISA::index($id);$b=PASMCanonicalISA::bank($id);
        if($b==='R') $target=&$this->r; elseif($b==='T') $target=&$this->t; elseif($b==='P') $target=&$this->p; else $target=&$this->m;
        if($value===0) unset($target[$i]); else $target[$i]=$value;
        return $value;
    }
    public function clearTemps(): void { $this->t=[]; }
    public function snapshot(): array { return ['id'=>$this->id,'name'=>$this->name,'R'=>$this->r,'T'=>$this->t,'P'=>$this->p,'M'=>$this->m]; }
    public function jsonSerialize(): array { return $this->snapshot(); }
    public static function fromArray(array $x): self { $f=new self((int)$x['id'],(string)($x['name']??'frame'));foreach(['R'=>'r','T'=>'t','P'=>'p','M'=>'m'] as $k=>$prop)$f->{$prop}=array_map('intval',(array)($x[$k]??[]));return $f; }
}

final class PASMFramePool
{
    /** @var array<int,PASMRegisterFrame> */ private array $frames=[]; private int $next=1;
    public function create(string $name='frame'): PASMRegisterFrame { $id=$this->next++; return $this->frames[$id]=new PASMRegisterFrame($id,$name); }
    public function put(PASMRegisterFrame $f): void { $this->frames[$f->id]=$f;$this->next=max($this->next,$f->id+1); }
    public function get(int $id): PASMRegisterFrame { return $this->frames[$id]??throw new RuntimeException("Unknown PASM frame {$id}"); }
    public function drop(int $id): void { unset($this->frames[$id]); }
    public function count(): int { return count($this->frames); }
}

/** Canonical command block: immutable code + optional register contract. */
final class PASMCanonicalBlock implements \JsonSerializable
{
    public function __construct(public readonly string $id,public readonly string $name,public readonly array $commands,public readonly array $schema=[]){ }
    public static function make(string $name,array $commands,array $schema=[]): self
    {
        $canon=json_encode(['commands'=>$commands,'schema'=>$schema],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        return new self(substr(hash('sha256',$canon),0,24),$name,array_values($commands),$schema);
    }
    public function jsonSerialize(): array { return ['id'=>$this->id,'name'=>$this->name,'commands'=>$this->commands,'schema'=>$this->schema]; }
    public static function fromArray(array $x): self { $b=self::make((string)$x['name'],(array)$x['commands'],(array)($x['schema']??[]));if(!hash_equals((string)$x['id'],$b->id))throw new RuntimeException('Canonical block checksum mismatch');return $b; }
}

/** Persistent immutable code table. Same commands/schema => same block ID everywhere. */
final class PASMCanonicalTable
{
    private array $blocks=[],$names=[];
    public function __construct(private readonly ?string $file=null){ if($file&&is_file($file))$this->load(); }
    public function define(string $name,array $commands,array $schema=[]): PASMCanonicalBlock { $b=PASMCanonicalBlock::make($name,$commands,$schema);$this->blocks[$b->id]=$b;$this->names[$name]=$b->id;$this->persist();return $b; }
    public function install(PASMCanonicalBlock $b): void { $this->blocks[$b->id]=$b;$this->names[$b->name]=$b->id;$this->persist(); }
    public function has(string $ref): bool { return isset($this->blocks[$ref])||isset($this->names[$ref]); }
    public function get(string $ref): PASMCanonicalBlock { $id=$this->names[$ref]??$ref;return $this->blocks[$id]??throw new RuntimeException("Unknown canonical block {$ref}"); }
    public function ids(): array { return array_keys($this->blocks); }
    public function persist(): void { if(!$this->file)return;$dir=dirname($this->file);if(!is_dir($dir))@mkdir($dir,0770,true);$j=json_encode(['v'=>1,'blocks'=>array_values($this->blocks),'names'=>$this->names],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$tmp=$this->file.'.tmp-'.getmypid();if(file_put_contents($tmp,$j,LOCK_EX)===false||!@rename($tmp,$this->file)){@unlink($tmp);throw new RuntimeException('Unable to persist canonical table');} }
    private function load(): void { $v=json_decode((string)file_get_contents($this->file),true,512,JSON_THROW_ON_ERROR);foreach((array)($v['blocks']??[]) as $x){$b=PASMCanonicalBlock::fromArray((array)$x);$this->blocks[$b->id]=$b;$this->names[$b->name]=$b->id;}foreach((array)($v['names']??[]) as $n=>$id)if(isset($this->blocks[$id]))$this->names[$n]=$id; }
}

/** Array-register canonical interpreter. One immutable block may execute in unlimited frames. */
final class PASMCanonicalExecutor
{
    public function __construct(public readonly PASMCanonicalTable $table){}
    public function invoke(string $ref,PASMRegisterFrame $f,int $startPc=0,?int $budget=null): array
    {
        $b=$this->table->get($ref);$cmd=$b->commands;$pc=$startPc;$steps=0;$zero=false;$ret=null;$n=count($cmd);
        $f->set('M'.PASMCanonicalISA::M_BLOCK,hexdec(substr($b->id,0,7)));$f->set('M'.PASMCanonicalISA::M_STATUS,1);
        while($pc<$n){ if($budget!==null&&$steps>=$budget){$f->set('M'.PASMCanonicalISA::M_IP,$pc);$f->set('M'.PASMCanonicalISA::M_STATUS,2);return ['state'=>'yielded','pc'=>$pc,'result'=>$ret,'steps'=>$steps];}
            $ins=(array)$cmd[$pc++];$steps++;$op=strtoupper((string)($ins[0]??''));
            $val=fn($x)=>is_string($x)&&preg_match('/^[RTPM]\d+$/i',$x)?$f->get($x):(int)$x;
            switch($op){
                case 'MOVI':$f->set((string)$ins[1],(int)$ins[2]);break;
                case 'MOV':$f->set((string)$ins[1],$f->get((string)$ins[2]));break;
                case 'ADD':$f->set((string)$ins[1],$val($ins[2])+$val($ins[3]));break;
                case 'SUB':$f->set((string)$ins[1],$val($ins[2])-$val($ins[3]));break;
                case 'MUL':$f->set((string)$ins[1],$val($ins[2])*$val($ins[3]));break;
                case 'DIV':$d=$val($ins[3]);if($d===0)throw new RuntimeException('Canonical division by zero');$f->set((string)$ins[1],intdiv($val($ins[2]),$d));break;
                case 'MOD':$d=$val($ins[3]);if($d===0)throw new RuntimeException('Canonical modulo by zero');$f->set((string)$ins[1],$val($ins[2])%$d);break;
                case 'AND':$f->set((string)$ins[1],$val($ins[2])&$val($ins[3]));break;
                case 'OR':$f->set((string)$ins[1],$val($ins[2])|$val($ins[3]));break;
                case 'XOR':$f->set((string)$ins[1],$val($ins[2])^$val($ins[3]));break;
                case 'INC':$f->set((string)$ins[1],$f->get((string)$ins[1])+1);break;
                case 'DEC':$f->set((string)$ins[1],$f->get((string)$ins[1])-1);break;
                case 'CMP':$zero=$val($ins[1])===$val($ins[2]);$f->set('M'.PASMCanonicalISA::M_FLAGS,$zero?1:0);break;
                case 'JMP':$pc=(int)$ins[1];break;
                case 'JZ':if($zero)$pc=(int)$ins[1];break;
                case 'JNZ':if(!$zero)$pc=(int)$ins[1];break;
                case 'RET':$ret=$val($ins[1]);$f->set('P0',$ret);$f->set('M'.PASMCanonicalISA::M_STATUS,0);$f->set('M'.PASMCanonicalISA::M_IP,$pc);return ['state'=>'done','pc'=>$pc,'result'=>$ret,'steps'=>$steps];
                case 'YIELD':$f->set('M'.PASMCanonicalISA::M_IP,$pc);$f->set('M'.PASMCanonicalISA::M_STATUS,2);return ['state'=>'yielded','pc'=>$pc,'result'=>$ret,'steps'=>$steps];
                case 'HALT':$f->set('M'.PASMCanonicalISA::M_STATUS,0);return ['state'=>'done','pc'=>$pc,'result'=>$ret,'steps'=>$steps];
                default:throw new RuntimeException("Unknown canonical opcode {$op}");
            }
        }
        $f->set('M'.PASMCanonicalISA::M_STATUS,0);return ['state'=>'done','pc'=>$pc,'result'=>$ret,'steps'=>$steps];
    }
}

/** Round-robin tasks. Code block is shared; each task owns only a frame + PC. */
final class PASMFrameScheduler
{
    private array $tasks=[];private int $next=1,$cursor=0;
    public function __construct(private readonly PASMCanonicalExecutor $exec,private readonly PASMFramePool $frames,private readonly int $quantum=64){}
    public function spawn(string $block,array $initial=[],string $name='task'): int { $this->exec->table->get($block);$f=$this->frames->create($name);foreach($initial as $r=>$v)$f->set((string)$r,(int)$v);$id=$this->next++;$this->tasks[$id]=['id'=>$id,'name'=>$name,'block'=>$block,'frame'=>$f->id,'pc'=>0,'state'=>'ready','result'=>null];return $id; }
    public function tick(): ?array { $ids=array_keys($this->tasks);$n=count($ids);if(!$n)return null;for($k=0;$k<$n;$k++){ $idx=($this->cursor+$k)%$n;$id=$ids[$idx];$t=&$this->tasks[$id];if($t['state']==='done'){unset($t);continue;}$f=$this->frames->get($t['frame']);$r=$this->exec->invoke($t['block'],$f,$t['pc'],$this->quantum);$t['pc']=$r['pc'];$t['state']=$r['state'];$t['result']=$r['result'];$ev=$t+['steps'=>$r['steps']];unset($t);$this->cursor=($idx+1)%$n;return $ev;}return null; }
    public function run(): array { $out=[];while(($e=$this->tick())!==null)$out[]=$e;return $out; }
    public function task(int $id): array { return $this->tasks[$id]??throw new RuntimeException('Unknown task'); }
    public function frame(int $task): PASMRegisterFrame { return $this->frames->get($this->task($task)['frame']); }
}
