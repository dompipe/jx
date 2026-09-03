<?php declare(strict_types=1);

require_once __DIR__ . '/jx-jxb-archive.php';

use jx\JxbArchive;

if(!class_exists(ZipArchive::class)){
    fwrite(STDOUT,"skip: ZipArchive unavailable\n");
    exit(0);
}
$dir=sys_get_temp_dir().'/jx-jxb-test-'.bin2hex(random_bytes(4));
if(!mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('Cannot create temp dir');
try{
    file_put_contents($dir.'/a.txt','alpha');
    file_put_contents($dir.'/b.bin',"\x00\x01\x02beta");
    $jxb=$dir.'/assets.jxb';
    JxbArchive::create($jxb,[
        'text/a.txt'=>$dir.'/a.txt',
        'data/b.bin'=>$dir.'/b.bin',
    ]);
    $archive=JxbArchive::open($jxb);
    try{
        if($archive->get('text/a.txt')!=='alpha')throw new RuntimeException('JXB text member mismatch');
        if($archive->get('data/b.bin')!=="\x00\x01\x02beta")throw new RuntimeException('JXB binary member mismatch');
        $names=$archive->names();
        foreach(['text/a.txt','data/b.bin','jx-manifest.json'] as $name)if(!in_array($name,$names,true))throw new RuntimeException("Missing JXB member {$name}");
        $stream=$archive->stream('text/a.txt');
        try{$streamed=stream_get_contents($stream);}finally{fclose($stream);}
        if($streamed!=='alpha')throw new RuntimeException('JXB stream mismatch');
    }finally{$archive->close();}
}finally{
    foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))@unlink($file);
    @rmdir($dir);
}
echo "ok\n";
