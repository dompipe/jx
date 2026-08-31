<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/Plugin.php';
    require_once __DIR__ . '/Anatomy.php';
}

namespace jx\plugins {

use JsonSerializable;
use jx\JxException;
use jx\JxPluginExtension;
use jx\Plugins;

/**
 * Canonical semantic anatomy: an arm is an Arm, not merely two pipe records.
 * Segment names remain available for rendering/IK, while the body-part object
 * carries the stable anatomical identity and surface controls.
 */
final class AnatomyBodyPart implements JsonSerializable
{
    /** @var list<string> */ private array $joints;
    /** @var list<string> */ private array $bones;
    /** @var array<string,mixed> */ private array $controls;

    /** @param list<string> $joints @param list<string> $bones @param array<string,mixed> $controls */
    public function __construct(
        private string $id,
        private string $type,
        private string $side,
        array $joints,
        array $bones,
        array $controls = [],
    ) {
        $this->id=self::name($id);
        $this->type=self::type($type);
        $this->side=strtolower(trim($side));
        if(!in_array($this->side,['left','right','center','auto'],true)) throw new JxException('Invalid anatomy body-part side','plugin.anatomy-semantics',true,['side'=>$side]);
        $this->joints=array_values(array_map([self::class,'name'],$joints));
        $this->bones=array_values(array_map([self::class,'name'],$bones));
        if($this->joints===[]) throw new JxException('Anatomy body part needs at least one joint','plugin.anatomy-semantics',true);
        $defaults=['size'=>1.0,'mass'=>1.0,'muscleTone'=>0.35,'pumpedness'=>0.25,'fatCover'=>0.15,'boneProminence'=>0.35];
        $this->controls=$defaults;
        foreach($controls as $k=>$v){
            if(!array_key_exists($k,$defaults)) continue;
            $f=(float)$v;if(!is_finite($f))throw new JxException('Invalid anatomy body-part control','plugin.anatomy-semantics',true,['control'=>$k]);
            $this->controls[$k]=$f;
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'id'=>$this->id,'type'=>$this->type,'family'=>self::template($this->type)['family'],
            'side'=>$this->side,'joints'=>$this->joints,'bones'=>$this->bones,
            'surface'=>self::template($this->type)['surface'],'controls'=>$this->controls,
        ];
    }

    /** @return array<string,mixed> */
    public static function template(string $type): array
    {
        $type=self::type($type);
        return match($type){
            'arm'=>['family'=>'arm','ports'=>['shoulder','elbow','wrist'],'segments'=>['upper-arm','forearm'],'surface'=>['archetype'=>'human-arm','taper'=>0.76]],
            'leg'=>['family'=>'leg','ports'=>['hip','knee','ankle','foot'],'segments'=>['thigh','shin','foot'],'surface'=>['archetype'=>'human-leg','taper'=>0.72]],
            'animal-front-leg'=>['family'=>'animal-front-leg','ports'=>['shoulder','elbow','carpus','paw'],'segments'=>['humerus','radius-ulna','metacarpal'],'surface'=>['archetype'=>'animal-front-leg','taper'=>0.68]],
            'animal-rear-leg'=>['family'=>'animal-rear-leg','ports'=>['hip','stifle','hock','paw'],'segments'=>['femur','tibia','metatarsal'],'surface'=>['archetype'=>'animal-rear-leg','taper'=>0.66]],
            'wing'=>['family'=>'wing','ports'=>['wing-root','wing-elbow','wing-wrist','wing-tip'],'segments'=>['wing-upper','wing-lower','wing-hand'],'surface'=>['archetype'=>'wing','taper'=>0.58,'membrane'=>true]],
            'torso'=>['family'=>'torso','ports'=>['torso-root','spine','chest','neck-root'],'segments'=>['spine'],'surface'=>['archetype'=>'torso','volume'=>true]],
            'neck'=>['family'=>'neck','ports'=>['neck-root','neck','head'],'segments'=>['neck'],'surface'=>['archetype'=>'neck','taper'=>0.82]],
            'tail'=>['family'=>'tail','ports'=>['tail-root','tail','tail-tip'],'segments'=>['tail'],'surface'=>['archetype'=>'tail','taper'=>0.42]],
            'beak'=>['family'=>'beak','ports'=>['beak-root','beak-tip'],'segments'=>['beak'],'surface'=>['archetype'=>'beak','taper'=>0.25]],
            'snout'=>['family'=>'snout','ports'=>['snout-root','snout-tip'],'segments'=>['snout'],'surface'=>['archetype'=>'snout','taper'=>0.48]],
            'jaw'=>['family'=>'jaw','ports'=>['jaw-root','jaw-tip'],'segments'=>['jaw'],'surface'=>['archetype'=>'jaw','taper'=>0.72]],
            'generic'=>['family'=>'generic','ports'=>['joint'],'segments'=>['bone'],'surface'=>['archetype'=>'generic']],
        };
    }

    private static function type(string $v): string
    {
        $v=strtolower(trim($v));
        if(!in_array($v,['arm','leg','animal-front-leg','animal-rear-leg','wing','torso','neck','tail','beak','snout','jaw','generic'],true)) throw new JxException('Unsupported anatomy body-part type','plugin.anatomy-semantics',true,['type'=>$v]);
        return $v;
    }
    private static function name(string $v): string
    {
        $v=trim($v);if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v))throw new JxException('Invalid anatomy body-part identifier','plugin.anatomy-semantics',true,['value'=>$v]);return $v;
    }
}

final class AnatomySemanticsPlugin implements JxPluginExtension
{
    public const VERSION='jx.anatomy-semantics/1';
    public function id(): string { return 'anatomy-semantics'; }
    public function version(): string { return self::VERSION; }
    public function extendsPlugin(): string { return 'anatomy'; }
    public function capabilities(): array { return ['anatomy.body-part','anatomy.arm','anatomy.leg','anatomy.animal-front-leg','anatomy.animal-rear-leg','anatomy.wing','anatomy.torso','anatomy.neck','anatomy.tail','anatomy.beak','anatomy.snout','anatomy.jaw']; }
    public function normalizeExtensionOptions(array $with): array { return $with; }

    /** @param list<string> $joints @param list<string> $bones @param array<string,mixed> $controls */
    public static function part(string $id,string $type,string $side,array $joints,array $bones,array $controls=[]): AnatomyBodyPart
    { return new AnatomyBodyPart($id,$type,$side,$joints,$bones,$controls); }

    /** @return array<string,mixed> */
    public static function template(string $type): array { return AnatomyBodyPart::template($type); }
}

Plugins::register(new AnatomySemanticsPlugin());

}
