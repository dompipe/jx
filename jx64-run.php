#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxb.php';

use jx\semantic\JxbBook;
use jx\semantic\SemanticException;

$args=$_SERVER['argv']??[];array_shift($args);
if(count($args)!==1||in_array($args[0]??'',['-h','--help'],true)){
    fwrite(STDERR,"Usage: php jx64-run.php legacy.64B\n");
    fwrite(STDERR,"Runs the historical JX64B001 compiled-Book format only.\n");
    exit(count($args)!==1?2:0);
}
$path=$args[0];
if(strtolower(pathinfo($path,PATHINFO_EXTENSION))!=='64b'){
    fwrite(STDERR,"jx64-run: expected an explicit legacy .64B artifact\n");
    exit(2);
}
try{
    $result=JxbBook::runFile($path);
    fwrite(STDOUT,(string)$result."\n");
}catch(SemanticException $e){
    fwrite(STDERR,"jx64-run: {$e->getMessage()}\n");
    exit(1);
}
