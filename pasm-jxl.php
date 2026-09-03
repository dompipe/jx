<?php declare(strict_types=1);

namespace pasm;

require_once __DIR__ . '/pasm-bytecode.php';

use InvalidArgumentException;
use RuntimeException;

/**
 * PASM semantic profile for prepared JXL.
 *
 * 0x51..0x76 map one-for-one to PASMBC 0x00..0x25. Every cell is exactly
 * six bytes and every operand byte is a JXL attachment (high bit set).
 * MOVI uses a second 0x77 continuation cell so the full signed 64-bit PASM
 * immediate survives without a constant pool or host-dependent encoding.
 */
final class PASMJxlOpcode
{
    public const BASE = 0x51;
    public const MOVI_CONT = 0x77;
    public const FIRST = self::BASE;
    public const LAST = self::MOVI_CONT;

    public static function fromPasm(int $opcode): int
    {
        if ($opcode < PASMBC::HALT || $opcode > PASMBC::JGE) {
            throw new InvalidArgumentException('Unsupported PASM opcode 0x' . dechex($opcode));
        }
        return self::BASE + $opcode;
    }

    public static function toPasm(int $opcode): int
    {
        if ($opcode < self::FIRST || $opcode >= self::MOVI_CONT) {
            throw new InvalidArgumentException('Not a PASM-profile JXL opcode 0x' . dechex($opcode));
        }
        return $opcode - self::BASE;
    }
}

/** Two-pass assembler from canonical PASM assembly to fixed-width JXL. */
final class PASMJxlCompiler
{
    public const CELL_BYTES = 6;
    public const ATTACH = 0x80;
    public const PAYLOAD = 0x7f;
    public const MAX_PAYLOAD35 = 0x7ffffffff;

    /** @var array<string,int> */
    private const OPS = [
        'HALT'=>PASMBC::HALT, 'MOVI'=>PASMBC::MOVI, 'MOVR'=>PASMBC::MOVR,
        'ADD'=>PASMBC::ADD, 'SUB'=>PASMBC::SUB, 'MUL'=>PASMBC::MUL,
        'DIV'=>PASMBC::DIV, 'MOD'=>PASMBC::MOD, 'AND'=>PASMBC::AND,
        'OR'=>PASMBC::OR, 'XOR'=>PASMBC::XOR, 'SHL'=>PASMBC::SHL,
        'SHR'=>PASMBC::SHR, 'CMP'=>PASMBC::CMP, 'JMP'=>PASMBC::JMP,
        'JZ'=>PASMBC::JZ, 'JNZ'=>PASMBC::JNZ, 'PUSH'=>PASMBC::PUSH,
        'POP'=>PASMBC::POP, 'LOAD32'=>PASMBC::LOAD32, 'STORE32'=>PASMBC::STORE32,
        'INC'=>PASMBC::INC, 'DEC'=>PASMBC::DEC, 'NEG'=>PASMBC::NEG,
        'RET'=>PASMBC::RET, 'ITERF'=>PASMBC::ITERF, 'ITERR'=>PASMBC::ITERR,
        'NLOAD'=>PASMBC::NLOAD, 'NSTORE'=>PASMBC::NSTORE,
        'MCALL0'=>PASMBC::MCALL0, 'MCALL1'=>PASMBC::MCALL1,
        'MCALL2'=>PASMBC::MCALL2, 'MCALL3'=>PASMBC::MCALL3,
        'IRESET'=>PASMBC::IRESET, 'JL'=>PASMBC::JL, 'JLE'=>PASMBC::JLE,
        'JG'=>PASMBC::JG, 'JGE'=>PASMBC::JGE,
    ];

    /** @return array<string,int> */
    public static function opcodeTable(): array { return self::OPS; }

    /** @return list<int> */
    public static function supportedPasmOpcodes(): array
    {
        $ops = array_values(self::OPS);
        sort($ops, SORT_NUMERIC);
        return $ops;
    }

    public function compile(string|array $source): string
    {
        $lines = is_array($source) ? $source : (preg_split('/\R/', $source) ?: []);
        $parsed = [];
        $labels = [];
        $oldToJxl = [];
        $oldPc = 0;
        $jxlPc = 0;

        foreach ($lines as $line) {
            $line = trim(preg_replace('/[;#].*$/', '', (string)$line) ?? '');
            if ($line === '') continue;
            if (str_ends_with($line, ':')) {
                $label = strtolower(rtrim($line, ':'));
                if (!preg_match('/^[a-z_][a-z0-9_]*$/', $label)) throw new InvalidArgumentException("Bad PASM label {$label}");
                if (isset($labels[$label])) throw new InvalidArgumentException("Duplicate PASM label {$label}");
                $labels[$label] = $jxlPc;
                continue;
            }
            $tokens = preg_split('/[\s,]+/', $line) ?: [];
            $op = strtoupper((string)array_shift($tokens));
            if (!isset(self::OPS[$op])) throw new InvalidArgumentException("Unknown PASM opcode {$op}");
            $oldToJxl[$oldPc] = $jxlPc;
            $parsed[] = ['old'=>$oldPc, 'jxl'=>$jxlPc, 'op'=>$op, 'args'=>$tokens];
            $oldPc += self::pasmSize($op);
            $jxlPc += $op === 'MOVI' ? 12 : 6;
        }
        $oldToJxl[$oldPc] = $jxlPc;

        $out = '';
        foreach ($parsed as $row) $out .= $this->emit($row['op'], $row['args'], $labels, $oldToJxl);
        return $out;
    }

    public function compileFile(string|array $source, string $path): string
    {
        $bytes = $this->compile($source);
        $dir = dirname($path);
        if ($dir !== '.' && !is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException("Cannot create {$dir}");
        if (file_put_contents($path, $bytes) !== strlen($bytes)) throw new RuntimeException("Cannot write {$path}");
        return $bytes;
    }

    public static function isJxl(string $bytes): bool
    {
        $n = strlen($bytes);
        if ($n === 0 || ($n % self::CELL_BYTES) !== 0) return false;
        $expectCont = false;
        for ($pc=0; $pc<$n; $pc+=self::CELL_BYTES) {
            $op = ord($bytes[$pc]);
            if ($expectCont) {
                if ($op !== PASMJxlOpcode::MOVI_CONT) return false;
                $expectCont = false;
            } else {
                if ($op < PASMJxlOpcode::FIRST || $op >= PASMJxlOpcode::MOVI_CONT) return false;
                if (PASMJxlOpcode::toPasm($op) === PASMBC::MOVI) $expectCont = true;
            }
            for ($i=1;$i<self::CELL_BYTES;$i++) if ((ord($bytes[$pc+$i]) & self::ATTACH) === 0) return false;
        }
        return !$expectCont;
    }

    /** Convert PASM-profile JXL back to canonical PASM assembly for runtime admission. */
    public function toPasmAssembly(string $jxl): string
    {
        if (!self::isJxl($jxl)) throw new InvalidArgumentException('Invalid PASM-profile JXL stream');
        $targets = [];
        for ($pc=0,$n=strlen($jxl); $pc<$n; ) {
            $op = PASMJxlOpcode::toPasm(ord($jxl[$pc]));
            if (in_array($op,[PASMBC::JMP,PASMBC::JZ,PASMBC::JNZ,PASMBC::JL,PASMBC::JLE,PASMBC::JG,PASMBC::JGE],true)) {
                $target = $this->read35($jxl, $pc+1);
                if (($target % self::CELL_BYTES)!==0 || $target<0 || $target>$n) throw new InvalidArgumentException("Bad JXL branch target {$target}");
                $targets[$target] = '__jxl_' . $target;
            }
            $pc += $op === PASMBC::MOVI ? 12 : 6;
        }

        $lines = [];
        for ($pc=0,$n=strlen($jxl); $pc<$n; ) {
            if (isset($targets[$pc])) $lines[] = $targets[$pc] . ':';
            [$text,$next] = $this->decodeText($jxl,$pc,$targets);
            $lines[] = '    ' . $text;
            $pc = $next;
        }
        if (isset($targets[$n])) $lines[] = $targets[$n] . ':';
        return implode("\n",$lines) . "\n";
    }

    /** @param array<string,int> $labels @param array<int,int> $oldToJxl */
    private function emit(string $op, array $a, array $labels, array $oldToJxl): string
    {
        $pasm = self::OPS[$op];
        $jop = PASMJxlOpcode::fromPasm($pasm);
        $reg = fn(string $x): int => PASMBC::regId(strtolower($x));
        $id16 = fn(string $x): int => self::parseRange($x,0,0xffff,'id');
        $byte = fn(string $x): int => self::parseRange($x,0,0xff,'byte');
        $target = function(string $x) use ($labels,$oldToJxl): int {
            $key = strtolower($x);
            if (isset($labels[$key])) return $labels[$key];
            $old = self::parseInt($x);
            if (!array_key_exists($old,$oldToJxl)) throw new InvalidArgumentException("PASM numeric branch target {$old} is not an instruction boundary");
            return $oldToJxl[$old];
        };

        switch ($op) {
            case 'HALT': return $this->cell($jop,0);
            case 'MOVI':
                self::argc($op,$a,2);
                $d=$reg($a[0]); $v=self::parseInt($a[1]);
                $lo=$v & 0xffffffff; $hi=($v >> 32) & 0xffffffff;
                return $this->cell($jop,$d | ($lo << 3)) . $this->cell(PASMJxlOpcode::MOVI_CONT,$hi);
            case 'MOVR': case 'CMP':
                self::argc($op,$a,2); return $this->cell($jop,$reg($a[0]) | ($reg($a[1])<<3));
            case 'ADD': case 'SUB': case 'MUL': case 'DIV': case 'MOD':
            case 'AND': case 'OR': case 'XOR': case 'SHL': case 'SHR':
                self::argc($op,$a,3); return $this->cell($jop,$reg($a[0]) | ($reg($a[1])<<3) | ($reg($a[2])<<6));
            case 'JMP': case 'JZ': case 'JNZ': case 'JL': case 'JLE': case 'JG': case 'JGE':
                self::argc($op,$a,1); return $this->cell($jop,$target($a[0]));
            case 'PUSH': case 'POP': case 'INC': case 'DEC': case 'NEG': case 'RET':
                self::argc($op,$a,1); return $this->cell($jop,$reg($a[0]));
            case 'LOAD32': case 'STORE32':
                if (count($a)<2 || count($a)>3) throw new InvalidArgumentException("{$op} expects 2 or 3 operands");
                $off=$byte($a[2]??'0');
                return $this->cell($jop,$reg($a[0]) | ($reg($a[1])<<3) | ($off<<6));
            case 'ITERF': case 'ITERR': case 'IRESET':
                self::argc($op,$a,1); return $this->cell($jop,$byte($a[0]));
            case 'NLOAD': case 'NSTORE':
                self::argc($op,$a,2); return $this->cell($jop,$reg($a[0]) | ($id16($a[1])<<3));
            case 'MCALL0':
                self::argc($op,$a,2); return $this->cell($jop,$id16($a[0]) | ($reg($a[1])<<16));
            case 'MCALL1':
                self::argc($op,$a,3); return $this->cell($jop,$id16($a[0]) | ($reg($a[1])<<16) | ($reg($a[2])<<19));
            case 'MCALL2':
                self::argc($op,$a,4); return $this->cell($jop,$id16($a[0]) | ($reg($a[1])<<16) | ($reg($a[2])<<19) | ($reg($a[3])<<22));
            case 'MCALL3':
                self::argc($op,$a,5); return $this->cell($jop,$id16($a[0]) | ($reg($a[1])<<16) | ($reg($a[2])<<19) | ($reg($a[3])<<22) | ($reg($a[4])<<25));
        }
        throw new InvalidArgumentException("No JXL lowering for {$op}");
    }

    /** @param array<int,string> $targets @return array{0:string,1:int} */
    private function decodeText(string $jxl,int $pc,array $targets): array
    {
        $pasm=PASMJxlOpcode::toPasm(ord($jxl[$pc]));
        $name=array_search($pasm,self::OPS,true);
        if ($name===false) throw new InvalidArgumentException('Unknown PASM-profile JXL opcode');
        $p=$this->read35($jxl,$pc+1);
        $r=fn(int $id): string=>PASMBC::regName($id&7);
        $next=$pc+6;
        $args=[];

        switch($pasm){
            case PASMBC::HALT: break;
            case PASMBC::MOVI:
                if ($pc+12>strlen($jxl) || ord($jxl[$pc+6])!==PASMJxlOpcode::MOVI_CONT) throw new InvalidArgumentException('MOVI missing JXL continuation');
                $d=$p&7; $lo=($p>>3)&0xffffffff; $hi=$this->read35($jxl,$pc+7)&0xffffffff;
                $value=($hi<<32)|$lo; $args=[$r($d),(string)$value]; $next=$pc+12; break;
            case PASMBC::MOVR: case PASMBC::CMP:
                $args=[$r($p),$r($p>>3)]; break;
            case PASMBC::ADD: case PASMBC::SUB: case PASMBC::MUL: case PASMBC::DIV: case PASMBC::MOD:
            case PASMBC::AND: case PASMBC::OR: case PASMBC::XOR: case PASMBC::SHL: case PASMBC::SHR:
                $args=[$r($p),$r($p>>3),$r($p>>6)]; break;
            case PASMBC::JMP: case PASMBC::JZ: case PASMBC::JNZ: case PASMBC::JL: case PASMBC::JLE: case PASMBC::JG: case PASMBC::JGE:
                $args=[$targets[$p]??('__jxl_'.$p)]; break;
            case PASMBC::PUSH: case PASMBC::POP: case PASMBC::INC: case PASMBC::DEC: case PASMBC::NEG: case PASMBC::RET:
                $args=[$r($p)]; break;
            case PASMBC::LOAD32: case PASMBC::STORE32:
                $args=[$r($p),$r($p>>3),(string)(($p>>6)&0xff)]; break;
            case PASMBC::ITERF: case PASMBC::ITERR: case PASMBC::IRESET:
                $args=[(string)($p&0xff)]; break;
            case PASMBC::NLOAD: case PASMBC::NSTORE:
                $args=[$r($p),(string)(($p>>3)&0xffff)]; break;
            case PASMBC::MCALL0:
                $args=[(string)($p&0xffff),$r($p>>16)]; break;
            case PASMBC::MCALL1:
                $args=[(string)($p&0xffff),$r($p>>16),$r($p>>19)]; break;
            case PASMBC::MCALL2:
                $args=[(string)($p&0xffff),$r($p>>16),$r($p>>19),$r($p>>22)]; break;
            case PASMBC::MCALL3:
                $args=[(string)($p&0xffff),$r($p>>16),$r($p>>19),$r($p>>22),$r($p>>25)]; break;
            default: throw new InvalidArgumentException('Unhandled PASM-profile JXL opcode');
        }
        return [$name . ($args===[]?'':' ' . implode(', ',$args)),$next];
    }

    private function cell(int $opcode,int $payload): string
    {
        if ($opcode<0 || $opcode>=self::ATTACH) throw new InvalidArgumentException('JXL opcode must keep the attachment bit clear');
        if ($payload<0 || $payload>self::MAX_PAYLOAD35) throw new InvalidArgumentException('JXL PASM payload exceeds 35 bits');
        $out=chr($opcode);
        for($i=0;$i<5;$i++){$out.=chr(self::ATTACH|($payload&self::PAYLOAD));$payload>>=7;}
        return $out;
    }

    private function read35(string $bytes,int $offset): int
    {
        $v=0;
        for($i=0;$i<5;$i++)$v|=(ord($bytes[$offset+$i])&self::PAYLOAD)<<($i*7);
        return $v;
    }

    private static function parseInt(string $value): int
    {
        $value=trim($value);
        if (preg_match('/^[+-]?0x[0-9a-f]+$/i',$value)) {
            $negative=str_starts_with($value,'-');
            $hex=ltrim($value,'+-');
            $n=hexdec(substr($hex,2));
            return $negative?-(int)$n:(int)$n;
        }
        if (!preg_match('/^[+-]?\d+$/',$value)) throw new InvalidArgumentException("Expected integer, got {$value}");
        return (int)$value;
    }

    private static function parseRange(string $value,int $min,int $max,string $kind): int
    {
        $n=self::parseInt($value);
        if($n<$min||$n>$max)throw new InvalidArgumentException("{$kind} must be {$min}..{$max}");
        return $n;
    }

    private static function argc(string $op,array $args,int $count): void
    {
        if(count($args)!==$count)throw new InvalidArgumentException("{$op} expects {$count} operand(s)");
    }

    private static function pasmSize(string $op): int
    {
        return match($op){
            'HALT'=>1,'MOVI'=>10,'MOVR','CMP'=>2,
            'ADD','SUB','MUL','DIV','MOD','AND','OR','XOR','SHL','SHR'=>3,
            'JMP','JZ','JNZ','JL','JLE','JG','JGE'=>5,
            'PUSH','POP','INC','DEC','NEG','RET'=>2,
            'LOAD32','STORE32'=>4,'ITERF','ITERR','IRESET'=>2,'NLOAD','NSTORE'=>4,
            'MCALL0','MCALL1'=>4,'MCALL2','MCALL3'=>5,
            default=>throw new InvalidArgumentException("Unknown PASM opcode {$op}"),
        };
    }
}
