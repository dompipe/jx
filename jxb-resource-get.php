<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxb-archive.php';

use jx\JxbArchive;

$args=$_SERVER['argv']??[];array_shift($args);
if($args===[]||in_array($args[0]??'',['-h','--help'],true)){
    fwrite(STDERR,"Usage:\n");
    fwrite(STDERR,"  php jxb-resource-get.php package.jxb --list\n");
    fwrite(STDERR,"  php jxb-resource-get.php package.jxb member [output]\n");
    exit($args===[]?2:0);
}
$path=array_shift($args);$archive=JxbArchive::open($path);
try{
    if(($args[0]??null)==='--list'){
        foreach($archive->names() as $name)fwrite(STDOUT,$name."\n");
        exit(0);
    }
    if($args===[])throw new RuntimeException('Missing JXB member name');
    $name=array_shift($args);$output=$args[0]??null;
    if($output===null){
        $stream=$archive->stream($name);
        try{stream_copy_to_stream($stream,STDOUT);}finally{fclose($stream);}
        exit(0);
    }
    $stream=$archive->stream($name);$out=fopen($output,'wb');if($out===false){fclose($stream);throw new RuntimeException("Cannot open {$output}");}
    try{stream_copy_to_stream($stream,$out);}finally{fclose($stream);fclose($out);}
}finally{$archive->close();}
