<?php declare(strict_types=1);

namespace jx;

require_once __DIR__ . '/jx-environment.php';

use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;

final class JxStyle implements JsonSerializable
{
    private array $props=[];

    public function set(string $property,mixed $value): self
    {
        $p=$this->canonicalProperty($property);
        $this->props[$p]=$this->validate($p,$value);
        return $this;
    }
    public function get(string $property,mixed $default=null): mixed{return $this->props[$this->canonicalProperty($property)]??$default;}
    public function gap(int|float|string $value): self{return $this->set('gap',$value);}
    public function color(string $hex): self{return $this->set('color',$hex);}
    public function backgroundColor(string $hex): self{return $this->set('background-color',$hex);}
    public function backgroundImage(string $image): self{return $this->set('background-image',$image);}
    public function backgroundOpacity(float $opacity): self{return $this->set('background-opacity',$opacity);}
    public function opacity(float $opacity): self{return $this->set('opacity',$opacity);}
    public function jsonSerialize(): array{return $this->props;}

    private function canonicalProperty(string $p): string
    {
        $p=strtolower(trim($p));
        return match($p){'bg'=>'background-color','background'=>'background-color','alpha'=>'opacity','transparency'=>'opacity','spacing','space'=>'gap',default=>$p};
    }
    private function validate(string $p,mixed $v): mixed
    {
        return match($p){
            'color','background-color'=>$this->hex((string)$v),
            'opacity','background-opacity'=>$this->fraction($v,$p),
            'gap'=>$this->spacing($v),
            'background-image'=>$this->image((string)$v),
            default=>$v,
        };
    }
    private function hex(string $v): string
    {
        $v=trim($v);if(!preg_match('/^#[0-9a-fA-F]{3,4}([0-9a-fA-F]{3,4})?$/',$v))throw new InvalidArgumentException("Invalid hex color {$v}");return strtolower($v);
    }
    private function fraction(mixed $v,string $p): float
    {
        if(!is_numeric($v))throw new InvalidArgumentException("{$p} must be numeric");$n=(float)$v;if($n<0||$n>1)throw new InvalidArgumentException("{$p} must be 0..1");return $n;
    }
    private function spacing(mixed $v): int|float|string
    {
        if(is_int($v)||is_float($v)){if($v<0)throw new InvalidArgumentException('gap cannot be negative');return $v;}
        $s=trim((string)$v);if(!preg_match('/^\d+(?:\.\d+)?(?:px|em|rem|%|vh|vw)$/',$s))throw new InvalidArgumentException('gap string needs a CSS-compatible unit');return $s;
    }
    private function image(string $v): string
    {
        $v=trim($v);if($v==='')throw new InvalidArgumentException('background image cannot be empty');if(str_contains($v,"\0"))throw new InvalidArgumentException('background image contains NUL');return $v;
    }
}

final class ControlTooltip implements JsonSerializable
{
    public function __construct(public readonly string $text,public readonly ?string $role=null){}
    public function jsonSerialize(): array{return ['text'=>$this->text,'role'=>$this->role];}
}

final class ControlEvent
{
    /** @var list<callable> */ private array $listeners=[];
    public function on(callable $listener): self{$this->listeners[]=$listener;return $this;}
    public function emit(mixed $payload=null): array{$out=[];foreach($this->listeners as $listener)$out[]=$listener($payload);return $out;}
    public function count(): int{return count($this->listeners);}
}

final class JxControl implements JsonSerializable
{
    private JxStyle $style;
    private ?ControlTooltip $tooltip=null;
    /** @var array<string,true> */ private array $groups=[];
    /** @var array<string,ControlEvent> */ private array $events=[];

    public function __construct(
        public readonly string $id,
        public readonly string $type='control',
        public mixed $value=null,
    ) {
        if(!preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/',$id))throw new InvalidArgumentException('Invalid control id');
        $this->style=new JxStyle();
    }

    public function style(): JxStyle{return $this->style;}
    public function tooltip(string $text,?string $role=null): self{$this->tooltip=new ControlTooltip($text,$role);return $this;}
    public function addGroup(string $group): self{$group=trim($group);if($group==='')throw new InvalidArgumentException('Empty group');$this->groups[$group]=true;return $this;}
    public function groups(): array{return array_keys($this->groups);}
    public function on(string $event,callable $listener): self{$this->event($event)->on($listener);return $this;}
    public function emit(string $event,mixed $payload=null): array{return $this->event($event)->emit($payload);}
    public function event(string $event): ControlEvent{$k=strtoupper(trim($event));if($k==='')throw new InvalidArgumentException('Empty event');return $this->events[$k]??=new ControlEvent();}

    public function jsonSerialize(): array
    {
        return ['abi'=>'jx.control/1','id'=>$this->id,'type'=>$this->type,'value'=>$this->value,'style'=>$this->style->jsonSerialize(),'tooltip'=>$this->tooltip?->jsonSerialize(),'groups'=>$this->groups(),'events'=>array_map(fn(ControlEvent $e)=>$e->count(),$this->events)];
    }
}

final class ControlGroup implements JsonSerializable
{
    /** @var array<string,JxControl> */ private array $controls=[];
    public function __construct(public readonly string $name){}
    public function add(JxControl $control): self{$this->controls[$control->id]=$control;$control->addGroup($this->name);return $this;}
    public function controls(): array{return array_values($this->controls);}
    public function jsonSerialize(): array{return ['name'=>$this->name,'controls'=>array_keys($this->controls)];}
}

/** Host realization. Canonical control data stays independent of CSS/DOM. */
final class JxControlRenderer
{
    public static function browser(JxControl $control,EnvironmentProfile $environment): string
    {
        $environment->require(Capability::DOM,'HOST.DOM.RENDER');
        $c=$control->jsonSerialize();$style=$c['style'];$css=[];
        foreach($style as $k=>$v){
            if($k==='background-opacity')continue;
            if($k==='background-image'){$css[]='background-image:url("'.self::cssEscape((string)$v).'")';continue;}
            if($k==='gap'&&is_numeric($v))$v=$v.'px';
            $css[]=$k.':'.self::cssEscape((string)$v);
        }
        if(isset($style['background-opacity'])&&isset($style['background-image'])){
            // Preserve image alpha canonically for hosts that can render layers; expose a CSS custom property in browser shadow.
            $css[]='--jx-background-opacity:'.(float)$style['background-opacity'];
        }
        $attrs=' id="'.htmlspecialchars($control->id,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" data-jx-type="'.htmlspecialchars($control->type,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';
        if($css!==[])$attrs.=' style="'.htmlspecialchars(implode(';',$css),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';
        if(($tip=$c['tooltip']['text']??null)!==null)$attrs.=' title="'.htmlspecialchars((string)$tip,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';
        return '<div'.$attrs.'>'.htmlspecialchars((string)($control->value??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</div>';
    }

    public static function native(JxControl $control,EnvironmentProfile $environment): array
    {
        $environment->require(Capability::WINDOW,'HOST.WINDOW.SHOW');
        return ['host'=>'native','control'=>$control->jsonSerialize()];
    }

    private static function cssEscape(string $v): string{return str_replace(['\\','"',';','\n','\r'],['\\\\','\\"','','',''],$v);}
}
