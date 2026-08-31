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
        $copy->with['transform'] = self::transform($transform);
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

    private static function transform(array $t): array
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

final class AnatomyModel implements JsonSerializable
{
    /** @var array<string,AnatomyPart> */ private array $parts=[];
    public function __construct(private string $id, private string $species='generic') { $this->id=self::name($id); $this->species=self::name($species); }
    public function add(AnatomyPart $part): self { $copy=clone $this; $d=$part->jsonSerialize(); $copy->parts[$d['id']]=$part; return $copy; }
    public function jsonSerialize(): array { return ['kind'=>'model','model'=>'anatomy','plugin'=>'anatomy','version'=>AnatomyPlugin::VERSION,'id'=>$this->id,'species'=>$this->species,'parts'=>array_map(fn(AnatomyPart $p)=>$p->jsonSerialize(),array_values($this->parts))]; }
    private static function name(string $v): string { $v=trim($v); if($v===''||strlen($v)>128||preg_match('/[^a-z0-9._-]/i',$v)) throw new JxException('Invalid anatomy model identifier','plugin.anatomy',true); return $v; }
}

final class AnatomyPlugin implements JxPlugin
{
    public const VERSION='jx.anatomy/1';
    public function id(): string { return 'anatomy'; }
    public function version(): string { return self::VERSION; }
    public function capabilities(): array { return ['anatomy.skeleton','anatomy.pipe-bone','anatomy.ball-joint','anatomy.archetype','anatomy.texture.uv','anatomy.texture.project','anatomy.texture.procedural','anatomy.texture.paint','anatomy.texture.align','anatomy.texture.pin']; }

    public static function model(string $id,string $species='generic'): AnatomyModel { return new AnatomyModel($id,$species); }
    /** @param array<string,mixed> $params @param array<string,mixed> $transform */
    public static function part(string $id,string $type,array $params=[],array $transform=[],?string $parent=null): AnatomyPart { return new AnatomyPart($id,$type,$params,$transform,$parent); }
    /** @param array<string,mixed> $with */
    public static function texture(string $id,string $mode,?string $source=null,array $with=[]): AnatomyTexture { return new AnatomyTexture($id,$mode,$source,$with); }
}

Plugins::register(new AnatomyPlugin());

}
