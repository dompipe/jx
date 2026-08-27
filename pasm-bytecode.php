<?php declare(strict_types=1);
namespace pasm;

require_once __DIR__ . '/pasm-runtime.php';
require_once __DIR__ . '/pasm-address-abi.php';

use InvalidArgumentException;
use RuntimeException;

final class PASMBC {
    public const HALT=0x00, MOVI=0x01, MOVR=0x02, ADD=0x03, SUB=0x04, MUL=0x05, DIV=0x06, MOD=0x07,
        AND=0x08, OR=0x09, XOR=0x0A, SHL=0x0B, SHR=0x0C, CMP=0x0D, JMP=0x0E, JZ=0x0F, JNZ=0x10,
        PUSH=0x11, POP=0x12, LOAD32=0x13, STORE32=0x14, INC=0x15, DEC=0x16, NEG=0x17, RET=0x18,
        ITERF=0x19, ITERR=0x1A,
        NLOAD=0x1B, NSTORE=0x1C,
        MCALL0=0x1D, MCALL1=0x1E, MCALL2=0x1F, MCALL3=0x20;
    public const REG = ['ecx'=>0,'ah'=>1,'adx'=>2,'bdx'=>3,'cdx'=>4,'ddx'=>5,'edx'=>6,'rdx'=>7];
    public static function regId(string $r): int { if(!array_key_exists($r,self::REG)) throw new InvalidArgumentException("Unsupported bytecode register $r"); return self::REG[$r]; }
    public static function regName(int $id): string { $r=array_search($id,self::REG,true); if($r===false) throw new RuntimeException("Bad register id $id"); return $r; }
}

/** Active PASM assembler with packed register tuples and sorted address IDs. */
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

    private function size(string $op): int { return match($op){
        'HALT'=>1,
        'MOVI'=>10,
        'MOVR','CMP'=>2,
        'ADD','SUB','MUL','DIV','MOD','AND','OR','XOR','SHL','SHR'=>3,
        'JMP','JZ','JNZ'=>5,
        'PUSH','POP','INC','DEC','NEG','RET'=>2,
        'LOAD32','STORE32'=>4,
        'ITERF','ITERR'=>2,
        'NLOAD','NSTORE'=>4,
        'MCALL0','MCALL1'=>4,
        'MCALL2','MCALL3'=>5,
        default=>throw new InvalidArgumentException("Unknown opcode $op")
    }; }

    private function i64(int $v): string { $lo=$v & 0xffffffff; $hi=($v>>32)&0xffffffff; return pack('V2',$lo,$hi); }
    private function id16(string|int $v): int {
        if(is_string($v)){
            $v=trim($v);
            $n=str_starts_with(strtolower($v),'0x') ? hexdec(substr($v,2)) : (is_numeric($v)?(int)$v:-1);
        } else $n=$v;
        if($n<0||$n>0xffff) throw new InvalidArgumentException('Sorted id must fit 16 bits');
        return (int)$n;
    }
    private function idBytes(string|int $v): string { $id=$this->id16($v); return chr(($id>>8)&255).chr($id&255); }
    private function pack1(int $a): string { return chr($a & 7); }
    private function pack2(int $a,int $b): string { return chr(($a&7) | (($b&7)<<3)); }
    private function pack3(int $d,int $a,int $b): string {
        $w=($d&7) | (($a&7)<<3) | (($b&7)<<6);
        return chr($w&255).chr(($w>>8)&255);
    }
    private function pack4(int $d,int $a,int $b,int $c): string {
        $w=($d&7) | (($a&7)<<3) | (($b&7)<<6) | (($c&7)<<9);
        return chr($w&255).chr(($w>>8)&255);
    }

    private function emit(string $op,array $a,array $labels,int $at): string {
        $r=fn(string $x)=>PASMBC::regId(strtolower($x));
        $target=function(string $x)use($labels){$k=strtolower($x); if(isset($labels[$k])) return $labels[$k]; if(is_numeric($x))return (int)$x; throw new InvalidArgumentException("Unknown label $x");};
        return match($op){
            'HALT'=>chr(PASMBC::HALT),
            'MOVI'=>chr(PASMBC::MOVI).chr($r($a[0])).$this->i64((int)$a[1]),
            'MOVR'=>chr(PASMBC::MOVR).$this->pack2($r($a[0]),$r($a[1])),
            'ADD'=>chr(PASMBC::ADD).$this->pack3($r($a[0]),$r($a[1]),$r($a[2])),
            'SUB'=>chr(PASMBC::SUB).$this->pack3($r($a[0]),$r($a[1]),$r($a[2])),
            'MUL'=>chr(PASMBC::MUL).$this->pack3($r($a[0]),$r($a[1]),$r($a[2])),
            'DIV'=>chr(PASMBC::DIV).$this->pack3($r($a[0]),$r($a[1]),$r($a[2])),
            'MOD'=>chr(PASMBC::MOD).$this->pack3($r($a[0]),$r($a[1]),$r($a[2])),
            'AND'=>chr(PASMBC::AND).$this->pack3($r($a[0]),$r($a[1]),$r($a[2])),
            'OR'=>chr(PASMBC::OR).$this->pack3($r($a[0]),$r($a[1]),$r($a[2])),
            'XOR'=>chr(PASMBC::XOR).$this->pack3($r($a[0]),$r($a[1]),$r($a[2])),
            'SHL'=>chr(PASMBC::SHL).$this->pack3($r($a[0]),$r($a[1]),$r($a[2])),
            'SHR'=>chr(PASMBC::SHR).$this->pack3($r($a[0]),$r($a[1]),$r($a[2])),
            'CMP'=>chr(PASMBC::CMP).$this->pack2($r($a[0]),$r($a[1])),
            'JMP'=>chr(PASMBC::JMP).pack('V',$target($a[0])),
            'JZ'=>chr(PASMBC::JZ).pack('V',$target($a[0])),
            'JNZ'=>chr(PASMBC::JNZ).pack('V',$target($a[0])),
            'PUSH'=>chr(PASMBC::PUSH).$this->pack1($r($a[0])),
            'POP'=>chr(PASMBC::POP).$this->pack1($r($a[0])),
            'INC'=>chr(PASMBC::INC).$this->pack1($r($a[0])),
            'DEC'=>chr(PASMBC::DEC).$this->pack1($r($a[0])),
            'NEG'=>chr(PASMBC::NEG).$this->pack1($r($a[0])),
            'RET'=>chr(PASMBC::RET).$this->pack1($r($a[0])),
            'LOAD32'=>chr(PASMBC::LOAD32).chr($r($a[0])).chr($r($a[1])).chr((int)($a[2]??0)&255),
            'STORE32'=>chr(PASMBC::STORE32).chr($r($a[0])).chr($r($a[1])).chr((int)($a[2]??0)&255),
            'ITERF'=>chr(PASMBC::ITERF).chr((int)$a[0]&255),
            'ITERR'=>chr(PASMBC::ITERR).chr((int)$a[0]&255),
            'NLOAD'=>chr(PASMBC::NLOAD).$this->pack1($r($a[0])).$this->idBytes($a[1]),
            'NSTORE'=>chr(PASMBC::NSTORE).$this->pack1($r($a[0])).$this->idBytes($a[1]),
            'MCALL0'=>chr(PASMBC::MCALL0).$this->idBytes($a[0]).$this->pack1($r($a[1])),
            'MCALL1'=>chr(PASMBC::MCALL1).$this->idBytes($a[0]).$this->pack2($r($a[1]),$r($a[2])),
            'MCALL2'=>chr(PASMBC::MCALL2).$this->idBytes($a[0]).$this->pack3($r($a[1]),$r($a[2]),$r($a[3])),
            'MCALL3'=>chr(PASMBC::MCALL3).$this->idBytes($a[0]).$this->pack4($r($a[1]),$r($a[2]),$r($a[3]),$r($a[4])),
        };
    }
}

/** Active mixed PASM VM with direct packed-register/address decoding. */
final class PASMBytecodeVM {
    private array $stack=[];
    private bool $zero=false;

    public function __construct(
        private PASMRuntime $runtime,
        private int $maxInstructions=1_000_000,
        private ?PASMNamedMemory $namedMemory=null,
        private ?PASMMethodABI $methods=null,
        private ?PASMIteratorTable $iterators=null,
    ) {}

    private function u32(string $b,int $p): int { return unpack('V',substr($b,$p,4))[1]; }
    private function i64(string $b,int $p): int { $u=unpack('Vlo/Vhi',substr($b,$p,8)); return ($u['hi']<<32)|$u['lo']; }
    private function id16(string $b,int $p): int { return (ord($b[$p])<<8)|ord($b[$p+1]); }

    /** @param array<int,int> $r */
    private function flush(array $r): void { foreach (PASMBC::REG as $name=>$id) PASM::${$name}=$r[$id]; }
    private function named(): PASMNamedMemory { return $this->namedMemory ?? throw new RuntimeException('Named-memory bytecode requires PASMNamedMemory'); }
    private function methodTable(): PASMMethodABI { return $this->methods ?? throw new RuntimeException('Method bytecode requires PASMMethodABI'); }
    private function iteratorTable(): PASMIteratorTable { return $this->iterators ?? throw new RuntimeException('Iterator bytecode requires PASMIteratorTable'); }

    public function run(string $code): mixed {
        $r=[]; foreach(PASMBC::REG as $name=>$id) $r[$id]=(int)(PASM::${$name}??0); ksort($r); $r=array_values($r);
        $stack=&$this->stack; $zero=&$this->zero; $pc=0; $n=strlen($code); $steps=0; $ret=null;

        while($pc<$n){
            if(++$steps>$this->maxInstructions) throw new RuntimeException('Bytecode instruction limit exceeded');
            $op=ord($code[$pc++]);
            switch($op){
                case PASMBC::HALT: $this->flush($r); return $ret;
                case PASMBC::MOVI: $d=ord($code[$pc++])&7; $r[$d]=$this->i64($code,$pc); $pc+=8; break;
                case PASMBC::MOVR: $w=ord($code[$pc++]); $d=$w&7; $s=($w>>3)&7; $r[$d]=$r[$s]; break;

                case PASMBC::ADD: case PASMBC::SUB: case PASMBC::MUL: case PASMBC::DIV: case PASMBC::MOD:
                case PASMBC::AND: case PASMBC::OR: case PASMBC::XOR: case PASMBC::SHL: case PASMBC::SHR:
                    $w0=ord($code[$pc++]); $w1=ord($code[$pc++]);
                    $d=$w0&7; $a=($w0>>3)&7; $b=(($w0>>6)|($w1<<2))&7; $av=$r[$a]; $bv=$r[$b];
                    switch($op){
                        case PASMBC::ADD:$r[$d]=$av+$bv;break;
                        case PASMBC::SUB:$r[$d]=$av-$bv;break;
                        case PASMBC::MUL:$r[$d]=$av*$bv;break;
                        case PASMBC::DIV:if($bv===0)throw new RuntimeException('Division by zero');$r[$d]=intdiv($av,$bv);break;
                        case PASMBC::MOD:if($bv===0)throw new RuntimeException('Modulo by zero');$r[$d]=$av%$bv;break;
                        case PASMBC::AND:$r[$d]=$av&$bv;break;
                        case PASMBC::OR:$r[$d]=$av|$bv;break;
                        case PASMBC::XOR:$r[$d]=$av^$bv;break;
                        case PASMBC::SHL:$r[$d]=$av<<$bv;break;
                        case PASMBC::SHR:$r[$d]=$av>>$bv;break;
                    } break;

                case PASMBC::CMP: $w=ord($code[$pc++]); $a=$w&7; $b=($w>>3)&7; $zero=($r[$a]===$r[$b]); break;
                case PASMBC::JMP:$pc=$this->u32($code,$pc);break;
                case PASMBC::JZ:$t=$this->u32($code,$pc);$pc+=4;if($zero)$pc=$t;break;
                case PASMBC::JNZ:$t=$this->u32($code,$pc);$pc+=4;if(!$zero)$pc=$t;break;
                case PASMBC::PUSH:$q=ord($code[$pc++])&7;$stack[]=$r[$q];break;
                case PASMBC::POP:$q=ord($code[$pc++])&7;if(!$stack)throw new RuntimeException('Bytecode stack underflow');$r[$q]=array_pop($stack);break;
                case PASMBC::INC:$q=ord($code[$pc++])&7;++$r[$q];break;
                case PASMBC::DEC:$q=ord($code[$pc++])&7;--$r[$q];break;
                case PASMBC::NEG:$q=ord($code[$pc++])&7;$r[$q]=-$r[$q];break;

                case PASMBC::LOAD32:
                    $d=ord($code[$pc++])&7;$base=$r[ord($code[$pc++])&7];$off=ord($code[$pc++]);$r[$d]=$this->runtime->memory->readU32($base+$off);break;
                case PASMBC::STORE32:
                    $s=ord($code[$pc++])&7;$base=$r[ord($code[$pc++])&7];$off=ord($code[$pc++]);$this->runtime->memory->writeU32($base+$off,$r[$s]);break;

                case PASMBC::ITERF: case PASMBC::ITERR:
                    $slot=ord($code[$pc++]); $it=$this->iteratorTable();
                    $item=$op===PASMBC::ITERF?$it->forward($slot):$it->reverse($slot);
                    if($item->valid){
                        $descriptor=$it->descriptor($slot);
                        if($descriptor->valueRegister!==null)$r[$descriptor->valueRegister]=(int)$item->value;
                        if($descriptor->keyRegister!==null)$r[$descriptor->keyRegister]=(int)$item->key;
                    }
                    $ret=$item; $zero=!$item->valid; break;

                case PASMBC::NLOAD:
                    $d=ord($code[$pc++])&7;$id=$this->id16($code,$pc);$pc+=2;$r[$d]=(int)$this->named()->read($id);break;
                case PASMBC::NSTORE:
                    $s=ord($code[$pc++])&7;$id=$this->id16($code,$pc);$pc+=2;$this->named()->write($id,$r[$s]);break;

                case PASMBC::MCALL0:
                    $id=$this->id16($code,$pc);$pc+=2;$w=ord($code[$pc++]);$d=$w&7;
                    $r[$d]=(int)$this->methodTable()->invoke(PASMMethodABI::bytes($id),[]);break;
                case PASMBC::MCALL1:
                    $id=$this->id16($code,$pc);$pc+=2;$w=ord($code[$pc++]);$d=$w&7;$a=($w>>3)&7;
                    $r[$d]=(int)$this->methodTable()->invoke(PASMMethodABI::bytes($id),[$r[$a]]);break;
                case PASMBC::MCALL2:
                    $id=$this->id16($code,$pc);$pc+=2;$w0=ord($code[$pc++]);$w1=ord($code[$pc++]);
                    $d=$w0&7;$a=($w0>>3)&7;$b=(($w0>>6)|($w1<<2))&7;
                    $r[$d]=(int)$this->methodTable()->invoke(PASMMethodABI::bytes($id),[$r[$a],$r[$b]]);break;
                case PASMBC::MCALL3:
                    $id=$this->id16($code,$pc);$pc+=2;$w0=ord($code[$pc++]);$w1=ord($code[$pc++]);$w=$w0|($w1<<8);
                    $d=$w&7;$a=($w>>3)&7;$b=($w>>6)&7;$c=($w>>9)&7;
                    $r[$d]=(int)$this->methodTable()->invoke(PASMMethodABI::bytes($id),[$r[$a],$r[$b],$r[$c]]);break;

                case PASMBC::RET:$q=ord($code[$pc++])&7;$ret=$r[$q];$this->flush($r);return $ret;
                default:throw new RuntimeException("Unknown bytecode opcode $op at ".($pc-1));
            }
        }
        $this->flush($r); return $ret;
    }
}
