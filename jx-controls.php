<?php declare(strict_types=1);

namespace jx;

require_once __DIR__ . '/jx-environment.php';
require_once __DIR__ . '/jx-bag-containers.php';

use InvalidArgumentException;
use JsonSerializable;

final class JxStyle implements JsonSerializable
{
    private array $props=[];
    /** @var null|callable(array):void */ private $onChange;

    public function __construct(?callable $onChange=null)
    {
        $this->onChange=$onChange;
    }

    public function set(string $property,mixed $value): self
    {
        $p=$this->canonicalProperty($property);
        $this->props[$p]=$this->validate($p,$value);
        $this->changed();
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

    /** Restore canonical style state without emitting a synthetic change. */
    public function restore(array $props): self
    {
        $normalized=[];
        foreach($props as $property=>$value){
            $p=$this->canonicalProperty((string)$property);
            $normalized[$p]=$this->validate($p,$value);
        }
        $this->props=$normalized;
        return $this;
    }

    private function changed(): void
    {
        if($this->onChange!==null)($this->onChange)($this->props);
    }
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
    /** @var null|callable(int):void */ private $onChange;
    public function __construct(?callable $onChange=null){$this->onChange=$onChange;}
    public function on(callable $listener): self{$this->listeners[]=$listener;if($this->onChange!==null)($this->onChange)(count($this->listeners));return $this;}
    public function emit(mixed $payload=null): array{$out=[];foreach($this->listeners as $listener)$out[]=$listener($payload);return $out;}
    public function count(): int{return count($this->listeners);}
}

/**
 * A control is a persistent Bag identity. Host windows/surfaces are placements
 * of this Bag; moving or rebinding never replaces the control state.
 */
final class JxControl implements JsonSerializable
{
    private Bag $bag;
    private JxStyle $style;
    private ?ControlTooltip $tooltip=null;
    /** @var array<string,true> */ private array $groups=[];
    /** @var array<string,ControlEvent> */ private array $events=[];

    public function __construct(
        public readonly string $id,
        public readonly string $type='control',
        mixed $value=null,
    ) {
        if(!preg_match('/^[A-Za-z_][A-Za-z0-9_.:-]*$/',$id))throw new InvalidArgumentException('Invalid control id');
        $this->bag=Bag::empty(65_536);
        $this->bag->write('control.identity',['id'=>$id,'type'=>$type]);
        $this->bag->write('control.value',$value);
        $this->bag->write('control.layout',['container'=>null,'x'=>0,'y'=>0,'width'=>null,'height'=>null,'z'=>0,'revision'=>0]);
        $this->bag->write('control.source',['source_id'=>null,'previous_source_id'=>null,'source_revision'=>0,'binding_revision'=>0,'binding_id'=>null,'through'=>null,'mode'=>null,'with'=>[]]);
        $this->bag->write('control.state',['enabled'=>true,'visible'=>true,'focused'=>false,'selected'=>false,'revision'=>0]);
        $this->bag->write('control.style',[]);
        $this->bag->write('control.tooltip',null);
        $this->bag->write('control.groups',[]);
        $this->bag->write('control.events',[]);
        $this->bag->write('control.history',[]);
        $this->style=new JxStyle(function(array $props):void{$this->bag->write('control.style',$props);});
        $this->record('create',['value'=>$value]);
    }

    public function bag(): Bag{return $this->bag;}
    public function bagId(): int{return $this->bag->id();}
    public function style(): JxStyle{return $this->style;}

    /** Preserve source compatibility for $control->value reads/writes while the Bag remains canonical. */
    public function __get(string $name): mixed
    {
        if($name==='value')return $this->bag->read('control.value');
        throw new InvalidArgumentException("Unknown control property {$name}");
    }
    public function __set(string $name,mixed $value): void
    {
        if($name==='value'){$this->setValue($value);return;}
        throw new InvalidArgumentException("Unknown control property {$name}");
    }
    public function value(): mixed{return $this->bag->read('control.value');}
    public function setValue(mixed $value): self
    {
        $this->bag->write('control.value',$value);
        $this->record('value',['value'=>$value]);
        return $this;
    }

    public function tooltip(string $text,?string $role=null): self
    {
        $this->tooltip=new ControlTooltip($text,$role);
        $this->bag->write('control.tooltip',$this->tooltip->jsonSerialize());
        return $this;
    }
    public function addGroup(string $group): self
    {
        $group=trim($group);if($group==='')throw new InvalidArgumentException('Empty group');
        $this->groups[$group]=true;
        $this->bag->write('control.groups',$this->groups());
        return $this;
    }
    public function groups(): array{return array_keys($this->groups);}
    public function on(string $event,callable $listener): self{$this->event($event)->on($listener);return $this;}
    public function emit(string $event,mixed $payload=null): array{return $this->event($event)->emit($payload);}
    public function event(string $event): ControlEvent
    {
        $k=strtoupper(trim($event));if($k==='')throw new InvalidArgumentException('Empty event');
        if(!isset($this->events[$k])){
            $this->events[$k]=new ControlEvent(function(int $count)use($k):void{
                $meta=$this->bag->read('control.events',[]);
                $meta[$k]=['listeners'=>$count];
                $this->bag->write('control.events',$meta);
            });
        }
        return $this->events[$k];
    }

    /** Move only the placement record. No value, source, style or event state is recreated. */
    public function moveTo(int|float $x,int|float $y,?string $container=null,?int $z=null): self
    {
        $layout=$this->layout();
        $layout['x']=$x;$layout['y']=$y;
        if($container!==null)$layout['container']=$container;
        if($z!==null)$layout['z']=$z;
        $layout['revision']=(int)($layout['revision']??0)+1;
        $this->bag->write('control.layout',$layout);
        $this->record('move',['x'=>$x,'y'=>$y,'container'=>$layout['container'],'z'=>$layout['z']]);
        return $this;
    }
    public function resize(int|float $width,int|float $height): self
    {
        if($width<0||$height<0)throw new InvalidArgumentException('Control dimensions cannot be negative');
        $layout=$this->layout();$layout['width']=$width;$layout['height']=$height;$layout['revision']=(int)($layout['revision']??0)+1;
        $this->bag->write('control.layout',$layout);$this->record('resize',['width'=>$width,'height'=>$height]);return $this;
    }
    public function layout(): array{return $this->bag->read('control.layout',[]);}

    /**
     * Rebind the persistent control Bag. The last value is intentionally retained
     * until the newly bound source publishes a newer canonical value.
     */
    public function bindSource(string $source,string $through='reactive',string $mode='auto',array $with=[]): self
    {
        $old=$this->sourceBinding();
        if(is_string($old['binding_id']??null))$this->bag->unbind($old['binding_id']);
        $bindingId=$this->bag->bind($source,$through,'control.value',$mode,$with);
        $binding=[
            'source_id'=>$source,
            'previous_source_id'=>$old['source_id']??null,
            'source_revision'=>0,
            'binding_revision'=>(int)($old['binding_revision']??0)+1,
            'binding_id'=>$bindingId,
            'through'=>$through,
            'mode'=>$mode,
            'with'=>$with,
        ];
        $this->bag->write('control.source',$binding);
        $this->record('source.bind',['source'=>$source,'binding_revision'=>$binding['binding_revision']]);
        return $this;
    }
    public function sourceBinding(): array{return $this->bag->read('control.source',[]);}

    /** Returns false for stale/foreign publications; true only when this Bag accepts the revision. */
    public function publishSourceValue(string $source,int $revision,mixed $value): bool
    {
        $binding=$this->sourceBinding();
        if(($binding['source_id']??null)!==$source)return false;
        if($revision<=(int)($binding['source_revision']??0))return false;
        $this->bag->write('control.value',$value);
        $binding['source_revision']=$revision;
        $this->bag->write('control.source',$binding);
        $this->record('source.publish',['source'=>$source,'revision'=>$revision,'value'=>$value]);
        return true;
    }

    public function setState(string $state,bool $value): self
    {
        if(!in_array($state,['enabled','visible','focused','selected'],true))throw new InvalidArgumentException("Unknown control state {$state}");
        $record=$this->bag->read('control.state',[]);$record[$state]=$value;$record['revision']=(int)($record['revision']??0)+1;
        $this->bag->write('control.state',$record);return $this;
    }
    public function state(): array{return $this->bag->read('control.state',[]);}
    public function history(): array{return $this->bag->read('control.history',[]);}

    public function jsonSerialize(): array
    {
        return [
            'abi'=>'jx.control/2',
            'bag'=>$this->bag->id(),
            'id'=>$this->id,
            'type'=>$this->type,
            'value'=>$this->value(),
            'layout'=>$this->layout(),
            'source'=>$this->sourceBinding(),
            'state'=>$this->state(),
            'style'=>$this->bag->read('control.style',[]),
            'tooltip'=>$this->bag->read('control.tooltip'),
            'groups'=>$this->bag->read('control.groups',[]),
            'events'=>$this->bag->read('control.events',[]),
        ];
    }

    private function record(string $kind,array $detail=[]): void
    {
        $history=$this->bag->read('control.history',[]);
        $history[]=['seq'=>count($history)+1,'kind'=>$kind,'detail'=>$detail];
        if(count($history)>64)$history=array_slice($history,-64);
        $this->bag->write('control.history',$history);
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
            $css[]='--jx-background-opacity:'.(float)$style['background-opacity'];
        }
        $attrs=' id="'.htmlspecialchars($control->id,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" data-jx-type="'.htmlspecialchars($control->type,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" data-jx-bag="'.$control->bagId().'"';
        if($css!==[])$attrs.=' style="'.htmlspecialchars(implode(';',$css),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';
        if(($tip=$c['tooltip']['text']??null)!==null)$attrs.=' title="'.htmlspecialchars((string)$tip,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';
        return '<div'.$attrs.'>'.htmlspecialchars((string)($control->value()??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</div>';
    }

    public static function native(JxControl $control,EnvironmentProfile $environment): array
    {
        $environment->require(Capability::WINDOW,'HOST.WINDOW.SHOW');
        return ['host'=>'native','control'=>$control->jsonSerialize()];
    }

    private static function cssEscape(string $v): string{return str_replace(['\\','"',';','\n','\r'],['\\\\','\\"','','',''],$v);}
}
