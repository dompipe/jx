<?php declare(strict_types=1);

require_once __DIR__ . '/php-jxl-driver.php';
require_once __DIR__ . '/jx-native-image.php';

use jx\JxNativeImage;
use jx\PhpJxlDriver;

$compiled=(new PhpJxlDriver())->compileDetailed('$x = 7; return $x;');
if(($compiled['code']??'')==='')throw new RuntimeException('PHP driver produced no native CODE');
if(($compiled['jxl']??'')==='')throw new RuntimeException('PHP driver produced no JXL image');
$image=JxNativeImage::decode($compiled['jxl']);
if($image['entrypoint']!==0)throw new RuntimeException('PHP JXL image entrypoint mismatch');
if(($image['sections']['CODE']??null)!==$compiled['code'])throw new RuntimeException('PHP JXL CODE section mismatch');
if(($image['flags']&JxNativeImage::FLAG_EXECUTABLE)===0)throw new RuntimeException('PHP JXL executable flag missing');
echo "ok\n";
