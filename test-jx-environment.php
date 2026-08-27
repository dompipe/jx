<?php declare(strict_types=1);

require_once __DIR__ . '/jx-environment.php';

use jx\Capability;
use jx\EnvironmentProfile;
use jx\EnvironmentViolation;
use jx\JxStage;

$eq=static function(mixed $a,mixed $b,string $label):void{if($a!==$b){fwrite(STDERR,"FAIL {$label}: got ".var_export($a,true)." expected ".var_export($b,true)."\n");exit(1);}};

$stage=JxStage::standard();
$browser=EnvironmentProfile::browser();
$server=EnvironmentProfile::server();
$native=EnvironmentProfile::native();
$test=EnvironmentProfile::test();

$eq($stage->decide('HOST.DOM.RENDER',$browser)->allowed,true,'browser DOM');
$eq($stage->decide('SQL.QUERY',$server)->allowed,true,'server SQL');
$eq($stage->decide('HOST.WINDOW.SHOW',$native)->allowed,true,'native window');
$eq($stage->decide('SQL.QUERY',$browser)->allowed,false,'browser blocks SQL');
$eq($stage->decide('HOST.DOM.RENDER',$server)->allowed,false,'server blocks DOM');
$eq($stage->decide('SQL.EXECUTE',$test)->allowed,true,'test can stage all');

$threw=false;
try{$stage->assert('SQL.QUERY',$browser);}catch(EnvironmentViolation $e){$threw=true;$eq($e->missingCapability,Capability::SQL,'first missing capability');}
$eq($threw,true,'illegal stage throws before host execution');

$custom=new EnvironmentProfile('server',[Capability::NETWORK,Capability::SQL],'read-only-no-secrets');
$decision=$stage->decide('SQL.QUERY',$custom);
$eq($decision->allowed,false,'SQL needs secrets as well as driver');
$eq($decision->missing,[Capability::SECRETS],'missing secrets exact');

fwrite(STDOUT,"PASS JX environmental staging browser/server/native capability gate\n");
