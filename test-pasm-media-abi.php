<?php declare(strict_types=1);

require_once __DIR__.'/jx/bootstrap.php';
require_once __DIR__.'/pasm-media-abi.php';

use jx\plugins\MediaPlugin;
use jx\plugins\AudioSignalsPlugin;
use pasm\PASMMediaGraphCompiler;
use pasm\PASMMediaGraphExecutor;
use pasm\PASMMediaHost;

function mediaAbiAssert(bool $ok,string $message):void{if(!$ok)throw new RuntimeException('pasm-media-abi: '.$message);}

$media=MediaPlugin::audio('song','/assets/song.flac','audio/flac')->jsonSerialize();
$wave=AudioSignalsPlugin::waveform('song','audio','wave',['history'=>512,'samples'=>256])->jsonSerialize();
$chart=[
    'kind'=>'control','control'=>'chart','plugin'=>'charts','version'=>'jx.charts/2','id'=>'wave-chart','type'=>'waveform',
    'source'=>['bag'=>'audio','at'=>'wave','reactive'=>true],
    'fields'=>['x'=>'time','series'=>[['field'=>'value','label'=>'value']]],'with'=>[],
];
$graph=(new PASMMediaGraphCompiler())->compile([$media],[$wave],[$chart]);
mediaAbiAssert(count($graph->slots->all())===4,'expected media, analysis, Bag, chart slots');
mediaAbiAssert(str_contains($graph->listing(),'MOPEN'),'MOPEN emitted');
mediaAbiAssert(str_contains($graph->listing(),'MANALYZE'),'MANALYZE emitted');
mediaAbiAssert(str_contains($graph->listing(),'MPUBLISH'),'MPUBLISH emitted');
mediaAbiAssert(str_contains($graph->listing(),'MCHART'),'MCHART emitted');
mediaAbiAssert(str_contains($graph->listing(),'MSYNC'),'MSYNC emitted');
mediaAbiAssert(strlen($graph->bytecode())===12,'compact graph byte count');

final class RecordingMediaHost implements PASMMediaHost{
    public array $events=[];
    public function open(array $slot):void{$this->events[]=['open',$slot['kind'],$slot['name']];}
    public function analyze(array $media,array $analysis):void{$this->events[]=['analyze',$media['name'],$analysis['name']];}
    public function publish(array $analysis,array $bag):void{$this->events[]=['publish',$analysis['name'],$bag['name']];}
    public function chart(array $bag,array $chart):void{$this->events[]=['chart',$bag['name'],$chart['name']];}
    public function sync(array $slot):void{$this->events[]=['sync',$slot['name']];}
    public function close(array $media):void{$this->events[]=['close',$media['name']];}
}
$host=new RecordingMediaHost();
(new PASMMediaGraphExecutor($host))->run($graph);
mediaAbiAssert(count($host->events)===5,'executor ran five graph operations');
mediaAbiAssert($host->events[1][0]==='analyze','analysis follows media open');
mediaAbiAssert($host->events[4][0]==='sync','Bag checkpoint is final boundary');

echo "pasm-media-abi: ok\n";
