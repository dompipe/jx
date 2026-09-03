<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-expr.php';
require_once __DIR__ . '/pasm-native-jxl.php';
require_once __DIR__ . '/jx-native-image.php';

use jx\JxNativeImage;
use pasm\PASMExprCompiler;
use pasm\PASMNativeJxlEncoder;

$args=$_SERVER['argv']??[];array_shift($args);
if($args===[]||in_array($args[0]??'',['-h','--help'],true)){
    fwrite(STDERR,"Usage: php jxl-native-compile.php input.jx [output.jxl]\n");
    exit($args===[]?2:0);
}
$input=$args[0];$output=$args[1]??preg_replace('/\.[^.]+$/','',$input).'.jxl';
$source=file_get_contents($input);if($source===false)throw new RuntimeException("Cannot read {$input}");
$compiler=new PASMExprCompiler();
$pasm=$compiler->compile($source);
$code=(new PASMNativeJxlEncoder())->compile($pasm);
$image=JxNativeImage::executable($code,0,JxNativeImage::ARCH_X86_64_SYSV)->encode();
if(file_put_contents($output,$image)!==strlen($image))throw new RuntimeException("Cannot write {$output}");
fwrite(STDOUT,$output."\n");
