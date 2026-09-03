<?php declare(strict_types=1);

namespace pasm\lang;

require_once __DIR__ . '/pasm-foreach-surface.php';

use InvalidArgumentException;

/**
 * Collection-loop lowering pass.
 *
 * Surface:
 *   foreach ($items as $value) { ... }
 *   foreach ($items as $key => $value) { ... }
 *   reveach ($items as $value) { ... }
 *   reveach ($items as $key => $value) { ... }
 *   forif ($items as $value if _ > 10) { ... }
 *   revif ($items as $value if _ > 10) { ... }
 *   forif ($value in $items if _ > 10) { ... }   // Python-like spelling
 *   revif ($value in $items if _ > 10) { ... }
 *
 * `_` is the current-value operator inside a forif/revif predicate. It lowers
 * to the bound value register before normal expression compilation.
 *
 * Filtered iteration reuses the same compact iterator controller. A predicate
 * miss skips the body and returns to the iterator check; it does not terminate
 * the collection walk and does not create another iterator object.
 *
 * The pass reuses the tested bounded while compiler for body/block semantics.
 * After register allocation it replaces the synthetic controller with:
 *
 *   IRESET slot      # once on loop entry
 *   check:
 *     ITERF slot     # or ITERR; repeated hot operation, exactly 2 bytes
 *     JZ exit
 *     JMP body
 *
 * Value/key destination registers live in the prelinked iterator descriptor.
 */
final class PASMForeachPass
{
    private array $collections = [];
    private array $plans = [];
    private array $bindings = [];
    private int $seq = 0;

    public function __construct(array $collectionNames)
    {
        foreach ($collectionNames as $name) $this->collections[$this->norm((string)$name)] = true;
    }

    public function lower(string $source): string
    {
        $this->plans=[];$this->bindings=[];$this->seq=0;
        return $this->lowerBlock($source);
    }

    public function bindings(): array { return $this->bindings; }

    public function rewriteAsm(string $asm,array $varMap): string
    {
        $lines=preg_split('/\R/',$asm)?:[];$ints=$varMap['int']??[];$this->bindings=[];
        foreach($this->plans as $plan){
            $gateReg=$ints[$plan['gate']]??null;$valueReg=$ints[$plan['value']]??null;$keyReg=$plan['key']===null?null:($ints[$plan['key']]??null);
            if(!is_string($gateReg)||!is_string($valueReg)||($plan['key']!==null&&!is_string($keyReg)))throw new LangException('Collection loop register allocation is incomplete','foreach-regalloc');

            $resetFound=false;
            foreach($lines as $i=>$line){
                if(preg_match('/^\s*MOVI\s+'.preg_quote($gateReg,'/').'\s+1\s*$/i',$line)){
                    $lines[$i]='        IRESET '.$plan['slot'];$resetFound=true;break;
                }
            }
            if(!$resetFound)throw new LangException('Could not locate collection-loop entry reset for '.$plan['collection'],'foreach-lower');

            $controllerFound=false;
            for($i=0,$n=count($lines)-3;$i<$n;$i++){
                if(!preg_match('/^\s*MOVI\s+(\w+)\s+0\s*$/i',$lines[$i],$m0))continue;
                $zeroReg=$m0[1];
                if(!preg_match('/^\s*CMP\s+'.preg_quote($gateReg,'/').'\s+'.preg_quote($zeroReg,'/').'\s*$/i',$lines[$i+1]))continue;
                if(!preg_match('/^\s*JNZ\s+(\S+)\s*$/i',$lines[$i+2],$mb))continue;
                if(!preg_match('/^\s*JMP\s+(\S+)\s*$/i',$lines[$i+3],$me))continue;
                $op=$plan['reverse']?'ITERR':'ITERF';
                array_splice($lines,$i,4,['        '.$op.'  '.$plan['slot'],'        JZ    '.$me[1],'        JMP   '.$mb[1]]);
                $controllerFound=true;break;
            }
            if(!$controllerFound)throw new LangException('Could not locate collection-loop controller for '.$plan['collection'],'foreach-lower');
            $this->bindings[]=['slot'=>$plan['slot'],'collection'=>$plan['collection'],'value_reg'=>\pasm\PASMBC::regId($valueReg),'key_reg'=>$keyReg===null?null:\pasm\PASMBC::regId($keyReg),'reverse'=>$plan['reverse']];
        }
        return implode("\n",$lines);
    }

    private function lowerBlock(string $src): string
    {
        $out='';$i=0;$n=strlen($src);
        while($i<$n){
            $keyword=null;
            foreach(PASMForeachSurface::keywords() as $candidate){
                if($this->wordAt($src,$i,$candidate)){$keyword=$candidate;break;}
            }
            if($keyword!==null){
                $j=$this->skipWs($src,$i+strlen($keyword));if($j>=$n||$src[$j]!=='(')throw new LangException("{$keyword} requires (...)",'parse');
                [$header,$afterHeader]=$this->extractDelimited($src,$j,'(',')');
                [$body,$afterBody]=$this->extractBody($src,$afterHeader);
                [$collection,$key,$value,$predicate]=$this->parseHeader($header,PASMForeachSurface::filtered($keyword));
                if(!isset($this->collections[$collection]))throw new LangException("Unbound collection {$collection}; bind it on Engine before compiling {$keyword}",'foreach-bind');
                $slot=count($this->plans);if($slot>255)throw new LangException('More than 256 collection-loop sites require a wider iterator ABI','foreach-slots');
                $gate='__jx_iter_gate_'.$this->seq++;
                $this->plans[]=['slot'=>$slot,'collection'=>$collection,'key'=>$key,'value'=>$value,'gate'=>$gate,'reverse'=>PASMForeachSurface::reverse($keyword)];

                $loweredBody=$this->lowerBlock($body);
                if($predicate!==null){
                    $predicate=$this->replaceCurrentOperator($predicate,$value);
                    $loweredBody='if ('.$predicate.") {\n".$loweredBody."\n}";
                }

                $out.='$'.$value." = 0;\n";
                if($key!==null)$out.='$'.$key." = 0;\n";
                $out.='$'.$gate." = 1;\n";
                $out.='while ($'.$gate.") {\n".$loweredBody."\n}\n";
                $i=$afterBody;continue;
            }
            if($src[$i]==='"'||$src[$i]==="'"){$q=$src[$i];$start=$i++;while($i<$n){if($src[$i]==='\\'){$i+=2;continue;}if($src[$i]===$q){$i++;break;}$i++;}$out.=substr($src,$start,$i-$start);continue;}
            $out.=$src[$i++];
        }
        return $out;
    }

    /** @return array{0:string,1:?string,2:string,3:?string} */
    private function parseHeader(string $header,bool $filtered): array
    {
        $h=trim($header);$predicate=null;
        if($filtered){
            if(!preg_match('/^(.*?)\s+if\s+(.+)$/is',$h,$parts))throw new LangException('Expected filtered loop header ending in: if <condition>','parse');
            $h=trim($parts[1]);$predicate=trim($parts[2]);
            if($predicate==='')throw new LangException('forif/revif requires a non-empty predicate','parse');
        }

        if(preg_match('/^\$?([A-Za-z_]\w*)\s+as\s+(?:(?:\$?([A-Za-z_]\w*))\s*=>\s*)?\$?([A-Za-z_]\w*)$/i',$h,$m)){
            $collection=$this->norm($m[1]);$key=isset($m[2])&&$m[2]!==''?$this->norm($m[2]):null;$value=$this->norm($m[3]);
        } elseif($filtered && preg_match('/^\$?([A-Za-z_]\w*)\s+in\s+\$?([A-Za-z_]\w*)$/i',$h,$m)) {
            // Python-like filtered form: forif ($value in $collection if condition)
            $value=$this->norm($m[1]);$collection=$this->norm($m[2]);$key=null;
        } else {
            $expected=$filtered
                ? 'Expected forif/revif header: $collection as [$key =>] $value if <condition>, or $value in $collection if <condition>'
                : 'Expected collection loop header: $collection as [$key =>] $value';
            throw new LangException($expected,'parse');
        }
        if($key===$value)throw new LangException('Collection key and value variables must be distinct','parse');
        return[$collection,$key,$value,$predicate];
    }

    /** Replace standalone `_` in a filtered predicate with its current value. */
    private function replaceCurrentOperator(string $expr,string $value): string
    {
        $out='';$quote=null;$n=strlen($expr);
        for($i=0;$i<$n;$i++){
            $c=$expr[$i];
            if($quote!==null){
                $out.=$c;
                if($c==='\\'&&$i+1<$n){$out.=$expr[++$i];continue;}
                if($c===$quote)$quote=null;
                continue;
            }
            if($c==='"'||$c==="'"){$quote=$c;$out.=$c;continue;}
            if($c==='_'){
                $prev=$i>0?$expr[$i-1]:'';$next=$i+1<$n?$expr[$i+1]:'';
                $prevIdent=$prev!==''&&(ctype_alnum($prev)||$prev==='_'||$prev==='$');
                $nextIdent=$next!==''&&(ctype_alnum($next)||$next==='_');
                if(!$prevIdent&&!$nextIdent){$out.='$'.$value;continue;}
            }
            $out.=$c;
        }
        return $out;
    }

    private function extractBody(string $src,int $from): array
    {
        $i=$this->skipWs($src,$from);if($i>=strlen($src))throw new LangException('Missing collection-loop body','parse');if($src[$i]==='{')return$this->extractDelimited($src,$i,'{','}');$semi=strpos($src,';',$i);if($semi===false)return[substr($src,$i),strlen($src)];return[substr($src,$i,$semi-$i),$semi+1];
    }

    private function extractDelimited(string $src,int $openAt,string $open,string $close): array
    {
        $depth=0;$quote=null;$n=strlen($src);for($i=$openAt;$i<$n;$i++){$c=$src[$i];if($quote!==null){if($c==='\\'){$i++;continue;}if($c===$quote)$quote=null;continue;}if($c==='"'||$c==="'"){$quote=$c;continue;}if($c===$open)$depth++;elseif($c===$close&&--$depth===0)return[substr($src,$openAt+1,$i-$openAt-1),$i+1];}throw new LangException("Unbalanced {$open}",'parse');
    }

    private function skipWs(string $src,int $i): int{$n=strlen($src);while($i<$n&&ctype_space($src[$i]))$i++;return$i;}
    private function wordAt(string $src,int $i,string $word): bool{$len=strlen($word);if(strncasecmp(substr($src,$i,$len),$word,$len)!==0)return false;$before=$i>0?$src[$i-1]:'';$after=$i+$len<strlen($src)?$src[$i+$len]:'';if($before!==''&&(ctype_alnum($before)||$before==='_'||$before==='$'))return false;if($after!==''&&(ctype_alnum($after)||$after==='_'))return false;return true;}
    private function norm(string $name): string{$name=ltrim(trim($name),'$');if(!preg_match('/^[A-Za-z_]\w*$/',$name))throw new InvalidArgumentException("Bad collection name {$name}");return strtolower($name);}
}
