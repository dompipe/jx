<?php declare(strict_types=1);

/** Tiny end-to-end contract check for benchmark-container-suite.php. */

$root=__DIR__;
$resultFile=$root.'/benchmark-container-suite-results.json';
@unlink($resultFile);

$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/benchmark-container-suite.php').' 1000 1 0';
exec($cmd.' 2>&1',$lines,$status);
if($status!==0){
    throw new RuntimeException("Master container benchmark failed:\n".implode("\n",$lines));
}
if(!is_file($resultFile))throw new RuntimeException('Master container benchmark did not write result JSON');

$report=json_decode((string)file_get_contents($resultFile),true,512,JSON_THROW_ON_ERROR);
@unlink($resultFile);

if(($report['suite']??null)!=='jx-container-master/1')throw new RuntimeException('Wrong master container suite ABI');
if(($report['sizes']??null)!==[1000])throw new RuntimeException('Master container suite did not preserve requested size');
if(($report['reps']??null)!==1||($report['warmups']??null)!==0)throw new RuntimeException('Master container suite repetition contract mismatch');

$rows=$report['results']['1000']['rows']??null;
if(!is_array($rows))throw new RuntimeException('Missing master container rows');
$disciplines=['record','vector','stack','queue','deque','map','set'];
$columns=['legacy_pasm_php','canonical_pasm_php','bag_php','php_array','php_spl','jxl_vm','jxl_native'];
foreach($disciplines as $discipline){
    if(!isset($rows[$discipline])||!is_array($rows[$discipline]))throw new RuntimeException("Missing {$discipline} row");
    foreach($columns as $column){
        if(!array_key_exists($column,$rows[$discipline]))throw new RuntimeException("Missing {$discipline}/{$column} cell");
    }
    if($rows[$discipline]['bag_php']===null)throw new RuntimeException("Bag/PHP missing for {$discipline}");
    if($rows[$discipline]['php_array']===null)throw new RuntimeException("PHP array baseline missing for {$discipline}");
}

// Historical PASM OOP has no Record class. The null is intentional and guards
// against silently inventing a comparison just to fill the table.
if($rows['record']['legacy_pasm_php']!==null||$rows['record']['canonical_pasm_php']!==null){
    throw new RuntimeException('Historical PASM Record cells must remain not-applicable');
}

foreach(['vector','stack','queue','deque','map','set'] as $discipline){
    if($rows[$discipline]['legacy_pasm_php']===null||$rows[$discipline]['canonical_pasm_php']===null){
        throw new RuntimeException("Historical/canonical PASM row missing for {$discipline}");
    }
}

if($rows['record']['php_spl']===null||$rows['vector']['php_spl']===null||$rows['stack']['php_spl']===null||$rows['queue']['php_spl']===null||$rows['deque']['php_spl']===null){
    throw new RuntimeException('Expected SPL structural baselines are incomplete');
}
if($rows['map']['php_spl']!==null||$rows['set']['php_spl']!==null){
    throw new RuntimeException('Map/Set SPL cells should remain not-applicable until a justified baseline exists');
}

echo "PASS container benchmark contract\n";
