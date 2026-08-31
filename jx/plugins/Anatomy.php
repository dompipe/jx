<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/Plugin.php';
}

namespace jx\plugins {

use JsonSerializable;
use jx\Boundary;
use jx\JxException;
use jx\JxPlugin;
use jx\Plugins;

final class AnatomyTexture implements JsonSerializable
{
    public const MODES = ['uv','project','procedural','paint'];
    /** @param array<string,mixed> $with */
    public function __construct(
        private string $id,
        private string $mode,
        private ?string $source = null,
        private array $with = [],
    ) {
        $this->id = self::name($id, 'texture id');
        $this->mode = strtolower(trim($mode));
        if (!in_array($this->mode, self::MODES, true)) throw new JxException('Unsupported anatomy texture mode', 'plugin.anatomy.texture', true, ['mode'=>$mode]);
        if ($source !== null) $this->source = self::uri($source);
        $this->with = self::options($with);
    }

    /** @param array<string,mixed> $transform */
    public function aligned(array $transform): self
    {
        $copy = clone $this;
        $copy->with['transform'] = self::textureTransform($transform);
        return $copy;
    }

    /** @param list<array<string|int,float|int|string>> $pins */
    public function pinned(array $pins): self
    {
        $copy = clone $this;
        $clean = [];
        foreach ($pins as $pin) {
            if (!is_array($pin)) continue;
            $clean[] = [
                'u'=>(float)($pin['u'] ?? 0), 'v'=>(float)($pin['v'] ?? 0),
                'x'=>(float)($pin['x'] ?? 0), 'y'=>(float)($pin['y'] ?? 0), 'z'=>(float)($pin['z'] ?? 0),
            ];
        }
        $copy->with['pins'] = $clean;
        return $copy;
    }

    public function jsonSerialize(): array
    {
        return ['id'=>$this->id,'mode'=>$this->mode,'source'=>$this->source,'with'=>$this->with];
    }

    private static function textureTransform(array $t): array
    {
        $out = [
            'offset'=>[(float)($t['offset'][0] ?? 0),(float)($t['offset'][1] ?? 0)],
            'scale'=>[(float)($t['scale'][0] ?? 1),(float)($t['scale'][1] ?? 1)],
            'rotation'=>(float)($t['rotation'] ?? 0),
            'pivot'=>[(float)($t['pivot'][0] ?? .5),(float)($t['pivot'][1] ?? .5)],
        ];
        foreach (array_merge($out['offset'],$out['scale'],$out['pivot'],[$out['rotation']]) as $v) if (!is_finite($v)) throw new JxException('Invalid texture transform', 'plugin.anatomy.texture', true);
        return $out;
    }
    private static function options(array $v): array { return Boundary::import($v); }
    private static function uri(string $v): string { $v=trim($v); if($v===''||strlen($v)>4096||str_contains($v,"\0")) throw new JxException('Invalid texture source','plugin.anatomy.texture',true); return $v; }
    private static function name(string $v,string $what): string { $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException("Invalid {$what}",'plugin.anatomy',true); return $v; }
}

final class AnatomyPart implements JsonSerializable
{
    /** @var array<string,AnatomyTexture> */ private array $textures = [];
    /** @param array<string,mixed> $params @param array<string,mixed> $transform */
    public function __construct(
        private string $id,
        private string $type,
        private array $params = [],
        private array $transform = [],
        private ?string $parent = null,
    ) {
        $this->id = self::name($id);
        $this->type = self::name($type);
        $this->params = Boundary::import($params);
        $this->transform = self::transform($transform);
        if ($parent !== null) $this->parent = self::name($parent);
    }

    public function withTexture(AnatomyTexture $texture): self { $copy=clone $this; $d=$texture->jsonSerialize(); $copy->textures[$d['id']]=$texture; return $copy; }
    /** @param array<string,mixed> $transform */
    public function moved(array $transform): self { $copy=clone $this; $copy->transform=self::transform($transform); return $copy; }
    /** @param array<string,mixed> $params */
    public function shaped(array $params): self { $copy=clone $this; $copy->params=array_replace($copy->params,Boundary::import($params)); return $copy; }

    public function jsonSerialize(): array
    {
        return [
            'id'=>$this->id,'type'=>$this->type,'parent'=>$this->parent,
            'params'=>$this->params,'transform'=>$this->transform,
            'textures'=>array_map(fn(AnatomyTexture $t)=>$t->jsonSerialize(),array_values($this->textures)),
        ];
    }

    private static function name(string $v): string { $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException('Invalid anatomy identifier','plugin.anatomy',true,['value'=>$v]); return $v; }
    private static function transform(array $t): array
    {
        $v = [
            'position'=>[(float)($t['position'][0]??0),(float)($t['position'][1]??0),(float)($t['position'][2]??0)],
            'rotation'=>[(float)($t['rotation'][0]??0),(float)($t['rotation'][1]??0),(float)($t['rotation'][2]??0)],
            'scale'=>[(float)($t['scale'][0]??1),(float)($t['scale'][1]??1),(float)($t['scale'][2]??1)],
        ];
        foreach(array_merge($v['position'],$v['rotation'],$v['scale']) as $n) if(!is_finite($n)) throw new JxException('Invalid anatomy transform','plugin.anatomy',true);
        return $v;
    }
}

/**
 * One deterministic animation track for one anatomy part.
 *
 * A track stores the raw mouse-created transforms as keyframes. smoothed()
 * removes short hand jitter, while linearize pulls the path toward the direct
 * endpoint path. No model API, GPU, or remote service is required.
 */
final class AnatomyAnimationTrack implements JsonSerializable
{
    /** @var list<array<string,mixed>> */ private array $keyframes=[];

    /** @param list<array<string,mixed>> $keyframes */
    public function __construct(
        private string $id,
        private string $part,
        array $keyframes = [],
        private string $interpolation = 'linear',
    ) {
        $this->id=self::name($id,'track id');
        $this->part=self::name($part,'part id');
        $this->interpolation=strtolower(trim($interpolation));
        if(!in_array($this->interpolation,['linear','smooth'],true)) throw new JxException('Unsupported anatomy animation interpolation','plugin.anatomy.animation',true,['interpolation'=>$interpolation]);
        foreach($keyframes as $frame) $this->keyframes[]=self::frame($frame);
        usort($this->keyframes,static fn(array $a,array $b): int => $a['time'] <=> $b['time']);
    }

    /** @param array<string,mixed> $transform */
    public function keyframe(float $time,array $transform): self
    {
        if(!is_finite($time)||$time<0) throw new JxException('Animation keyframe time must be finite and non-negative','plugin.anatomy.animation',true);
        $copy=clone $this;
        $copy->keyframes[]=self::frame(['time'=>$time,'transform'=>$transform]);
        usort($copy->keyframes,static fn(array $a,array $b): int => $a['time'] <=> $b['time']);
        return $copy;
    }

    public function smoothed(float $smooth=.65,float $linearize=0.0,int $passes=2): self
    {
        $smooth=self::unit($smooth,'smooth'); $linearize=self::unit($linearize,'linearize');
        $passes=max(0,min(12,$passes));
        $copy=clone $this;
        if(count($copy->keyframes)<3) return $copy;

        for($pass=0;$pass<$passes;$pass++) {
            $before=$copy->keyframes; $last=count($before)-1;
            for($i=1;$i<$last;$i++) {
                foreach(['position','rotation','scale'] as $field) {
                    $a=$before[$i-1]['transform'][$field]; $b=$before[$i]['transform'][$field]; $c=$before[$i+1]['transform'][$field];
                    for($axis=0;$axis<3;$axis++) {
                        $filtered=($a[$axis]+2*$b[$axis]+$c[$axis])/4.0;
                        $copy->keyframes[$i]['transform'][$field][$axis]=$b[$axis]+($filtered-$b[$axis])*$smooth;
                    }
                }
            }
        }

        if($linearize>0) {
            $first=$copy->keyframes[0]; $last=$copy->keyframes[count($copy->keyframes)-1];
            $span=max(1.0e-9,$last['time']-$first['time']);
            foreach($copy->keyframes as $i=>$frame) {
                if($i===0||$i===count($copy->keyframes)-1) continue;
                $u=max(0.0,min(1.0,($frame['time']-$first['time'])/$span));
                foreach(['position','rotation','scale'] as $field) {
                    for($axis=0;$axis<3;$axis++) {
                        $line=$first['transform'][$field][$axis]+($last['transform'][$field][$axis]-$first['transform'][$field][$axis])*$u;
                        $v=$copy->keyframes[$i]['transform'][$field][$axis];
                        $copy->keyframes[$i]['transform'][$field][$axis]=$v+($line-$v)*$linearize;
                    }
                }
            }
        }
        $copy->interpolation=$smooth>0 ? 'smooth' : 'linear';
        return $copy;
    }

    public function jsonSerialize(): array
    {
        return ['id'=>$this->id,'part'=>$this->part,'interpolation'=>$this->interpolation,'keyframes'=>$this->keyframes];
    }

    /** @param array<string,mixed> $frame */
    private static function frame(array $frame): array
    {
        $time=(float)($frame['time']??0); if(!is_finite($time)||$time<0) throw new JxException('Invalid anatomy animation keyframe time','plugin.anatomy.animation',true);
        $t=is_array($frame['transform']??null)?$frame['transform']:[];
        $out=['time'=>$time,'transform'=>[
            'position'=>self::vec3($t['position']??null,[0,0,0]),
            'rotation'=>self::vec3($t['rotation']??null,[0,0,0]),
            'scale'=>self::vec3($t['scale']??null,[1,1,1]),
        ]];
        return $out;
    }
    private static function vec3(mixed $v,array $fallback): array { $v=is_array($v)?$v:[]; $out=[]; for($i=0;$i<3;$i++){ $n=(float)($v[$i]??$fallback[$i]); if(!is_finite($n)) throw new JxException('Invalid animation vector','plugin.anatomy.animation',true); $out[]=$n; } return $out; }
    private static function unit(float $v,string $name): float { if(!is_finite($v)) throw new JxException("Invalid {$name}",'plugin.anatomy.animation',true); return max(0.0,min(1.0,$v)); }
    private static function name(string $v,string $what): string { $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException("Invalid {$what}",'plugin.anatomy.animation',true); return $v; }
}

final class AnatomyAnimation implements JsonSerializable
{
    /** @var array<string,AnatomyAnimationTrack> */ private array $tracks=[];
    public function __construct(private string $id,private float $duration=1.0,private bool $loop=false)
    {
        $this->id=self::name($id); if(!is_finite($duration)||$duration<=0) throw new JxException('Animation duration must be positive','plugin.anatomy.animation',true); $this->duration=$duration;
    }
    public function add(AnatomyAnimationTrack $track): self { $copy=clone $this; $d=$track->jsonSerialize(); $copy->tracks[$d['id']]=$track; return $copy; }
    public function jsonSerialize(): array { return ['id'=>$this->id,'duration'=>$this->duration,'loop'=>$this->loop,'tracks'=>array_map(fn(AnatomyAnimationTrack $t)=>$t->jsonSerialize(),array_values($this->tracks))]; }
    private static function name(string $v): string { $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException('Invalid anatomy animation id','plugin.anatomy.animation',true); return $v; }
}

final class AnatomyModel implements JsonSerializable
{
    /** @var array<string,AnatomyPart> */ private array $parts=[];
    /** @var array<string,AnatomyAnimation> */ private array $animations=[];
    public function __construct(private string $id, private string $species='generic') { $this->id=self::name($id); $this->species=self::name($species); }
    public function add(AnatomyPart $part): self { $copy=clone $this; $d=$part->jsonSerialize(); $copy->parts[$d['id']]=$part; return $copy; }
    public function animate(AnatomyAnimation $animation): self { $copy=clone $this; $d=$animation->jsonSerialize(); $copy->animations[$d['id']]=$animation; return $copy; }
    public function jsonSerialize(): array { return ['kind'=>'model','model'=>'anatomy','plugin'=>'anatomy','version'=>AnatomyPlugin::VERSION,'id'=>$this->id,'species'=>$this->species,'parts'=>array_map(fn(AnatomyPart $p)=>$p->jsonSerialize(),array_values($this->parts)),'animations'=>array_map(fn(AnatomyAnimation $a)=>$a->jsonSerialize(),array_values($this->animations))]; }
    private static function name(string $v): string { $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException('Invalid anatomy model identifier','plugin.anatomy',true); return $v; }
}

final class AnatomyPlugin implements JxPlugin
{
    public const VERSION='jx.anatomy/2';
    public function id(): string { return 'anatomy'; }
    public function version(): string { return self::VERSION; }
    public function capabilities(): array { return [
        'anatomy.skeleton','anatomy.pipe-bone','anatomy.ball-joint','anatomy.archetype',
        'anatomy.texture.uv','anatomy.texture.project','anatomy.texture.procedural','anatomy.texture.paint','anatomy.texture.align','anatomy.texture.pin',
        'anatomy.animation','anatomy.animation.mouse-path','anatomy.animation.smooth','anatomy.animation.linearize','anatomy.animation.playback'
    ]; }

    public static function model(string $id,string $species='generic'): AnatomyModel { return new AnatomyModel($id,$species); }
    /** @param array<string,mixed> $params @param array<string,mixed> $transform */
    public static function part(string $id,string $type,array $params=[],array $transform=[],?string $parent=null): AnatomyPart { return new AnatomyPart($id,$type,$params,$transform,$parent); }
    /** @param array<string,mixed> $with */
    public static function texture(string $id,string $mode,?string $source=null,array $with=[]): AnatomyTexture { return new AnatomyTexture($id,$mode,$source,$with); }
    /** @param list<array<string,mixed>> $keyframes */
    public static function animationTrack(string $id,string $part,array $keyframes=[],string $interpolation='linear'): AnatomyAnimationTrack { return new AnatomyAnimationTrack($id,$part,$keyframes,$interpolation); }
    public static function animation(string $id,float $duration=1.0,bool $loop=false): AnatomyAnimation { return new AnatomyAnimation($id,$duration,$loop); }
}

Plugins::register(new AnatomyPlugin());

}
