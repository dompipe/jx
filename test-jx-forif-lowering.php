<?php declare(strict_types=1);

require_once __DIR__ . '/jx-forif-lowering.php';

use jx\ForIfLowering;

$plan = ForIfLowering::parse('_, no1, no2, no3 = forif ($value in $values if no1 < _)');
$ir = $plan->toArray();

if ($ir['op'] !== 'FORIF_ROW') throw new RuntimeException('wrong op');
if ($ir['direction'] !== 'forif' || $ir['reverse'] !== false) throw new RuntimeException('wrong direction');
if ($ir['collection'] !== 'values') throw new RuntimeException('wrong collection');
if ($ir['targets'] !== ['_','no1','no2','no3']) throw new RuntimeException('wrong tuple targets');
if ($ir['positions'] !== ['_'=>0,'no1'=>1,'no2'=>2,'no3'=>3]) throw new RuntimeException('wrong tuple positions');
if ($ir['predicate'] !== 'no1 < _') throw new RuntimeException('wrong predicate');

$rev = ForIfLowering::parse('_, left, right = revif ($rows as $row if right >= _)')->toArray();
if ($rev['reverse'] !== true) throw new RuntimeException('revif must reverse traversal');
if ($rev['targets'][0] !== '_') throw new RuntimeException('_ must remain position zero');

$failed = false;
try { ForIfLowering::parse('no1, _ = forif ($x in $xs if no1 < _)'); }
catch (InvalidArgumentException) { $failed = true; }
if (!$failed) throw new RuntimeException('position zero must require _');

fwrite(STDOUT, "jx PHP forif lowering: ok\n");
