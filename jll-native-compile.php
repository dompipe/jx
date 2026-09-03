<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-expr.php';
require_once __DIR__ . '/pasm-native-jxl.php';
require_once __DIR__ . '/jx-native-image.php';

use jx\JxNativeImage;
use pasm\PASMExprCompiler;
use pasm\PASMNativeJxlEncoder;

$args=$_SERVER['argv']??[];array_shift($args);
if($args===[]||in_array($args[0]??'',['-h','--help'],true)){
    fwrite(STDERR,"Usage: php jll-native-compile.php input.jx [output.jll] [exports.json]\n");
    fwrite(STDERR,"exports.json: [{\"name\":\"fn\",\"offset\":0,\"params\":[\"i64\"],\"return\":\"i64\"}]\n");
    exit($args===[]?2:0);
}
$input=$args[0];$output=$args[1]??preg_replace('/\.[^.]+$/','',$input).'.jll';$exportsPath=$args[2]??null;
$source=file_get_contents($input);if($source===false)throw new RuntimeException("Cannot read {$input}");
$compiler=new PASMExprCompiler();$pasm=$compiler->compile($source);$code=(new PASMNativeJxlEncoder())->compile($pasm);
$image=JxNativeImage::library($code,JxNativeImage::ARCH_X86_64_SYSV);
if($exportsPath!==null){
    $raw=file_get_contents($exportsPath);if($raw===false)throw new RuntimeException("Cannot read {$exportsPath}");
    $exports=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($exports))throw new RuntimeException('exports.json must be an array');
    foreach($exports as $e){
        if(!is_array($e)||!isset($e['name'],$e['offset']))throw new RuntimeException('Each export needs name and offset');
        $image->export((string)$e['name'],(int)$e['offset'],array_values($e['params']??[]),(string)($e['return']??'void'),(int)($e['flags']??0));
    }
}
$bytes=$image->encode();if(file_put_contents($output,$bytes)!==strlen($bytes))throw new RuntimeException("Cannot write {$output}");
fwrite(STDOUT,$output."\n");
