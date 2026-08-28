<?php declare(strict_types=1);

require_once __DIR__ . '/jx-controls.php';

use jx\ControlGroup;
use jx\EnvironmentProfile;
use jx\EnvironmentViolation;
use jx\JxControl;
use jx\JxControlRenderer;

$eq=static function(mixed $a,mixed $b,string $label):void{if($a!==$b){fwrite(STDERR,"FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");exit(1);}};

$panel=new JxControl('market.panel','panel','Market');
$bagId=$panel->bagId();
$panel->style()
    ->gap(12)
    ->color('#fff')
    ->backgroundColor('#101820')
    ->backgroundImage('/assets/market.png')
    ->backgroundOpacity(0.75)
    ->opacity(0.95);
$panel->tooltip('Open the trading floor','help');
$panel->setState('selected',true);

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

// A control is its Bag. Placement must not become identity.
$panel->bindSource('market.feed.a','reactive','notify',['as'=>'string']);
$eq($panel->publishSourceValue('market.feed.a',1,'A:125'),true,'source A publishes');
$eq($panel->value(),'A:125','source A value');
$bindingA=$panel->sourceBinding();

$panel->moveTo(480,160,'trading.right',9)->resize(640,360);
$eq($panel->bagId(),$bagId,'move keeps Bag identity');
$eq($panel->value(),'A:125','move keeps canonical value');
$eq($panel->style()->get('gap'),12,'move keeps style');
$eq($panel->groups(),['market'],'move keeps groups');
$eq($panel->state()['selected'],true,'move keeps state');
$eq($panel->layout()['container'],'trading.right','move changes only placement container');
$eq($panel->layout()['x'],480,'move changes x');
$eq($panel->layout()['y'],160,'move changes y');
$eq($panel->layout()['z'],9,'move changes z');

// Rebinding changes the dependency, not the control. Last value remains until B publishes.
$panel->bindSource('market.feed.b','reactive','notify',['as'=>'string']);
$bindingB=$panel->sourceBinding();
$eq($panel->bagId(),$bagId,'source change keeps Bag identity');
$eq($panel->value(),'A:125','source change keeps last canonical value');
$eq($bindingB['previous_source_id'],'market.feed.a','source provenance retained');
$eq($bindingB['binding_revision'],$bindingA['binding_revision']+1,'binding revision advances');
$eq($panel->publishSourceValue('market.feed.a',2,'STALE'),false,'old source rejected after rebind');
$eq($panel->value(),'A:125','stale old-source value cannot overwrite control');
$eq($panel->publishSourceValue('market.feed.b',1,'B:250'),true,'new source publishes');
$eq($panel->value(),'B:250','new source updates same Bag');
$eq($panel->publishSourceValue('market.feed.b',1,'B:OLD'),false,'same source stale revision rejected');
$eq($panel->value(),'B:250','stale revision cannot regress value');
$eq($panel->emit('click',11),[12],'listener survives move and rebind');
$eq($eventValue,11,'listener still live');

$bag=$panel->bag()->all();
$eq($bag['control.identity']['id'],'market.panel','Bag owns control identity');
$eq($bag['control.value'],'B:250','Bag owns current value');
$eq($bag['control.layout']['x'],480,'Bag owns placement');
$eq($bag['control.source']['source_id'],'market.feed.b','Bag owns source descriptor');
$eq($bag['control.style']['background-opacity'],0.75,'Bag owns style');
$eq($bag['control.tooltip']['text'],'Open the trading floor','Bag owns tooltip');
$eq($bag['control.events']['CLICK']['listeners'],1,'Bag records event identity/count');
if(count($panel->history())<5){fwrite(STDERR,"FAIL control history did not retain transitions\n");exit(1);}

$html=JxControlRenderer::browser($panel,EnvironmentProfile::browser());
if(!str_contains($html,'gap:12px')||!str_contains($html,'#fff')||!str_contains($html,'market.png')||!str_contains($html,'--jx-background-opacity:0.75')||!str_contains($html,'title="Open the trading floor"')||!str_contains($html,'data-jx-bag="'.$bagId.'"')){
    fwrite(STDERR,"FAIL browser control shadow: {$html}\n");exit(1);
}

$native=JxControlRenderer::native($panel,EnvironmentProfile::native());
$eq($native['host'],'native','native host realization');
$eq($native['control']['abi'],'jx.control/2','Bag-backed control ABI');
$eq($native['control']['bag'],$bagId,'native receives stable Bag id');
$eq($native['control']['style']['gap'],12,'native keeps canonical style');
$eq($native['control']['layout']['x'],480,'native sees moved placement');
$eq($native['control']['source']['source_id'],'market.feed.b','native sees current source without losing state');

$blocked=false;
try{JxControlRenderer::browser($panel,EnvironmentProfile::server());}catch(EnvironmentViolation){$blocked=true;}
$eq($blocked,true,'server cannot render DOM directly');

$bad=false;
try{$panel->style()->color('red');}catch(InvalidArgumentException){$bad=true;}
$eq($bad,true,'non-hex color rejected');

fwrite(STDOUT,"PASS JX Bag-backed controls preserve state across move and source rebind\n");
