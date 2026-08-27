<?php declare(strict_types=1);

namespace pasm;

use InvalidArgumentException;
use RuntimeException;

/** High byte sorts callable methods into execution families; low byte selects. */
final class PASMMethodFamily
{
    public const BAG = 0x10;
    public const VECTOR = 0x11;
    public const MAP = 0x12;
    public const SET = 0x13;
    public const CONTROL = 0x20;
    public const SQL = 0x30;
    public const CHART = 0x40;
    public const DELIVERY = 0x50;
    public const BOOK = 0x60;
    public const HOST = 0x70;
}

final class PASMMethodABI
{
    /** @var array<int,array{canonical:string,handler:?\Closure}> */
    private array $methods = [];
    /** @var array<string,int> */
    private array $names = [];
    /** @var array<int,int> method id => one-byte promoted opcode */
    private array $promoted = [];
    /** @var array<int,int> promoted opcode => method id */
    private array $promotedReverse = [];

    public static function id(int $family, int $slot): int
    {
        self::byte($family, 'method family'); self::byte($slot, 'method slot');
        return (($family & 0xff) << 8) | ($slot & 0xff);
    }

    public static function split(int $id): array
    {
        if ($id < 0 || $id > 0xffff) throw new InvalidArgumentException('Method id must fit 16 bits');
        return ['family'=>($id >> 8) & 0xff, 'slot'=>$id & 0xff];
    }

    public static function bytes(int $id): string
    {
        $p=self::split($id); return chr($p['family']).chr($p['slot']);
    }

    public static function fromBytes(string $bytes, int $offset=0): int
    {
        if (strlen($bytes) < $offset+2) throw new InvalidArgumentException('Method call needs two readable bytes');
        return self::id(ord($bytes[$offset]), ord($bytes[$offset+1]));
    }

    public function register(int $family, int $slot, string $canonical, array $aliases=[], ?callable $handler=null): int
    {
        $id=self::id($family,$slot); $key=$this->key($canonical);
        if (isset($this->methods[$id]) && $this->methods[$id]['canonical'] !== $key) throw new InvalidArgumentException(sprintf('Method id 0x%04X collision',$id));
        $closure=$handler===null?null:\Closure::fromCallable($handler);
        $this->methods[$id]=['canonical'=>$key,'handler'=>$closure];
        foreach (array_merge([$canonical],$aliases) as $name) {
            $n=$this->key((string)$name);
            if (isset($this->names[$n]) && $this->names[$n] !== $id) throw new InvalidArgumentException("Method name collision: {$name}");
            $this->names[$n]=$id;
        }
        return $id;
    }

    public function resolve(string $name): int
    {
        return $this->names[$this->key($name)] ?? throw new RuntimeException("Unknown method {$name}");
    }

    /** Promote a measured-hot two-byte call to one byte. 0x80..0xFF reserved for surfaced calls. */
    public function promote(int $id, int $opcode): void
    {
        if (!isset($this->methods[$id])) throw new RuntimeException('Cannot promote unregistered method');
        if ($opcode < 0x80 || $opcode > 0xff) throw new InvalidArgumentException('Promoted opcode must be 0x80..0xFF');
        if (isset($this->promotedReverse[$opcode]) && $this->promotedReverse[$opcode] !== $id) throw new InvalidArgumentException(sprintf('Promoted opcode 0x%02X collision',$opcode));
        $this->promoted[$id]=$opcode; $this->promotedReverse[$opcode]=$id;
    }

    /** One byte if surfaced, otherwise sorted family+slot two-byte call. */
    public function encodeCall(int|string $method): string
    {
        $id=is_int($method)?$method:$this->resolve($method);
        return isset($this->promoted[$id]) ? chr($this->promoted[$id]) : self::bytes($id);
    }

    public function decodeCall(string $code, int $offset=0): array
    {
        if ($offset<0 || $offset>=strlen($code)) throw new InvalidArgumentException('Method pc out of range');
        $first=ord($code[$offset]);
        if (isset($this->promotedReverse[$first])) return ['id'=>$this->promotedReverse[$first],'bytes'=>1,'promoted'=>true];
        return ['id'=>self::fromBytes($code,$offset),'bytes'=>2,'promoted'=>false];
    }

    public function invoke(string $code, array $args=[], int $offset=0): mixed
    {
        $d=$this->decodeCall($code,$offset); $entry=$this->methods[$d['id']] ?? throw new RuntimeException('Unregistered method id');
        if (!$entry['handler']) return $entry['canonical'];
        return ($entry['handler'])(...$args);
    }

    private function key(string $s): string { return strtoupper(trim($s)); }
    private static function byte(int $v,string $what): void { if($v<0||$v>255)throw new InvalidArgumentException("{$what} must fit one byte"); }
}

/** Named-memory high byte sorts memory space; low byte selects a prelinked slot. */
final class PASMMemorySpace
{
    public const SPECIAL = 0x00;
    public const LOCAL = 0x01;
    public const ARG = 0x02;
    public const PERSISTENT = 0x03;
    public const BOOK = 0x04;
    public const BAG = 0x05;
    public const PAGE_TASK = 0x06;
    public const CONTROL_STYLE = 0x07;
    public const SQL_RESULT = 0x08;
    public const HOST_EVENT = 0x09;
    public const CONSTANT = 0x0A;
    public const CHANNEL_DELIVERY = 0x0B;
    public const LIBRARY_PLUGIN = 0x0C;
    public const USER_BASE = 0x0D;
}

final class PASMNamedMemory
{
    /** @var array<int,mixed> */
    private array $values=[];
    /** @var array<string,int> */
    private array $names=[];

    public static function id(int $space,int $slot): int
    {
        if($space<0||$space>255||$slot<0||$slot>255)throw new InvalidArgumentException('Named memory space/slot must fit one byte');
        return (($space&255)<<8)|($slot&255);
    }
    public static function split(int $id): array
    {
        if($id<0||$id>0xffff)throw new InvalidArgumentException('Named memory id must fit 16 bits');
        return ['space'=>($id>>8)&255,'slot'=>$id&255];
    }
    public static function bytes(int $id): string { $p=self::split($id);return chr($p['space']).chr($p['slot']); }
    public static function fromBytes(string $b,int $offset=0): int
    {
        if(strlen($b)<$offset+2)throw new InvalidArgumentException('Named memory address needs two readable bytes');
        return self::id(ord($b[$offset]),ord($b[$offset+1]));
    }
    public function bind(int $space,int $slot,string $name,mixed $initial=null): int
    {
        $id=self::id($space,$slot);$k=$this->key($space,$name);
        if(isset($this->names[$k])&&$this->names[$k]!==$id)throw new InvalidArgumentException("Named memory collision {$name}");
        $this->names[$k]=$id;if(!array_key_exists($id,$this->values))$this->values[$id]=$initial;return $id;
    }
    public function resolve(int $space,string $name): int
    {
        return $this->names[$this->key($space,$name)]??throw new RuntimeException("Unknown named memory {$name}");
    }
    public function read(int|string $address,?int $space=null): mixed
    {
        $id=is_int($address)?$address:$this->resolve($space??throw new InvalidArgumentException('space required for name'),$address);
        if(!array_key_exists($id,$this->values))throw new RuntimeException(sprintf('Unbound named memory 0x%04X',$id));
        return $this->values[$id];
    }
    public function write(int|string $address,mixed $value,?int $space=null): mixed
    {
        $id=is_int($address)?$address:$this->resolve($space??throw new InvalidArgumentException('space required for name'),$address);
        if(!array_key_exists($id,$this->values))throw new RuntimeException(sprintf('Unbound named memory 0x%04X',$id));
        return $this->values[$id]=$value;
    }
    public function readBytes(string $address): mixed { return $this->read(self::fromBytes($address)); }
    public function writeBytes(string $address,mixed $value): mixed { return $this->write(self::fromBytes($address),$value); }
    private function key(int $space,string $name): string{return sprintf('%02X:%s',$space,strtolower(trim($name)));}
}
