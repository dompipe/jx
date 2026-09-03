<?php declare(strict_types=1);

namespace jx\semantic;

use InvalidArgumentException;

final readonly class PreparedGlobalValue
{
    public function __construct(public int $register, public bool $temporary) {}
}

final readonly class PreparedGlobalCompilation
{
    /**
     * @param array<string,PreparedBagDeclaration> $bags
     * @param array<string,int> $registers
     * @param array<int,int> $initialRegisters
     */
    public function __construct(
        public string $jxl,
        public PreparedContainerBindings $bindings,
        public array $bags,
        public array $registers,
        public array $initialRegisters,
    ) {}

    public function registerBinary(): string
    {
        $out='';
        for ($i=0;$i<8;$i++) $out.=self::u64le($this->initialRegisters[$i]??0);
        return $out;
    }

    public function metadata(): array
    {
        return [
            'format'=>'jx.jxl-prepared-program/1',
            'target'=>'x86_64-sysv',
            'instruction_bytes'=>JxlPreparedInstruction::BYTES,
            'code_bytes'=>strlen($this->jxl),
            'code_sha256'=>hash('sha256',$this->jxl),
            'opcode_bands'=>[
                'prepared_register'=>'0x20-0x37',
                'prepared_container'=>'0x40-0x50',
            ],
            'bags'=>array_map(static fn(PreparedBagDeclaration $b): array=>$b->metadata(),$this->bags),
            'register_window'=>[
                'count'=>8,
                'payload'=>'i64/u64',
                'variables'=>$this->registers,
                'initial'=>$this->initialRegisters,
            ],
            'bindings'=>$this->bindings->metadata(),
        ];
    }

    public function json(): string
    {
        return json_encode($this->metadata(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    }

    private static function u64le(int $value): string
    {
        // pack V2 from two's-complement host integer; prepared x86-64 targets
        // are little-endian and PHP integers on the compiler hosts are 64-bit.
        return pack('V2',$value & 0xFFFFFFFF,($value >> 32) & 0xFFFFFFFF);
    }
}

/**
 * Mixed prepared-register + native-container source compiler.
 *
 * Every executable instruction is exactly six bytes. Ordinary arithmetic and
 * control flow use R0..R7 directly; a contiguous 0x40..0x50 sequence is a
 * native Bag region. The global assembly dispatcher enters such a region once,
 * then the resident container executor runs it without returning to the global
 * loop until the next register/control boundary.
 */
final class PreparedGlobalSourceCompiler
{
    private string $code='';
    /** @var array<string,int> */ private array $registers=[];
    /** @var array<int,true> */ private array $permanent=[];
    /** @var array<int,true> */ private array $temporaries=[];
    /** @var list<array{breaks:list<int>,continues:list<int>,continue_target:?int}> */ private array $loops=[];
    /** @var array<int,true> */ private array $regionDirty=[];

    /** @param array<string,PreparedBagDeclaration> $bags */
    public function __construct(
        private readonly PreparedContainerBindings $bindings,
        private readonly array $bags,
    ) {}

    public function compile(Program $program): PreparedGlobalCompilation
    {
        if ($this->bags===[]) throw new SemanticException('Global prepared JXL currently requires at least one canonical Bag declaration','jxl-global');
        $this->code='';$this->registers=[];$this->permanent=[];$this->temporaries=[];$this->loops=[];$this->regionDirty=[];
        if ($program->functions!==[]) throw new SemanticException('Prepared global JXL functions are not yet lowered; inline the hot region or use the legacy stack-JXL function path','jxl-global');
        foreach ($program->statements as $statement) $this->statement($statement);
        $this->emitCore(JxlPreparedInstruction::halt());
        return new PreparedGlobalCompilation($this->code,$this->bindings,$this->bags,$this->registers,[]);
    }

    private function statement(Node $n): void
    {
        switch($n->op){
            case 'block':
                foreach($n->data['statements'] as $s)$this->statement($s);
                return;
            case 'decl':
                $name=strtolower($n->data['name']);
                if($n->type===Type::BAG && isset($this->bags[$name])) return;
                if(!in_array(Type::canonical($n->type),[Type::INT,Type::BOOL,Type::ANY],true)){
                    throw new SemanticException("Prepared global JXL declaration {$name} must be int/bool/any",'jxl-global',$n->line);
                }
                $dst=$this->variable($name);
                if($n->data['init']===null)$this->emitCore(JxlPreparedInstruction::movi($dst,0));
                else{$value=$this->expression($n->data['init'],$dst);$this->releaseUnless($value,$dst);}
                return;
            case 'expr':
                $value=$this->expression($n->data['expr'],null);
                $this->release($value);
                return;
            case 'if':
                $this->compileIf($n);return;
            case 'while':
                $this->compileWhile($n);return;
            case 'do_while':
                $this->compileDoWhile($n);return;
            case 'for':
                $this->compileFor($n);return;
            case 'repeat':
                $this->compileRepeat($n);return;
            case 'break':
                if($this->loops===[])throw new SemanticException('break outside loop','jxl-global',$n->line);
                $start=$this->offset();$this->emitCore(JxlPreparedInstruction::jumpPlaceholder());
                $idx=array_key_last($this->loops);$this->loops[$idx]['breaks'][]=$start+1;return;
            case 'continue':
                if($this->loops===[])throw new SemanticException('continue outside loop','jxl-global',$n->line);
                $start=$this->offset();$this->emitCore(JxlPreparedInstruction::jumpPlaceholder());
                $idx=array_key_last($this->loops);$target=$this->loops[$idx]['continue_target'];
                if($target!==null)JxlPreparedInstruction::patchTarget($this->code,$start+1,$target);
                else $this->loops[$idx]['continues'][]=$start+1;
                return;
            default:
                throw new SemanticException("Prepared global JXL cannot yet lower {$n->op}",'jxl-global',$n->line);
        }
    }

    private function compileIf(Node $n): void
    {
        $cond=$this->expression($n->data['cond'],null);
        $branch=$this->offset();$this->emitCore(JxlPreparedInstruction::branchPlaceholder(JxlPreparedOpcode::JZ,$cond->register));
        $this->release($cond);
        $this->statement($n->data['then']);
        if($n->data['else']!==null){
            $jump=$this->offset();$this->emitCore(JxlPreparedInstruction::jumpPlaceholder());
            $else=$this->offset();$this->markTarget();JxlPreparedInstruction::patchTarget($this->code,$branch+2,$else);
            $this->statement($n->data['else']);
            $end=$this->offset();$this->markTarget();JxlPreparedInstruction::patchTarget($this->code,$jump+1,$end);
        }else{
            $end=$this->offset();$this->markTarget();JxlPreparedInstruction::patchTarget($this->code,$branch+2,$end);
        }
    }

    private function compileWhile(Node $n): void
    {
        $head=$this->offset();$this->markTarget();
        $cond=$this->expression($n->data['cond'],null);
        $exit=$this->offset();$this->emitCore(JxlPreparedInstruction::branchPlaceholder(JxlPreparedOpcode::JZ,$cond->register));
        $this->release($cond);
        $this->loops[]=['breaks'=>[],'continues'=>[],'continue_target'=>$head];$idx=array_key_last($this->loops);
        $this->statement($n->data['body']);
        $this->emitCore(JxlPreparedInstruction::jump($head));
        $end=$this->offset();$this->markTarget();
        JxlPreparedInstruction::patchTarget($this->code,$exit+2,$end);
        $this->patchLoop($idx,$end,$head);array_pop($this->loops);
    }

    private function compileDoWhile(Node $n): void
    {
        $head=$this->offset();$this->markTarget();
        $this->loops[]=['breaks'=>[],'continues'=>[],'continue_target'=>null];$idx=array_key_last($this->loops);
        $this->statement($n->data['body']);
        $condition=$this->offset();$this->markTarget();$this->loops[$idx]['continue_target']=$condition;$this->patchContinues($idx,$condition);
        $cond=$this->expression($n->data['cond'],null);
        $this->emitCore(JxlPreparedInstruction::branch(JxlPreparedOpcode::JNZ,$cond->register,$head));
        $this->release($cond);
        $end=$this->offset();$this->markTarget();$this->patchBreaks($idx,$end);array_pop($this->loops);
    }

    private function compileFor(Node $n): void
    {
        if($n->data['init']!==null){$v=$this->expression($n->data['init'],null);$this->release($v);}
        $head=$this->offset();$this->markTarget();
        if($n->data['cond']!==null)$cond=$this->expression($n->data['cond'],null);
        else{$r=$this->temp();$this->emitCore(JxlPreparedInstruction::movi($r,1));$cond=new PreparedGlobalValue($r,true);}
        $exit=$this->offset();$this->emitCore(JxlPreparedInstruction::branchPlaceholder(JxlPreparedOpcode::JZ,$cond->register));$this->release($cond);
        $this->loops[]=['breaks'=>[],'continues'=>[],'continue_target'=>null];$idx=array_key_last($this->loops);
        $this->statement($n->data['body']);
        $step=$this->offset();$this->markTarget();$this->loops[$idx]['continue_target']=$step;$this->patchContinues($idx,$step);
        if($n->data['step']!==null){$v=$this->expression($n->data['step'],null);$this->release($v);}
        $this->emitCore(JxlPreparedInstruction::jump($head));
        $end=$this->offset();$this->markTarget();JxlPreparedInstruction::patchTarget($this->code,$exit+2,$end);$this->patchBreaks($idx,$end);array_pop($this->loops);
    }

    private function compileRepeat(Node $n): void
    {
        $counter=$this->temp();
        $count=$this->expression($n->data['count'],$counter);$this->releaseUnless($count,$counter);
        $one=$this->temp();$this->emitCore(JxlPreparedInstruction::movi($one,1));
        $head=$this->offset();$this->markTarget();
        $exit=$this->offset();$this->emitCore(JxlPreparedInstruction::branchPlaceholder(JxlPreparedOpcode::JZ,$counter));
        $this->loops[]=['breaks'=>[],'continues'=>[],'continue_target'=>null];$idx=array_key_last($this->loops);
        $this->statement($n->data['body']);
        $step=$this->offset();$this->markTarget();$this->loops[$idx]['continue_target']=$step;$this->patchContinues($idx,$step);
        $this->emitCore(JxlPreparedInstruction::binary(JxlPreparedOpcode::SUB,$counter,$counter,$one));
        $this->emitCore(JxlPreparedInstruction::jump($head));
        $end=$this->offset();$this->markTarget();JxlPreparedInstruction::patchTarget($this->code,$exit+2,$end);$this->patchBreaks($idx,$end);array_pop($this->loops);
        $this->release(new PreparedGlobalValue($counter,true));$this->release(new PreparedGlobalValue($one,true));
    }

    private function expression(Node $n,?int $target): PreparedGlobalValue
    {
        switch($n->op){
            case 'group':return $this->expression($n->data['expr'],$target);
            case 'literal':
                $literal=$n->data['value'];
                if(is_bool($literal))$literal=$literal?1:0;elseif($literal===null)$literal=0;
                if(!is_int($literal))throw new SemanticException('Prepared global JXL literals must be int/bool/null','jxl-global',$n->line);
                $dst=$target??$this->temp();$this->emitCore(JxlPreparedInstruction::movi($dst,$literal));return new PreparedGlobalValue($dst,$target===null);
            case 'var':
                $src=$this->variable($n->data['name']);
                if($target===null||$target===$src)return new PreparedGlobalValue($src,false);
                $this->emitCore(JxlPreparedInstruction::mov($target,$src));return new PreparedGlobalValue($target,false);
            case 'binary':return $this->binary($n,$target);
            case 'unary':return $this->unary($n,$target);
            case 'postfix':return $this->postfix($n,$target);
            case 'assign':return $this->assign($n,$target);
            case 'call':
                if($this->isBagCall($n))return $this->bagCall($n,$target);
                throw new SemanticException('Prepared global JXL only lowers direct Bag calls in the native program path','jxl-global',$n->line);
            case 'member':
                if($this->isRecordField($n))return $this->recordRead($n,$target);
                throw new SemanticException('Prepared global JXL member read must be a canonical record Bag field','jxl-global',$n->line);
            default:throw new SemanticException("Prepared global JXL expression {$n->op} unsupported",'jxl-global',$n->line);
        }
    }

    private function binary(Node $n,?int $target): PreparedGlobalValue
    {
        $opcode=match($n->data['operator']){
            '+'=>JxlPreparedOpcode::ADD,'-'=>JxlPreparedOpcode::SUB,'*'=>JxlPreparedOpcode::MUL,'/'=>JxlPreparedOpcode::DIV,'%'=>JxlPreparedOpcode::MOD,
            '==','==='=>JxlPreparedOpcode::EQ,'!=','!=='=>JxlPreparedOpcode::NE,'<'=>JxlPreparedOpcode::LT,'<='=>JxlPreparedOpcode::LE,'>'=>JxlPreparedOpcode::GT,'>='=>JxlPreparedOpcode::GE,
            '&'=>JxlPreparedOpcode::BAND,'|'=>JxlPreparedOpcode::BOR,'^'=>JxlPreparedOpcode::BXOR,'<<'=>JxlPreparedOpcode::SHL,'>>'=>JxlPreparedOpcode::SHR,
            default=>throw new SemanticException('Prepared global JXL binary '.$n->data['operator'].' unsupported','jxl-global',$n->line),
        };
        $left=$this->expression($n->data['left'],null);$right=$this->expression($n->data['right'],null);
        $dst=$target??$this->temp();$this->emitCore(JxlPreparedInstruction::binary($opcode,$dst,$left->register,$right->register));
        $this->releaseUnless($left,$dst);$this->releaseUnless($right,$dst);
        return new PreparedGlobalValue($dst,$target===null);
    }

    private function unary(Node $n,?int $target): PreparedGlobalValue
    {
        $op=$n->data['operator'];
        if($op==='+')return $this->expression($n->data['expr'],$target);
        if($op==='++'||$op==='--'){
            $x=$n->data['expr'];if($x->op!=='var')throw new SemanticException('Prepared prefix increment requires variable','jxl-global',$n->line);
            $dst=$this->variable($x->data['name']);$one=$this->temp();$this->emitCore(JxlPreparedInstruction::movi($one,1));
            $this->emitCore(JxlPreparedInstruction::binary($op==='++'?JxlPreparedOpcode::ADD:JxlPreparedOpcode::SUB,$dst,$dst,$one));$this->release(new PreparedGlobalValue($one,true));
            if($target!==null&&$target!==$dst){$this->emitCore(JxlPreparedInstruction::mov($target,$dst));return new PreparedGlobalValue($target,false);}return new PreparedGlobalValue($dst,false);
        }
        $opcode=match($op){'-'=>JxlPreparedOpcode::NEG,'!'=>JxlPreparedOpcode::NOT,default=>throw new SemanticException("Prepared unary {$op} unsupported",'jxl-global',$n->line)};
        $src=$this->expression($n->data['expr'],null);$dst=$target??$this->temp();$this->emitCore(JxlPreparedInstruction::unary($opcode,$dst,$src->register));$this->releaseUnless($src,$dst);return new PreparedGlobalValue($dst,$target===null);
    }

    private function postfix(Node $n,?int $target): PreparedGlobalValue
    {
        $x=$n->data['expr'];if($x->op!=='var')throw new SemanticException('Prepared postfix increment requires variable','jxl-global',$n->line);
        $reg=$this->variable($x->data['name']);$result=$target??$this->temp();$temporary=$target===null;
        if($result!==$reg)$this->emitCore(JxlPreparedInstruction::mov($result,$reg));
        $one=$this->temp();$this->emitCore(JxlPreparedInstruction::movi($one,1));
        $this->emitCore(JxlPreparedInstruction::binary($n->data['operator']==='++'?JxlPreparedOpcode::ADD:JxlPreparedOpcode::SUB,$reg,$reg,$one));$this->release(new PreparedGlobalValue($one,true));
        return new PreparedGlobalValue($result,$temporary);
    }

    private function assign(Node $n,?int $target): PreparedGlobalValue
    {
        $lhs=$n->data['target'];$operator=$n->data['operator'];
        if($lhs->op==='member'&&$this->isRecordField($lhs)){
            if($operator!=='=')throw new SemanticException('Record Bag field compound assignment is not yet fused; read/compute/write explicitly','jxl-global',$n->line);
            $value=$this->expression($n->data['value'],null);$this->recordWrite($lhs,$value->register);
            if($target!==null&&$target!==$value->register){$this->emitCore(JxlPreparedInstruction::mov($target,$value->register));$this->release($value);return new PreparedGlobalValue($target,false);}return $value;
        }
        if($lhs->op!=='var')throw new SemanticException('Prepared assignment target must be variable or record Bag field','jxl-global',$n->line);
        $dst=$this->variable($lhs->data['name']);
        if($operator==='='){$value=$this->expression($n->data['value'],$dst);$this->releaseUnless($value,$dst);}
        else{
            $op=match($operator){'+='=>JxlPreparedOpcode::ADD,'-='=>JxlPreparedOpcode::SUB,'*='=>JxlPreparedOpcode::MUL,'/='=>JxlPreparedOpcode::DIV,'%='=>JxlPreparedOpcode::MOD,default=>throw new SemanticException("Prepared assignment {$operator} unsupported",'jxl-global',$n->line)};
            $rhs=$this->expression($n->data['value'],null);$this->emitCore(JxlPreparedInstruction::binary($op,$dst,$dst,$rhs->register));$this->releaseUnless($rhs,$dst);
        }
        if($target!==null&&$target!==$dst){$this->emitCore(JxlPreparedInstruction::mov($target,$dst));return new PreparedGlobalValue($target,false);}return new PreparedGlobalValue($dst,false);
    }

    private function bagCall(Node $call,?int $target): PreparedGlobalValue
    {
        $member=$call->data['callee'];$bagName=strtolower($member->data['object']->data['name']);$bag=$this->bags[$bagName];
        $binding=$this->bindings->bind($bag->handle,$bag->discipline,$member->data['name'],$bag->width,$bag->capacity,$bag->mask,$bag->flags);
        $expected=JxlContainerOpcode::sourceCount($binding->opcode);if($bag->discipline==='set'&&$binding->operation==='EMPLACE')$expected=1;
        $args=$call->data['args'];if(count($args)!==$expected)throw new SemanticException("{$bagName}.{$member->data['name']} expects {$expected} argument(s)",'jxl-global',$call->line);
        $refs=[];foreach($args as $arg)$refs[]=$this->expression($arg,null);
        $returns=JxlContainerOpcode::returnsResult($binding->opcode);
        if($target!==null&&!$returns)throw new SemanticException("{$bagName}.{$member->data['name']} does not return a value",'jxl-global',$call->line);
        $dst=$returns?($target??$this->temp()):null;$temporary=$returns&&$target===null;
        $src0=$refs[0]->register??null;$src1=$refs[1]->register??null;if($bag->discipline==='set'&&$binding->operation==='EMPLACE')$src1=$src0;
        $this->emitContainer($binding,$src0,$src1,$dst);
        foreach($refs as $ref)$this->releaseUnless($ref,$dst);
        return new PreparedGlobalValue($dst??($target??$this->zeroValue()),$temporary);
    }

    private function recordRead(Node $member,?int $target): PreparedGlobalValue
    {
        [$bag,$field]=$this->recordField($member);$slot=$this->temp();$this->emitCore(JxlPreparedInstruction::movi($slot,$field['slot']));
        $dst=$target??$this->temp();$binding=$this->bindings->bind($bag->handle,'record','get',$bag->width,$bag->capacity,$bag->mask,$bag->flags);
        $this->emitContainer($binding,$slot,null,$dst);$this->release(new PreparedGlobalValue($slot,true));return new PreparedGlobalValue($dst,$target===null);
    }

    private function recordWrite(Node $member,int $src): void
    {
        [$bag,$field]=$this->recordField($member);$slot=$this->temp();$this->emitCore(JxlPreparedInstruction::movi($slot,$field['slot']));
        $binding=$this->bindings->bind($bag->handle,'record','put',$bag->width,$bag->capacity,$bag->mask,$bag->flags);$this->emitContainer($binding,$slot,$src,null);$this->release(new PreparedGlobalValue($slot,true));
    }

    private function emitContainer(PreparedContainerBinding $binding,?int $src0,?int $src1,?int $dst): void
    {
        $this->code.=JxlContainerInstruction::emit($binding,$src0,$src1,$dst);
        $mutating=in_array($binding->operation,['PUSH','POP','PUSHF','PUSHB','POPF','POPB','EMPLACE','PUT','REMOVE'],true);
        if($mutating&&!isset($this->regionDirty[$binding->bagHandle])){
            $dirty=$this->bindings->bind($binding->bagHandle,$binding->discipline,'DIRTY',$binding->width,$binding->capacity,$binding->mask,$binding->flags);
            $this->code.=JxlContainerInstruction::emit($dirty);$this->regionDirty[$binding->bagHandle]=true;
        }
        if($binding->operation==='DIRTY')$this->regionDirty[$binding->bagHandle]=true;
        if($binding->operation==='SYNC')unset($this->regionDirty[$binding->bagHandle]);
    }

    private function emitCore(string $instruction): void
    {
        if(strlen($instruction)!==JxlPreparedInstruction::BYTES)throw new InvalidArgumentException('Prepared global core instruction must be six bytes');
        $this->code.=$instruction;$this->regionDirty=[];
    }

    private function markTarget(): void{$this->regionDirty=[];}
    private function offset(): int{return strlen($this->code);}

    private function isBagCall(Node $n): bool
    {
        if($n->op!=='call'||$n->data['callee']->op!=='member')return false;$object=$n->data['callee']->data['object'];return $object->op==='var'&&isset($this->bags[strtolower($object->data['name'])]);
    }
    private function isRecordField(Node $n): bool
    {
        if($n->op!=='member'||$n->data['object']->op!=='var')return false;$name=strtolower($n->data['object']->data['name']);return isset($this->bags[$name])&&$this->bags[$name]->discipline==='record'&&isset($this->bags[$name]->fields[strtolower($n->data['name'])]);
    }
    /** @return array{0:PreparedBagDeclaration,1:array{slot:int,type:string}} */
    private function recordField(Node $n): array
    {
        $bag=$this->bags[strtolower($n->data['object']->data['name'])]??null;if(!$bag||$bag->discipline!=='record')throw new SemanticException('Record field target is not a record Bag','jxl-global',$n->line);
        $name=strtolower($n->data['name']);$field=$bag->fields[$name]??null;if($field===null)throw new SemanticException("Unknown record field {$name}",'jxl-global',$n->line);return[$bag,$field];
    }

    private function variable(string $name): int
    {
        $name=strtolower($name);if(isset($this->registers[$name]))return$this->registers[$name];$reg=$this->allocate(false);$this->permanent[$reg]=true;return$this->registers[$name]=$reg;
    }
    private function temp(): int{$reg=$this->allocate(true);$this->temporaries[$reg]=true;return$reg;}
    private function allocate(bool $temporary): int
    {
        for($i=0;$i<8;$i++)if(!isset($this->permanent[$i])&&!isset($this->temporaries[$i]))return$i;
        throw new SemanticException('Prepared global JXL exhausted the eight-register window','jxl-global');
    }
    private function release(PreparedGlobalValue $value): void{if($value->temporary)unset($this->temporaries[$value->register]);}
    private function releaseUnless(PreparedGlobalValue $value,?int $keep): void{if($value->temporary&&$value->register!==$keep)unset($this->temporaries[$value->register]);}
    private function zeroValue(): int
    {
        $r=$this->temp();$this->emitCore(JxlPreparedInstruction::movi($r,0));unset($this->temporaries[$r]);return$r;
    }

    private function patchBreaks(int $idx,int $target): void{foreach($this->loops[$idx]['breaks'] as $p)JxlPreparedInstruction::patchTarget($this->code,$p,$target);}
    private function patchContinues(int $idx,int $target): void{foreach($this->loops[$idx]['continues'] as $p)JxlPreparedInstruction::patchTarget($this->code,$p,$target);$this->loops[$idx]['continues']=[];}
    private function patchLoop(int $idx,int $end,int $continue): void{$this->patchBreaks($idx,$end);$this->patchContinues($idx,$continue);}
}
