<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/Plugin.php';
    require_once __DIR__ . '/Anatomy.php';
}

namespace jx\plugins {

use jx\JxException;
use jx\JxPluginExtension;
use jx\Plugins;

/**
 * JX-native glTF 2.0 binary exporter for Anatomy models.
 *
 * No Three.js exporter is used. The JX anatomy descriptor is converted directly
 * to GLB: semantic bones become muscle-profiled tube meshes, joints become
 * spheres, and optional PNG body-part skins are embedded in the GLB buffer.
 */
final class AnatomyGLBPlugin implements JxPluginExtension
{
    public const VERSION = 'jx.anatomy-glb/1';

    public function id(): string { return 'anatomy-glb'; }
    public function version(): string { return self::VERSION; }
    public function extendsPlugin(): string { return 'anatomy'; }
    public function capabilities(): array
    {
        return [
            'anatomy.export.glb', 'anatomy.export.gltf2', 'anatomy.export.mesh',
            'anatomy.export.png-texture', 'anatomy.export.semantic-muscle',
        ];
    }
    public function normalizeExtensionOptions(array $with): array { return $with; }

    /**
     * @param array<string,mixed> $descriptor JX Anatomy descriptor.
     * @param list<array<string,mixed>> $textures Body-part PNG payloads.
     */
    public static function export(array $descriptor, array $textures = []): string
    {
        return (new AnatomyGlbWriter($descriptor, $textures))->build();
    }
}

/** @internal */
final class AnatomyGlbWriter
{
    /** @var array<string,mixed> */ private array $model;
    /** @var list<array<string,mixed>> */ private array $textures;
    private string $bin = '';
    /** @var list<array<string,mixed>> */ private array $bufferViews = [];
    /** @var list<array<string,mixed>> */ private array $accessors = [];
    /** @var list<array<string,mixed>> */ private array $meshes = [];
    /** @var list<array<string,mixed>> */ private array $nodes = [];
    /** @var list<array<string,mixed>> */ private array $materials = [];
    /** @var list<array<string,mixed>> */ private array $images = [];
    /** @var list<array<string,mixed>> */ private array $gltfTextures = [];
    /** @var array<string,int> */ private array $materialByBodyPart = [];
    /** @var array<string,array{0:float,1:float}> */ private array $uvRangeByBone = [];
    /** @var array<string,array<string,mixed>> */ private array $bodyPartByBone = [];
    /** @var array<string,array<string,mixed>> */ private array $textureByBodyPart = [];

    /** @param array<string,mixed> $descriptor @param list<array<string,mixed>> $textures */
    public function __construct(array $descriptor, array $textures)
    {
        if (($descriptor['model'] ?? null) !== 'anatomy') {
            throw new JxException('GLB export requires a JX anatomy descriptor', 'plugin.anatomy-glb', true);
        }
        $this->model = $descriptor;
        $this->textures = $textures;
        foreach ($textures as $texture) {
            if (!is_array($texture)) continue;
            $id = trim((string)($texture['bodyPart'] ?? ''));
            if ($id !== '') $this->textureByBodyPart[$id] = $texture;
        }
        $this->indexBodyParts();
    }

    public function build(): string
    {
        $this->buildMaterials();
        $rootNodes = [];
        foreach (($this->model['parts'] ?? []) as $part) {
            if (!is_array($part)) continue;
            $node = $this->addPart($part);
            if ($node !== null) $rootNodes[] = $node;
        }
        if ($rootNodes === []) {
            throw new JxException('Anatomy descriptor contains no exportable geometry', 'plugin.anatomy-glb', true);
        }

        $gltf = [
            'asset' => ['version'=>'2.0', 'generator'=>'JX AnatomyGLB '.AnatomyGLBPlugin::VERSION],
            'scene' => 0,
            'scenes' => [['name'=>(string)($this->model['id'] ?? 'JX Anatomy'), 'nodes'=>$rootNodes]],
            'nodes' => $this->nodes,
            'meshes' => $this->meshes,
            'accessors' => $this->accessors,
            'bufferViews' => $this->bufferViews,
            'buffers' => [['byteLength'=>strlen($this->bin)]],
            'materials' => $this->materials,
            'extras' => [
                'jxVersion'=>(string)($this->model['version'] ?? 'jx.anatomy/2'),
                'species'=>(string)($this->model['species'] ?? 'generic'),
                'bodyParts'=>$this->compactBodyParts(),
            ],
        ];
        if ($this->images !== []) {
            $gltf['images'] = $this->images;
            $gltf['textures'] = $this->gltfTextures;
            $gltf['samplers'] = [['magFilter'=>9729,'minFilter'=>9987,'wrapS'=>10497,'wrapT'=>10497]];
        }

        $json = json_encode($gltf, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $json .= str_repeat(' ', (4 - (strlen($json) % 4)) % 4);
        $bin = $this->bin . str_repeat("\0", (4 - (strlen($this->bin) % 4)) % 4);
        $total = 12 + 8 + strlen($json) + 8 + strlen($bin);
        return pack('V3', 0x46546C67, 2, $total)
            . pack('V2', strlen($json), 0x4E4F534A) . $json
            . pack('V2', strlen($bin), 0x004E4942) . $bin;
    }

    private function indexBodyParts(): void
    {
        foreach (($this->model['bodyParts'] ?? []) as $bp) {
            if (!is_array($bp)) continue;
            $segments = is_array($bp['segments'] ?? null) ? $bp['segments'] : [];
            $boneIds = [];
            foreach ($segments as $seg) {
                if (is_array($seg) && isset($seg['id'])) $boneIds[] = (string)$seg['id'];
            }
            foreach (($bp['boneIds'] ?? []) as $id) $boneIds[] = (string)$id;
            $boneIds = array_values(array_unique(array_filter($boneIds)));
            foreach ($boneIds as $id) $this->bodyPartByBone[$id] = $bp;

            $lengths = [];
            $total = 0.0;
            foreach ($boneIds as $id) {
                $part = $this->partById($id);
                $len = max(1e-6, (float)($part['params']['length'] ?? 1.0));
                $lengths[$id] = $len; $total += $len;
            }
            $acc = 0.0;
            foreach ($boneIds as $id) {
                $len = $lengths[$id] ?? 1.0;
                $this->uvRangeByBone[$id] = [$acc/$total, ($acc+$len)/$total];
                $acc += $len;
            }
        }
    }

    /** @return array<string,mixed> */
    private function partById(string $id): array
    {
        foreach (($this->model['parts'] ?? []) as $part) {
            if (is_array($part) && (string)($part['id'] ?? '') === $id) return $part;
        }
        return [];
    }

    private function buildMaterials(): void
    {
        $this->materials[] = [
            'name'=>'JX default skin',
            'pbrMetallicRoughness'=>['baseColorFactor'=>[0.78,0.56,0.45,1.0],'metallicFactor'=>0.0,'roughnessFactor'=>0.82],
            'doubleSided'=>false,
        ];
        $this->materials[] = [
            'name'=>'JX joint',
            'pbrMetallicRoughness'=>['baseColorFactor'=>[0.69,0.73,0.77,1.0],'metallicFactor'=>0.0,'roughnessFactor'=>0.78],
        ];

        foreach (($this->model['bodyParts'] ?? []) as $bp) {
            if (!is_array($bp)) continue;
            $id = (string)($bp['id'] ?? ''); if ($id === '') continue;
            $texture = $this->textureByBodyPart[$id] ?? null;
            $mat = [
                'name'=>'JX '.((string)($bp['label'] ?? $bp['type'] ?? $id)),
                'pbrMetallicRoughness'=>['baseColorFactor'=>[1.0,1.0,1.0,1.0],'metallicFactor'=>0.0,'roughnessFactor'=>0.80],
            ];
            if (is_array($texture)) {
                $decoded = $this->decodePng((string)($texture['dataUrl'] ?? ''));
                if ($decoded !== null) {
                    $view = $this->addBufferView($decoded, null);
                    $imageIndex = count($this->images);
                    $this->images[] = ['name'=>(string)($texture['name'] ?? ($id.'.png')), 'bufferView'=>$view, 'mimeType'=>'image/png'];
                    $texIndex = count($this->gltfTextures);
                    $this->gltfTextures[] = ['sampler'=>0, 'source'=>$imageIndex];
                    $opacity = $this->clamp((float)($texture['opacity'] ?? 1.0), 0.02, 1.0);
                    $mat['pbrMetallicRoughness']['baseColorTexture'] = ['index'=>$texIndex];
                    $mat['pbrMetallicRoughness']['baseColorFactor'] = [1.0,1.0,1.0,$opacity];
                    if ($opacity < 0.999) $mat['alphaMode'] = 'BLEND';
                }
            }
            $this->materialByBodyPart[$id] = count($this->materials);
            $this->materials[] = $mat;
        }
    }

    /** @param array<string,mixed> $part */
    private function addPart(array $part): ?int
    {
        $id = (string)($part['id'] ?? ('part-'.count($this->nodes)));
        $type = strtolower((string)($part['type'] ?? ''));
        $params = is_array($part['params'] ?? null) ? $part['params'] : [];
        $semantic = strtolower((string)($part['semantic'] ?? $params['semantic'] ?? $type));
        $material = $this->materialForPart($id, $type);
        $uvRange = $this->uvRangeByBone[$id] ?? [0.0,1.0];
        $texture = null;
        if (isset($this->bodyPartByBone[$id])) {
            $bpId = (string)($this->bodyPartByBone[$id]['id'] ?? '');
            $texture = $this->textureByBodyPart[$bpId] ?? null;
        }

        if (in_array($type, ['joint','ball-joint'], true)) {
            $radius = max(0.005, (float)($params['radius'] ?? 0.05));
            $geo = $this->sphere($radius, 16, 10);
            $material = 1;
        } elseif ($type === 'pipe' || $type === 'bone' || str_contains($type,'arm') || str_contains($type,'leg') || str_contains($type,'limb')) {
            $length = max(0.005, (float)($params['length'] ?? 1.0));
            $radius = max(0.004, (float)($params['radius'] ?? $params['thickness'] ?? 0.07));
            $controls = $this->controlsForBone($id);
            $geo = $this->muscleTube($length, $radius, $semantic, $controls, $uvRange, is_array($texture)?$texture:[]);
        } elseif (str_contains($type,'torso')) {
            $geo = $this->sphere(0.60, 22, 14);
        } elseif (str_contains($type,'head') || str_contains($type,'skull')) {
            $geo = $this->sphere(0.34, 20, 14);
        } elseif (str_contains($type,'beak') || str_contains($type,'snout') || str_contains($type,'nose') || str_contains($type,'bill')) {
            $length = max(0.02, (float)($params['length'] ?? 0.5));
            $radius = max(0.01, (float)($params['width'] ?? 0.18));
            $geo = $this->muscleTube($length, $radius, $semantic, [], [0.0,1.0], []);
        } else {
            $geo = $this->sphere(0.10, 12, 8);
        }

        $meshIndex = $this->addMesh($id, $geo, $material);
        $tr = is_array($part['transform'] ?? null) ? $part['transform'] : [];
        $node = ['name'=>$id, 'mesh'=>$meshIndex, 'extras'=>['jxPartId'=>$id,'semantic'=>$semantic,'type'=>$type]];
        $p = $tr['position'] ?? [0,0,0];
        $s = $tr['scale'] ?? [1,1,1];
        $r = $tr['rotation'] ?? [0,0,0];
        $node['translation'] = [(float)($p[0]??0),(float)($p[1]??0),(float)($p[2]??0)];
        $node['scale'] = [(float)($s[0]??1),(float)($s[1]??1),(float)($s[2]??1)];
        $node['rotation'] = $this->eulerQuaternion((float)($r[0]??0),(float)($r[1]??0),(float)($r[2]??0));
        $index = count($this->nodes); $this->nodes[] = $node; return $index;
    }

    private function materialForPart(string $id, string $type): int
    {
        if (in_array($type,['joint','ball-joint'],true)) return 1;
        $bp = $this->bodyPartByBone[$id] ?? null;
        if (is_array($bp)) {
            $bpId = (string)($bp['id'] ?? '');
            if (isset($this->materialByBodyPart[$bpId])) return $this->materialByBodyPart[$bpId];
        }
        return 0;
    }

    /** @return array<string,float> */
    private function controlsForBone(string $id): array
    {
        $bp = $this->bodyPartByBone[$id] ?? [];
        $c = [];
        if (is_array($bp['controls'] ?? null)) $c = $bp['controls'];
        elseif (is_array($bp['anatomy']['controls'] ?? null)) $c = $bp['anatomy']['controls'];
        return [
            'mass'=>(float)($c['mass'] ?? 1.0),
            'muscleTone'=>(float)($c['muscleTone'] ?? 0.35),
            'pumpedness'=>(float)($c['pumpedness'] ?? 0.25),
            'fatCover'=>(float)($c['fatCover'] ?? 0.15),
        ];
    }

    /** @param array<string,mixed> $geo */
    private function addMesh(string $name, array $geo, int $material): int
    {
        $pos = $this->addFloatAccessor($geo['positions'], 'VEC3', 34962, true);
        $norm = $this->addFloatAccessor($geo['normals'], 'VEC3', 34962, false);
        $uv = $this->addFloatAccessor($geo['uvs'], 'VEC2', 34962, false);
        $idx = $this->addIndexAccessor($geo['indices']);
        $mesh = ['name'=>$name, 'primitives'=>[ ['attributes'=>['POSITION'=>$pos,'NORMAL'=>$norm,'TEXCOORD_0'=>$uv],'indices'=>$idx,'material'=>$material,'mode'=>4] ]];
        $index = count($this->meshes); $this->meshes[] = $mesh; return $index;
    }

    /** @param list<float> $values */
    private function addFloatAccessor(array $values, string $type, int $target, bool $bounds): int
    {
        $arity = $type === 'VEC2' ? 2 : 3;
        $bytes = ''; foreach ($values as $v) $bytes .= pack('g', (float)$v);
        $view = $this->addBufferView($bytes, $target);
        $acc = ['bufferView'=>$view,'byteOffset'=>0,'componentType'=>5126,'count'=>intdiv(count($values),$arity),'type'=>$type];
        if ($bounds && $type === 'VEC3' && $values !== []) {
            $min=[INF,INF,INF]; $max=[-INF,-INF,-INF];
            for($i=0;$i<count($values);$i+=3) for($k=0;$k<3;$k++){ $v=(float)$values[$i+$k]; if($v<$min[$k])$min[$k]=$v; if($v>$max[$k])$max[$k]=$v; }
            $acc['min']=$min; $acc['max']=$max;
        }
        $index=count($this->accessors); $this->accessors[]=$acc; return $index;
    }

    /** @param list<int> $indices */
    private function addIndexAccessor(array $indices): int
    {
        $bytes=''; foreach($indices as $i)$bytes.=pack('V',(int)$i);
        $view=$this->addBufferView($bytes,34963);
        $acc=['bufferView'=>$view,'byteOffset'=>0,'componentType'=>5125,'count'=>count($indices),'type'=>'SCALAR'];
        if($indices!==[]){$acc['min']=[min($indices)];$acc['max']=[max($indices)];}
        $index=count($this->accessors);$this->accessors[]=$acc;return $index;
    }

    private function addBufferView(string $bytes, ?int $target): int
    {
        $pad=(4-(strlen($this->bin)%4))%4;if($pad)$this->bin.=str_repeat("\0",$pad);
        $offset=strlen($this->bin);$this->bin.=$bytes;
        $view=['buffer'=>0,'byteOffset'=>$offset,'byteLength'=>strlen($bytes)];if($target!==null)$view['target']=$target;
        $index=count($this->bufferViews);$this->bufferViews[]=$view;return $index;
    }

    /** @return array{positions:list<float>,normals:list<float>,uvs:list<float>,indices:list<int>} */
    private function sphere(float $radius, int $segments, int $rings): array
    {
        $p=[];$n=[];$uv=[];$idx=[];
        for($y=0;$y<=$rings;$y++){
            $v=$y/$rings;$phi=M_PI*$v;
            for($x=0;$x<=$segments;$x++){
                $u=$x/$segments;$theta=2*M_PI*$u;
                $sx=sin($phi)*cos($theta);$sy=cos($phi);$sz=sin($phi)*sin($theta);
                array_push($p,$radius*$sx,$radius*$sy,$radius*$sz);array_push($n,$sx,$sy,$sz);array_push($uv,$u,1-$v);
            }
        }
        $row=$segments+1;
        for($y=0;$y<$rings;$y++)for($x=0;$x<$segments;$x++){
            $a=$y*$row+$x;$b=$a+$row;$c=$b+1;$d=$a+1;
            array_push($idx,$a,$b,$d,$d,$b,$c);
        }
        return ['positions'=>$p,'normals'=>$n,'uvs'=>$uv,'indices'=>$idx];
    }

    /** @param array<string,float> $controls @param array{0:float,1:float} $uvRange @param array<string,mixed> $texture */
    private function muscleTube(float $length,float $radius,string $semantic,array $controls,array $uvRange,array $texture): array
    {
        $radial=20;$rings=10;$p=[];$n=[];$uv=[];$idx=[];
        $tone=$this->clamp((float)($controls['muscleTone']??0.35),0,1);
        $pump=$this->clamp((float)($controls['pumpedness']??0.25),0,2);
        $mass=$this->clamp((float)($controls['mass']??1.0),0.2,3);
        $fat=$this->clamp((float)($controls['fatCover']??0.15),0,2);
        $gain=(1+$tone*.22+$pump*.34)*$mass*(1+$fat*.10);
        [$a,$m,$b]=$this->profile($semantic);
        for($y=0;$y<=$rings;$y++){
            $t=$y/$rings;$shape=$t<=.5?$a+($m-$a)*$t*2:$m+($b-$m)*($t-.5)*2;
            $rr=max(.001,$radius*$shape*$gain);$py=-$length/2+$length*$t;
            for($x=0;$x<=$radial;$x++){
                $v=$x/$radial;$theta=2*M_PI*$v;$cx=cos($theta);$cz=sin($theta);
                array_push($p,$rr*$cx,$py,$rr*$cz);array_push($n,$cx,0.0,$cz);
                $u=$uvRange[0]+($uvRange[1]-$uvRange[0])*$t;$vv=$v;
                if(!empty($texture['flipU']))$u=1-$u;if(!empty($texture['flipV']))$vv=1-$vv;
                array_push($uv,$u,$vv);
            }
        }
        $row=$radial+1;for($y=0;$y<$rings;$y++)for($x=0;$x<$radial;$x++){
            $i=$y*$row+$x;$j=$i+$row;array_push($idx,$i,$j,$i+1,$i+1,$j,$j+1);
        }
        return ['positions'=>$p,'normals'=>$n,'uvs'=>$uv,'indices'=>$idx];
    }

    /** @return array{0:float,1:float,2:float} */
    private function profile(string $semantic): array
    {
        return match(strtolower($semantic)){
            'upper-arm'=>[.82,1.38,.82], 'forearm'=>[1.12,1.28,.62],
            'thigh'=>[1.24,1.48,.86], 'shin'=>[1.05,1.20,.55], 'foot'=>[.70,.82,.62],
            'humerus'=>[1.12,1.34,.78], 'radius-ulna'=>[1.02,1.18,.58], 'metacarpal'=>[.65,.72,.50],
            'femur'=>[1.18,1.44,.82], 'tibia'=>[1.02,1.20,.58], 'metatarsal'=>[.60,.70,.46],
            'wing-upper'=>[.82,1.02,.68], 'wing-lower'=>[.70,.88,.50], 'wing-hand'=>[.52,.62,.34],
            'neck'=>[1.06,1.14,.90], 'tail'=>[1.0,.86,.26], 'spine'=>[1.20,1.35,1.05],
            'jaw'=>[.95,1.05,.60], 'beak'=>[.82,.58,.15], 'snout'=>[1.02,1.0,.72],
            default=>[1.0,1.0,1.0],
        };
    }

    /** @return array{0:float,1:float,2:float,3:float} */
    private function eulerQuaternion(float $x,float $y,float $z): array
    {
        $c1=cos($x/2);$c2=cos($y/2);$c3=cos($z/2);$s1=sin($x/2);$s2=sin($y/2);$s3=sin($z/2);
        return [$s1*$c2*$c3+$c1*$s2*$s3,$c1*$s2*$c3-$s1*$c2*$s3,$c1*$c2*$s3+$s1*$s2*$c3,$c1*$c2*$c3-$s1*$s2*$s3];
    }

    private function decodePng(string $dataUrl): ?string
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\r\n]+)$#', $dataUrl, $m)) return null;
        $bytes=base64_decode(str_replace(["\r","\n"],'',$m[1]),true);
        if($bytes===false||strlen($bytes)<8||substr($bytes,0,8)!=="\x89PNG\r\n\x1a\n")return null;
        if(strlen($bytes)>16*1024*1024)throw new JxException('PNG skin exceeds 16 MiB','plugin.anatomy-glb',true);
        return $bytes;
    }

    /** @return list<array<string,mixed>> */
    private function compactBodyParts(): array
    {
        $out=[];foreach(($this->model['bodyParts']??[]) as $bp){if(!is_array($bp))continue;$out[]=['id'=>$bp['id']??null,'type'=>$bp['type']??null,'side'=>$bp['side']??null];}return $out;
    }
    private function clamp(float $v,float $a,float $b): float { return max($a,min($b,is_finite($v)?$v:$a)); }
}

Plugins::register(new AnatomyGLBPlugin());

}