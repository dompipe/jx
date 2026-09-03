<?php declare(strict_types=1);

require_once __DIR__ . '/jx-native-image.php';

use jx\JxNativeImage;

$args=$_SERVER['argv']??[];array_shift($args);
if(count($args)!==1||in_array($args[0]??'',['-h','--help'],true)){
    fwrite(STDERR,"Usage: php jll-inspect.php library.jll\n");
    exit(count($args)!==1?2:0);
}
$bytes=file_get_contents($args[0]);if($bytes===false)throw new RuntimeException("Cannot read {$args[0]}");
$image=JxNativeImage::decode($bytes);
printf("architecture: %d\n",$image['architecture']);
printf("entrypoint: %s\n",$image['entrypoint']===null?'none':(string)$image['entrypoint']);
foreach($image['exports'] as $export){
    $sig=$image['signatures'][$export['signature']]??['params'=>[],'return'=>'void'];
    printf("%s(%s) -> %s @ +0x%x\n",$export['name'],implode(', ',$sig['params']),$sig['return'],$export['offset']);
}
