<?php declare(strict_types=1);
require_once __DIR__.'/jx/plugins/AnatomyIK.php';

use jx\plugins\AnatomyIKPlugin;

$chain=AnatomyIKPlugin::chain(
    'left-arm',
    ['left-shoulder','left-elbow','left-hand'],
    ['left-upper-arm','left-forearm'],
    [0.0,1.0,1.0],
    18,
    .0004
);
$d=$chain->jsonSerialize();
assert($d['solver']==='fabrik');
assert(count($d['joints'])===3);
assert(count($d['bones'])===2);
assert($d['rootLocked']===true);

$frames=[
    ['time'=>0.0,'position'=>[0.0,0.0,0.0]],
    ['time'=>0.1,'position'=>[0.11,0.05,0.0]],
    ['time'=>0.2,'position'=>[0.18,-0.04,0.0]],
    ['time'=>0.3,'position'=>[0.30,0.0,0.0]],
];
$motion=AnatomyIKPlugin::motion('reach','left-arm',$frames,.8,.35,4,true)->jsonSerialize();
assert($motion['kind']==='ik-motion');
assert($motion['loop']===true);
assert(count($motion['rawKeyframes'])===4);
assert(count($motion['keyframes'])===4);
assert($motion['keyframes'][0]['position']===$motion['rawKeyframes'][0]['position']);
assert($motion['keyframes'][3]['position']===$motion['rawKeyframes'][3]['position']);
assert($motion['settings']['smooth']===.8);
assert($motion['settings']['linearize']===.35);
assert(in_array('anatomy.ik.fabrik',(new \jx\plugins\AnatomyIKPlugin())->capabilities(),true));

echo "PASS anatomy IK descriptors and path cleanup\n";
