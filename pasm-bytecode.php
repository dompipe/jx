<?php declare(strict_types=1);
namespace pasm;

require_once __DIR__ . '/pasm-runtime.php';

use InvalidArgumentException;
use RuntimeException;

final class PASMBC {
    public const HALT=0x00, MOVI=0x01, MOVR=0x02, ADD=0x03, SUB=0x04, MUL=0x05, DIV=0x06, MOD=0x07,
        AND=0x08, OR=0x09, XOR=0x0A, SHL=0x0B, SHR=0x0C, CMP=0x0D, JMP=0x0E, JZ=0x0F, JNZ=0x10,
        PUSH=0x11, POP=0x12, LOAD32=0x13, STORE32=0x14, INC=0x15, DEC=0x16, NEG=0x17, RET=0x18;
    public const REG = ['ecx'=>0,'ah'=>1,'adx'=>2,'bdx'=>3,'cdx'=>4,'ddx'=>5,'edx'=>6,'rdx'=>7];
    public static function regId(string $r): int { if(!array_key_exists($r,self::REG)) throw new InvalidArgumentException("Unsupported bytecode register $r"); return self::REG[$r]; }
    public static function regName(int $id): string { $r=array_search($id,self::REG,true); if($r===false) throw new RuntimeException("Bad register id $id"); return $r; }
}

/** Tiny assembler. Syntax: LABEL:, MOVI ecx 42, ADD adx ecx ah, JNZ label, RET adx */
final class PASMAssembler {
    public function compile(string|array $source): string {
        $lines=is_array($source)?$source:preg_split('/\R/', $source);
        $parsed=[]; $labels=[]; $pc=0;
        foreach($lines as $line){
            $line=trim(preg_replace('/[;#].*$/','',(string)$line)); if($line==='') continue;
            if(str_ends_with($line,':')){$labels[strtolower(rtrim($line,':'))]=$pc; continue;}
            $t=preg_split('/[\s,]+/',$line); $op=strtoupper(array_shift($t)); $size=$this->size($op); $parsed[]=[$pc,$op,$t]; $pc+=$size;
        }
        $out=''; foreach($parsed as [$at,$op,$a]) $out.=$this->emit($op,$a,$labels,$at); return $out;
    }
    private function size(string $op): int { return match($op){'HALT'=>1,'MOVI'=>10,'MOVR'=>3,'ADD','SUB','MUL','DIV','MOD','AND','OR','XOR','SHL','SHR'=>4,'CMP'=>3,'JMP','JZ','JNZ'=>5,'PUSH','POP','INC','DEC','NEG','RET'=>2,'LOAD32','STORE32'=>4,default=>throw new InvalidArgumentException("Unknown opcode $op")}; }
    private function i64(int $v): string { $lo=$v & 0xffffffff; $hi=($v>>32)&0xffffffff; return pack('V2',$lo,$hi); }
    private function emit(string $op,array $a,array $labels,int $at): string {
        $r=fn(string $x)=>PASMBC::regId(strtolower($x));
        $target=function(string $x)use($labels){$k=strtolower($x); if(isset($labels[$k])) return $labels[$k]; if(is_numeric($x))return (int)$x; throw new InvalidArgumentException("Unknown label $x");};
        return match($op){
            'HALT'=>chr(PASMBC::HALT),
            'MOVI'=>chr(PASMBC::MOVI).chr($r($a[0])).$this->i64((int)$a[1]),
            'MOVR'=>chr(PASMBC::MOVR).chr($r($a[0])).chr($r($a[1])),
            'ADD'=>chr(PASMBC::ADD).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'SUB'=>chr(PASMBC::SUB).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'MUL'=>chr(PASMBC::MUL).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'DIV'=>chr(PASMBC::DIV).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'MOD'=>chr(PASMBC::MOD).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'AND'=>chr(PASMBC::AND).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])), 'OR'=>chr(PASMBC::OR).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])), 'XOR'=>chr(PASMBC::XOR).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])), 'SHL'=>chr(PASMBC::SHL).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])), 'SHR'=>chr(PASMBC::SHR).chr($r($a[0])).chr($r($a[1])).chr($r($a[2])),
            'CMP'=>chr(PASMBC::CMP).chr($r($a[0])).chr($r($a[1])),
            'JMP'=>chr(PASMBC::JMP).pack('V',$target($a[0])), 'JZ'=>chr(PASMBC::JZ).pack('V',$target($a[0])), 'JNZ'=>chr(PASMBC::JNZ).pack('V',$target($a[0])),
            'PUSH'=>chr(PASMBC::PUSH).chr($r($a[0])), 'POP'=>chr(PASMBC::POP).chr($r($a[0])), 'INC'=>chr(PASMBC::INC).chr($r($a[0])), 'DEC'=>chr(PASMBC::DEC).chr($r($a[0])), 'NEG'=>chr(PASMBC::NEG).chr($r($a[0])), 'RET'=>chr(PASMBC::RET).chr($r($a[0])),
            'LOAD32'=>chr(PASMBC::LOAD32).chr($r($a[0])).chr($r($a[1])).chr((int)($a[2]??0)&255),
            'STORE32'=>chr(PASMBC::STORE32).chr($r($a[0])).chr($r($a[1])).chr((int)($a[2]??0)&255),
        };
    }
}

final class PASMBytecodeVM {
    private array $stack=[]; private bool $zero=false;
    public function __construct(private PASMRuntime $runtime, private int $maxInstructions=1_000_000) {}
    private function get(int $id): int { $r=PASMBC::regName($id); return (int)(PASM::${$r}??0); }
    private function set(int $id,int $v): int { $r=PASMBC::regName($id); return PASM::${$r}=$v; }
    private function u32(string $b,int $p): int { return unpack('V',substr($b,$p,4))[1]; }
    private function i64(string $b,int $p): int { $u=unpack('Vlo/Vhi',substr($b,$p,8)); return ($u['hi']<<32)|$u['lo']; }
    public function run(string $code): mixed {
        $pc=0;$n=strlen($code);$steps=0;$ret=null;
        while($pc<$n){ if(++$steps>$this->maxInstructions) throw new RuntimeException('Bytecode instruction limit exceeded'); $op=ord($code[$pc++]);
            switch($op){
                case PASMBC::HALT:return $ret;
                case PASMBC::MOVI:$d=ord($code[$pc++]);$this->set($d,$this->i64($code,$pc));$pc+=8;break;
                case PASMBC::MOVR:$d=ord($code[$pc++]);$s=ord($code[$pc++]);$this->set($d,$this->get($s));break;
                case PASMBC::ADD:case PASMBC::SUB:case PASMBC::MUL:case PASMBC::DIV:case PASMBC::MOD:case PASMBC::AND:case PASMBC::OR:case PASMBC::XOR:case PASMBC::SHL:case PASMBC::SHR:
                    $d=ord($code[$pc++]);$a=$this->get(ord($code[$pc++]));$b=$this->get(ord($code[$pc++]));
                    $v=match($op){PASMBC::ADD=>$a+$b,PASMBC::SUB=>$a-$b,PASMBC::MUL=>$a*$b,PASMBC::DIV=>$b===0?throw new RuntimeException('Division by zero'):intdiv($a,$b),PASMBC::MOD=>$b===0?throw new RuntimeException('Modulo by zero'):$a%$b,PASMBC::AND=>$a&$b,PASMBC::OR=>$a|$b,PASMBC::XOR=>$a^$b,PASMBC::SHL=>$a<<$b,PASMBC::SHR=>$a>>$b}; $this->set($d,$v);break;
                case PASMBC::CMP:$a=$this->get(ord($code[$pc++]));$b=$this->get(ord($code[$pc++]));$this->zero=($a===$b);break;
                case PASMBC::JMP:$pc=$this->u32($code,$pc);break; case PASMBC::JZ:$t=$this->u32($code,$pc);$pc+=4;if($this->zero)$pc=$t;break; case PASMBC::JNZ:$t=$this->u32($code,$pc);$pc+=4;if(!$this->zero)$pc=$t;break;
                case PASMBC::PUSH:$this->stack[]=$this->get(ord($code[$pc++]));break; case PASMBC::POP:if(!$this->stack)throw new RuntimeException('Bytecode stack underflow');$this->set(ord($code[$pc++]),array_pop($this->stack));break;
                case PASMBC::INC:$id=ord($code[$pc++]);$this->set($id,$this->get($id)+1);break; case PASMBC::DEC:$id=ord($code[$pc++]);$this->set($id,$this->get($id)-1);break; case PASMBC::NEG:$id=ord($code[$pc++]);$this->set($id,-$this->get($id));break;
                case PASMBC::LOAD32:$d=ord($code[$pc++]);$base=$this->get(ord($code[$pc++]));$off=ord($code[$pc++]);$this->set($d,$this->runtime->memory->readU32($base+$off));break;
                case PASMBC::STORE32:$s=ord($code[$pc++]);$base=$this->get(ord($code[$pc++]));$off=ord($code[$pc++]);$this->runtime->memory->writeU32($base+$off,$this->get($s));break;
                case PASMBC::RET:$ret=$this->get(ord($code[$pc++]));return $ret;
                default:throw new RuntimeException("Unknown bytecode opcode $op at ".($pc-1));
            }
        } return $ret;
    }
}
