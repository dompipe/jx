<?php declare(strict_types=1);
function pasl_strnet_body(): string {
    $chunks = [];
    for ($i = 0; $i < 16; $i++) {
        $f = __DIR__ . "/pasl-strnet.p{$i}.b64";
        if (!is_file($f)) break;
        $chunks[] = file_get_contents($f);
    }
    $raw = base64_decode(implode('', $chunks), true);
    if ($raw === false) throw new RuntimeException('PASL strnet b64');
    $out = @zlib_decode($raw);
    if ($out === false) throw new RuntimeException('PASL strnet zlib');
    return $out;
}
