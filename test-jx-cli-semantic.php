<?php declare(strict_types=1);

$root = __DIR__;
$php = escapeshellarg(PHP_BINARY);
$runner = escapeshellarg($root . '/jx-run.php');
$src = 'function add(int $a,int $b): int { return $a+$b; } int $x=add(20,22); $x;';

$run = static function(string $cmd): array {
    $out=[]; $code=0; exec($cmd . ' 2>&1', $out, $code); return [$code, implode("\n", $out)];
};

[$code,$out] = $run("{$php} {$runner} --semantic --print -c " . escapeshellarg($src));
assert($code === 0, $out);
assert(trim($out) === '42', $out);

$tmp = sys_get_temp_dir() . '/jx-cli-' . bin2hex(random_bytes(4));
$jxl = $tmp . '.jxl';
$book = $tmp . '.64B';
$source = $tmp . '.jx';
file_put_contents($source, $src);

[$code,$out] = $run("{$php} {$runner} --jxl -o " . escapeshellarg($jxl) . ' ' . escapeshellarg($source));
assert($code === 0, $out);
assert(is_file($jxl) && filesize($jxl) > 0);
[$code,$out] = $run("{$php} {$runner} --print " . escapeshellarg($jxl));
assert($code === 0, $out);
assert(trim($out) === '42', $out);

[$code,$out] = $run("{$php} {$runner} --64b -o " . escapeshellarg($book) . ' ' . escapeshellarg($source));
assert($code === 0, $out);
assert(is_file($book) && filesize($book) > 48);
[$code,$out] = $run("{$php} {$runner} --print " . escapeshellarg($book));
assert($code === 0, $out);
assert(trim($out) === '42', $out);

@unlink($jxl); @unlink($book); @unlink($source);
echo "jx.exe semantic/JXL/64B CLI: ok\n";
