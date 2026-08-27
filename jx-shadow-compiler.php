<?php declare(strict_types=1);

namespace jx;

require_once __DIR__ . '/jx-shadow-runtime.php';
require_once __DIR__ . '/pasm-lang.php';
require_once __DIR__ . '/pasm-bytecode-optimized.php';

use InvalidArgumentException;
use pasm\PASMAssembler;
use pasm\PASMOptimizingAssembler;
use pasm\lang\LangException;
use pasm\lang\PASMFusedCompiler;
use pasm\lang\PASMLoopSpace;

/**
 * Compile ordinary PASL scalar code into an executable reactive PASM shadow.
 *
 * Bindings are source variable => ReactiveSource. The wrapper allocates those
 * variables through the normal compiler, then erases their synthetic zero-load
 * instructions from the execution shadow and prelinks source IDs to the actual
 * allocated 3-bit registers.
 *
 * The PHP-ish/PASL text is therefore compile-time input only. Reactive dispatch
 * later enters bytecode directly.
 */
final class ReactiveShadowCompiler
{
    public function __construct(private readonly bool $optimize = true) {}

    /**
     * @param array<string,ReactiveSource> $bindings variable name => source
     */
    public function compile(string $shadowId, string $source, array $bindings): PASMExecutableShadow
    {
        if ($bindings === []) throw new InvalidArgumentException('Reactive shadow compiler needs source bindings');

        $normalized=[];$prelude=[];
        foreach($bindings as $var=>$reactive){
            if(!$reactive instanceof ReactiveSource) throw new InvalidArgumentException('Reactive shadow binding must be a ReactiveSource');
            $name=$this->norm((string)$var);
            if(isset($normalized[$name])) throw new InvalidArgumentException("Duplicate reactive input {$name}");
            $normalized[$name]=$reactive;
            $prelude[]='$'.$name.' = 0;';
        }

        $compiler=new PASMFusedCompiler($this->optimize,false,PASMLoopSpace::DEFAULT_MAX_DEPTH,[]);
        $asm=$compiler->compile(implode("\n",$prelude)."\n".$source);
        $varMap=$compiler->varMap()['int']??[];

        $inputRegisters=[];
        foreach($normalized as $name=>$reactive){
            $reg=$varMap[$name]??null;
            if(!is_string($reg)) throw new LangException("Reactive input {$name} was not allocated as an integer register",'reactive-regalloc');
            $inputRegisters[$reactive->id()]=$reg;
            $asm=$this->eraseSyntheticLoad($asm,$reg,$name);
        }

        $assembler=$this->optimize?new PASMOptimizingAssembler(true):new PASMAssembler();
        $bytecode=$assembler->compile($asm);
        return new PASMExecutableShadow($shadowId,$inputRegisters,$bytecode,true);
    }

    /**
     * Compile, register all bound sources, and attach the shadow to the runtime.
     * @param array<string,ReactiveSource> $bindings
     */
    public function compileInto(ReactiveShadowRuntime $runtime,string $shadowId,string $source,array $bindings,bool $runInitial=true): PASMExecutableShadow
    {
        foreach($bindings as $reactive){
            if(!$reactive instanceof ReactiveSource) throw new InvalidArgumentException('Reactive shadow binding must be a ReactiveSource');
            $runtime->addSource($reactive);
        }
        $shadow=$this->compile($shadowId,$source,$bindings);
        $runtime->addShadow($shadow,$runInitial);
        return $shadow;
    }

    private function eraseSyntheticLoad(string $asm,string $reg,string $name): string
    {
        $lines=preg_split('/\R/',$asm)?:[];
        $removed=false;
        foreach($lines as $i=>$line){
            if($removed) break;
            if(preg_match('/^\s*MOVI\s+'.preg_quote($reg,'/').'\s+0\s*$/i',$line)){
                $lines[$i]='; reactive input $'.$name.' prelinked to '.$reg.'; synthetic MOVI erased';
                $removed=true;
            }
        }
        if(!$removed) throw new LangException("Could not erase synthetic reactive input load for {$name}:{$reg}",'reactive-lower');
        return implode("\n",$lines);
    }

    private function norm(string $name): string
    {
        $name=ltrim(trim($name),'$');
        if(!preg_match('/^[A-Za-z_]\w*$/',$name)) throw new InvalidArgumentException("Bad reactive input name {$name}");
        return strtolower($name);
    }
}
