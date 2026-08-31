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

final class AnatomyIKChain implements JsonSerializable
{
    /** @param list<string> $joints @param list<string> $bones */
    public function __construct(
        private string $id,
        private array $joints,
        private array $bones = [],
        private ?array $pole = null,
        private int $iterations = 14,
        private float $tolerance = 0.0004,
    ) {
        $this->id = self::name($id);
        if (count($joints) < 2) throw new JxException('IK chain requires at least two joints','plugin.anatomy-ik',true);
        $this->joints = array_values(array_map([self::class,'name'],$joints));
        $this->bones = array_values(array_map([self::class,'name'],$bones));
        if ($this->bones !== [] && count($this->bones) !== count($this->joints)-1) throw new JxException('IK bone count must be joints-1','plugin.anatomy-ik',true);
        if ($pole !== null) $this->pole = self::vec3($pole);
        $this->iterations = max(1,min(64,$iterations));
        if (!is_finite($tolerance) || $tolerance <= 0) throw new JxException('IK tolerance must be positive','plugin.anatomy-ik',true);
        $this->tolerance = $tolerance;
    }

    public function jsonSerialize(): array
    {
        return [
            'id'=>$this->id,'joints'=>$this->joints,'bones'=>$this->bones,
            'pole'=>$this->pole,'iterations'=>$this->iterations,'tolerance'=>$this->tolerance,
            'solver'=>'fabrik','rootLocked'=>true,
        ];
    }

    private static function name(string $v): string
    {
        $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException('Invalid anatomy IK identifier','plugin.anatomy-ik',true,['value'=>$v]); return $v;
    }
    private static function vec3(array $v): array
    {
        $out=[]; for($i=0;$i<3;$i++){ $n=(float)($v[$i]??0); if(!is_finite($n)) throw new JxException('Invalid anatomy IK vector','plugin.anatomy-ik',true); $out[]=$n; } return $out;
    }
}

final class AnatomyIKMotion implements JsonSerializable
{
    /** @var list<array{time:float,position:array<int,float>}> */ private array $raw=[];
    /** @var list<array{time:float,position:array<int,float>}> */ private array $keyframes=[];

    /** @param list<array<string,mixed>> $frames */
    public function __construct(
        private string $id,
        private string $chain,
        array $frames,
        private float $smooth=.65,
        private float $linearize=0.0,
        private int $passes=3,
        private bool $loop=false,
    ) {
        $this->id=self::name($id); $this->chain=self::name($chain);
        $this->smooth=self::unit($smooth); $this->linearize=self::unit($linearize); $this->passes=max(0,min(16,$passes));
        foreach($frames as $f) $this->raw[]=self::frame($f);
        usort($this->raw,static fn(array $a,array $b):int=>$a['time']<=>$b['time']);
        if(count($this->raw)<2) throw new JxException('IK motion requires at least two target frames','plugin.anatomy-ik.motion',true);
        $this->keyframes=self::smoothFrames($this->raw,$this->smooth,$this->linearize,$this->passes);
    }

    public function jsonSerialize(): array
    {
        $duration=max(.001,$this->keyframes[count($this->keyframes)-1]['time']);
        return [
            'id'=>$this->id,'kind'=>'ik-motion','chain'=>$this->chain,'duration'=>$duration,'loop'=>$this->loop,
            'rawKeyframes'=>$this->raw,'keyframes'=>$this->keyframes,
            'settings'=>['smooth'=>$this->smooth,'linearize'=>$this->linearize,'passes'=>$this->passes],
        ];
    }

    /** @param list<array{time:float,position:array<int,float>}> $frames */
    public static function smoothFrames(array $frames,float $smooth,float $linearize,int $passes): array
    {
        $out=$frames; $smooth=self::unit($smooth); $linearize=self::unit($linearize); $passes=max(0,min(16,$passes));
        if(count($out)<3) return $out;
        for($pass=0;$pass<$passes;$pass++) {
            $before=$out; $last=count($out)-1;
            for($i=1;$i<$last;$i++) for($axis=0;$axis<3;$axis++) {
                $a=$before[$i-1]['position'][$axis]; $b=$before[$i]['position'][$axis]; $c=$before[$i+1]['position'][$axis];
                $filtered=($a+2*$b+$c)/4.0; $out[$i]['position'][$axis]=$b+($filtered-$b)*$smooth;
            }
        }
        if($linearize>0) {
            $first=$out[0]; $last=$out[count($out)-1]; $span=max(1.0e-9,$last['time']-$first['time']);
            foreach($out as $i=>$f) {
                if($i===0||$i===count($out)-1) continue;
                $u=max(0.0,min(1.0,($f['time']-$first['time'])/$span));
                for($axis=0;$axis<3;$axis++) {
                    $line=$first['position'][$axis]+($last['position'][$axis]-$first['position'][$axis])*$u;
                    $out[$i]['position'][$axis]+=$linearize*($line-$out[$i]['position'][$axis]);
                }
            }
        }
        return $out;
    }

    private static function frame(array $f): array
    {
        $time=(float)($f['time']??0); if(!is_finite($time)||$time<0) throw new JxException('Invalid IK motion time','plugin.anatomy-ik.motion',true);
        $p=is_array($f['position']??null)?$f['position']:[]; $out=[];
        for($i=0;$i<3;$i++){ $n=(float)($p[$i]??0); if(!is_finite($n)) throw new JxException('Invalid IK motion position','plugin.anatomy-ik.motion',true); $out[]=$n; }
        return ['time'=>$time,'position'=>$out];
    }
    private static function unit(float $v): float { if(!is_finite($v)) throw new JxException('Invalid IK motion amount','plugin.anatomy-ik.motion',true); return max(0.0,min(1.0,$v)); }
    private static function name(string $v): string { $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException('Invalid IK motion identifier','plugin.anatomy-ik.motion',true); return $v; }
}

final class AnatomyIKPlugin implements JxPluginExtension
{
    public const VERSION='jx.anatomy-ik/1';
    public function id(): string { return 'anatomy-ik'; }
    public function version(): string { return self::VERSION; }
    public function extendsPlugin(): string { return 'anatomy'; }
    public function capabilities(): array { return ['anatomy.ik','anatomy.ik.fabrik','anatomy.ik.pole','anatomy.ik.mouse-target','anatomy.ik.motion','anatomy.ik.motion.smooth','anatomy.ik.motion.linearize']; }
    public function normalizeExtensionOptions(array $with): array { return Boundary::import($with); }
    /** @param list<string> $joints @param list<string> $bones */
    public static function chain(string $id,array $joints,array $bones=[],?array $pole=null,int $iterations=14,float $tolerance=.0004): AnatomyIKChain { return new AnatomyIKChain($id,$joints,$bones,$pole,$iterations,$tolerance); }
    /** @param list<array<string,mixed>> $frames */
    public static function motion(string $id,string $chain,array $frames,float $smooth=.65,float $linearize=0,int $passes=3,bool $loop=false): AnatomyIKMotion { return new AnatomyIKMotion($id,$chain,$frames,$smooth,$linearize,$passes,$loop); }
}

Plugins::register(new AnatomyIKPlugin());

}
