<?php declare(strict_types=1);

require_once __DIR__ . '/jx-bytecode-page-report.php';

use jx\JxBytecodePageReport;
use jx\JxCompilerOutput;

$fail=static function(string $m):never{fwrite(STDERR,"FAIL {$m}\n");exit(1);};
$eq=static function(mixed $a,mixed $b,string $m)use($fail):void{if($a!==$b)$fail($m.' got='.var_export($a,true).' expected='.var_export($b,true));};

$r=new JxBytecodePageReport(
    page:3,
    bytecode:"\x03\x01\x02",
    optimized:true,
    fused:true,
    reactive:true,
    source:'orders.jx',
    shadow:'orders.total',
    dependencies:['sql:abc','bag:def'],
    registers:['orders'=>'ecx','prices'=>'ah'],
    iteratorSlots:1,
    output:'orders.pbc',
);

$eq(
    $r->compact(),
    'jx.exe PAGE 003  OK  3B  O1+FUSED+REACTIVE  deps:2  regs:2  iter:1  target:PASM',
    'compact contract'
);
if(!str_contains($r->verbose(),'shadow     : orders.total'))$fail('verbose shadow');
$j=json_decode($r->json(),true,512,JSON_THROW_ON_ERROR);
$eq($j['executable'],'jx.exe','json executable');
$eq($j['event'],'bytecode.page','json event');
$eq($j['page_id'],'003','json page id');
$eq($j['dependencies'],['sql:abc','bag:def'],'json dependencies');

// Exercise the exact frontend used by the native Windows jx.exe launcher.
$tmp=tempnam(sys_get_temp_dir(),'jx-page-');
if($tmp===false)$fail('tempnam');
@unlink($tmp);$tmp.='.pbc';
$cmd=[PHP_BINARY,__DIR__.'/jx-run.php','--report=compact','-O1','-o',$tmp,'-c','$a = 1; $result = $a * 2;'];
$spec=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
$p=proc_open($cmd,$spec,$pipes,__DIR__);
if(!is_resource($p))$fail('proc_open jx-run');
fclose($pipes[0]);
$out=stream_get_contents($pipes[1]);fclose($pipes[1]);
$err=stream_get_contents($pipes[2]);fclose($pipes[2]);
$rc=proc_close($p);
if($rc!==0)$fail('jx-run compile rc='.$rc.' stderr='.$err.' stdout='.$out);
if(!is_file($tmp) || filesize($tmp)===0)$fail('jx-run did not write pbc');
if(!preg_match('/^jx\.exe PAGE 001  OK  \d+B  O1  deps:0  regs:0  iter:0  target:PASM$/m',$err)){
    $fail('jx.exe compact compiler line missing: '.trim($err));
}
@unlink($tmp);

fwrite(STDOUT,"PASS jx.exe bytecode page output compact+verbose+json real CLI compile\n");
