<?php declare(strict_types=1);

require_once __DIR__.'/jx/bootstrap.php';

use jx\JxException;
use jx\Plugins;
use jx\plugins\AudioSignalsPlugin;
use jx\plugins\MediaPlugin;
use jx\plugins\VideoAnalysisPlugin;

function signalAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException('jx-media-signals: '.$message);}

signalAssert(Plugins::has('audio-signals'),'audio-signals plugin registered');
signalAssert(Plugins::has('video-analysis'),'video-analysis plugin registered');

$flac=MediaPlugin::audio('song','/assets/song.flac','audio/flac')->jsonSerialize();
signalAssert($flac['mime']==='audio/flac','FLAC media contract');
$mic=MediaPlugin::microphone('mic')->jsonSerialize();
signalAssert(($mic['source']['device']??null)==='microphone','microphone device source');
$camera=MediaPlugin::camera('cam')->jsonSerialize();
signalAssert(($camera['source']['device']??null)==='camera','camera device source');

foreach(['waveform','level','pitch','beat','tempo','channels'] as $mode){
    $binding=AudioSignalsPlugin::$mode('song','audio',$mode)->jsonSerialize();
    signalAssert(($binding['binding']??null)==='media.audio.'.$mode,"{$mode} binding name");
    signalAssert(($binding['target']['bag']??null)==='audio',"{$mode} target Bag");
    signalAssert(($binding['reactive']??false)===true,"{$mode} reactive");
}

$wave=AudioSignalsPlugin::waveform('song','audio','wave',['history'=>4096,'samples'=>2048,'every_ms'=>25])->jsonSerialize();
signalAssert(($wave['with']['history']??0)===4096,'waveform bounded history');
signalAssert(($wave['with']['samples']??0)===2048,'waveform sample window');

$frames=VideoAnalysisPlugin::frames('cam','vision','frames',50.0,['brightness','motion','rgb'],['history'=>64])->jsonSerialize();
signalAssert(($frames['sampling']['every_ms']??null)===50.0,'video sampling interval');
signalAssert(in_array('motion',$frames['measures']??[],true),'video motion measure');

$failed=false;
try{AudioSignalsPlugin::waveform('song','audio','bad',['history'=>0]);}catch(JxException){$failed=true;}
signalAssert($failed,'invalid history rejected');

$failed=false;
try{AudioSignalsPlugin::level('song','audio','bad',['every_ms'=>1]);}catch(JxException){$failed=true;}
signalAssert($failed,'over-fast canonical publish interval rejected');

echo "jx-media-signals: ok\n";
