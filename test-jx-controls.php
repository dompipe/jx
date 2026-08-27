<?php declare(strict_types=1);

require_once __DIR__ . '/jx-controls.php';

use jx\ControlGroup;
use jx\EnvironmentProfile;
use jx\EnvironmentViolation;
use jx\JxControl;
use jx\JxControlRenderer;

$eq=static function(mixed $a,mixed $b,string $label):void{if($a!==$b){fwrite(STDERR,"FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");exit(1);}};

$panel=new JxControl('market.panel','panel','Market');
$panel->style()
    ->gap(12)
    ->color('#fff')
    ->backgroundColor('#101820')
    ->backgroundImage('/assets/market.png')
    ->backgroundOpacity(0.75)
    ->opacity(0.95);
$panel->tooltip('Open the trading floor','help');

$group=(new ControlGroup('market'))->add($panel);
$eq($group->controls()[0],$panel,'group collector');
$eq($panel->groups(),['market'],'control records group');
$eq($panel->style()->get('gap'),12,'gap');
$eq($panel->style()->get('color'),'#fff','hex color');
$eq($panel->style()->get('background-opacity'),0.75,'background image transparency');

$eventValue=0;
$panel->on('click',function(int $v)use(&$eventValue):int{$eventValue=$v;return $v+1;});
$eq($panel->emit('click',7),[8],'event result');
$eq($eventValue,7,'event payload');

$html=JxControlRenderer::browser($panel,EnvironmentProfile::browser());
if(!str_contains($html,'gap:12px')||!str_contains($html,'#fff')||!str_contains($html,'market.png')||!str_contains($html,'--jx-background-opacity:0.75')||!str_contains($html,'title=&quot;Open the trading floor&quot;')){
    fwrite(STDERR,"FAIL browser control shadow: {$html}\n");exit(1);
}

$native=JxControlRenderer::native($panel,EnvironmentProfile::native());
$eq($native['host'],'native','native host realization');
$eq($native['control']['style']['gap'],12,'native keeps canonical style');

$blocked=false;
try{JxControlRenderer::browser($panel,EnvironmentProfile::server());}catch(EnvironmentViolation){$blocked=true;}
$eq($blocked,true,'server cannot render DOM directly');

$bad=false;
try{$panel->style()->color('red');}catch(InvalidArgumentException){$bad=true;}
$eq($bad,true,'non-hex color rejected');

fwrite(STDOUT,"PASS JX controls styles gap hex background-opacity tooltip groups events hosts\n");
