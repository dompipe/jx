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

$rawTrack = AnatomyPlugin::animationTrack('head-motion','head',[
    ['time'=>0.0,'transform'=>['position'=>[0,1.72,0],'rotation'=>[0,.15,0],'scale'=>[1,1,1]]],
    ['time'=>0.25,'transform'=>['position'=>[.18,1.90,.03],'rotation'=>[0,.18,.02],'scale'=>[1,1,1]]],
    ['time'=>0.50,'transform'=>['position'=>[.39,1.75,.08],'rotation'=>[0,.21,-.01],'scale'=>[1,1,1]]],
    ['time'=>0.75,'transform'=>['position'=>[.61,2.02,.13],'rotation'=>[0,.24,.01],'scale'=>[1,1,1]]],
    ['time'=>1.00,'transform'=>['position'=>[.80,1.92,.20],'rotation'=>[0,.28,0],'scale'=>[1,1,1]]],
]);
$cleanTrack = $rawTrack->smoothed(.8,.35,3);
$clip = AnatomyPlugin::animation('head-drag',1.0,false)->add($cleanTrack);

$model = AnatomyPlugin::model('figure','human')->add($neck)->add($head)->animate($clip);
$data = $model->jsonSerialize();
assert($data['model']==='anatomy');
assert($data['version']==='jx.anatomy/2');
assert(count($data['parts'])===2);
assert(count($data['animations'])===1);
$h = array_values(array_filter($data['parts'],fn($p)=>$p['id']==='head'))[0];
assert($h['transform']['position'][1]===1.72);
assert($h['textures'][0]['with']['transform']['scale'][0]===1.12);
assert(count($h['textures'][0]['with']['pins'])===2);
$frames=$data['animations'][0]['tracks'][0]['keyframes'];
assert(count($frames)===5);
assert($frames[0]['transform']['position']===[0.0,1.72,0.0]);
assert($frames[4]['transform']['position']===[0.8,1.92,0.2]);
assert($frames[2]['transform']['position'][1] > 1.75); // jitter was pulled toward its neighbors
$caps=(new \jx\plugins\AnatomyPlugin())->capabilities();
assert(in_array('anatomy.texture.align',$caps,true));
assert(in_array('anatomy.animation.mouse-path',$caps,true));
assert(in_array('anatomy.animation.linearize',$caps,true));
echo "PASS anatomy texture alignment + animation smoothing\n";
