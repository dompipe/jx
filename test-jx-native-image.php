<?php declare(strict_types=1);

require_once __DIR__ . '/jx-native-image.php';

use jx\JxNativeImage;

$code="\x48\x89\xC0\xC3";

$exe=JxNativeImage::executable($code,0)->section('DATA',"abc")->encode();
$decoded=JxNativeImage::decode($exe);
if($decoded['entrypoint']!==0)throw new RuntimeException('JXL entrypoint did not round-trip');
if(($decoded['flags']&JxNativeImage::FLAG_EXECUTABLE)===0)throw new RuntimeException('JXL executable flag missing');
if(($decoded['flags']&JxNativeImage::FLAG_LIBRARY)!==0)throw new RuntimeException('JXL incorrectly marked library');
if(($decoded['sections']['CODE']??null)!==$code)throw new RuntimeException('JXL CODE did not round-trip');
if(($decoded['sections']['DATA']??null)!=="abc")throw new RuntimeException('JXL DATA did not round-trip');

$lib=JxNativeImage::library($code)
    ->export('sum',0,['i64','i64'],'i64')
    ->export('identity',1,['i64'],'i64')
    ->encode();
$decoded=JxNativeImage::decode($lib);
if($decoded['entrypoint']!==null)throw new RuntimeException('JLL unexpectedly has entrypoint');
if(($decoded['flags']&JxNativeImage::FLAG_LIBRARY)===0)throw new RuntimeException('JLL library flag missing');
if(count($decoded['exports'])!==2)throw new RuntimeException('JLL export count mismatch');
if(($decoded['exports'][0]['name']??null)!=='sum')throw new RuntimeException('JLL first export name mismatch');
$sig=$decoded['signatures'][$decoded['exports'][0]['signature']]??null;
if(!is_array($sig)||$sig['params']!==['i64','i64']||$sig['return']!=='i64')throw new RuntimeException('JLL signature mismatch');
foreach(['STRINGS','SIGNATURES','EXPORTS'] as $section)if(!isset($decoded['sections'][$section]))throw new RuntimeException("Missing {$section} section");

echo "ok\n";
