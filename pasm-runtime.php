<?php declare(strict_types=1);
namespace pasm;

require_once __DIR__ . '/pasm-data-structures.php';

use RuntimeException;
use OutOfBoundsException;
use InvalidArgumentException;

/** Fixed-size byte-addressable PASM memory arena. */
final class PASMMemory implements \Countable
{
    private string $bytes;
    private int $size;
    private int $brk = 0;
    /** @var array<int,int> */ private array $allocations = [];

    public function __construct(int $bytes = 1_048_576)
    {
        if ($bytes < 1) throw new InvalidArgumentException('Memory size must be positive');
        $this->size = $bytes;
        $this->bytes = str_repeat("\0", $bytes);
    }
    public function count(): int { return $this->size; }
    public function capacity(): int { return $this->size; }
    public function used(): int { return $this->brk; }
    public function alloc(int $length, int $alignment = 8): int
    {
        if ($length < 0 || $alignment < 1) throw new InvalidArgumentException('Invalid allocation');
        $ptr = ($this->brk + $alignment - 1) & ~($alignment - 1);
        if ($ptr + $length > $this->size) throw new RuntimeException('PASM memory exhausted');
        $this->allocations[$ptr] = $length;
        $this->brk = $ptr + $length;
        PASM::$rdx = $ptr;
        return $ptr;
    }
    public function free(int $ptr): bool
    {
        $ok = isset($this->allocations[$ptr]);
        unset($this->allocations[$ptr]);
        PASM::$cl = $ok ? 1 : 0;
        return $ok;
    }
    private function guard(int $ptr, int $length): void
    {
        if ($ptr < 0 || $length < 0 || $ptr + $length > $this->size) throw new OutOfBoundsException("Memory range {$ptr}:{$length} outside arena");
    }
    public function write(int $ptr, string $data): void
    {
        $n = strlen($data); $this->guard($ptr, $n);
        $this->bytes = substr_replace($this->bytes, $data, $ptr, $n);
        PASM::$rdx = $n;
    }
    public function read(int $ptr, int $length): string
    {
        $this->guard($ptr, $length); $v = substr($this->bytes, $ptr, $length); PASM::$rdx = $v; return $v;
    }
    public function memset(int $ptr, int $value, int $length): void { $this->guard($ptr,$length); $this->write($ptr, str_repeat(chr($value & 0xff), $length)); }
    public function memcpy(int $dst, int $src, int $length): void { $this->write($dst, $this->read($src,$length)); }
    public function readU8(int $p): int { $this->guard($p,1); return PASM::$rdx = ord($this->bytes[$p]); }
    public function writeU8(int $p,int $v): void { $this->guard($p,1); $this->bytes[$p]=chr($v & 0xff); PASM::$rdx=$v & 0xff; }
    public function readU16(int $p): int { return PASM::$rdx = unpack('v', $this->read($p,2))[1]; }
    public function writeU16(int $p,int $v): void { $this->write($p, pack('v',$v)); }
    public function readU32(int $p): int { return PASM::$rdx = unpack('V', $this->read($p,4))[1]; }
    public function writeU32(int $p,int $v): void { $this->write($p, pack('V',$v)); }
    public function readU64(int $p): int { return PASM::$rdx = unpack('P', $this->read($p,8))[1]; }
    public function writeU64(int $p,int $v): void { $this->write($p, pack('P',$v)); }
}

/** Compact binary PASM wire packet. */
final class PASMPacket
{
    public const MAGIC = "PASM";
    public const VERSION = 1;
    public const OP_DATA=1, OP_REG_WRITE=2, OP_STACK=3, OP_EXEC=4, OP_PING=5, OP_ACK=6;
    public function __construct(public int $opcode, public string $payload='', public int $flags=0, public int $sequence=0) {}
    public function encode(): string
    {
        $len=strlen($this->payload); $crc=crc32($this->payload);
        return self::MAGIC . pack('CCnNNN', self::VERSION, $this->opcode, $this->flags, $this->sequence, $len, $crc) . $this->payload;
    }
    public static function decode(string $raw): self
    {
        if (strlen($raw)<20 || substr($raw,0,4)!==self::MAGIC) throw new RuntimeException('Invalid PASM packet');
        $h=unpack('Cversion/Copcode/nflags/Nsequence/Nlength/Ncrc', substr($raw,4,16));
        if ($h['version']!==self::VERSION) throw new RuntimeException('Unsupported PASM packet version');
        $payload=substr($raw,20,$h['length']);
        if (strlen($payload)!==$h['length'] || crc32($payload)!==$h['crc']) throw new RuntimeException('Corrupt PASM packet');
        return new self($h['opcode'],$payload,$h['flags'],$h['sequence']);
    }
    public static function reg(string $name, mixed $value, int $seq=0): self
    {
        $payload = json_encode([$name,$value], JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        return new self(self::OP_REG_WRITE,$payload,0,$seq);
    }
}

/** Socket-backed transport. Uses stream sockets so TCP/UDP/Unix transports share one API. */
final class PASMNetwork
{
    /** @var resource|null */ private $socket=null;
    private int $sequence=0;
    public function connect(string $address, float $timeout=2.0): static
    {
        $errno=0;$err=''; $s=@stream_socket_client($address,$errno,$err,$timeout,STREAM_CLIENT_CONNECT);
        if (!$s) { PASM::$err=$errno; PASM::$err_str=$err; throw new RuntimeException("Network connect failed: {$err}"); }
        stream_set_blocking($s,true); $this->socket=$s; PASM::$cl=1; return $this;
    }
    public function bind(string $address): static
    {
        $errno=0;$err=''; $s=@stream_socket_server($address,$errno,$err,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN);
        if (!$s) throw new RuntimeException("Network bind failed: {$err}"); $this->socket=$s; PASM::$cl=1; return $this;
    }
    public function accept(float $timeout=0.0): self
    {
        $this->need(); $s=@stream_socket_accept($this->socket,$timeout); if(!$s) throw new RuntimeException('No connection accepted');
        $n=new self(); $n->socket=$s; return $n;
    }
    private function need(): void { if(!is_resource($this->socket)) throw new RuntimeException('Network socket is not open'); }
    private function writeAll(string $data): void { $this->need(); $n=strlen($data);$o=0; while($o<$n){$w=fwrite($this->socket,substr($data,$o));if($w===false||$w===0)throw new RuntimeException('Socket write failed');$o+=$w;} PASM::$rdx=$o; }
    private function readExact(int $n): string { $this->need();$b='';while(strlen($b)<$n){$x=fread($this->socket,$n-strlen($b));if($x===false||$x==='')throw new RuntimeException('Socket closed');$b.=$x;}return $b; }
    public function send(PASMPacket $packet): static { $raw=$packet->encode(); $this->writeAll(pack('N',strlen($raw)).$raw); return $this; }
    public function recv(): PASMPacket { $len=unpack('N',$this->readExact(4))[1]; if($len>16_777_216)throw new RuntimeException('Packet too large'); return PASMPacket::decode($this->readExact($len)); }
    public function sendRegister(string $register): static { if(!property_exists(PASM::class,$register)) throw new OutOfBoundsException('Unknown register'); return $this->send(PASMPacket::reg($register,PASM::${$register},++$this->sequence)); }
    public function apply(PASMPacket $packet): mixed
    {
        if($packet->opcode===PASMPacket::OP_REG_WRITE){[$r,$v]=json_decode($packet->payload,true,512,JSON_THROW_ON_ERROR);if(!property_exists(PASM::class,$r))throw new OutOfBoundsException('Unknown register');PASM::${$r}=$v;return PASM::$rdx=$v;}
        if($packet->opcode===PASMPacket::OP_STACK){$v=json_decode($packet->payload,true,512,JSON_THROW_ON_ERROR);PASM::$stack=$v;return PASM::$rdx=count($v);}
        return PASM::$rdx=$packet->payload;
    }
    public function close(): void { if(is_resource($this->socket)) fclose($this->socket); $this->socket=null; PASM::$cl=0; }
}

/** Cooperative atomic/lock primitives. File locks work across PHP processes. */
final class PASMAtomic
{
    /** @var array<string,resource> */ private array $locks=[];
    public function lock(string $name): void { $f=fopen(sys_get_temp_dir().'/pasm-'.hash('sha256',$name).'.lock','c+'); if(!$f||!flock($f,LOCK_EX))throw new RuntimeException('Lock failed');$this->locks[$name]=$f;PASM::$cl=1; }
    public function unlock(string $name): void { if(isset($this->locks[$name])){flock($this->locks[$name],LOCK_UN);fclose($this->locks[$name]);unset($this->locks[$name]);}PASM::$cl=0; }
    public function compareExchange(mixed &$slot,mixed $expected,mixed $replacement): bool { if($slot===$expected){$slot=$replacement;PASM::$ZF=1;PASM::$rdx=$replacement;return true;}PASM::$ZF=0;PASM::$rdx=$slot;return false; }
    public function exchange(mixed &$slot,mixed $replacement): mixed { $old=$slot;$slot=$replacement;PASM::$rdx=$old;return $old; }
    public function increment(int &$slot,int $by=1): int { $slot+=$by;return PASM::$rdx=$slot; }
}

/** Safe local instruction dispatcher and optional process worker. */
final class PASMExecutor
{
    /** @var array<string,callable> */ private array $programs=[];
    public function register(string $name, callable $program): static { $this->programs[$name]=$program; return $this; }
    public function exec(string $name, mixed ...$args): mixed { if(!isset($this->programs[$name]))throw new RuntimeException("Unknown PASM program {$name}"); return PASM::$rdx=($this->programs[$name])(...$args); }
    public function callInstruction(string $name): mixed
    {
        if(!method_exists(PASM::class,$name)) throw new RuntimeException("Unknown PASM instruction {$name}");
        return PASM::$name();
    }
}

/** Runtime facade: instruction-shaped API around memory/network/atomics/execution. */
final class PASMRuntime
{
    public readonly PASMMemory $memory;
    public readonly PASMNetwork $network;
    public readonly PASMAtomic $atomic;
    public readonly PASMExecutor $executor;
    public function __construct(int $memoryBytes=1_048_576){$this->memory=new PASMMemory($memoryBytes);$this->network=new PASMNetwork();$this->atomic=new PASMAtomic();$this->executor=new PASMExecutor();}
    public function alloc(int $n): int{return $this->memory->alloc($n);} public function load32(int $p): int{return $this->memory->readU32($p);} public function store32(int $p,int $v):static{$this->memory->writeU32($p,$v);return $this;}
    public function netConnect(string $a,float $t=2.0):static{$this->network->connect($a,$t);return $this;} public function netSendReg(string $r):static{$this->network->sendRegister($r);return $this;} public function netRecv():mixed{return $this->network->apply($this->network->recv());}
}
