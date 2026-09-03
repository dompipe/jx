#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxb-archive.php';

use jx\JxbArchive;

/**
 * Canonical JXB compiler/packer.
 *
 * New .jxb files are indexed compressed resource archives. The former compiled
 * Book writer remains available through explicit legacy/64B tooling and must
 * not emit new files that masquerade as canonical JXB resources.
 */
$args=$_SERVER['argv']??[];array_shift($args);
if(count($args)<2||in_array($args[0]??'',['-h','--help'],true)){
    fwrite(STDERR,"Usage: php jxb-compile.php output.jxb member=source [member=source ...]\n");
    fwrite(STDERR,"Example: php jxb-compile.php assets.jxb images/logo.png=assets/logo.png tables/db.bin=build/db.bin\n");
    fwrite(STDERR,"Historical compiled Books: use the explicit legacy/64B compatibility tooling.\n");
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
fwrite(STDOUT,json_encode([
    'output'=>$output,
    'format'=>'jx.jxb/1',
    'kind'=>'resource-archive',
    'members'=>array_keys($members),
],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
