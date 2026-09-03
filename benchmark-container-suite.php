<?php declare(strict_types=1);

/**
 * Master JX container benchmark matrix.
 *
 * Stable layers:
 *   legacy PASM/PHP
 *   canonical PASM/PHP
 *   JX Bag/PHP
 *   idiomatic PHP
 *   SPL structural baselines
 *   JXL VM (when implemented)
 *   JXL native prepared execution
 *
 * Missing measurements are never estimated. Historical PASM Record and SPL
 * Map/Set are N/A; unimplemented execution paths remain TBD.
 */

$arg1=$argv[1]??'';
if($arg1==='--stress')$sizes=[1000,10000,100000,1000000,10000000];
elseif($arg1!=='' && !str_starts_with($arg1,'--'))$sizes=array_values(array_filter(array_map('intval',explode(',',$arg1)),static fn(int $x):bool=>$x>=2));
else $sizes=[1000,10000,100000,1000000];
$reps=max(1,(int)($argv[2]??9));
$warmups=max(0,(int)($argv[3]??2));

$disciplines=['record','vector','stack','queue','deque','map','set'];
$nameMap=[
    'Record put/get'=>'record','Vector add/get'=>'vector','Stack push/pop'=>'stack',
    'Queue enq/deq'=>'queue','Deque back/front'=>'deque','Map put/get'=>'map','Set add/has'=>'set',
    'record put/get'=>'record','vector append/get'=>'vector','stack push/pop'=>'stack',
    'queue enqueue/dequeue'=>'queue','deque back/front'=>'deque','map put/get'=>'map','set add/contains'=>'set',
];
$notApplicable=[
    'record'=>['legacy_pasm_php'=>true,'canonical_pasm_php'=>true],
    'map'=>['php_spl'=>true],
    'set'=>['php_spl'=>true],
];

function suite_json_command(string $cmd): array
{
    $raw=shell_exec($cmd);
    if(!$raw)throw new RuntimeException("Benchmark command failed: {$cmd}");
    try{return json_decode(trim($raw),true,512,JSON_THROW_ON_ERROR);}
    catch(JsonException $e){throw new RuntimeException("Benchmark did not return JSON: {$cmd}\n{$raw}",0,$e);}
}

function suite_metric(?array $metric): ?array
{
    if($metric===null)return null;
    return [
        'median_ms'=>$metric['median_ms']??null,'min_ms'=>$metric['min_ms']??null,
        'p95_ms'=>$metric['p95_ms']??null,'mops_s'=>$metric['mops_s']??null,
        'ns_op'=>$metric['ns_op']??null,'checksum'=>$metric['checksum']??null,
    ];
}

$results=[];$jxlStatuses=[];
$jxlProvider=__DIR__.'/benchmark-jxl-containers.php';
$jxlProviderPresent=is_file($jxlProvider);

foreach($sizes as $ops){
    $low=[];
    foreach(['old','new','native','spl'] as $mode){
        $cmd=escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=1 '.escapeshellarg(__DIR__.'/benchmark-pasm-oop-fast.php')
            .' --child '.escapeshellarg($mode).' '.escapeshellarg((string)$ops)
            .' '.escapeshellarg((string)$reps).' '.escapeshellarg((string)$warmups);
        $low[$mode]=suite_json_command($cmd);
    }

    $bag=suite_json_command(
        escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=1 '.escapeshellarg(__DIR__.'/benchmark-jx-bag-containers.php')
        .' '.escapeshellarg((string)$ops).' '.escapeshellarg((string)$reps).' '.escapeshellarg((string)$warmups).' --json'
    );

    $jxl=null;
    if($jxlProviderPresent){
        $jxl=suite_json_command(
            escapeshellarg(PHP_BINARY).' '.escapeshellarg($jxlProvider)
            .' '.escapeshellarg((string)$ops).' '.escapeshellarg((string)$reps).' '.escapeshellarg((string)$warmups).' --json'
        );
        $jxlStatuses[(string)$ops]=['status'=>$jxl['status']??'unknown','reason'=>$jxl['reason']??null];
    }

    $rows=[];
    foreach($disciplines as $discipline){
        $rows[$discipline]=[
            'legacy_pasm_php'=>null,'canonical_pasm_php'=>null,'bag_php'=>null,
            'php_array'=>null,'php_spl'=>null,'jxl_vm'=>null,'jxl_native'=>null,
        ];
    }

    foreach(['old'=>'legacy_pasm_php','new'=>'canonical_pasm_php','native'=>'php_array','spl'=>'php_spl'] as $mode=>$column){
        foreach($low[$mode]['metrics'] as $name=>$metric){
            if(isset($nameMap[$name]))$rows[$nameMap[$name]][$column]=suite_metric($metric);
        }
    }
    foreach($bag['cases'] as $case){
        $name=$case['name']??'';
        if(isset($nameMap[$name]))$rows[$nameMap[$name]]['bag_php']=suite_metric($case);
    }
    if(is_array($jxl)){
        foreach(['vm'=>'jxl_vm','native'=>'jxl_native'] as $providerKey=>$column){
            foreach(($jxl[$providerKey]??[]) as $discipline=>$metric){
                if(isset($rows[$discipline]))$rows[$discipline][$column]=suite_metric($metric);
            }
        }
    }

    foreach($rows as $discipline=>$columns){
        $checks=[];
        foreach($columns as $column=>$metric){
            if($metric!==null && ($metric['checksum']??null)!==null)$checks[$column]=$metric['checksum'];
        }
        if(count(array_unique($checks,SORT_REGULAR))>1){
            throw new RuntimeException('Checksum mismatch for '.$discipline.' at '.$ops.' ops: '.json_encode($checks));
        }
    }

    $results[(string)$ops]=[
        'ops'=>$ops,'rows'=>$rows,'bag_checkpoint'=>$bag['checkpoint']??null,
        'process_peak_mb'=>[
            'legacy_pasm_php'=>$low['old']['peak_mb']??null,'canonical_pasm_php'=>$low['new']['peak_mb']??null,
            'php_array'=>$low['native']['peak_mb']??null,'php_spl'=>$low['spl']['peak_mb']??null,
            'bag_php'=>$bag['process_peak_mb']??null,
        ],
    ];
}

$report=[
    'suite'=>'jx-container-master/1','generated_at'=>gmdate(DATE_ATOM),'php_version'=>PHP_VERSION,
    'reps'=>$reps,'warmups'=>$warmups,'sizes'=>$sizes,
    'operation_law'=>'N writes/inserts + N reads/removals = total_ops',
    'not_applicable'=>$notApplicable,
    'jxl_provider'=>[
        'path'=>'benchmark-jxl-containers.php','present'=>$jxlProviderPresent,
        'runs'=>$jxlStatuses,'missing_measurement'=>'null/TBD; never estimated',
    ],
    'specialized_regressions'=>[
        'benchmark-pasm-oop-fast-deque.php'=>'opposite-end deque algorithm regression',
        'benchmark-pasm-oop-fast-sync.php'=>'hot work versus canonical dirty-page export',
    ],
    'results'=>$results,
];
file_put_contents(__DIR__.'/benchmark-container-suite-results.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));

echo "JX master container benchmark; reps={$reps}; warmups={$warmups}; JXL provider=".($jxlProviderPresent?'present':'absent')."\n";
foreach($results as $ops=>$result){
    echo "\nTOTAL OPERATIONS: ",number_format((int)$ops),"\n";
    printf("%-9s %11s %11s %11s %11s %11s %11s %11s\n",'container','legacy','canonical','Bag/PHP','PHP array','PHP SPL','JXL VM','JXL native');
    foreach($result['rows'] as $discipline=>$columns){
        $cell=static function(string $column,?array $m)use($discipline,$notApplicable):string{
            if($m!==null)return sprintf('%.3f',$m['median_ms']);
            return isset($notApplicable[$discipline][$column])?'N/A':'TBD';
        };
        printf(
            "%-9s %11s %11s %11s %11s %11s %11s %11s\n",ucfirst($discipline),
            $cell('legacy_pasm_php',$columns['legacy_pasm_php']),$cell('canonical_pasm_php',$columns['canonical_pasm_php']),
            $cell('bag_php',$columns['bag_php']),$cell('php_array',$columns['php_array']),$cell('php_spl',$columns['php_spl']),
            $cell('jxl_vm',$columns['jxl_vm']),$cell('jxl_native',$columns['jxl_native'])
        );
    }
}

echo "\nSpecialized regressions remain separate by design:\n";
echo "  php benchmark-pasm-oop-fast-deque.php\n";
echo "  php benchmark-pasm-oop-fast-sync.php\n";
echo "Results: benchmark-container-suite-results.json\n";
