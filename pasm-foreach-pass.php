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
 *   forif ($value in $items if _ > 10) { ... }
 *   revif ($value in $items if _ > 10) { ... }
 *
 * Positional row destructuring for callback/iterator array results:
 *   _, no1, no2, no3 = forif ($value in $items if no1 < _) { ... }
 *
 * The iterator/callback row is exploded before the predicate:
 *   _   = row[0]
 *   no1 = row[1]
 *   no2 = row[2]
 *   ...
 *
 * `_` is therefore always argument/value position zero. A predicate may use
 * any destructured value from the same row. revif changes traversal direction
 * only; it does not reverse positions inside the returned row.
 *
 * Filtered iteration reuses the same compact iterator controller. Positional
 * destination registers live in the prelinked iterator descriptor, so ITERF /
 * ITERR remain exactly two bytes in the repeated instruction stream.
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
            $gateReg=$ints[$plan['gate']]??null;
            $valueRegs=[];
            foreach($plan['values'] as $valueName){
                $vr=$ints[$valueName]??null;
                if(!is_string($vr))throw new LangException('Collection row register allocation is incomplete for '.$valueName,'foreach-regalloc');
                $valueRegs[]=\pasm\PASMBC::regId($vr);
            }
            $keyReg=$plan['key']===null?null:($ints[$plan['key']]??null);
            if(!is_string($gateReg)||($plan['key']!==null&&!is_string($keyReg)))throw new LangException('Collection loop register allocation is incomplete','foreach-regalloc');

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
            $this->bindings[]=[
                'slot'=>$plan['slot'],
                'collection'=>$plan['collection'],
                'value_reg'=>$valueRegs[0]??null,
                'value_regs'=>$valueRegs,
                'key_reg'=>$keyReg===null?null:\pasm\PASMBC::regId($keyReg),
                'reverse'=>$plan['reverse'],
            ];
        }
        return implode("\n",$lines);
    }

    private function lowerBlock(string $src): string
    {
        $out='';$i=0;$n=strlen($src);
        while($i<$n){
            $destructure=null;
            $keyword=null;

            // Exact row-binding prefix: _, no1, no2 = forif (...)
            $rest=substr($src,$i);
            if(preg_match('/^\s*((?:\$?[A-Za-z_]\w*\s*,\s*)+\$?[A-Za-z_]\w*)\s*=\s*(forif|revif)\b/i',$rest,$dm)){
                $candidate=strtolower($dm[2]);
                $destructure=$this->parseDestructure($dm[1]);
                $keyword=$candidate;
                $i += strpos($rest,$dm[2]);
            } else {
                foreach(PASMForeachSurface::keywords() as $candidate){
                    if($this->wordAt($src,$i,$candidate)){$keyword=$candidate;break;}
                }
            }

            if($keyword!==null){
                $j=$this->skipWs($src,$i+strlen($keyword));if($j>=$n||$src[$j]!=='(')throw new LangException("{$keyword} requires (...)",'parse');
                [$header,$afterHeader]=$this->extractDelimited($src,$j,'(',')');
                [$body,$afterBody]=$this->extractBody($src,$afterHeader);
                $filtered=PASMForeachSurface::filtered($keyword);
                [$collection,$key,$value,$predicate]=$this->parseHeader($header,$filtered);
                if(!isset($this->collections[$collection]))throw new LangException("Unbound collection {$collection}; bind it on Engine before compiling {$keyword}",'foreach-bind');
                if($destructure!==null&&!$filtered)throw new LangException('Row destructuring prefix is reserved for forif/revif','parse');

                $values=$destructure??[$value];
                if($destructure!==null&&$values[0]!=='_')throw new LangException('forif/revif row destructuring must bind _ as position zero','parse');
                if(count($values)>8)throw new LangException('forif/revif row destructuring supports at most 8 positional values','foreach-regalloc');

                $slot=count($this->plans);if($slot>255)throw new LangException('More than 256 collection-loop sites require a wider iterator ABI','foreach-slots');
                $gate='__jx_iter_gate_'.$this->seq++;
                $this->plans[]=['slot'=>$slot,'collection'=>$collection,'key'=>$key,'value'=>$values[0],'values'=>$values,'gate'=>$gate,'reverse'=>PASMForeachSurface::reverse($keyword)];

                $current=$values[0];
                if($filtered)$body=$this->replaceCurrentOperator($body,$current);
                $loweredBody=$this->lowerBlock($body);
                if($predicate!==null){
                    $predicate=$this->replaceCurrentOperator($predicate,$current);
                    $loweredBody='if ('.$predicate.") {\n".$loweredBody."\n}";
                }

                foreach($values as $target)$out.='$'.$target." = 0;\n";
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

    /** @return list<string> */
    private function parseDestructure(string $lhs): array
    {
        $parts=array_map('trim',explode(',',$lhs));$out=[];
        foreach($parts as $part){
            $name=$this->norm($part);
            if(in_array($name,$out,true))throw new LangException('Duplicate forif/revif destructuring target '.$name,'parse');
            $out[]=$name;
        }
        if(count($out)<2)throw new LangException('Row destructuring requires at least two targets','parse');
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

    /** Replace standalone `_` with the row/frame position-zero variable. */
    private function replaceCurrentOperator(string $expr,string $value): string
    {
        if($value==='_')return $this->prefixBareCurrent($expr);
        $out='';$quote=null;$n=strlen($expr);
        for($i=0;$i<$n;$i++){
            $c=$expr[$i];
            if($quote!==null){$out.=$c;if($c==='\\'&&$i+1<$n){$out.=$expr[++$i];continue;}if($c===$quote)$quote=null;continue;}
            if($c==='"'||$c==="'"){$quote=$c;$out.=$c;continue;}
            if($c==='_'){
                $prev=$i>0?$expr[$i-1]:'';$next=$i+1<$n?$expr[$i+1]:'';
                $prevIdent=$prev!==''&&(ctype_alnum($prev)||$prev==='_'||$prev==='$');$nextIdent=$next!==''&&(ctype_alnum($next)||$next==='_');
                if(!$prevIdent&&!$nextIdent){$out.='$'.$value;continue;}
            }
            $out.=$c;
        }
        return $out;
    }

    /** Turn bare standalone `_` into PASL variable `$_` without touching identifiers/strings. */
    private function prefixBareCurrent(string $expr): string
    {
        $out='';$quote=null;$n=strlen($expr);
        for($i=0;$i<$n;$i++){
            $c=$expr[$i];
            if($quote!==null){$out.=$c;if($c==='\\'&&$i+1<$n){$out.=$expr[++$i];continue;}if($c===$quote)$quote=null;continue;}
            if($c==='"'||$c==="'"){$quote=$c;$out.=$c;continue;}
            if($c==='_'){
                $prev=$i>0?$expr[$i-1]:'';$next=$i+1<$n?$expr[$i+1]:'';
                $prevIdent=$prev!==''&&(ctype_alnum($prev)||$prev==='_'||$prev==='$');$nextIdent=$next!==''&&(ctype_alnum($next)||$next==='_');
                if(!$prevIdent&&!$nextIdent){$out.='$_';continue;}
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
