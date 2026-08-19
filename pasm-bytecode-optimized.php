<?php declare(strict_types=1);
namespace pasm;

require_once __DIR__ . '/pasm-bytecode.php';

use InvalidArgumentException;
use RuntimeException;

/** Superinstruction opcodes. Base PASM bytecode remains valid. */
final class PASMSuperBC
{
    public const MOVI2_ADD = 0x80;
    public const MOVI2_MUL = 0x81;
    public const CMP_JZ    = 0x82;
    public const CMP_JNZ   = 0x83;
    public const DEC_CMP_JNZ = 0x84;
    public const LOAD32_ADD = 0x85;

    public static function name(int $op): string
    {
        return match ($op) {
            self::MOVI2_ADD => 'MOVI2_ADD', self::MOVI2_MUL => 'MOVI2_MUL',
            self::CMP_JZ => 'CMP_JZ', self::CMP_JNZ => 'CMP_JNZ',
            self::DEC_CMP_JNZ => 'DEC_CMP_JNZ', self::LOAD32_ADD => 'LOAD32_ADD',
            default => 'UNKNOWN',
        };
    }
}

/**
 * Peephole/superinstruction compiler.
 * It optimizes parsed assembly before encoding, then recomputes every label target.
 */
final class PASMOptimizingAssembler
{
    public function __construct(private bool $enabled = true) {}

    public function compile(string|array $source): string
    {
        $items = $this->parse($source);
        if ($this->enabled) $items = $this->optimize($items);
        return $this->encode($items);
    }

    /** @return array<int,array{type:string,name?:string,op?:string,args?:array}> */
    private function parse(string|array $source): array
    {
        $lines = is_array($source) ? $source : preg_split('/\R/', $source);
        $out = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/[;#].*$/', '', (string)$line));
            if ($line === '') continue;
            if (str_ends_with($line, ':')) {
                $out[] = ['type'=>'label','name'=>strtolower(rtrim($line, ':'))];
                continue;
            }
            $t = preg_split('/[\s,]+/', $line);
            $op = strtoupper((string)array_shift($t));
            $out[] = ['type'=>'ins','op'=>$op,'args'=>$t];
        }
        return $out;
    }

    private function isIns(array $x, string $op): bool
    {
        return ($x['type'] ?? '') === 'ins' && ($x['op'] ?? '') === $op;
    }

    /** Labels are barriers: no fusion crosses a label. */
    private function optimize(array $items): array
    {
        $out=[]; $n=count($items);
        for ($i=0; $i<$n;) {
            $a=$items[$i];
            if (($a['type'] ?? '') !== 'ins') { $out[]=$a; $i++; continue; }

            // MOVI a,A ; MOVI b,B ; ADD/MUL d,a,b -> one dispatch.
            if ($i+2<$n && $this->isIns($a,'MOVI') && $this->isIns($items[$i+1],'MOVI')) {
                $b=$items[$i+1]; $c=$items[$i+2];
                if (($c['type']??'')==='ins' && in_array($c['op'],['ADD','MUL'],true)) {
                    [$ra,$va]=$a['args']; [$rb,$vb]=$b['args']; [$rd,$ca,$cb]=$c['args'];
                    if (strtolower($ca)===strtolower($ra) && strtolower($cb)===strtolower($rb) && strtolower($ra)!==strtolower($rb)) {
                        $out[]=['type'=>'ins','op'=>$c['op']==='ADD'?'MOVI2_ADD':'MOVI2_MUL','args'=>[$rd,$ra,$va,$rb,$vb]];
                        $i+=3; continue;
                    }
                }
            }

            // DEC x ; CMP x,y ; JNZ label -> DEC_CMP_JNZ x,y,label
            if ($i+2<$n && $this->isIns($a,'DEC') && $this->isIns($items[$i+1],'CMP') && $this->isIns($items[$i+2],'JNZ')) {
                $x=$a['args'][0]; [$cx,$cy]=$items[$i+1]['args'];
                if (strtolower($x)===strtolower($cx)) {
                    $out[]=['type'=>'ins','op'=>'DEC_CMP_JNZ','args'=>[$x,$cy,$items[$i+2]['args'][0]]];
                    $i+=3; continue;
                }
            }

            // CMP a,b ; JZ/JNZ target -> fused compare/branch.
            if ($i+1<$n && $this->isIns($a,'CMP') && ($this->isIns($items[$i+1],'JZ') || $this->isIns($items[$i+1],'JNZ'))) {
                $j=$items[$i+1];
                $out[]=['type'=>'ins','op'=>'CMP_'.$j['op'],'args'=>[$a['args'][0],$a['args'][1],$j['args'][0]]];
                $i+=2; continue;
            }

            // LOAD32 tmp,base,off ; ADD out,tmp,r -> preserve tmp and out.
            if ($i+1<$n && $this->isIns($a,'LOAD32') && $this->isIns($items[$i+1],'ADD')) {
                [$tmp,$base,$off]=$a['args']; [$dest,$l,$r]=$items[$i+1]['args'];
                if (strtolower($tmp)===strtolower($l)) {
                    $out[]=['type'=>'ins','op'=>'LOAD32_ADD','args'=>[$tmp,$dest,$base,$off,$r]];
                    $i+=2; continue;
                }
            }

            $out[]=$a; $i++;
        }
        return $out;
    }

    private function size(string $op): int
    {
        return match($op) {
            'HALT'=>1,'MOVI'=>10,'MOVR'=>3,
            'ADD','SUB','MUL','DIV','MOD','AND','OR','XOR','SHL','SHR'=>4,
            'CMP'=>3,'JMP','JZ','JNZ'=>5,'PUSH','POP','INC','DEC','NEG','RET'=>2,
            'LOAD32','STORE32'=>4,
            'MOVI2_ADD','MOVI2_MUL'=>20,
            'CMP_JZ','CMP_JNZ','DEC_CMP_JNZ'=>7,
            'LOAD32_ADD'=>6,
            default=>throw new InvalidArgumentException("Unknown opcode $op")
        };
    }

    private function i64(int $v): string { return pack('V2',$v & 0xffffffff,($v>>32)&0xffffffff); }

    private function encode(array $items): string
    {
        $labels=[]; $pc=0;
        foreach($items as $x){
            if(($x['type']??'')==='label'){$labels[$x['name']]=$pc;}
            else {$pc+=$this->size($x['op']);}
        }
        $out='';
        foreach($items as $x){ if(($x['type']??'')==='label') continue; $out.=$this->emit($x['op'],$x['args'],$labels); }
        return $out;
    }

    private function emit(string $op,array $a,array $labels): string
    {
        $r=fn(string $x)=>PASMBC::regId(strtolower($x));
        $target=function(string $x)use($labels){$k=strtolower($x);if(isset($labels[$k]))return $labels[$k];if(is_numeric($x))return(int)$x;throw new InvalidArgumentException("Unknown label $x");};
        return match($op){
            'HALT'=>chr(PASMBC::HALT),
            'MOVI'=>chr(PASMBC::MOVI).chr($r($a[0])).$this->i64((int)$a[1]),
            'MOVR'=>chr(PASMBC::MOVR).chr($r($a[0])).chr($r($a[1])),
            'ADD'=>chr(PASMBC::ADD).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'SUB'=>chr(PASMBC::SUB).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'MUL'=>chr(PASMBC::MUL).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'DIV'=>chr(PASMBC::DIV).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'MOD'=>chr(PASMBC::MOD).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'AND'=>chr(PASMBC::AND).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'OR'=>chr(PASMBC::OR).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'XOR'=>chr(PASMBC::XOR).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'SHL'=>chr(PASMBC::SHL).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'SHR'=>chr(PASMBC::SHR).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'CMP'=>chr(PASMBC::CMP).chr($r($a[0])).chr($r($a[1])),
            'JMP'=>chr(PASMBC::JMP).pack('V',$target($a[0])),
            'JZ'=>chr(PASMBC::JZ).pack('V',$target($a[0])),
            'JNZ'=>chr(PASMBC::JNZ).pack('V',$target($a[0])),
            'PUSH'=>chr(PASMBC::PUSH).chr($r($a[0])),'POP'=>chr(PASMBC::POP).chr($r($a[0])),
            'INC'=>chr(PASMBC::INC).chr($r($a[0])),'DEC'=>chr(PASMBC::DEC).chr($r($a[0])),'NEG'=>chr(PASMBC::NEG).chr($r($a[0])),'RET'=>chr(PASMBC::RET).chr($r($a[0])),
            'LOAD32'=>chr(PASMBC::LOAD32).chr($r($a[0])).chr($r($a[1])).chr((int)($a[2]??0)&255),
            'STORE32'=>chr(PASMBC::STORE32).chr($r($a[0])).chr($r($a[1])).chr((int)($a[2]??0)&255),
            'MOVI2_ADD'=>chr(PASMSuperBC::MOVI2_ADD).chr($r($a[0])).chr($r($a[1])).$this->i64((int)$a[2]).chr($r($a[3])).$this->i64((int)$a[4]),
            'MOVI2_MUL'=>chr(PASMSuperBC::MOVI2_MUL).chr($r($a[0])).chr($r($a[1])).$this->i64((int)$a[2]).chr($r($a[3])).$this->i64((int)$a[4]),
            'CMP_JZ'=>chr(PASMSuperBC::CMP_JZ).chr($r($a[0])).chr($r($a[1])).pack('V',$target($a[2])),
            'CMP_JNZ'=>chr(PASMSuperBC::CMP_JNZ).chr($r($a[0])).chr($r($a[1])).pack('V',$target($a[2])),
            'DEC_CMP_JNZ'=>chr(PASMSuperBC::DEC_CMP_JNZ).chr($r($a[0])).chr($r($a[1])).pack('V',$target($a[2])),
            'LOAD32_ADD'=>chr(PASMSuperBC::LOAD32_ADD).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])).chr((int)$a[3]&255).chr($r($a[4])),
        };
    }
}

/** VM supporting both base PASM bytecode and fused superinstructions. */
final class PASMOptimizedBytecodeVM
{
    private array $stack=[]; private bool $zero=false;
    public function __construct(private PASMRuntime $runtime, private int $maxInstructions=1_000_000) {}
    private function get(int $id): int { $r=PASMBC::regName($id); return (int)(PASM::${$r}??0); }
    private function set(int $id,int $v): int { $r=PASMBC::regName($id); return PASM::${$r}=$v; }
    private function u32(string $b,int $p): int { return unpack('V',substr($b,$p,4))[1]; }
    private function i64(string $b,int $p): int { $u=unpack('Vlo/Vhi',substr($b,$p,8)); return ($u['hi']<<32)|$u['lo']; }

    public function run(string $code): mixed
    {
        $pc=0;$n=strlen($code);$steps=0;$ret=null;
        while($pc<$n){
            if(++$steps>$this->maxInstructions) throw new RuntimeException('Bytecode instruction limit exceeded');
            $op=ord($code[$pc++]);
            switch($op){
                case PASMSuperBC::MOVI2_ADD: case PASMSuperBC::MOVI2_MUL:
                    $d=ord($code[$pc++]);$ra=ord($code[$pc++]);$a=$this->i64($code,$pc);$pc+=8;$rb=ord($code[$pc++]);$b=$this->i64($code,$pc);$pc+=8;
                    $this->set($ra,$a);$this->set($rb,$b);$this->set($d,$op===PASMSuperBC::MOVI2_ADD?$a+$b:$a*$b);break;
                case PASMSuperBC::CMP_JZ: case PASMSuperBC::CMP_JNZ:
                    $a=$this->get(ord($code[$pc++]));$b=$this->get(ord($code[$pc++]));$t=$this->u32($code,$pc);$pc+=4;$this->zero=($a===$b);
                    if(($op===PASMSuperBC::CMP_JZ && $this->zero)||($op===PASMSuperBC::CMP_JNZ && !$this->zero))$pc=$t;break;
                case PASMSuperBC::DEC_CMP_JNZ:
                    $x=ord($code[$pc++]);$y=ord($code[$pc++]);$t=$this->u32($code,$pc);$pc+=4;$v=$this->set($x,$this->get($x)-1);$this->zero=($v===$this->get($y));if(!$this->zero)$pc=$t;break;
                case PASMSuperBC::LOAD32_ADD:
                    $tmp=ord($code[$pc++]);$dest=ord($code[$pc++]);$base=$this->get(ord($code[$pc++]));$off=ord($code[$pc++]);$rhs=$this->get(ord($code[$pc++]));$v=$this->runtime->memory->readU32($base+$off);$this->set($tmp,$v);$this->set($dest,$v+$rhs);break;
                case PASMBC::HALT:return $ret;
                case PASMBC::MOVI:$d=ord($code[$pc++]);$this->set($d,$this->i64($code,$pc));$pc+=8;break;
                case PASMBC::MOVR:$d=ord($code[$pc++]);$s=ord($code[$pc++]);$this->set($d,$this->get($s));break;
                case PASMBC::ADD:case PASMBC::SUB:case PASMBC::MUL:case PASMBC::DIV:case PASMBC::MOD:case PASMBC::AND:case PASMBC::OR:case PASMBC::XOR:case PASMBC::SHL:case PASMBC::SHR:
                    $d=ord($code[$pc++]);$a=$this->get(ord($code[$pc++]));$b=$this->get(ord($code[$pc++]));$v=match($op){PASMBC::ADD=>$a+$b,PASMBC::SUB=>$a-$b,PASMBC::MUL=>$a*$b,PASMBC::DIV=>$b===0?throw new RuntimeException('Division by zero'):intdiv($a,$b),PASMBC::MOD=>$b===0?throw new RuntimeException('Modulo by zero'):$a%$b,PASMBC::AND=>$a&$b,PASMBC::OR=>$a|$b,PASMBC::XOR=>$a^$b,PASMBC::SHL=>$a<<$b,PASMBC::SHR=>$a>>$b};$this->set($d,$v);break;
                case PASMBC::CMP:$a=$this->get(ord($code[$pc++]));$b=$this->get(ord($code[$pc++]));$this->zero=($a===$b);break;
                case PASMBC::JMP:$pc=$this->u32($code,$pc);break;
                case PASMBC::JZ:$t=$this->u32($code,$pc);$pc+=4;if($this->zero)$pc=$t;break;
                case PASMBC::JNZ:$t=$this->u32($code,$pc);$pc+=4;if(!$this->zero)$pc=$t;break;
                case PASMBC::PUSH:$this->stack[]=$this->get(ord($code[$pc++]));break;
                case PASMBC::POP:if(!$this->stack)throw new RuntimeException('Bytecode stack underflow');$this->set(ord($code[$pc++]),array_pop($this->stack));break;
                case PASMBC::INC:$id=ord($code[$pc++]);$this->set($id,$this->get($id)+1);break;
                case PASMBC::DEC:$id=ord($code[$pc++]);$this->set($id,$this->get($id)-1);break;
                case PASMBC::NEG:$id=ord($code[$pc++]);$this->set($id,-$this->get($id));break;
                case PASMBC::LOAD32:$d=ord($code[$pc++]);$base=$this->get(ord($code[$pc++]));$off=ord($code[$pc++]);$this->set($d,$this->runtime->memory->readU32($base+$off));break;
                case PASMBC::STORE32:$s=ord($code[$pc++]);$base=$this->get(ord($code[$pc++]));$off=ord($code[$pc++]);$this->runtime->memory->writeU32($base+$off,$this->get($s));break;
                case PASMBC::RET:$ret=$this->get(ord($code[$pc++]));return $ret;
                default:throw new RuntimeException("Unknown bytecode opcode $op at ".($pc-1));
            }
        }
        return $ret;
    }
}
