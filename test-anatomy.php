<?php declare(strict_types=1);
require_once __DIR__.'/jx/plugins/Anatomy.php';

use jx\plugins\AnatomyPlugin;

$skin = AnatomyPlugin::texture('face-photo','project','/assets/reference/front.png',[
    'space'=>'part',
])->aligned([
    'offset'=>[0.05,-0.03],
    'scale'=>[1.12,1.12],
    'rotation'=>0.08,
    'pivot'=>[0.5,0.5],
])->pinned([
    ['u'=>0.50,'v'=>0.42,'x'=>0.0,'y'=>0.12,'z'=>0.31],
    ['u'=>0.72,'v'=>0.45,'x'=>0.16,'y'=>0.10,'z'=>0.25],
]);

$head = AnatomyPlugin::part('head','skull',[
    'length'=>1.0,'width'=>1.0,'depth'=>1.0,'muscle'=>0.35,
],[
    'position'=>[0,1.72,0],
    'rotation'=>[0,0.15,0],
    'scale'=>[1,1,1],
])->withTexture($skin);

$neck = AnatomyPlugin::part('neck','pipe',[
    'length'=>0.34,'radius'=>0.11,'pumpedness'=>0.25,
],[
    'position'=>[0,1.43,0],
]);

$model = AnatomyPlugin::model('figure','human')->add($neck)->add($head);
$data = $model->jsonSerialize();
assert($data['model']==='anatomy');
assert(count($data['parts'])===2);
$h = array_values(array_filter($data['parts'],fn($p)=>$p['id']==='head'))[0];
assert($h['transform']['position'][1]===1.72);
assert($h['textures'][0]['with']['transform']['scale'][0]===1.12);
assert(count($h['textures'][0]['with']['pins'])===2);
assert(in_array('anatomy.texture.align',(new \jx\plugins\AnatomyPlugin())->capabilities(),true));
echo "PASS anatomy texture alignment\n";
