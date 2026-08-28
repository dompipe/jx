<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\AppliedBytecode;
use jx\AppliedBytecodeCompiler;
use jx\JxCompilerOutput;
use jx\NativeBook64;

$fail=static function(string $m):never{fwrite(STDERR,"FAIL {$m}\n");exit(1);};
$eq=static function(mixed $a,mixed $b,string $m)use($fail):void{if($a!==$b)$fail($m.' got='.var_export($a,true).' expected='.var_export($b,true));};

$eq(bin2hex(AppliedBytecode::idleTick()), '7f0001', 'idle tick exact bytes');
$eq(bin2hex(AppliedBytecode::idleCollect()), '7f0002', 'idle collect exact bytes');
$eq(AppliedBytecode::RUNTIME_TICK_OFFSET, 0, 'tick entry offset');
$eq(AppliedBytecode::RUNTIME_COLLECT_OFFSET, 3, 'collect entry offset');
$eq(strlen(AppliedBytecode::runtimeBusPage()), 6, 'runtime page bytes');

$compiler=new AppliedBytecodeCompiler();
$prepared1="\xc3";
$prepared2="\xc4\x29";
$code=$compiler->compile([
    'idle.tick',
    ['prepared'=>$prepared1],
    ['prepared'=>$prepared2],
    'idle.collect',
]);
$eq(bin2hex($code), '7f0001c3c4297f0002', 'mixed applied lowering');

$page=$compiler->page(2,[
    'idle.tick',
    ['prepared'=>$prepared1],
    'idle.collect',
],'<jx-runtime>','CODE/applied-bus.bin');
$eq($page->bytecode, "\x7f\x00\x01\xc3\x7f\x00\x02", 'jx.exe page bytes');
if(!str_contains(JxCompilerOutput::render($page), 'jx.exe PAGE 002'))$fail('jx.exe page report identity');
if(!str_contains(JxCompilerOutput::render($page), 'target:JX-APPLIED'))$fail('applied page target');

$threw=false;
try { AppliedBytecode::prepared("\x01\x02\x03"); } catch (Throwable) { $threw=true; }
if(!$threw)$fail('prepared bytecode accepted >2 bytes');

if(class_exists(ZipArchive::class)){
    $dir=sys_get_temp_dir().'/jx-applied-'.bin2hex(random_bytes(5));
    if(!mkdir($dir,0775,true)&&!is_dir($dir))$fail('temp dir');
    $path=$dir.'/runtime.64B';
    try{
        $sections=['CODE/native.bin'=>"native"]+$compiler->nativeRuntimeSection();
        NativeBook64::build($path,$sections,[
            'book'=>'jx-runtime',
            'target'=>'jx.exe',
            'compiler'=>'jx.exe',
            'applied_bytecode'=>AppliedBytecode::VERSION,
        ]);
        $loaded=NativeBook64::load($path);
        $eq($loaded['sections']['CODE/applied-bus.bin']??null,AppliedBytecode::runtimeBusPage(),'64B applied runtime section');
        $eq($loaded['manifest']['applied_bytecode']??null,AppliedBytecode::VERSION,'64B applied ABI metadata');
    }finally{
        @unlink($path);@rmdir($dir);
    }
}

fwrite(STDOUT,"PASS jx.exe applied bytecode lowering 3-byte bus + 1/2-byte prepared calls + native runtime section\n");
