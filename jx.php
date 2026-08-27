<?php declare(strict_types=1);
/**
 * jx (jinx) — single realized construct on top of PASM.
 *
 * Product name: jx. Engine: PASM (frames, segments, master table, bytecode).
 * Memory law: no free writes; only allowance + underwritten bag + handshake.
 */
namespace jx;

use InvalidArgumentException;
use RuntimeException;
use WeakMap;

// Optional PASM engine hooks (present in this repo).
foreach ([__DIR__, dirname(__DIR__)] as $base) {
    foreach (['pasm-runtime.php', 'pasm-master-table.php', 'pasm-lang-core.php'] as $f) {
        $p = $base . DIRECTORY_SEPARATOR . $f;
        if (is_file($p)) {
            require_once $p;
        }
    }
}

class JxException extends RuntimeException
{
    public function __construct(string $message, public readonly string $kind = 'jx', public readonly bool $resistant = false)
    { parent::__construct(($resistant ? '[Resistant] ' : '[jx] ') . $message); }
}

final class Complex
{
    public function __construct(public float $re = 0.0, public float $im = 0.0) {}
    public static function of(float $re, float $im = 0.0): self { return new self($re, $im); }
    public static function parse(string $s): self
    {
        $s = str_replace(' ', '', strtolower(trim($s)));
        if ($s === '' || $s === '0') return new self(0.0, 0.0);
        if (preg_match('/^([+-]?\d+(?:\.\d+)?)?([+-]\d*(?:\.\d+)?)i$/', $s, $m)) {
            $re = ($m[1] === '' || $m[1] === null) ? 0.0 : (float)$m[1]; $imPart = $m[2];
            if ($imPart === '+' || $imPart === '') $im = 1.0; elseif ($imPart === '-') $im = -1.0; else $im = (float)$imPart;
            return new self($re, $im);
        }
        if ($s === 'i') return new self(0.0, 1.0);
        if ($s === '-i') return new self(0.0, -1.0);
        if (is_numeric($s)) return new self((float)$s, 0.0);
        throw new JxException("Invalid complex literal: {$s}", 'complex');
    }
    public function add(self $o): self { return new self($this->re + $o->re, $this->im + $o->im); }
    public function sub(self $o): self { return new self($this->re - $o->re, $this->im - $o->im); }
    public function mul(self $o): self { return new self($this->re*$o->re-$this->im*$o->im,$this->re*$o->im+$this->im*$o->re); }
    public function conj(): self { return new self($this->re, -$this->im); }
    public function mag(): float { return sqrt($this->re*$this->re+$this->im*$this->im); }
    public function __toString(): string
    {
        if ($this->im == 0.0) return (string)$this->re;
        $sign=$this->im>=0?'+':'-';$aim=abs($this->im);$imS=$aim==1.0?'i':"{$aim}i";
        if ($this->re == 0.0) return ($this->im < 0 ? '-' : '') . $imS;
        return "{$this->re}{$sign}{$imS}";
    }
}

final class RefSign
{
    public function __construct(public readonly int $bagId,public readonly string $node,public readonly string $token,public readonly int $issuedAt) {}
    public function matches(Bag $bag,string $token):bool{return $this->bagId===$bag->id()&&hash_equals($this->token,$token)&&$bag->isLiveRef($this);}
}

class Bag
{
    private static int $nextId=1; private int $id; private int $capacity; private int $used=0;
    private array $cells=[]; private array $refs=[]; private array $liveTokens=[]; private array $props=[];
    protected function __construct(int $capacity){if($capacity<0)throw new JxException('Bag capacity must be non-negative','bag');$this->id=self::$nextId++;$this->capacity=$capacity;}
    public static function underwrite(int $size):self{return new self($size);} public function id():int{return $this->id;} public function capacity():int{return $this->capacity;} public function used():int{return $this->used;}
    public function quotient():int{return max(0,$this->capacity-$this->used);}
    public function sign(string $node):RefSign{$token=bin2hex(random_bytes(16));$ref=new RefSign($this->id,$node,$token,time());$this->refs[$node]=$ref;$this->liveTokens[$token]=true;return $ref;}
    public function unsign(RefSign $ref):void{if($ref->bagId!==$this->id)throw new JxException('RefSign does not belong to this bag','bag');unset($this->liveTokens[$ref->token]);if(isset($this->refs[$ref->node])&&$this->refs[$ref->node]->token===$ref->token)unset($this->refs[$ref->node]);}
    public function isLiveRef(RefSign $ref):bool{return $ref->bagId===$this->id&&isset($this->liveTokens[$ref->token]);}
    private function assertRef(RefSign $ref):void{if(!$this->isLiveRef($ref))throw new JxException('Dead or foreign RefSign — write denied','bag');}
    private function sizeOf(mixed $data):int
    {
        if(is_string($data))return strlen($data);if(is_int($data)||is_float($data)||is_bool($data)||$data===null)return 8;if($data instanceof Complex)return 16;
        if(is_array($data)){$n=16;foreach($data as $k=>$v)$n+=$this->sizeOf($k)+$this->sizeOf($v);return $n;}return strlen(serialize($data));
    }
    /** Omitted node is resolved from the RefSign at commit time. */
    public function set(mixed $data,?string $node=null):BagWrite{return new BagWrite($this,$data,$node);}
    public function commitWrite(BagWrite $w,RefSign $ref):void
    {
        $this->assertRef($ref);$node=$w->node ?? $ref->node;$newSize=$this->sizeOf($w->data);$oldSize=isset($this->cells[$node])?$this->sizeOf($this->cells[$node]):0;$delta=$newSize-$oldSize;
        if($delta>0&&$this->quotient()<$delta)throw new JxException("Bag overflow: need {$delta} more bytes, quotient {$this->quotient()}",'bag',true);
        $this->cells[$node]=$w->data;$this->used+=$delta;
    }
    public function get(RefSign $ref,?string $node=null):mixed{$this->assertRef($ref);$node??=$ref->node;return $this->cells[$node]??null;}
    public function peek(string $node='_default'):mixed{return $this->cells[$node]??null;}
    public function push(string $key,mixed $value):void{$size=$this->sizeOf($key)+$this->sizeOf($value);if(!array_key_exists($key,$this->props)&&$this->quotient()<$size)throw new JxException('Bag overflow on push','bag',true);if(!array_key_exists($key,$this->props))$this->used+=$size;$this->props[$key]=$value;}
    public function prop(string $key):mixed{return $this->props[$key]??null;}
    public function tell(string $op,mixed ...$args):mixed
    {
        return match(strtolower($op)){
            'push'=>(function()use($args){$this->push((string)$args[0],$args[1]??null);return $this;})(),
            'sign'=>$this->sign((string)($args[0]??'_default')),
            'unsign'=>(function()use($args){$this->unsign($args[0]);return $this;})(),
            'set'=>$this->set($args[0]??null,isset($args[1])?(string)$args[1]:null),
            'get'=>$this->get($args[0],isset($args[1])?(string)$args[1]:null),
            'quotient'=>$this->quotient(),'capacity'=>$this->capacity(),'used'=>$this->used(),'id'=>$this->id(),
            default=>throw new JxException("Unknown bag op: {$op}",'bag',true),
        };
    }
}

final class BagWrite
{
    public function __construct(public readonly Bag $bag,public readonly mixed $data,public readonly ?string $node) {}
    public function commit(RefSign $ref):void{$this->bag->commitWrite($this,$ref);} public function pass(RefSign $ref):void{$this->commit($ref);}
}

final class Task extends Bag
{
    private string $name; private string $state='ready';
    private function __construct(int $capacity,string $name){parent::__construct($capacity);$this->name=$name;$this->push('_task_name',$name);}
    public static function underwrite(int $size,string $name='task'):self{return new self($size,$name);} public function name():string{return $this->name;} public function state():string{return $this->state;} public function setState(string $state):void{$this->state=$state;}
}

final class Page extends Bag
{
    private $runner; private function __construct(int $capacity,callable $runner,string $name){parent::__construct($capacity);$this->runner=$runner;$this->push('_page_name',$name);}
    public static function spawn(callable $runner,int $capacity=4096,string $name='page'):self{return new self($capacity,$runner,$name);} public function run():mixed{return ($this->runner)($this);}
}

final class Book
{
    private array $bags=[];private array $pages=[];private function __construct(public readonly string $name,public readonly int $allowance){}
    public static function open(string $name,int $allowance=PHP_INT_MAX):self{return new self($name,$allowance);}
    public function registerBag(string $name,Bag $bag):void{$this->bags[$name]=$bag;} public function registerPage(string $name,Page $page):void{$this->pages[$name]=$page;}
    public function bag(string $name):?Bag{return $this->bags[$name]??null;} public function page(string $name):?Page{return $this->pages[$name]??null;}
}

final class Delivery
{
    public static function extract(mixed $source,string|array $path,mixed $default=null):mixed{$parts=is_array($path)?$path:($path===''?[]:explode('.',$path));$cur=$source;foreach($parts as $part){if(is_array($cur)&&array_key_exists($part,$cur)){$cur=$cur[$part];continue;}if(is_object($cur)&&isset($cur->{$part})){$cur=$cur->{$part};continue;}return $default;}return $cur;}
}

final class ConstBox{private array $values=[];public function define(string $name,mixed $value):void{if(array_key_exists($name,$this->values))throw new JxException("Constant already defined: {$name}",'const');$this->values[$name]=$value;}public function get(string $name):mixed{return $this->values[$name]??throw new JxException("Undefined constant: {$name}",'const');}}
final class SmartTable{public function extrude(string $op):array{return ['mode'=>'smart','operation'=>$op,'lowering'=>'pasm'];}}
final class Sym{public function __construct(public readonly string $name){}public function __toString():string{return $this->name;}}

final class Jx
{
    public static function bag(int $size):Bag{return Bag::underwrite($size);} public static function task(int $size,string $name='task'):Task{return Task::underwrite($size,$name);}
    public static function page(callable $runner,int $size=4096,string $name='page'):Page{return Page::spawn($runner,$size,$name);} public static function book(string $name,int $allowance=PHP_INT_MAX):Book{return Book::open($name,$allowance);}
    public static function delivery(mixed $source,string|array $path,mixed $default=null):mixed{return Delivery::extract($source,$path,$default);} public static function complex(float $re=0.0,float $im=0.0):Complex{return new Complex($re,$im);}
    public static function table():SmartTable{return new SmartTable();} public static function sym(string $name):Sym{return new Sym($name);}
}
