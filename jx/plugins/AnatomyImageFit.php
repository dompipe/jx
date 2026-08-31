<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/Plugin.php';
    require_once __DIR__ . '/Anatomy.php';
}

namespace jx\plugins {

use JsonSerializable;
use jx\Boundary;
use jx\JxException;
use jx\JxPluginExtension;
use jx\Plugins;

/** A user-clicked body port / joint in image coordinates. */
final class AnatomyImageJoint implements JsonSerializable
{
    public function __construct(
        private string $id,
        private float $x,
        private float $y,
        private string $semantic='joint',
        private float $z=0.0,
        private bool $locked=false,
    ) {
        $this->id=self::name($id);
        $this->semantic=self::name($semantic);
        foreach([$x,$y,$z] as $v) if(!is_finite($v)) throw new JxException('Image joint coordinate must be finite','plugin.anatomy-image-fit',true);
    }
    public function jsonSerialize(): array { return ['id'=>$this->id,'x'=>$this->x,'y'=>$this->y,'z'=>$this->z,'semantic'=>$this->semantic,'locked'=>$this->locked,'port'=>true]; }
    private static function name(string $v): string { $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException('Invalid image skeleton identifier','plugin.anatomy-image-fit',true,['value'=>$v]); return $v; }
}

/** A rough vector supplied by the user. The image fitter refines near it only. */
final class AnatomyImageBone implements JsonSerializable
{
    public function __construct(
        private string $id,
        private string $a,
        private string $b,
        private string $semantic='bone',
        private string $pass='fundamental',
        private bool $locked=false,
    ) {
        $this->id=self::name($id);$this->a=self::name($a);$this->b=self::name($b);$this->semantic=self::name($semantic);$this->pass=self::name($pass);
        if($this->a===$this->b) throw new JxException('Image bone endpoints must differ','plugin.anatomy-image-fit',true);
    }
    public function jsonSerialize(): array { return ['id'=>$this->id,'a'=>$this->a,'b'=>$this->b,'semantic'=>$this->semantic,'pass'=>$this->pass,'locked'=>$this->locked]; }
    private static function name(string $v): string { $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException('Invalid image skeleton identifier','plugin.anatomy-image-fit',true,['value'=>$v]); return $v; }
}

final class AnatomyImagePass implements JsonSerializable
{
    public function __construct(private string $id,private string $kind='fundamental',private bool $locked=false)
    { $this->id=self::name($id);$this->kind=self::name($kind); }
    public function jsonSerialize(): array { return ['id'=>$this->id,'kind'=>$this->kind,'locked'=>$this->locked]; }
    private static function name(string $v): string { $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException('Invalid image pass identifier','plugin.anatomy-image-fit',true); return $v; }
}

/**
 * Serializable, multi-pass vector prior for deterministic image fitting.
 * Pixels are deliberately not embedded here: image ownership/storage belongs
 * to the host. The plan remains small enough for webserver queues and Bags.
 */
final class AnatomyImageSkeleton implements JsonSerializable
{
    /** @var array<string,AnatomyImageJoint> */ private array $joints=[];
    /** @var array<string,AnatomyImageBone> */ private array $bones=[];
    /** @var array<string,AnatomyImagePass> */ private array $passes=[];

    public function __construct(private string $id,private int $width,private int $height,private ?string $source=null)
    {
        $this->id=self::name($id); if($width<2||$height<2||$width>32768||$height>32768) throw new JxException('Invalid image skeleton dimensions','plugin.anatomy-image-fit',true);
        if($source!==null){$source=trim($source);if($source===''||strlen($source)>4096||str_contains($source,"\0"))throw new JxException('Invalid image source','plugin.anatomy-image-fit',true);$this->source=$source;}
    }
    public function pass(AnatomyImagePass $pass): self { $copy=clone $this;$d=$pass->jsonSerialize();$copy->passes[$d['id']]=$pass;return $copy; }
    public function joint(AnatomyImageJoint $joint): self { $copy=clone $this;$d=$joint->jsonSerialize();$copy->joints[$d['id']]=$joint;return $copy; }
    public function bone(AnatomyImageBone $bone): self { $copy=clone $this;$d=$bone->jsonSerialize();$copy->bones[$d['id']]=$bone;return $copy; }
    public function jsonSerialize(): array { return ['kind'=>'anatomy-image-skeleton','plugin'=>'anatomy-image-fit','version'=>AnatomyImageFitPlugin::VERSION,'id'=>$this->id,'image'=>['width'=>$this->width,'height'=>$this->height,'source'=>$this->source],'passes'=>array_map(fn($v)=>$v->jsonSerialize(),array_values($this->passes)),'joints'=>array_map(fn($v)=>$v->jsonSerialize(),array_values($this->joints)),'bones'=>array_map(fn($v)=>$v->jsonSerialize(),array_values($this->bones))]; }
    private static function name(string $v): string { $v=trim($v);if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v))throw new JxException('Invalid image skeleton id','plugin.anatomy-image-fit',true);return $v; }
}

final class AnatomyImageFitPlugin implements JxPluginExtension
{
    public const VERSION='jx.anatomy-image-fit/1';
    public function id(): string { return 'anatomy-image-fit'; }
    public function version(): string { return self::VERSION; }
    public function extendsPlugin(): string { return 'anatomy'; }
    public function capabilities(): array { return [
        'anatomy.image.reference','anatomy.image.vector-skeleton','anatomy.image.body-port',
        'anatomy.image.multi-pass','anatomy.image.sobel','anatomy.image.vector-prior',
        'anatomy.image.bilateral-edge-fit','anatomy.image.width-fit','anatomy.image.to-rig'
    ]; }
    public function normalizeExtensionOptions(array $with): array
    {
        $with=Boundary::import($with);
        $with['maxSide']=max(64,min(2048,(int)($with['maxSide']??768)));
        $with['passes']=max(1,min(8,(int)($with['passes']??2)));
        $with['snapStrength']=max(0.0,min(1.0,(float)($with['snapStrength']??0.68)));
        $with['jointSnap']=max(0.0,min(1.0,(float)($with['jointSnap']??0.32)));
        return $with;
    }
    public static function skeleton(string $id,int $width,int $height,?string $source=null): AnatomyImageSkeleton { return new AnatomyImageSkeleton($id,$width,$height,$source); }
    public static function pass(string $id,string $kind='fundamental',bool $locked=false): AnatomyImagePass { return new AnatomyImagePass($id,$kind,$locked); }
    public static function joint(string $id,float $x,float $y,string $semantic='joint',float $z=0.0,bool $locked=false): AnatomyImageJoint { return new AnatomyImageJoint($id,$x,$y,$semantic,$z,$locked); }
    public static function bone(string $id,string $a,string $b,string $semantic='bone',string $pass='fundamental',bool $locked=false): AnatomyImageBone { return new AnatomyImageBone($id,$a,$b,$semantic,$pass,$locked); }
}

Plugins::register(new AnatomyImageFitPlugin());

}
