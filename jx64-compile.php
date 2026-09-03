#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * Explicit legacy compiled-Book compiler.
 *
 * This preserves the JX64B001 / jx.64B/1 package generation without claiming
 * that those bytes are a canonical modern .jxb resource archive.
 */
require_once __DIR__ . '/jx-jxb.php';

use jx\semantic\JxbBook;
use jx\semantic\SemanticException;

$args=$_SERVER['argv']??[];array_shift($args);
if($args===[]||in_array($args[0]??'',['-h','--help'],true)){
    fwrite(STDERR,"Usage: php jx64-compile.php input.jx [output.64B] [book-name]\n");
    fwrite(STDERR,"Legacy compatibility compiler for JX64B001 compiled Books.\n");
    fwrite(STDERR,"Canonical .jxb output is resources; use jxb-compile.php for that.\n");
    exit($args===[]?2:0);
}

$input=$args[0];
$output=$args[1]??(dirname($input)==='.'?'':dirname($input).DIRECTORY_SEPARATOR).pathinfo($input,PATHINFO_FILENAME).'.64B';
$name=$args[2]??null;
if(strtolower(pathinfo($output,PATHINFO_EXTENSION))!=='64b'){
    throw new RuntimeException('jx64-compile.php writes explicit legacy .64B artifacts only');
}
try{
    $r=JxbBook::compileFile($input,$output,$name);
    fwrite(STDOUT,json_encode([
        'output'=>$r['path'],
        'compatibility_format'=>'64B',
        'internal_format'=>$r['manifest']['format']??null,
        'content_sha256'=>$r['content_sha256'],
        'file_sha256'=>$r['file_sha256'],
        'sections'=>$r['manifest']['sections']??[],
    ],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
}catch(SemanticException|JsonException $e){
    fwrite(STDERR,"jx64-compile: {$e->getMessage()}\n");
    exit(1);
}
