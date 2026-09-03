<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxb-archive.php';

use jx\JxbArchive;

$args=$_SERVER['argv']??[];array_shift($args);
if(count($args)<2||in_array($args[0]??'',['-h','--help'],true)){
    fwrite(STDERR,"Usage: php jxb-resource-pack.php output.jxb member=source [member=source ...]\n");
    fwrite(STDERR,"Example: php jxb-resource-pack.php assets.jxb images/logo.png=assets/logo.png tables/db.bin=build/db.bin\n");
    exit(count($args)<2?2:0);
}
$output=array_shift($args);
if(strtolower(pathinfo($output,PATHINFO_EXTENSION))!=='jxb')throw new RuntimeException('Canonical resource packages use .jxb');
$members=[];
foreach($args as $pair){
    $at=strpos($pair,'=');
    if($at===false||$at===0||$at===strlen($pair)-1)throw new RuntimeException("Expected member=source, got {$pair}");
    $name=substr($pair,0,$at);$source=substr($pair,$at+1);
    if(isset($members[$name]))throw new RuntimeException("Duplicate JXB member {$name}");
    $members[$name]=$source;
}
JxbArchive::create($output,$members);
fwrite(STDOUT,$output."\n");
