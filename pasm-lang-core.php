<?php declare(strict_types=1);
namespace pasm\lang;
foreach ([__DIR__, dirname(__DIR__)] as $base) {
    foreach (['pasm-runtime.php', 'pasm-bytecode.php', 'pasm-bytecode-optimized.php'] as $f) {
        $p = $base . '/' . $f;
        if (is_file($p)) require_once $p;
    }
}
use pasm\{PASMBC, PASMAssembler, PASMOptimizingAssembler, PASMBytecodeVM, PASMOptimizedBytecodeVM, PASMRuntime};
use InvalidArgumentException; use RuntimeException; use Throwable;

class LangException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $phase = 'compile',
        public readonly ?int $sourceLine = null,
        ?Throwable $previous = null,
    ) {
        $loc = $sourceLine !== null ? " line {$sourceLine}" : '';
        parent::__construct("[PASL:{$phase}{$loc}] {$message}", 0, $previous);
    }
}

final class Complex
{
    public function __construct(public int $re = 0, public int $im = 0) {}

    public static function parse(string $s): self
    {
        $s = str_replace(' ', '', strtolower(trim($s)));
        if ($s === '' || $s === '0') return new self(0, 0);
        if (preg_match('/^([+-]?\d+)?([+-]\d*)i$/', $s, $m)) {
            $re = $m[1] === '' || $m[1] === null ? 0 : (int)$m[1];
            $imPart = $m[2];
            if ($imPart === '+' || $imPart === '') $im = 1;
            elseif ($imPart === '-') $im = -1;
            else $im = (int)$imPart;
            return new self($re, $im);
        }
        if ($s === 'i') return new self(0, 1);
        if ($s === '-i') return new self(0, -1);
        if (preg_match('/^[+-]?\d+$/', $s)) return new self((int)$s, 0);
        throw new LangException("Invalid complex literal: {$s}", 'parse');
    }

    public function add(self $o): self { return new self($this->re + $o->re, $this->im + $o->im); }
    public function sub(self $o): self { return new self($this->re - $o->re, $this->im - $o->im); }
    public function mul(self $o): self {
        return new self(
            $this->re * $o->re - $this->im * $o->im,
            $this->re * $o->im + $this->im * $o->re
        );
    }
    public function __toString(): string {
        if ($this->im === 0) return (string)$this->re;
        if ($this->re === 0) return $this->im === 1 ? 'i' : ($this->im === -1 ? '-i' : "{$this->im}i");
        $sign = $this->im >= 0 ? '+' : '-';
        $aim = abs($this->im);
        $imS = $aim === 1 ? 'i' : "{$aim}i";
        return "{$this->re}{$sign}{$imS}";
    }
}

final class PbcFile
{
    public const MAGIC = "PBC1";
    public const FLAG_OPTIMIZED = 1;
    public const FLAG_HAS_ENTRY = 2;

    public static function pack(string $code, int $flags = self::FLAG_OPTIMIZED, int $entry = 0, array $symbols = []): string
    {
        $crc = crc32($code);
        $hdr = self::MAGIC . pack('vvVVV', 1, $flags, strlen($code), $entry, $crc);
        $symBlob = pack('v', count($symbols));
        foreach ($symbols as $name => $off) {
            $symBlob .= $name . "\0" . pack('V', (int)$off);
        }
        return $hdr . $code . $symBlob;
    }

    public static function unpack(string $blob): array
    {
        if (strlen($blob) < 20 || substr($blob, 0, 4) !== self::MAGIC) {
            throw new LangException('Not a PBC1 file', 'load');
        }
        $h = unpack('vversion/vflags/Vlen/Ventry/Vcrc', substr($blob, 4, 16));
        if ((int)$h['version'] !== 1) throw new LangException('Unsupported PBC version', 'load');
        $code = substr($blob, 20, (int)$h['len']);
        if (strlen($code) !== (int)$h['len'] || crc32($code) !== (int)$h['crc']) {
            throw new LangException('Corrupt PBC payload', 'load');
        }
        $symbols = [];
        $pos = 20 + (int)$h['len'];
        if ($pos + 2 <= strlen($blob)) {
            $cnt = unpack('v', substr($blob, $pos, 2))[1];
            $pos += 2;
            for ($i = 0; $i < $cnt; $i++) {
                $end = strpos($blob, "\0", $pos);
                if ($end === false) break;
                $name = substr($blob, $pos, $end - $pos);
                $pos = $end + 1;
                $off = unpack('V', substr($blob, $pos, 4))[1];
                $pos += 4;
                $symbols[$name] = $off;
            }
        }
        return ['flags' => (int)$h['flags'], 'entry' => (int)$h['entry'], 'code' => $code, 'symbols' => $symbols];
    }

    public static function write(string $path, string $code, int $flags = self::FLAG_OPTIMIZED, array $symbols = []): void
    {
        $data = self::pack($code, $flags, 0, $symbols);
        if (file_put_contents($path, $data) === false) {
            throw new LangException("Cannot write {$path}", 'emit');
        }
    }

    public static function read(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) throw new LangException("Cannot read {$path}", 'load');
        return self::unpack($raw);
    }
}
