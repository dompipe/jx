<?php declare(strict_types=1);
require_once __DIR__.'/jx/plugins/AnatomySemantics.php';

use jx\plugins\AnatomySemanticsPlugin;

$arm = AnatomySemanticsPlugin::part(
    'left-arm','arm','left',
    ['left-shoulder','left-elbow','left-wrist'],
    ['left-upper-arm','left-forearm'],
    ['muscleTone'=>0.72,'pumpedness'=>0.58]
);

$data=$arm->jsonSerialize();
assert($data['type']==='arm');
assert($data['family']==='arm');
assert($data['side']==='left');
assert($data['joints']===['left-shoulder','left-elbow','left-wrist']);
assert($data['bones']===['left-upper-arm','left-forearm']);
assert($data['surface']['archetype']==='human-arm');
assert($data['controls']['muscleTone']===0.72);

$leg=AnatomySemanticsPlugin::template('leg');
assert($leg['ports']===['hip','knee','ankle','foot']);
assert($leg['segments']===['thigh','shin','foot']);

$wing=AnatomySemanticsPlugin::template('wing');
assert($wing['ports']===['wing-root','wing-elbow','wing-wrist','wing-tip']);
assert($wing['surface']['membrane']===true);

$rear=AnatomySemanticsPlugin::template('animal-rear-leg');
assert($rear['ports'][2]==='hock');
assert($rear['segments']===['femur','tibia','metatarsal']);

echo "PASS anatomy semantic body parts\n";
