#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxb-archive.php';

use jx\JxbArchive;

/**
 * Canonical JXB files are resources, not executable Books.
 * This compatibility command now offers archive inspection/extraction only.
 */
$args=$_SERVER['argv']??[];array_shift($args);
if($args===[]||in_array($args[0]??'',['-h','--help'],true)){
    fwrite(STDERR,"JXB resources are not executable.\n");
    fwrite(STDERR,"Usage:\n");
    fwrite(STDERR,"  php jxb-run.php package.jxb --list\n");
    fwrite(STDERR,"  php jxb-run.php package.jxb --get member [output]\n");
    fwrite(STDERR,"Historical compiled-Book execution belongs to explicit legacy/64B tooling.\n");
    exit($args===[]?2:0);
}
$path=array_shift($args);$mode=array_shift($args)??'--list';
$archive=JxbArchive::open($path);
try{
    if($mode==='--list'){
        foreach($archive->names() as $name)fwrite(STDOUT,$name."\n");
        exit(0);
    }
    if($mode==='--get'){
        $name=array_shift($args);if(!is_string($name)||$name==='')throw new RuntimeException('--get requires a member name');
        $output=array_shift($args);
        $stream=$archive->stream($name);
        try{
            if($output===null){stream_copy_to_stream($stream,STDOUT);}
            else{
                $out=fopen($output,'wb');if($out===false)throw new RuntimeException("Cannot open {$output}");
                try{stream_copy_to_stream($stream,$out);}finally{fclose($out);}
            }
        }finally{fclose($stream);}
        exit(0);
    }
    throw new RuntimeException('JXB is not executable; use --list or --get');
}finally{$archive->close();}
