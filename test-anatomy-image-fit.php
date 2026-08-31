<?php declare(strict_types=1);
require_once __DIR__.'/jx/plugins/AnatomyImageFit.php';

use jx\Plugins;
use jx\plugins\AnatomyImageFitPlugin;

$plan = AnatomyImageFitPlugin::skeleton('duck-reference',960,640,'/assets/duck.png')
    ->pass(AnatomyImageFitPlugin::pass('fundamental','fundamental',true))
    ->pass(AnatomyImageFitPlugin::pass('wings','wings',false))
    ->joint(AnatomyImageFitPlugin::joint('torso-root',480,320,'torso-root',0,true))
    ->joint(AnatomyImageFitPlugin::joint('wing-root',510,285,'wing-root'))
    ->joint(AnatomyImageFitPlugin::joint('wing-tip',760,245,'wing-tip'))
    ->bone(AnatomyImageFitPlugin::bone('torso-wing','torso-root','wing-root','wing-upper','wings'))
    ->bone(AnatomyImageFitPlugin::bone('outer-wing','wing-root','wing-tip','wing-lower','wings'));

$data=$plan->jsonSerialize();
assert($data['kind']==='anatomy-image-skeleton');
assert($data['version']==='jx.anatomy-image-fit/1');
assert($data['image']['width']===960);
assert(count($data['passes'])===2);
assert(count($data['joints'])===3);
assert(count($data['bones'])===2);
assert($data['joints'][0]['port']===true);
assert($data['bones'][1]['semantic']==='wing-lower');
assert(Plugins::isExtensionOf('anatomy-image-fit','anatomy'));
$normalized=(new AnatomyImageFitPlugin())->normalizeExtensionOptions(['maxSide'=>9000,'passes'=>99,'snapStrength'=>2,'jointSnap'=>-1]);
assert($normalized['maxSide']===2048);
assert($normalized['passes']===8);
assert($normalized['snapStrength']===1.0);
assert($normalized['jointSnap']===0.0);
echo "PASS anatomy image skeleton descriptors\n";
