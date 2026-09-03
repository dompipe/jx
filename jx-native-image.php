<?php declare(strict_types=1);

namespace jx;

use InvalidArgumentException;
use RuntimeException;

/**
 * Shared native Jinx image used by both .jxl and .jll.
 *
 * .jxl = image with an executable CODE-relative entrypoint.
 * .jll = the same image format, normally without an entrypoint.
 *
 * The file is intentionally mmap-friendly: fixed header, fixed-size binary
 * section directory, native CODE/DATA payloads, and compact binary export /
 * signature tables backed by one STRINGS section. No JSON parser is required
 * merely to load a JLL and resolve a public function.
 */
final class JxNativeImage
{
    public const MAGIC = "JXNI\x01\x00\x00\x00";
    public const VERSION = 1;
    public const HEADER_SIZE = 40;
    public const DIRECTORY_ENTRY_SIZE = 32;

    public const FLAG_EXECUTABLE = 0x0001;
    public const FLAG_LIBRARY = 0x0002;
    public const FLAG_EXPORTS = 0x0004;
    public const FLAG_IMPORTS = 0x0008;
    public const FLAG_RELOCATABLE = 0x0010;

    public const ARCH_X86_64_SYSV = 1;
    public const ARCH_X86_64_WIN64 = 2;
    public const ARCH_AARCH64 = 3;

    /** @var array<string,string> */
    private array $sections = [];
    /** @var list<array{name:string,offset:int,signature:int,flags:int}> */
    private array $exports = [];
    /** @var list<array{params:list<string>,return:string}> */
    private array $signatures = [];

    public function __construct(
        private int $architecture = self::ARCH_X86_64_SYSV,
        private ?int $entrypoint = null,
        private int $flags = 0,
    ) {
        if ($entrypoint !== null) {
            if ($entrypoint < 0) throw new InvalidArgumentException('Entrypoint cannot be negative');
            $this->flags = ($this->flags | self::FLAG_EXECUTABLE) & ~self::FLAG_LIBRARY;
        } else {
            $this->flags = ($this->flags | self::FLAG_LIBRARY) & ~self::FLAG_EXECUTABLE;
        }
    }

    public static function executable(string $code, int $entrypoint = 0, int $architecture = self::ARCH_X86_64_SYSV): self
    {
        $image = new self($architecture, $entrypoint, self::FLAG_EXECUTABLE);
        return $image->section('CODE', $code);
    }

    public static function library(string $code, int $architecture = self::ARCH_X86_64_SYSV): self
    {
        $image = new self($architecture, null, self::FLAG_LIBRARY | self::FLAG_RELOCATABLE);
        return $image->section('CODE', $code);
    }

    public function section(string $name, string $bytes): self
    {
        $name = strtoupper(trim($name));
        if (!preg_match('/^[A-Z0-9_]{1,16}$/', $name)) throw new InvalidArgumentException('Bad native image section name');
        $this->sections[$name] = $bytes;
        return $this;
    }

    /** @param list<string> $params */
    public function signature(array $params, string $return = 'void'): int
    {
        $normalized = [
            'params' => array_values(array_map(static fn(mixed $v): string => trim((string)$v), $params)),
            'return' => trim($return),
        ];
        if ($normalized['return'] === '') throw new InvalidArgumentException('Return type cannot be empty');
        foreach ($normalized['params'] as $p) if ($p === '') throw new InvalidArgumentException('Parameter type cannot be empty');
        foreach ($this->signatures as $i=>$existing) if ($existing === $normalized) return $i;
        $this->signatures[] = $normalized;
        return count($this->signatures) - 1;
    }

    /** @param list<string> $params */
    public function export(string $name, int $codeOffset, array $params = [], string $return = 'void', int $flags = 0): self
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name)) throw new InvalidArgumentException("Bad export name {$name}");
        if ($codeOffset < 0) throw new InvalidArgumentException('Export offset cannot be negative');
        foreach ($this->exports as $existing) if ($existing['name'] === $name) throw new InvalidArgumentException("Duplicate export {$name}");
        $signature = $this->signature($params, $return);
        $this->exports[] = ['name'=>$name,'offset'=>$codeOffset,'signature'=>$signature,'flags'=>$flags];
        $this->flags |= self::FLAG_EXPORTS;
        return $this;
    }

    public function encode(): string
    {
        if (!isset($this->sections['CODE'])) throw new RuntimeException('Native image requires CODE section');
        $codeSize = strlen($this->sections['CODE']);
        if ($this->entrypoint !== null && $this->entrypoint >= $codeSize) throw new RuntimeException('Entrypoint lies outside CODE section');
        foreach ($this->exports as $export) if ($export['offset'] >= $codeSize) throw new RuntimeException("Export {$export['name']} lies outside CODE section");

        $sections = $this->sections;
        if ($this->exports !== [] || $this->signatures !== []) {
            [$strings,$signatureBytes,$exportBytes] = $this->encodePublicContract();
            $sections['STRINGS'] = $strings;
            $sections['SIGNATURES'] = $signatureBytes;
            $sections['EXPORTS'] = $exportBytes;
        }

        $directoryBytes = '';
        $payload = '';
        foreach ($sections as $name=>$bytes) {
            $offset = strlen($payload);
            $size = strlen($bytes);
            $directoryBytes .= str_pad($name, 16, "\0") . self::u64($offset) . self::u64($size);
            $payload .= $bytes;
        }

        $entryBytes = $this->entrypoint === null ? pack('V2',0xffffffff,0xffffffff) : self::u64($this->entrypoint);
        $header = self::MAGIC
            . pack('V', self::VERSION)
            . pack('V', $this->architecture)
            . pack('V', $this->flags)
            . pack('V', count($sections))
            . $entryBytes
            . pack('V', strlen($directoryBytes))
            . pack('V', 0);

        if (strlen($header) !== self::HEADER_SIZE) throw new RuntimeException('Internal native-image header size error');
        return $header . $directoryBytes . $payload;
    }

    /** @return array{0:string,1:string,2:string} */
    private function encodePublicContract(): array
    {
        $strings = "\0";
        /** @var array<string,int> $stringOffsets */
        $stringOffsets = [''=>0];
        $intern = static function(string $text) use (&$strings,&$stringOffsets): int {
            if (str_contains($text,"\0")) throw new InvalidArgumentException('Native image strings cannot contain NUL');
            if (isset($stringOffsets[$text])) return $stringOffsets[$text];
            $offset = strlen($strings);
            $stringOffsets[$text] = $offset;
            $strings .= $text . "\0";
            return $offset;
        };

        $signatureBytes = pack('V',count($this->signatures));
        foreach ($this->signatures as $sig) {
            $signatureBytes .= pack('V',$intern($sig['return'])) . pack('v',count($sig['params'])) . pack('v',0);
            foreach ($sig['params'] as $param) $signatureBytes .= pack('V',$intern($param));
        }

        $exportBytes = pack('V',count($this->exports));
        foreach ($this->exports as $export) {
            $exportBytes .= pack('V',$intern($export['name']))
                . pack('V',$export['signature'])
                . self::u64($export['offset'])
                . pack('V',$export['flags'])
                . pack('V',0);
        }
        return [$strings,$signatureBytes,$exportBytes];
    }

    /** @return array{version:int,architecture:int,flags:int,entrypoint:?int,sections:array<string,string>,exports:list<array{name:string,offset:int,signature:int,flags:int}>,signatures:list<array{params:list<string>,return:string}>} */
    public static function decode(string $bytes): array
    {
        if (strlen($bytes) < self::HEADER_SIZE || substr($bytes,0,8) !== self::MAGIC) throw new RuntimeException('Not a JX native image');
        $h = unpack('Vversion/Varchitecture/Vflags/Vcount/VentryLo/VentryHi/VdirSize/Vreserved', substr($bytes,8,32));
        if (!is_array($h) || $h['version'] !== self::VERSION) throw new RuntimeException('Unsupported JX native image version');
        if ($h['dirSize'] !== $h['count'] * self::DIRECTORY_ENTRY_SIZE) throw new RuntimeException('Corrupt native image directory size');
        $entrypoint = ($h['entryHi'] === 0xffffffff && $h['entryLo'] === 0xffffffff)
            ? null
            : self::joinU64($h['entryLo'],$h['entryHi']);

        $dirStart = self::HEADER_SIZE;
        $payloadStart = $dirStart + $h['dirSize'];
        if ($payloadStart > strlen($bytes)) throw new RuntimeException('Native image directory exceeds file size');
        $sections = [];
        for ($i=0;$i<$h['count'];$i++) {
            $at = $dirStart + $i*self::DIRECTORY_ENTRY_SIZE;
            $name = rtrim(substr($bytes,$at,16),"\0");
            if ($name === '' || !preg_match('/^[A-Z0-9_]{1,16}$/',$name)) throw new RuntimeException('Corrupt native image section name');
            if (isset($sections[$name])) throw new RuntimeException("Duplicate native image section {$name}");
            $offset = self::readU64($bytes,$at+16);
            $size = self::readU64($bytes,$at+24);
            if ($offset < 0 || $size < 0 || $payloadStart+$offset+$size > strlen($bytes)) throw new RuntimeException("Native image section {$name} is out of range");
            $sections[$name] = substr($bytes,$payloadStart+$offset,$size);
        }
        if (!isset($sections['CODE'])) throw new RuntimeException('Native image has no CODE section');
        if ($entrypoint !== null && $entrypoint >= strlen($sections['CODE'])) throw new RuntimeException('Native image entrypoint lies outside CODE');

        $signatures = isset($sections['SIGNATURES'],$sections['STRINGS']) ? self::decodeSignatures($sections['SIGNATURES'],$sections['STRINGS']) : [];
        $exports = isset($sections['EXPORTS'],$sections['STRINGS']) ? self::decodeExports($sections['EXPORTS'],$sections['STRINGS'],$signatures,strlen($sections['CODE'])) : [];
        return [
            'version'=>$h['version'],'architecture'=>$h['architecture'],'flags'=>$h['flags'],'entrypoint'=>$entrypoint,
            'sections'=>$sections,'exports'=>$exports,'signatures'=>$signatures,
        ];
    }

    /** @return list<array{params:list<string>,return:string}> */
    private static function decodeSignatures(string $bytes,string $strings): array
    {
        if (strlen($bytes)<4) throw new RuntimeException('Corrupt SIGNATURES section');
        $count=self::readU32($bytes,0);$at=4;$out=[];
        for($i=0;$i<$count;$i++){
            if($at+8>strlen($bytes))throw new RuntimeException('Truncated signature record');
            $returnOffset=self::readU32($bytes,$at);$paramCount=self::readU16($bytes,$at+4);$at+=8;
            $params=[];
            for($p=0;$p<$paramCount;$p++){
                if($at+4>strlen($bytes))throw new RuntimeException('Truncated signature parameters');
                $params[]=self::stringAt($strings,self::readU32($bytes,$at));$at+=4;
            }
            $out[]=['params'=>$params,'return'=>self::stringAt($strings,$returnOffset)];
        }
        if($at!==strlen($bytes))throw new RuntimeException('Trailing bytes in SIGNATURES section');
        return$out;
    }

    /** @param list<array{params:list<string>,return:string}> $signatures @return list<array{name:string,offset:int,signature:int,flags:int}> */
    private static function decodeExports(string $bytes,string $strings,array $signatures,int $codeSize): array
    {
        if(strlen($bytes)<4)throw new RuntimeException('Corrupt EXPORTS section');
        $count=self::readU32($bytes,0);$expected=4+$count*24;
        if(strlen($bytes)!==$expected)throw new RuntimeException('Corrupt EXPORTS record size');
        $out=[];$at=4;$seen=[];
        for($i=0;$i<$count;$i++,$at+=24){
            $name=self::stringAt($strings,self::readU32($bytes,$at));
            $sig=self::readU32($bytes,$at+4);$offset=self::readU64($bytes,$at+8);$flags=self::readU32($bytes,$at+16);
            if(!isset($signatures[$sig]))throw new RuntimeException("Export {$name} references missing signature {$sig}");
            if($offset>=$codeSize)throw new RuntimeException("Export {$name} lies outside CODE");
            if(isset($seen[$name]))throw new RuntimeException("Duplicate export {$name}");$seen[$name]=true;
            $out[]=['name'=>$name,'offset'=>$offset,'signature'=>$sig,'flags'=>$flags];
        }
        return$out;
    }

    private static function stringAt(string $strings,int $offset): string
    {
        if($offset<0||$offset>=strlen($strings))throw new RuntimeException('String-table offset out of range');
        $end=strpos($strings,"\0",$offset);if($end===false)throw new RuntimeException('Unterminated string-table value');
        return substr($strings,$offset,$end-$offset);
    }

    private static function u64(int $value): string
    {
        if ($value < 0) throw new InvalidArgumentException('u64 value cannot be negative');
        return pack('V2', $value & 0xffffffff, ($value >> 32) & 0xffffffff);
    }
    private static function readU16(string $bytes,int $at): int
    {
        $u=unpack('vvalue',substr($bytes,$at,2));if(!is_array($u))throw new RuntimeException('Cannot decode u16');return(int)$u['value'];
    }
    private static function readU32(string $bytes,int $at): int
    {
        $u=unpack('Vvalue',substr($bytes,$at,4));if(!is_array($u))throw new RuntimeException('Cannot decode u32');return(int)$u['value'];
    }
    private static function readU64(string $bytes,int $at): int
    {
        $u=unpack('Vlo/Vhi',substr($bytes,$at,8));if(!is_array($u))throw new RuntimeException('Cannot decode u64');return self::joinU64((int)$u['lo'],(int)$u['hi']);
    }
    private static function joinU64(int $lo,int $hi): int
    {
        if($hi>0x7fffffff)throw new RuntimeException('u64 value exceeds signed PHP integer range');
        return ($hi<<32)|$lo;
    }
}
