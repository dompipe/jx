<?php
$long = '$s=0;';
for ($i = 0; $i < 40; $i++) {
    $long .= '$s=$s+1;';
}
return [
  'tiny' => '$x=1; $x++;',
  'arith' => '$a=1; $b=2; $c=$a+$b*$a-$b; $c+=3; $c*=2;',
  'loop' => '$sum=0; $i=100; while($i){ $sum=$sum+$i; $i--; }',
  'nested' => '$s=0; $i=20; while($i){ $j=10; while($j){ $s=$s+1; $j--; } $i--; }',
  'ifelse' => '$x=0; if($x==0){ $x=1; } else { $x=2; } $y=$x+$x;',
  'mixed' => '$sum=0; $i=50; while($i){ $sum=$sum+$i; $i--; } $a=$sum+$sum; $a++;',
  'long40' => $long,
];
