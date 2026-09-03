<?php declare(strict_types=1);

namespace jx\semantic;

use InvalidArgumentException;

/**
 * Fixed-width register/control instructions for global prepared JXL.
 *
 * Legacy stack JXL remains 0x00..0x1B. Prepared register JXL occupies the
 * previously-unused 0x20..0x37 range and shares the same six-byte/high-bit
 * attachment law as prepared container JXL (0x40..0x50).
 */
final class JxlPreparedOpcode
{
    public const MOVI = 0x20;
    public const MOV  = 0x21;
    public const ADD  = 0x22;
    public const SUB  = 0x23;
    public const MUL  = 0x24;
    public const DIV  = 0x25;
    public const MOD  = 0x26;
    public const EQ   = 0x27;
    public const NE   = 0x28;
    public const LT   = 0x29;
    public const LE   = 0x2A;
    public const GT   = 0x2B;
    public const GE   = 0x2C;
    public const BAND = 0x2D;
    public const BOR  = 0x2E;
    public const BXOR = 0x2F;
    public const SHL  = 0x30;
    public const SHR  = 0x31;
    public const NEG  = 0x32;
    public const NOT  = 0x33;
    public const JMP  = 0x34;
    public const JZ   = 0x35;
    public const JNZ  = 0x36;
    public const HALT = 0x37;

    /** @return array<int,string> */
    public static function names(): array
    {
        return [
            self::MOVI=>'MOVI', self::MOV=>'MOV',
            self::ADD=>'ADD', self::SUB=>'SUB', self::MUL=>'MUL', self::DIV=>'DIV', self::MOD=>'MOD',
            self::EQ=>'EQ', self::NE=>'NE', self::LT=>'LT', self::LE=>'LE', self::GT=>'GT', self::GE=>'GE',
            self::BAND=>'BAND', self::BOR=>'BOR', self::BXOR=>'BXOR', self::SHL=>'SHL', self::SHR=>'SHR',
            self::NEG=>'NEG', self::NOT=>'NOT',
            self::JMP=>'JMP', self::JZ=>'JZ', self::JNZ=>'JNZ', self::HALT=>'HALT',
        ];
    }

    public static function name(int $opcode): string
    {
        return self::names()[$opcode] ?? throw new InvalidArgumentException('Unknown prepared JXL opcode 0x'.dechex($opcode));
    }

    public static function isPrepared(int $opcode): bool
    {
        return isset(self::names()[$opcode]);
    }

    public static function isBinary(int $opcode): bool
    {
        return in_array($opcode, [
            self::ADD,self::SUB,self::MUL,self::DIV,self::MOD,
            self::EQ,self::NE,self::LT,self::LE,self::GT,self::GE,
            self::BAND,self::BOR,self::BXOR,self::SHL,self::SHR,
        ], true);
    }
}

final class JxlPreparedInstruction
{
    public const BYTES = 6;
    public const ATTACH = 0x80;
    public const PAYLOAD = 0x7F;
    public const UNUSED = 0x7F;
    public const MAX_TARGET = 0x0FFFFFFF; // 28 payload bits
    public const MIN_IMMEDIATE = -0x08000000;
    public const MAX_IMMEDIATE = 0x07FFFFFF;

    public static function movi(int $dst, int $value): string
    {
        self::selector($dst);
        if ($value < self::MIN_IMMEDIATE || $value > self::MAX_IMMEDIATE) {
            throw new InvalidArgumentException('Prepared JXL MOVI currently accepts signed 28-bit integers');
        }
        $zigzag = $value < 0 ? ((-$value << 1) - 1) : ($value << 1);
        return chr(JxlPreparedOpcode::MOVI).self::attachment($dst).self::payload28($zigzag);
    }

    public static function mov(int $dst, int $src): string
    {
        self::selector($dst); self::selector($src);
        return self::fixed(JxlPreparedOpcode::MOV, [$dst,$src]);
    }

    public static function binary(int $opcode, int $dst, int $src0, int $src1): string
    {
        if (!JxlPreparedOpcode::isBinary($opcode)) throw new InvalidArgumentException('Opcode is not prepared binary JXL');
        self::selector($dst); self::selector($src0); self::selector($src1);
        return self::fixed($opcode, [$dst,$src0,$src1]);
    }

    public static function unary(int $opcode, int $dst, int $src): string
    {
        if (!in_array($opcode, [JxlPreparedOpcode::NEG,JxlPreparedOpcode::NOT], true)) {
            throw new InvalidArgumentException('Opcode is not prepared unary JXL');
        }
        self::selector($dst); self::selector($src);
        return self::fixed($opcode, [$dst,$src]);
    }

    public static function jump(int $target): string
    {
        self::target($target);
        return chr(JxlPreparedOpcode::JMP).self::payload28($target).self::attachment(self::UNUSED);
    }

    public static function branch(int $opcode, int $condition, int $target): string
    {
        if (!in_array($opcode, [JxlPreparedOpcode::JZ,JxlPreparedOpcode::JNZ], true)) {
            throw new InvalidArgumentException('Opcode is not prepared branch JXL');
        }
        self::selector($condition); self::target($target);
        return chr($opcode).self::attachment($condition).self::payload28($target);
    }

    public static function halt(): string
    {
        return self::fixed(JxlPreparedOpcode::HALT, []);
    }

    /** Emit a patchable jump whose target payload begins at byte +1. */
    public static function jumpPlaceholder(): string
    {
        return chr(JxlPreparedOpcode::JMP).self::payload28(0).self::attachment(self::UNUSED);
    }

    /** Emit a patchable branch whose 28-bit target payload begins at byte +2. */
    public static function branchPlaceholder(int $opcode, int $condition): string
    {
        if (!in_array($opcode, [JxlPreparedOpcode::JZ,JxlPreparedOpcode::JNZ], true)) {
            throw new InvalidArgumentException('Opcode is not prepared branch JXL');
        }
        self::selector($condition);
        return chr($opcode).self::attachment($condition).self::payload28(0);
    }

    public static function patchTarget(string &$code, int $payloadOffset, int $target): void
    {
        self::target($target);
        if ($payloadOffset < 0 || $payloadOffset + 4 > strlen($code)) throw new InvalidArgumentException('Prepared JXL patch out of range');
        $encoded = self::payload28($target);
        for ($i=0; $i<4; $i++) $code[$payloadOffset+$i] = $encoded[$i];
    }

    /** @return array{opcode:int,name:string,dst:?int,src0:?int,src1:?int,immediate:?int,target:?int,condition:?int,next:int} */
    public static function decode(string $code, int $offset=0): array
    {
        if ($offset < 0 || $offset + self::BYTES > strlen($code)) throw new InvalidArgumentException('Truncated prepared JXL instruction');
        $opcode = ord($code[$offset]);
        if (($opcode & self::ATTACH) !== 0 || !JxlPreparedOpcode::isPrepared($opcode)) {
            throw new InvalidArgumentException('Not a prepared register JXL opcode');
        }
        for ($i=1; $i<self::BYTES; $i++) if ((ord($code[$offset+$i]) & self::ATTACH) === 0) {
            throw new InvalidArgumentException('Prepared JXL operand must have attachment bit set');
        }

        $dst=$src0=$src1=$immediate=$target=$condition=null;
        if ($opcode === JxlPreparedOpcode::MOVI) {
            $dst=self::decodeSelector(ord($code[$offset+1]));
            $z=self::read28($code,$offset+2);
            $immediate=($z & 1) ? -(int)(($z+1)>>1) : (int)($z>>1);
        } elseif ($opcode === JxlPreparedOpcode::MOV) {
            $dst=self::decodeSelector(ord($code[$offset+1]));
            $src0=self::decodeSelector(ord($code[$offset+2]));
        } elseif (JxlPreparedOpcode::isBinary($opcode)) {
            $dst=self::decodeSelector(ord($code[$offset+1]));
            $src0=self::decodeSelector(ord($code[$offset+2]));
            $src1=self::decodeSelector(ord($code[$offset+3]));
        } elseif (in_array($opcode,[JxlPreparedOpcode::NEG,JxlPreparedOpcode::NOT],true)) {
            $dst=self::decodeSelector(ord($code[$offset+1]));
            $src0=self::decodeSelector(ord($code[$offset+2]));
        } elseif ($opcode === JxlPreparedOpcode::JMP) {
            $target=self::read28($code,$offset+1);
        } elseif (in_array($opcode,[JxlPreparedOpcode::JZ,JxlPreparedOpcode::JNZ],true)) {
            $condition=self::decodeSelector(ord($code[$offset+1]));
            $target=self::read28($code,$offset+2);
        }
        return [
            'opcode'=>$opcode,'name'=>JxlPreparedOpcode::name($opcode),
            'dst'=>$dst,'src0'=>$src0,'src1'=>$src1,
            'immediate'=>$immediate,'target'=>$target,'condition'=>$condition,
            'next'=>$offset+self::BYTES,
        ];
    }

    /** @param list<int> $selectors */
    private static function fixed(int $opcode, array $selectors): string
    {
        $out=chr($opcode);
        foreach ($selectors as $selector) $out.=self::attachment($selector);
        while (strlen($out)<self::BYTES) $out.=self::attachment(self::UNUSED);
        return $out;
    }

    private static function attachment(int $payload): string
    {
        if ($payload<0 || $payload>self::PAYLOAD) throw new InvalidArgumentException('Prepared JXL attachment payload out of range');
        return chr(self::ATTACH|$payload);
    }

    private static function payload28(int $value): string
    {
        if ($value<0 || $value>self::MAX_TARGET) throw new InvalidArgumentException('Prepared JXL 28-bit payload out of range');
        $out='';
        for ($i=0;$i<4;$i++) {$out.=self::attachment($value & self::PAYLOAD);$value>>=7;}
        return $out;
    }

    private static function read28(string $code,int $offset): int
    {
        $value=0;
        for ($i=0;$i<4;$i++) $value|=(ord($code[$offset+$i]) & self::PAYLOAD) << ($i*7);
        return $value;
    }

    private static function decodeSelector(int $byte): int
    {
        $selector=$byte & self::PAYLOAD;
        self::selector($selector);
        return $selector;
    }

    private static function selector(int $selector): void
    {
        if ($selector<0 || $selector>7) throw new InvalidArgumentException('Prepared JXL selector must address R0..R7');
    }

    private static function target(int $target): void
    {
        if ($target<0 || $target>self::MAX_TARGET || ($target % self::BYTES)!==0) {
            throw new InvalidArgumentException('Prepared JXL branch target must be a six-byte-aligned 28-bit byte offset');
        }
    }
}
