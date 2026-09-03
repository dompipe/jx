<?php declare(strict_types=1);

namespace jx;

use InvalidArgumentException;
use RuntimeException;

/**
 * Shared native Jinx image used by both .jxl and .jll.
 *
 * .jxl = image with an executable entrypoint.
 * .jll = the same image format, normally without an entrypoint.
 *
 * Public functions are self-describing through EXPORTS + SIGNATURES. Private
 * functions need not be present in either table.
 */
final class JxNativeImage
{
    public const MAGIC = "JXNI\x01\x00\x00\x00";
    public const VERSION = 1;

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
        if ($entrypoint !== null) $this->flags |= self::FLAG_EXECUTABLE;
        else $this->flags |= self::FLAG_LIBRARY;
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
        $normalized = ['params'=>array_values(array_map('strval',$params)), 'return'=>(string)$return];
        foreach ($this->signatures as $i=>$existing) if ($existing === $normalized) return $i;
        $this->signatures[] = $normalized;
        return count($this->signatures) - 1;
    }

    /** @param list<string> $params */
    public function export(string $name, int $codeOffset, array $params = [], string $return = 'void', int $flags = 0): self
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name)) throw new InvalidArgumentException("Bad export name {$name}");
        if ($codeOffset < 0) throw new InvalidArgumentException('Export offset cannot be negative');
        $signature = $this->signature($params, $return);
        $this->exports[] = ['name'=>$name,'offset'=>$codeOffset,'signature'=>$signature,'flags'=>$flags];
        $this->flags |= self::FLAG_EXPORTS;
        return $this;
    }

    public function encode(): string
    {
        if (!isset($this->sections['CODE'])) throw new RuntimeException('Native image requires CODE section');

        if ($this->signatures !== []) $this->sections['SIGNATURES'] = self::json($this->signatures);
        if ($this->exports !== []) $this->sections['EXPORTS'] = self::json($this->exports);

        $directory = [];
        $payload = '';
        foreach ($this->sections as $name=>$bytes) {
            $directory[] = ['name'=>$name,'offset'=>strlen($payload),'size'=>strlen($bytes)];
            $payload .= $bytes;
        }
        $directoryBytes = self::json($directory);

        // Fixed 40-byte header. All integers little-endian u32 except entrypoint u64.
        $entryBytes = $this->entrypoint === null ? pack('V2',0xffffffff,0xffffffff) : self::u64($this->entrypoint);
        $header = self::MAGIC
            . pack('V', self::VERSION)
            . pack('V', $this->architecture)
            . pack('V', $this->flags)
            . pack('V', count($directory))
            . $entryBytes
            . pack('V', strlen($directoryBytes))
            . pack('V', 0);

        return $header . $directoryBytes . $payload;
    }

    /** @return array{version:int,architecture:int,flags:int,entrypoint:?int,sections:array<string,string>,exports:list<array{name:string,offset:int,signature:int,flags:int}>,signatures:list<array{params:list<string>,return:string}>} */
    public static function decode(string $bytes): array
    {
        if (strlen($bytes) < 40 || substr($bytes,0,8) !== self::MAGIC) throw new RuntimeException('Not a JX native image');
        $h = unpack('Vversion/Varchitecture/Vflags/Vcount/VentryLo/VentryHi/VdirSize/Vreserved', substr($bytes,8,32));
        if (!is_array($h) || $h['version'] !== self::VERSION) throw new RuntimeException('Unsupported JX native image version');
        $entrypoint = ($h['entryHi'] === 0xffffffff && $h['entryLo'] === 0xffffffff)
            ? null
            : (($h['entryHi'] << 32) | $h['entryLo']);
        $dirStart = 40;
        $dir = json_decode(substr($bytes,$dirStart,$h['dirSize']), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($dir) || count($dir) !== $h['count']) throw new RuntimeException('Corrupt native image directory');
        $payloadStart = $dirStart + $h['dirSize'];
        $sections = [];
        foreach ($dir as $row) {
            if (!is_array($row) || !isset($row['name'],$row['offset'],$row['size'])) throw new RuntimeException('Corrupt native section entry');
            $sections[(string)$row['name']] = substr($bytes,$payloadStart+(int)$row['offset'],(int)$row['size']);
        }
        $signatures = isset($sections['SIGNATURES']) ? json_decode($sections['SIGNATURES'],true,512,JSON_THROW_ON_ERROR) : [];
        $exports = isset($sections['EXPORTS']) ? json_decode($sections['EXPORTS'],true,512,JSON_THROW_ON_ERROR) : [];
        return [
            'version'=>$h['version'],'architecture'=>$h['architecture'],'flags'=>$h['flags'],'entrypoint'=>$entrypoint,
            'sections'=>$sections,'exports'=>is_array($exports)?$exports:[],'signatures'=>is_array($signatures)?$signatures:[],
        ];
    }

    private static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function u64(int $value): string
    {
        if ($value < 0) throw new InvalidArgumentException('u64 value cannot be negative');
        return pack('V2', $value & 0xffffffff, ($value >> 32) & 0xffffffff);
    }
}
