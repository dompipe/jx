<?php declare(strict_types=1);

require_once __DIR__ . '/jx/plugins/AnatomyGLB.php';

use jx\plugins\AnatomyGLBPlugin;

function ok(bool $v, string $message): void { if (!$v) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }

$model = [
    'kind'=>'model','model'=>'anatomy','plugin'=>'anatomy','version'=>'jx.anatomy/2','id'=>'glb-test','species'=>'human',
    'parts'=>[
        ['id'=>'shoulder','type'=>'ball-joint','semantic'=>'shoulder','params'=>['radius'=>.08],'transform'=>['position'=>[0,0,0],'rotation'=>[0,0,0],'scale'=>[1,1,1]],'textures'=>[]],
        ['id'=>'upper','type'=>'pipe','semantic'=>'upper-arm','params'=>['radius'=>.07,'length'=>.8,'pumpedness'=>.4],'transform'=>['position'=>[0,-.4,0],'rotation'=>[0,0,0],'scale'=>[1,1,1]],'textures'=>[]],
        ['id'=>'elbow','type'=>'ball-joint','semantic'=>'elbow','params'=>['radius'=>.07],'transform'=>['position'=>[0,-.8,0],'rotation'=>[0,0,0],'scale'=>[1,1,1]],'textures'=>[]],
        ['id'=>'fore','type'=>'pipe','semantic'=>'forearm','params'=>['radius'=>.055,'length'=>.7,'pumpedness'=>.3],'transform'=>['position'=>[0,-1.15,0],'rotation'=>[0,0,0],'scale'=>[1,1,1]],'textures'=>[]],
        ['id'=>'wrist','type'=>'ball-joint','semantic'=>'wrist','params'=>['radius'=>.06],'transform'=>['position'=>[0,-1.5,0],'rotation'=>[0,0,0],'scale'=>[1,1,1]],'textures'=>[]],
    ],
    'bodyParts'=>[[
        'id'=>'arm-1','type'=>'arm','label'=>'Arm','side'=>'right','boneIds'=>['upper','fore'],
        'segments'=>[['id'=>'upper','semantic'=>'upper-arm','a'=>'shoulder','b'=>'elbow','index'=>0],['id'=>'fore','semantic'=>'forearm','a'=>'elbow','b'=>'wrist','index'=>1]],
        'controls'=>['mass'=>1.0,'muscleTone'=>.55,'pumpedness'=>.45,'fatCover'=>.15],
    ]],
];

$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZuxsAAAAASUVORK5CYII=';
$glb = AnatomyGLBPlugin::export($model, [[
    'bodyPart'=>'arm-1','name'=>'skin.png','mime'=>'image/png','dataUrl'=>$png,'opacity'=>1,'flipU'=>false,'flipV'=>false,
]]);

ok(strlen($glb) > 1000, 'GLB should contain binary mesh data');
ok(substr($glb, 0, 4) === 'glTF', 'GLB magic must be glTF');
$head = unpack('Vmagic/Vversion/Vlength', substr($glb,0,12));
ok(($head['version'] ?? 0) === 2, 'GLB version must be 2');
ok(($head['length'] ?? 0) === strlen($glb), 'GLB header length must match output');
$jsonHead = unpack('Vlength/Vtype', substr($glb,12,8));
ok(($jsonHead['type'] ?? 0) === 0x4E4F534A, 'first chunk must be JSON');
$jsonLen = (int)$jsonHead['length'];
$json = json_decode(rtrim(substr($glb,20,$jsonLen)), true, 512, JSON_THROW_ON_ERROR);
ok(($json['asset']['version'] ?? null) === '2.0', 'glTF asset version must be 2.0');
ok(count($json['meshes'] ?? []) === 5, 'all anatomy parts should become meshes');
ok(count($json['nodes'] ?? []) === 5, 'all anatomy parts should become nodes');
ok(count($json['images'] ?? []) === 1, 'PNG must be embedded as a GLB image');
ok(count($json['textures'] ?? []) === 1, 'PNG image must have a glTF texture');
ok(count($json['materials'] ?? []) >= 3, 'body-part textured material should be emitted');
$binOffset = 20 + $jsonLen;
$binHead = unpack('Vlength/Vtype', substr($glb,$binOffset,8));
ok(($binHead['type'] ?? 0) === 0x004E4942, 'second chunk must be BIN');
ok(($binHead['length'] ?? 0) > 0, 'BIN chunk must contain geometry and PNG bytes');

echo "PASS JX AnatomyGLB: ".strlen($glb)." bytes, ".count($json['meshes'])." meshes, embedded PNG\n";
