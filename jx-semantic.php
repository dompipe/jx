<?php declare(strict_types=1);

namespace jx\semantic;

/**
 * JX typed semantic front end and prepared JXL execution core.
 *
 * This file is intentionally host-neutral at the semantic layer. PHP is the
 * current compiler/orchestration host; canonical meaning lives in the IR.
 *
 * JXL byte law:
 *   0xxxxxxx = executable opcode
 *   1xxxxxxx = attached data byte; never an opcode
 */

final class SemanticException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $phase = 'semantic', public readonly ?int $lineNumber = null)
    {
        parent::__construct($lineNumber === null ? $message : "line {$lineNumber}: {$message}");
    }
}

final class Type
{
    public const ANY = 'any';
    public const VOID = 'void';
    public const INT = 'int';
    public const FLOAT = 'float';
    public const BOOL = 'bool';
    public const STRING = 'string';
    public const NULL = 'null';
    public const COMPLEX = 'complex';
    public const BAG = 'bag';
    public const LIST = 'list';
    public const MAP = 'map';
    public const OBJECT = 'object';
    public const HANDLE = 'handle';

    public static function canonical(string $name): string
    {
        return match (strtolower($name)) {
            'integer', 'i64', 'int64' => self::INT,
            'double', 'f64', 'float64' => self::FLOAT,
            'boolean' => self::BOOL,
            'str' => self::STRING,
            default => strtolower($name),
        };
    }

    public static function accepts(string $type, mixed $value): bool
    {
        $type = self::canonical($type);
        return match ($type) {
            self::ANY => true,
            self::VOID => $value === null,
            self::INT => is_int($value),
            self::FLOAT => is_float($value) || is_int($value),
            self::BOOL => is_bool($value),
            self::STRING => is_string($value),
            self::NULL => $value === null,
            self::LIST => is_array($value) && array_is_list($value),
            self::MAP => is_array($value),
            self::OBJECT => is_object($value),
            default => true, // named user types are checked by object/class identity.
        };
    }
}

final readonly class Token
{
    public function __construct(
        public string $kind,
        public string $lexeme,
        public mixed $literal,
        public int $line,
    ) {}
}

final class Lexer
{
    /** @var array<string,true> */
    private const KEYWORDS = [
        'function'=>true,'return'=>true,'if'=>true,'elseif'=>true,'else'=>true,
        'while'=>true,'do'=>true,'for'=>true,'foreach'=>true,'as'=>true,'repeat'=>true,
        'break'=>true,'continue'=>true,'true'=>true,'false'=>true,'null'=>true,
        'try'=>true,'catch'=>true,'finally'=>true,'throw'=>true,
        'class'=>true,'extends'=>true,'implements'=>true,'interface'=>true,'trait'=>true,'enum'=>true,
        'public'=>true,'protected'=>true,'private'=>true,'static'=>true,'const'=>true,'new'=>true,
        'namespace'=>true,'import'=>true,'use'=>true,'from'=>true,'in'=>true,
        'int'=>true,'float'=>true,'bool'=>true,'string'=>true,'complex'=>true,'bag'=>true,
        'list'=>true,'map'=>true,'any'=>true,'void'=>true,
    ];

    /** @return list<Token> */
    public function scan(string $source): array
    {
        $out = [];
        $n = strlen($source);
        $i = 0;
        $line = 1;
        while ($i < $n) {
            $c = $source[$i];
            if ($c === "\n") { $line++; $i++; continue; }
            if (ctype_space($c)) { $i++; continue; }

            if ($c === '/' && $i + 1 < $n && $source[$i+1] === '/') {
                $i += 2; while ($i < $n && $source[$i] !== "\n") $i++; continue;
            }
            if ($c === '#') {
                while ($i < $n && $source[$i] !== "\n") $i++; continue;
            }
            if ($c === '/' && $i + 1 < $n && $source[$i+1] === '*') {
                $i += 2;
                while ($i + 1 < $n && !($source[$i] === '*' && $source[$i+1] === '/')) {
                    if ($source[$i] === "\n") $line++;
                    $i++;
                }
                if ($i + 1 >= $n) throw new SemanticException('Unterminated block comment', 'lex', $line);
                $i += 2; continue;
            }

            if ($c === '$') {
                $start = $i++;
                if ($i >= $n || !(ctype_alpha($source[$i]) || $source[$i] === '_')) {
                    throw new SemanticException('Expected variable name after $', 'lex', $line);
                }
                while ($i < $n && (ctype_alnum($source[$i]) || $source[$i] === '_')) $i++;
                $lex = substr($source, $start, $i-$start);
                $out[] = new Token('VAR', $lex, substr($lex, 1), $line);
                continue;
            }

            if (ctype_alpha($c) || $c === '_') {
                $start = $i++;
                while ($i < $n && (ctype_alnum($source[$i]) || $source[$i] === '_')) $i++;
                $lex = substr($source, $start, $i-$start);
                $low = strtolower($lex);
                $out[] = new Token(isset(self::KEYWORDS[$low]) ? strtoupper($low) : 'IDENT', $lex, $lex, $line);
                continue;
            }

            if (ctype_digit($c)) {
                $start = $i++;
                while ($i < $n && ctype_digit($source[$i])) $i++;
                $isFloat = false;
                if ($i < $n && $source[$i] === '.' && $i + 1 < $n && ctype_digit($source[$i+1])) {
                    $isFloat = true; $i++;
                    while ($i < $n && ctype_digit($source[$i])) $i++;
                }
                $lex = substr($source, $start, $i-$start);
                $out[] = new Token($isFloat ? 'FLOAT_LIT' : 'INT_LIT', $lex, $isFloat ? (float)$lex : (int)$lex, $line);
                continue;
            }

            if ($c === '"' || $c === "'") {
                $quote = $c; $startLine = $line; $i++; $buf = '';
                while ($i < $n && $source[$i] !== $quote) {
                    if ($source[$i] === "\n") $line++;
                    if ($source[$i] === '\\' && $i + 1 < $n) {
                        $i++; $e = $source[$i];
                        $buf .= match ($e) { 'n'=>"\n", 'r'=>"\r", 't'=>"\t", '\\'=>'\\', '"'=>'"', "'"=>"'", default=>$e };
                        $i++; continue;
                    }
                    $buf .= $source[$i++];
                }
                if ($i >= $n) throw new SemanticException('Unterminated string', 'lex', $startLine);
                $i++;
                $out[] = new Token('STRING_LIT', $buf, $buf, $startLine);
                continue;
            }

            $matched = false;
            foreach (['===','!==','<<=','>>=','=>','==','!=','<=','>=','++','--','+=','-=','*=','/=','%=','&&','||','<<','>>','??','->'] as $op) {
                if (substr($source, $i, strlen($op)) === $op) {
                    $out[] = new Token($op, $op, null, $line); $i += strlen($op); $matched = true; break;
                }
            }
            if ($matched) continue;

            if (str_contains('(){}[];,:.?+-*/%<>=!~&|^', $c)) {
                $out[] = new Token($c, $c, null, $line); $i++; continue;
            }

            throw new SemanticException("Unexpected character {$c}", 'lex', $line);
        }
        $out[] = new Token('EOF', '', null, $line);
        return $out;
    }
}

final readonly class Node
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public string $op,
        public array $data = [],
        public string $type = Type::ANY,
        public int $line = 0,
    ) {}
}

final readonly class Program
{
    /** @param list<Node> $statements @param array<string,Node> $functions @param array<string,Node> $classes */
    public function __construct(
        public array $statements,
        public array $functions,
        public array $classes,
        public ?string $namespace = null,
        public array $imports = [],
    ) {}
}

final class Parser
{
    /** @var list<Token> */ private array $t = [];
    private int $i = 0;
    /** @var array<string,Node> */ private array $functions = [];
    /** @var array<string,Node> */ private array $classes = [];
    private ?string $namespace = null;
    private array $imports = [];

    public function parse(string $source): Program
    {
        $this->t = (new Lexer())->scan($source);
        $this->i = 0; $this->functions = []; $this->classes = []; $this->namespace = null; $this->imports = [];
        $stmts = [];
        while (!$this->check('EOF')) {
            if ($this->match('NAMESPACE')) { $this->parseNamespace(); continue; }
            if ($this->match('IMPORT','USE')) { $this->parseImport(); continue; }
            if ($this->match('FUNCTION')) { $fn = $this->parseFunction(false); $this->functions[strtolower($fn->data['name'])] = $fn; continue; }
            if ($this->match('CLASS')) { $cl = $this->parseClass(); $this->classes[strtolower($cl->data['name'])] = $cl; continue; }
            $stmts[] = $this->statement();
        }
        return new Program($stmts, $this->functions, $this->classes, $this->namespace, $this->imports);
    }

    private function parseNamespace(): void
    {
        $parts = [$this->consumeName('Expected namespace name')->lexeme];
        while ($this->match('.')) $parts[] = $this->consumeName('Expected namespace segment')->lexeme;
        $this->consume(';','Expected ; after namespace');
        $this->namespace = implode('.', $parts);
    }

    private function parseImport(): void
    {
        $parts = [$this->consumeName('Expected import name')->lexeme];
        while ($this->match('.')) $parts[] = $this->consumeName('Expected import segment')->lexeme;
        $alias = null;
        if ($this->match('AS')) $alias = $this->consumeName('Expected import alias')->lexeme;
        $this->consume(';','Expected ; after import');
        $this->imports[] = ['path'=>implode('.', $parts), 'alias'=>$alias];
    }

    private function parseFunction(bool $method, string $visibility = 'public', bool $static = false): Node
    {
        $name = $this->consumeName('Expected function name');
        $this->consume('(','Expected ( after function name');
        $params = [];
        if (!$this->check(')')) {
            do {
                $type = Type::ANY;
                if ($this->isTypeToken($this->peek())) $type = Type::canonical(strtolower($this->advance()->lexeme));
                $var = $this->consumeVarOrName('Expected parameter name');
                $params[] = ['name'=>$this->varName($var), 'type'=>$type];
            } while ($this->match(','));
        }
        $this->consume(')','Expected ) after parameters');
        $ret = Type::ANY;
        if ($this->match(':')) $ret = Type::canonical(strtolower($this->consumeName('Expected return type')->lexeme));
        $body = $this->blockStatement();
        return new Node('function', [
            'name'=>$name->lexeme,'params'=>$params,'return'=>$ret,'body'=>$body,
            'method'=>$method,'visibility'=>$visibility,'static'=>$static,
        ], $ret, $name->line);
    }

    private function parseClass(): Node
    {
        $name = $this->consumeName('Expected class name');
        $extends = null; $implements = [];
        if ($this->match('EXTENDS')) $extends = $this->consumeName('Expected parent class')->lexeme;
        if ($this->match('IMPLEMENTS')) {
            do { $implements[] = $this->consumeName('Expected interface name')->lexeme; } while ($this->match(','));
        }
        $this->consume('{','Expected { after class declaration');
        $methods = []; $properties = [];
        while (!$this->check('}') && !$this->check('EOF')) {
            $visibility = 'public';
            if ($this->match('PUBLIC','PROTECTED','PRIVATE')) $visibility = strtolower($this->previous()->lexeme);
            $static = $this->match('STATIC');
            if ($this->match('FUNCTION')) {
                $m = $this->parseFunction(true, $visibility, $static);
                $methods[strtolower($m->data['name'])] = $m;
                continue;
            }
            $type = Type::ANY;
            if ($this->isTypeToken($this->peek())) $type = Type::canonical(strtolower($this->advance()->lexeme));
            $p = $this->consumeVarOrName('Expected property or method');
            $init = null;
            if ($this->match('=')) $init = $this->expression();
            $this->consume(';','Expected ; after property');
            $properties[$this->varName($p)] = ['type'=>$type,'init'=>$init,'visibility'=>$visibility,'static'=>$static];
        }
        $this->consume('}','Expected } after class body');
        return new Node('class', [
            'name'=>$name->lexeme,'extends'=>$extends,'implements'=>$implements,
            'methods'=>$methods,'properties'=>$properties,
        ], $name->lexeme, $name->line);
    }

    private function statement(): Node
    {
        if ($this->match('{')) return $this->finishBlock($this->previous()->line);
        if ($this->match('IF')) return $this->ifStatement();
        if ($this->match('WHILE')) return $this->whileStatement();
        if ($this->match('DO')) return $this->doWhileStatement();
        if ($this->match('FOR')) return $this->forStatement();
        if ($this->match('FOREACH')) return $this->foreachStatement();
        if ($this->match('REPEAT')) return $this->repeatStatement();
        if ($this->match('RETURN')) return $this->returnStatement();
        if ($this->match('BREAK')) { $l=$this->previous()->line; $this->consume(';','Expected ; after break'); return new Node('break', [], Type::VOID, $l); }
        if ($this->match('CONTINUE')) { $l=$this->previous()->line; $this->consume(';','Expected ; after continue'); return new Node('continue', [], Type::VOID, $l); }
        if ($this->match('THROW')) { $l=$this->previous()->line; $e=$this->expression(); $this->consume(';','Expected ; after throw'); return new Node('throw',['expr'=>$e],Type::VOID,$l); }
        if ($this->match('TRY')) return $this->tryStatement();
        if ($this->match('CONST')) return $this->varDecl(true, Type::ANY, $this->previous()->line);
        if ($this->isTypeToken($this->peek())) { $tok=$this->advance(); return $this->varDecl(false, Type::canonical(strtolower($tok->lexeme)), $tok->line); }
        $line = $this->peek()->line;
        $e = $this->expression();
        $this->consume(';','Expected ; after expression');
        return new Node('expr',['expr'=>$e],$e->type,$line);
    }

    private function blockStatement(): Node
    {
        $open = $this->consume('{','Expected {');
        return $this->finishBlock($open->line);
    }

    private function finishBlock(int $line): Node
    {
        $items=[];
        while (!$this->check('}') && !$this->check('EOF')) $items[]=$this->statement();
        $this->consume('}','Expected } after block');
        return new Node('block',['statements'=>$items],Type::VOID,$line);
    }

    private function ifStatement(): Node
    {
        $line=$this->previous()->line;
        $this->consume('(','Expected ( after if'); $cond=$this->expression(); $this->consume(')','Expected ) after condition');
        $then=$this->statement(); $else=null;
        if ($this->match('ELSEIF')) {
            $else=$this->ifStatementFromElseIf();
        } elseif ($this->match('ELSE')) $else=$this->statement();
        return new Node('if',['cond'=>$cond,'then'=>$then,'else'=>$else],Type::VOID,$line);
    }

    private function ifStatementFromElseIf(): Node
    {
        $line=$this->previous()->line;
        $this->consume('(','Expected ( after elseif'); $cond=$this->expression(); $this->consume(')','Expected )');
        $then=$this->statement(); $else=null;
        if ($this->match('ELSEIF')) $else=$this->ifStatementFromElseIf();
        elseif ($this->match('ELSE')) $else=$this->statement();
        return new Node('if',['cond'=>$cond,'then'=>$then,'else'=>$else],Type::VOID,$line);
    }

    private function whileStatement(): Node
    {
        $line=$this->previous()->line; $this->consume('(','Expected ( after while'); $cond=$this->expression(); $this->consume(')','Expected )');
        return new Node('while',['cond'=>$cond,'body'=>$this->statement()],Type::VOID,$line);
    }

    private function doWhileStatement(): Node
    {
        $line=$this->previous()->line; $body=$this->statement(); $this->consume('WHILE','Expected while after do body');
        $this->consume('(','Expected ('); $cond=$this->expression(); $this->consume(')','Expected )'); $this->consume(';','Expected ;');
        return new Node('do_while',['cond'=>$cond,'body'=>$body],Type::VOID,$line);
    }

    private function forStatement(): Node
    {
        $line=$this->previous()->line; $this->consume('(','Expected ( after for');
        $init=null; $cond=null; $step=null;
        if (!$this->check(';')) $init=$this->expression(); $this->consume(';','Expected ; in for');
        if (!$this->check(';')) $cond=$this->expression(); $this->consume(';','Expected ; in for');
        if (!$this->check(')')) $step=$this->expression(); $this->consume(')','Expected ) after for');
        return new Node('for',['init'=>$init,'cond'=>$cond,'step'=>$step,'body'=>$this->statement()],Type::VOID,$line);
    }

    private function foreachStatement(): Node
    {
        $line=$this->previous()->line; $this->consume('(','Expected ( after foreach');
        $iter=$this->expression(); $this->consume('AS','Expected as in foreach');
        $first=$this->consumeVarOrName('Expected foreach variable'); $key=null; $value=$this->varName($first);
        if ($this->match('=>')) { $key=$value; $value=$this->varName($this->consumeVarOrName('Expected foreach value variable')); }
        $this->consume(')','Expected ) after foreach');
        return new Node('foreach',['iter'=>$iter,'key'=>$key,'value'=>$value,'body'=>$this->statement()],Type::VOID,$line);
    }

    private function repeatStatement(): Node
    {
        $line=$this->previous()->line; $this->consume('(','Expected ( after repeat'); $count=$this->expression(); $this->consume(')','Expected )');
        return new Node('repeat',['count'=>$count,'body'=>$this->statement()],Type::VOID,$line);
    }

    private function returnStatement(): Node
    {
        $line=$this->previous()->line; $expr=$this->check(';') ? null : $this->expression(); $this->consume(';','Expected ; after return');
        return new Node('return',['expr'=>$expr],$expr?->type ?? Type::VOID,$line);
    }

    private function tryStatement(): Node
    {
        $line=$this->previous()->line; $body=$this->statement(); $catch=null; $finally=null;
        if ($this->match('CATCH')) {
            $this->consume('(','Expected ( after catch');
            $type=Type::ANY; if ($this->isTypeToken($this->peek()) || $this->check('IDENT')) $type=$this->advance()->lexeme;
            $var=$this->check(')') ? null : $this->varName($this->consumeVarOrName('Expected catch variable'));
            $this->consume(')','Expected ) after catch');
            $catch=['type'=>$type,'var'=>$var,'body'=>$this->statement()];
        }
        if ($this->match('FINALLY')) $finally=$this->statement();
        if ($catch===null && $finally===null) throw new SemanticException('try requires catch or finally','parse',$line);
        return new Node('try',['body'=>$body,'catch'=>$catch,'finally'=>$finally],Type::VOID,$line);
    }

    private function varDecl(bool $const, string $type, int $line): Node
    {
        $name=$this->consumeVarOrName('Expected variable name'); $init=null;
        if ($this->match('=')) $init=$this->expression();
        $this->consume(';','Expected ; after declaration');
        return new Node('decl',['name'=>$this->varName($name),'const'=>$const,'init'=>$init],$type,$line);
    }

    private function expression(int $minPrec = 0): Node
    {
        $left=$this->prefix();
        while (true) {
            $tok=$this->peek(); $prec=$this->precedence($tok->kind);
            if ($prec < $minPrec) break;
            $op=$this->advance();
            if (in_array($op->kind,['=','+=','-=','*=','/=','%='],true)) {
                $right=$this->expression($prec);
                $left=new Node('assign',['target'=>$left,'operator'=>$op->kind,'value'=>$right],$right->type,$op->line); continue;
            }
            $right=$this->expression($prec+1);
            $type=in_array($op->kind,['==','===','!=','!==','<','<=','>','>=','&&','||'],true)?Type::BOOL:$left->type;
            $left=new Node('binary',['operator'=>$op->kind,'left'=>$left,'right'=>$right],$type,$op->line);
        }
        return $left;
    }

    private function prefix(): Node
    {
        $tok=$this->advance();
        $node=match ($tok->kind) {
            'INT_LIT'=>new Node('literal',['value'=>$tok->literal],Type::INT,$tok->line),
            'FLOAT_LIT'=>new Node('literal',['value'=>$tok->literal],Type::FLOAT,$tok->line),
            'STRING_LIT'=>new Node('literal',['value'=>$tok->literal],Type::STRING,$tok->line),
            'TRUE'=>new Node('literal',['value'=>true],Type::BOOL,$tok->line),
            'FALSE'=>new Node('literal',['value'=>false],Type::BOOL,$tok->line),
            'NULL'=>new Node('literal',['value'=>null],Type::NULL,$tok->line),
            'VAR','IDENT'=>new Node('var',['name'=>$this->varName($tok)],Type::ANY,$tok->line),
            '('=>$this->group($tok->line),
            '['=>$this->listLiteral($tok->line),
            'NEW'=>$this->newExpression($tok->line),
            '!','~','-','+','++','--'=>new Node('unary',['operator'=>$tok->kind,'expr'=>$this->expression(90)],Type::ANY,$tok->line),
            default=>throw new SemanticException("Expected expression, got {$tok->lexeme}",'parse',$tok->line),
        };
        while (true) {
            if ($this->match('(')) {
                $args=$this->argumentList();
                $node=new Node('call',['callee'=>$node,'args'=>$args],Type::ANY,$node->line); continue;
            }
            if ($this->match('.','->')) {
                $name=$this->consumeName('Expected member name');
                $node=new Node('member',['object'=>$node,'name'=>$name->lexeme],Type::ANY,$name->line); continue;
            }
            if ($this->match('[')) {
                $idx=$this->expression(); $this->consume(']','Expected ]');
                $node=new Node('index',['object'=>$node,'index'=>$idx],Type::ANY,$node->line); continue;
            }
            if ($this->match('++','--')) {
                $op=$this->previous(); $node=new Node('postfix',['operator'=>$op->kind,'expr'=>$node],$node->type,$op->line); continue;
            }
            break;
        }
        return $node;
    }

    private function group(int $line): Node { $e=$this->expression(); $this->consume(')','Expected )'); return new Node('group',['expr'=>$e],$e->type,$line); }

    private function listLiteral(int $line): Node
    {
        $items=[]; if (!$this->check(']')) { do { $items[]=$this->expression(); } while ($this->match(',')); }
        $this->consume(']','Expected ]'); return new Node('list',['items'=>$items],Type::LIST,$line);
    }

    private function newExpression(int $line): Node
    {
        $name=$this->consumeName('Expected class name after new'); $args=[];
        if ($this->match('(')) $args=$this->argumentList();
        return new Node('new',['class'=>$name->lexeme,'args'=>$args],$name->lexeme,$line);
    }

    /** @return list<Node> */
    private function argumentList(): array
    {
        $args=[]; if (!$this->check(')')) { do { $args[]=$this->expression(); } while ($this->match(',')); }
        $this->consume(')','Expected ) after arguments'); return $args;
    }

    private function precedence(string $op): int
    {
        return match ($op) {
            '=','+=','-=','*=','/=','%='=>10,
            '||'=>20,'&&'=>25,'|'=>30,'^'=>35,'&'=>40,
            '==','===','!=','!=='=>50,'<','<=','>','>='=>55,
            '<<','>>'=>60,'+','-'=>70,'*','/','%'=>80,
            default=>-1,
        };
    }

    private function isTypeToken(Token $t): bool { return in_array($t->kind,['INT','FLOAT','BOOL','STRING','COMPLEX','BAG','LIST','MAP','ANY','VOID'],true); }
    private function varName(Token $t): string { return strtolower((string)$t->literal); }
    private function consumeVarOrName(string $msg): Token { if ($this->check('VAR')||$this->check('IDENT')) return $this->advance(); throw new SemanticException($msg,'parse',$this->peek()->line); }
    private function consumeName(string $msg): Token { if ($this->check('IDENT') || $this->isTypeToken($this->peek())) return $this->advance(); throw new SemanticException($msg,'parse',$this->peek()->line); }
    private function match(string ...$kinds): bool { foreach($kinds as $k) if($this->check($k)){ $this->advance(); return true; } return false; }
    private function check(string $kind): bool { return $this->peek()->kind === $kind; }
    private function consume(string $kind,string $msg): Token { if($this->check($kind))return $this->advance(); throw new SemanticException($msg,'parse',$this->peek()->line); }
    private function advance(): Token { if(!$this->check('EOF'))$this->i++; return $this->t[$this->i-1]; }
    private function peek(): Token { return $this->t[$this->i]; }
    private function previous(): Token { return $this->t[$this->i-1]; }
}

final class Environment
{
    /** @var array<string,mixed> */ private array $values=[];
    /** @var array<string,string> */ private array $types=[];
    /** @var array<string,bool> */ private array $const=[];
    public function __construct(private ?self $parent=null) {}

    public function define(string $name,mixed $value,string $type=Type::ANY,bool $const=false): void
    {
        $name=strtolower($name); $type=Type::canonical($type);
        if($type!==Type::ANY && !Type::accepts($type,$value) && $value!==null) throw new SemanticException("{$name} expects {$type}",'type');
        $this->values[$name]=$value; $this->types[$name]=$type; $this->const[$name]=$const;
    }
    public function hasLocal(string $name): bool { return array_key_exists(strtolower($name),$this->values); }
    public function get(string $name): mixed
    {
        $name=strtolower($name); if(array_key_exists($name,$this->values))return $this->values[$name];
        if($this->parent)return $this->parent->get($name); throw new SemanticException("Undefined variable {$name}",'runtime');
    }
    public function set(string $name,mixed $value): mixed
    {
        $name=strtolower($name);
        if(array_key_exists($name,$this->values)){
            if($this->const[$name]??false)throw new SemanticException("Cannot assign const {$name}",'runtime');
            $type=$this->types[$name]??Type::ANY; if($type!==Type::ANY&&!Type::accepts($type,$value))throw new SemanticException("{$name} expects {$type}",'type');
            $this->values[$name]=$value; return $value;
        }
        if($this->parent)return $this->parent->set($name,$value);
        $this->values[$name]=$value; $this->types[$name]=Type::ANY; $this->const[$name]=false; return $value;
    }
    public function typeOf(string $name): string
    {
        $name=strtolower($name); if(array_key_exists($name,$this->types))return $this->types[$name];
        return $this->parent?->typeOf($name)??Type::ANY;
    }
}

final class ReturnSignal extends \RuntimeException { public function __construct(public readonly mixed $value){parent::__construct('return');} }
final class BreakSignal extends \RuntimeException {}
final class ContinueSignal extends \RuntimeException {}
final class UserThrow extends \RuntimeException { public function __construct(public readonly mixed $value){parent::__construct(is_scalar($value)?(string)$value:'JX throw');} }

final class ObjectValue
{
    /** @var array<string,mixed> */ public array $properties=[];
    public function __construct(public readonly Node $classNode) {}
}

final class Interpreter
{
    private Program $program;
    private Environment $globals;

    public function run(Program $program): mixed
    {
        $this->program=$program; $this->globals=new Environment();
        $this->installBuiltins($this->globals);
        $last=null; foreach($program->statements as $s)$last=$this->exec($s,$this->globals);
        return $last;
    }

    public function runSource(string $source): mixed { return $this->run((new Parser())->parse($source)); }

    private function installBuiltins(Environment $e): void
    {
        $e->define('len', fn(mixed $v):int=>is_countable($v)?count($v):strlen((string)$v));
        $e->define('range', function(int $a,int $b=null,int $step=1):array { if($b===null){$b=$a;$a=0;} return range($a,$b-($step>0?1:-1),$step); });
        $e->define('print', function(mixed ...$v):mixed { echo implode('',array_map(static fn($x)=>is_scalar($x)||(null===$x)?(string)$x:json_encode($x),$v)); return $v===[]?null:$v[array_key_last($v)]; });
    }

    private function exec(Node $n,Environment $e): mixed
    {
        return match($n->op){
            'block'=>$this->execBlock($n,$e),'expr'=>$this->eval($n->data['expr'],$e),'decl'=>$this->execDecl($n,$e),
            'if'=>$this->execIf($n,$e),'while'=>$this->execWhile($n,$e),'do_while'=>$this->execDoWhile($n,$e),
            'for'=>$this->execFor($n,$e),'foreach'=>$this->execForeach($n,$e),'repeat'=>$this->execRepeat($n,$e),
            'return'=>throw new ReturnSignal($n->data['expr']? $this->eval($n->data['expr'],$e):null),
            'break'=>throw new BreakSignal(),'continue'=>throw new ContinueSignal(),
            'throw'=>throw new UserThrow($this->eval($n->data['expr'],$e)),'try'=>$this->execTry($n,$e),
            default=>throw new SemanticException("Cannot execute statement {$n->op}",'runtime',$n->line),
        };
    }

    private function execBlock(Node $n,Environment $parent): mixed
    {
        $e=new Environment($parent); $last=null; foreach($n->data['statements'] as $s)$last=$this->exec($s,$e); return $last;
    }
    private function execDecl(Node $n,Environment $e): mixed
    {
        $v=$n->data['init']?$this->eval($n->data['init'],$e):null; $e->define($n->data['name'],$v,$n->type,$n->data['const']); return $v;
    }
    private function execIf(Node $n,Environment $e): mixed { if($this->truth($this->eval($n->data['cond'],$e)))return $this->exec($n->data['then'],$e); return $n->data['else']?$this->exec($n->data['else'],$e):null; }
    private function execWhile(Node $n,Environment $e): mixed
    {
        $last=null; while($this->truth($this->eval($n->data['cond'],$e))){try{$last=$this->exec($n->data['body'],$e);}catch(ContinueSignal){}catch(BreakSignal){break;}} return $last;
    }
    private function execDoWhile(Node $n,Environment $e): mixed
    {
        $last=null; do{try{$last=$this->exec($n->data['body'],$e);}catch(ContinueSignal){}catch(BreakSignal){break;}}while($this->truth($this->eval($n->data['cond'],$e))); return $last;
    }
    private function execFor(Node $n,Environment $e): mixed
    {
        $last=null; if($n->data['init'])$this->eval($n->data['init'],$e);
        while($n->data['cond']===null||$this->truth($this->eval($n->data['cond'],$e))){
            try{$last=$this->exec($n->data['body'],$e);}catch(ContinueSignal){}catch(BreakSignal){break;}
            if($n->data['step'])$this->eval($n->data['step'],$e);
        } return $last;
    }
    private function execForeach(Node $n,Environment $e): mixed
    {
        $it=$this->eval($n->data['iter'],$e); if(!is_iterable($it))throw new SemanticException('foreach value is not iterable','runtime',$n->line);
        $last=null; foreach($it as $k=>$v){
            if($n->data['key']!==null){if($e->hasLocal($n->data['key']))$e->set($n->data['key'],$k);else $e->define($n->data['key'],$k);}
            if($e->hasLocal($n->data['value']))$e->set($n->data['value'],$v);else $e->define($n->data['value'],$v);
            try{$last=$this->exec($n->data['body'],$e);}catch(ContinueSignal){continue;}catch(BreakSignal){break;}
        } return $last;
    }
    private function execRepeat(Node $n,Environment $e): mixed
    {
        $count=(int)$this->eval($n->data['count'],$e); if($count<0)throw new SemanticException('repeat count must be non-negative','runtime',$n->line);
        $last=null; for($i=0;$i<$count;$i++){try{$last=$this->exec($n->data['body'],$e);}catch(ContinueSignal){continue;}catch(BreakSignal){break;}} return $last;
    }
    private function execTry(Node $n,Environment $e): mixed
    {
        $result=null;
        try{$result=$this->exec($n->data['body'],$e);}catch(UserThrow $x){
            $c=$n->data['catch']; if($c===null)throw $x; $ce=new Environment($e); if($c['var'])$ce->define($c['var'],$x->value,$c['type']); $result=$this->exec($c['body'],$ce);
        } finally { if($n->data['finally'])$this->exec($n->data['finally'],$e); }
        return $result;
    }

    private function eval(Node $n,Environment $e): mixed
    {
        return match($n->op){
            'literal'=>$n->data['value'],'group'=>$this->eval($n->data['expr'],$e),'var'=>$e->get($n->data['name']),
            'list'=>array_map(fn(Node $x)=>$this->eval($x,$e),$n->data['items']),
            'unary'=>$this->evalUnary($n,$e),'postfix'=>$this->evalPostfix($n,$e),'binary'=>$this->evalBinary($n,$e),
            'assign'=>$this->evalAssign($n,$e),'call'=>$this->evalCall($n,$e),'member'=>$this->evalMember($n,$e),'index'=>$this->evalIndex($n,$e),
            'new'=>$this->evalNew($n,$e),
            default=>throw new SemanticException("Cannot evaluate {$n->op}",'runtime',$n->line),
        };
    }

    private function evalUnary(Node $n,Environment $e): mixed
    {
        $op=$n->data['operator']; $x=$n->data['expr'];
        if(($op==='++'||$op==='--')&&$x->op==='var'){ $v=$e->get($x->data['name'])+($op==='++'?1:-1); return $e->set($x->data['name'],$v); }
        $v=$this->eval($x,$e); return match($op){'!'=>!$this->truth($v),'~'=>~(int)$v,'-' => -$v,'+'=>+$v,default=>$v};
    }
    private function evalPostfix(Node $n,Environment $e): mixed
    {
        $x=$n->data['expr']; if($x->op!=='var')throw new SemanticException('postfix target must be variable','runtime',$n->line);
        $old=$e->get($x->data['name']); $e->set($x->data['name'],$old+($n->data['operator']==='++'?1:-1)); return $old;
    }
    private function evalBinary(Node $n,Environment $e): mixed
    {
        $op=$n->data['operator'];
        if($op==='&&'){ $l=$this->eval($n->data['left'],$e); return $this->truth($l)&&$this->truth($this->eval($n->data['right'],$e)); }
        if($op==='||'){ $l=$this->eval($n->data['left'],$e); return $this->truth($l)||$this->truth($this->eval($n->data['right'],$e)); }
        $a=$this->eval($n->data['left'],$e); $b=$this->eval($n->data['right'],$e);
        return match($op){
            '+'=>$a+$b,'-'=>$a-$b,'*'=>$a*$b,'/'=>$a/$b,'%'=>$a%$b,
            '<<'=>(int)$a<<(int)$b,'>>'=>(int)$a>>(int)$b,'&'=>(int)$a&(int)$b,'|'=>(int)$a|(int)$b,'^'=>(int)$a^(int)$b,
            '=='=>$a==$b,'==='=>$a===$b,'!='=>$a!=$b,'!=='=>$a!==$b,'<'=>$a<$b,'<='=>$a<=$b,'>'=>$a>$b,'>='=>$a>=$b,
            default=>throw new SemanticException("Unsupported binary {$op}",'runtime',$n->line),
        };
    }
    private function evalAssign(Node $n,Environment $e): mixed
    {
        $target=$n->data['target']; $rhs=$this->eval($n->data['value'],$e); $op=$n->data['operator'];
        if($target->op==='var'){
            $name=$target->data['name'];
            if($op!=='='){ $old=$e->get($name); $rhs=match($op){'+='=>$old+$rhs,'-='=>$old-$rhs,'*='=>$old*$rhs,'/='=>$old/$rhs,'%='=>$old%$rhs}; }
            return $e->set($name,$rhs);
        }
        if($target->op==='member'){
            $obj=$this->eval($target->data['object'],$e); if(!$obj instanceof ObjectValue)throw new SemanticException('member assignment requires object','runtime',$n->line);
            $name=strtolower($target->data['name']); $old=$obj->properties[$name]??null;
            $obj->properties[$name]=$op==='='?$rhs:match($op){'+='=>$old+$rhs,'-='=>$old-$rhs,'*='=>$old*$rhs,'/='=>$old/$rhs,'%='=>$old%$rhs}; return $obj->properties[$name];
        }
        throw new SemanticException('Invalid assignment target','runtime',$n->line);
    }
    private function evalCall(Node $n,Environment $e): mixed
    {
        $callee=$n->data['callee']; $args=array_map(fn(Node $a)=>$this->eval($a,$e),$n->data['args']);
        if($callee->op==='var'){
            $name=strtolower($callee->data['name']);
            if(isset($this->program->functions[$name]))return $this->callFunction($this->program->functions[$name],$args,$this->globals,null);
            $fn=$e->get($name); if(is_callable($fn))return $fn(...$args);
            throw new SemanticException("{$name} is not callable",'runtime',$n->line);
        }
        if($callee->op==='member'){
            $obj=$this->eval($callee->data['object'],$e); if(!$obj instanceof ObjectValue)throw new SemanticException('method call requires object','runtime',$n->line);
            $name=strtolower($callee->data['name']); $methods=$obj->classNode->data['methods']; if(!isset($methods[$name]))throw new SemanticException("Unknown method {$name}",'runtime',$n->line);
            return $this->callFunction($methods[$name],$args,$this->globals,$obj);
        }
        $fn=$this->eval($callee,$e); if(!is_callable($fn))throw new SemanticException('Value is not callable','runtime',$n->line); return $fn(...$args);
    }
    private function callFunction(Node $fn,array $args,Environment $parent,?ObjectValue $thisObj): mixed
    {
        $params=$fn->data['params']; if(count($args)!==count($params))throw new SemanticException("{$fn->data['name']} expects ".count($params).' args','runtime',$fn->line);
        $e=new Environment($parent); if($thisObj)$e->define('this',$thisObj,$thisObj->classNode->data['name']);
        foreach($params as $i=>$p)$e->define($p['name'],$args[$i],$p['type']);
        try{$result=$this->exec($fn->data['body'],$e);}catch(ReturnSignal $r){$result=$r->value;}
        $ret=$fn->data['return']; if($ret!==Type::ANY&&$ret!==Type::VOID&&!Type::accepts($ret,$result))throw new SemanticException("{$fn->data['name']} must return {$ret}",'type',$fn->line);
        return $result;
    }
    private function evalNew(Node $n,Environment $e): ObjectValue
    {
        $name=strtolower($n->data['class']); $cl=$this->program->classes[$name]??null; if(!$cl)throw new SemanticException("Unknown class {$name}",'runtime',$n->line);
        $o=new ObjectValue($cl);
        foreach($cl->data['properties'] as $p=>$spec)$o->properties[strtolower($p)]=$spec['init']?$this->eval($spec['init'],$e):null;
        $ctor=$cl->data['methods']['constructor']??$cl->data['methods']['__construct']??null;
        if($ctor){$args=array_map(fn(Node $a)=>$this->eval($a,$e),$n->data['args']);$this->callFunction($ctor,$args,$this->globals,$o);} elseif($n->data['args']!==[]) throw new SemanticException("{$cl->data['name']} has no constructor",'runtime',$n->line);
        return $o;
    }
    private function evalMember(Node $n,Environment $e): mixed
    {
        $obj=$this->eval($n->data['object'],$e); if($obj instanceof ObjectValue){$name=strtolower($n->data['name']); if(array_key_exists($name,$obj->properties))return $obj->properties[$name]; return ['__method__'=>$name,'__object__'=>$obj];}
        if(is_array($obj))return $obj[$n->data['name']]??null; if(is_object($obj)&&isset($obj->{$n->data['name']}))return $obj->{$n->data['name']};
        throw new SemanticException('Cannot read member','runtime',$n->line);
    }
    private function evalIndex(Node $n,Environment $e): mixed { $o=$this->eval($n->data['object'],$e); $i=$this->eval($n->data['index'],$e); return $o[$i]??null; }
    private function truth(mixed $v): bool { return (bool)$v; }
}

final class JxlOp
{
    public const NOP=0x00, ICONST=0x01, LOAD=0x02, STORE=0x03, POP=0x04;
    public const ADD=0x05, SUB=0x06, MUL=0x07, DIV=0x08, MOD=0x09;
    public const EQ=0x0A, NE=0x0B, LT=0x0C, LE=0x0D, GT=0x0E, GE=0x0F;
    public const BAND=0x10, BOR=0x11, BXOR=0x12, SHL=0x13, SHR=0x14, NEG=0x15, NOT=0x16;
    public const JMP=0x17, JZ=0x18, CALL=0x19, RET=0x1A, HALT=0x1B;
}

final class JxlEmitter
{
    private string $code='';
    /** @var array<string,int> */ private array $slots=[];
    /** @var array<string,int> */ private array $functionIds=[];
    /** @var array<int,int> */ private array $functionOffsets=[];
    /** @var list<array{pos:int,id:int}> */ private array $callPatches=[];
    /** @var list<array{breaks:list<int>,continues:list<int>,continueTarget:int}> */ private array $loops=[];

    public function emit(Program $p): string
    {
        $this->code='';$this->slots=[];$this->functionIds=[];$this->functionOffsets=[];$this->callPatches=[];$this->loops=[];
        $id=0; foreach($p->functions as $name=>$fn)$this->functionIds[$name]=$id++;
        foreach($p->statements as $s)$this->stmt($s);
        $this->op(JxlOp::HALT);
        foreach($p->functions as $name=>$fn){
            $fid=$this->functionIds[$name];$this->functionOffsets[$fid]=strlen($this->code);
            $oldSlots=$this->slots;$this->slots=[];
            foreach($fn->data['params'] as $idx=>$param)$this->slots[$param['name']]=$idx;
            $this->stmt($fn->data['body']);$this->op(JxlOp::ICONST);$this->int(0);$this->op(JxlOp::RET);
            $this->slots=$oldSlots;
        }
        foreach($this->callPatches as $p2){$off=$this->functionOffsets[$p2['id']]??null;if($off===null)throw new SemanticException('Unresolved JXL function target','jxl');$this->patchFixed($p2['pos'],$off);}
        return $this->code;
    }

    private function stmt(Node $n): void
    {
        switch($n->op){
            case 'block': foreach($n->data['statements'] as $s)$this->stmt($s); return;
            case 'expr': $this->expr($n->data['expr']);$this->op(JxlOp::POP);return;
            case 'decl': if($n->data['init'])$this->expr($n->data['init']);else{$this->op(JxlOp::ICONST);$this->int(0);} $this->op(JxlOp::STORE);$this->int($this->slot($n->data['name']));return;
            case 'if': $this->expr($n->data['cond']);$this->op(JxlOp::JZ);$jz=$this->fixedPlaceholder();$this->stmt($n->data['then']); if($n->data['else']){$this->op(JxlOp::JMP);$je=$this->fixedPlaceholder();$this->patchFixed($jz,strlen($this->code));$this->stmt($n->data['else']);$this->patchFixed($je,strlen($this->code));}else $this->patchFixed($jz,strlen($this->code));return;
            case 'while': $head=strlen($this->code);$this->expr($n->data['cond']);$this->op(JxlOp::JZ);$exit=$this->fixedPlaceholder();$this->loops[]=['breaks'=>[],'continues'=>[],'continueTarget'=>$head];$idx=array_key_last($this->loops);$this->stmt($n->data['body']);$this->patchLoopContinues($idx,$head);$this->op(JxlOp::JMP);$this->fixed($head);$end=strlen($this->code);$this->patchFixed($exit,$end);$this->patchLoopBreaks($idx,$end);array_pop($this->loops);return;
            case 'do_while': $head=strlen($this->code);$this->loops[]=['breaks'=>[],'continues'=>[],'continueTarget'=>0];$idx=array_key_last($this->loops);$this->stmt($n->data['body']);$condPos=strlen($this->code);$this->loops[$idx]['continueTarget']=$condPos;$this->patchLoopContinues($idx,$condPos);$this->expr($n->data['cond']);$this->op(JxlOp::JZ);$exit=$this->fixedPlaceholder();$this->op(JxlOp::JMP);$this->fixed($head);$end=strlen($this->code);$this->patchFixed($exit,$end);$this->patchLoopBreaks($idx,$end);array_pop($this->loops);return;
            case 'for': if($n->data['init']){$this->expr($n->data['init']);$this->op(JxlOp::POP);} $head=strlen($this->code);if($n->data['cond'])$this->expr($n->data['cond']);else{$this->op(JxlOp::ICONST);$this->int(1);} $this->op(JxlOp::JZ);$exit=$this->fixedPlaceholder();$this->loops[]=['breaks'=>[],'continues'=>[],'continueTarget'=>0];$idx=array_key_last($this->loops);$this->stmt($n->data['body']);$stepPos=strlen($this->code);$this->loops[$idx]['continueTarget']=$stepPos;$this->patchLoopContinues($idx,$stepPos);if($n->data['step']){$this->expr($n->data['step']);$this->op(JxlOp::POP);} $this->op(JxlOp::JMP);$this->fixed($head);$end=strlen($this->code);$this->patchFixed($exit,$end);$this->patchLoopBreaks($idx,$end);array_pop($this->loops);return;
            case 'repeat': if($n->data['count']->op!=='literal'||!is_int($n->data['count']->data['value']))throw new SemanticException('JXL repeat currently requires constant integer count','jxl',$n->line);$tmp='__repeat_'.strlen($this->slots);$this->op(JxlOp::ICONST);$this->int($n->data['count']->data['value']);$this->op(JxlOp::STORE);$this->int($this->slot($tmp));$head=strlen($this->code);$this->op(JxlOp::LOAD);$this->int($this->slot($tmp));$this->op(JxlOp::JZ);$exit=$this->fixedPlaceholder();$this->loops[]=['breaks'=>[],'continues'=>[],'continueTarget'=>0];$idx=array_key_last($this->loops);$this->stmt($n->data['body']);$stepPos=strlen($this->code);$this->loops[$idx]['continueTarget']=$stepPos;$this->patchLoopContinues($idx,$stepPos);$this->op(JxlOp::LOAD);$this->int($this->slot($tmp));$this->op(JxlOp::ICONST);$this->int(1);$this->op(JxlOp::SUB);$this->op(JxlOp::STORE);$this->int($this->slot($tmp));$this->op(JxlOp::JMP);$this->fixed($head);$end=strlen($this->code);$this->patchFixed($exit,$end);$this->patchLoopBreaks($idx,$end);array_pop($this->loops);return;
            case 'break': if($this->loops===[])throw new SemanticException('break outside loop','jxl',$n->line);$this->op(JxlOp::JMP);$pos=$this->fixedPlaceholder();$idx=array_key_last($this->loops);$this->loops[$idx]['breaks'][]=$pos;return;
            case 'continue': if($this->loops===[])throw new SemanticException('continue outside loop','jxl',$n->line);$this->op(JxlOp::JMP);$pos=$this->fixedPlaceholder();$idx=array_key_last($this->loops);$target=$this->loops[$idx]['continueTarget'];if($target>0)$this->patchFixed($pos,$target);else$this->loops[$idx]['continues'][]=$pos;return;
            case 'return': if($n->data['expr'])$this->expr($n->data['expr']);else{$this->op(JxlOp::ICONST);$this->int(0);}$this->op(JxlOp::RET);return;
            default: throw new SemanticException("JXL emitter does not yet lower {$n->op}",'jxl',$n->line);
        }
    }

    private function expr(Node $n): void
    {
        switch($n->op){
            case 'literal': if(!is_int($n->data['value'])&&!is_bool($n->data['value'])&&$n->data['value']!==null)throw new SemanticException('JXL numeric core accepts int/bool/null literals','jxl',$n->line);$this->op(JxlOp::ICONST);$this->int((int)($n->data['value']??0));return;
            case 'group':$this->expr($n->data['expr']);return;
            case 'var':$this->op(JxlOp::LOAD);$this->int($this->slot($n->data['name']));return;
            case 'unary':$this->expr($n->data['expr']);if($n->data['operator']==='-')$this->op(JxlOp::NEG);elseif($n->data['operator']==='!')$this->op(JxlOp::NOT);else throw new SemanticException('Unsupported JXL unary','jxl',$n->line);return;
            case 'postfix': if($n->data['expr']->op!=='var')throw new SemanticException('JXL postfix requires variable','jxl',$n->line);$slot=$this->slot($n->data['expr']->data['name']);$this->op(JxlOp::LOAD);$this->int($slot);$this->op(JxlOp::LOAD);$this->int($slot);$this->op(JxlOp::ICONST);$this->int(1);$this->op($n->data['operator']==='++'?JxlOp::ADD:JxlOp::SUB);$this->op(JxlOp::STORE);$this->int($slot);return;
            case 'assign': if($n->data['target']->op!=='var')throw new SemanticException('JXL assignment target must be variable','jxl',$n->line);$slot=$this->slot($n->data['target']->data['name']);$op=$n->data['operator'];if($op==='='){$this->expr($n->data['value']);$this->op(JxlOp::STORE);$this->int($slot);$this->op(JxlOp::LOAD);$this->int($slot);return;}$this->op(JxlOp::LOAD);$this->int($slot);$this->expr($n->data['value']);$this->op(match($op){'+='=>JxlOp::ADD,'-='=>JxlOp::SUB,'*='=>JxlOp::MUL,'/='=>JxlOp::DIV,'%='=>JxlOp::MOD,default=>throw new SemanticException('Unsupported JXL assignment','jxl',$n->line)});$this->op(JxlOp::STORE);$this->int($slot);$this->op(JxlOp::LOAD);$this->int($slot);return;
            case 'binary':$this->expr($n->data['left']);$this->expr($n->data['right']);$this->op(match($n->data['operator']){'+'=>JxlOp::ADD,'-'=>JxlOp::SUB,'*'=>JxlOp::MUL,'/'=>JxlOp::DIV,'%'=>JxlOp::MOD,'==','==='=>JxlOp::EQ,'!=','!=='=>JxlOp::NE,'<'=>JxlOp::LT,'<='=>JxlOp::LE,'>'=>JxlOp::GT,'>='=>JxlOp::GE,'&'=>JxlOp::BAND,'|'=>JxlOp::BOR,'^'=>JxlOp::BXOR,'<<'=>JxlOp::SHL,'>>'=>JxlOp::SHR,default=>throw new SemanticException('Unsupported JXL binary '.$n->data['operator'],'jxl',$n->line)});return;
            case 'call': if($n->data['callee']->op!=='var')throw new SemanticException('JXL call requires direct function name','jxl',$n->line);$name=strtolower($n->data['callee']->data['name']);if(!isset($this->functionIds[$name]))throw new SemanticException("Unknown JXL function {$name}",'jxl',$n->line);foreach($n->data['args'] as $a)$this->expr($a);$this->op(JxlOp::CALL);$this->int(count($n->data['args']));$this->int($this->functionIds[$name]);$pos=$this->fixedPlaceholder();$this->callPatches[]=['pos'=>$pos,'id'=>$this->functionIds[$name]];return;
            default:throw new SemanticException("JXL expression {$n->op} unsupported",'jxl',$n->line);
        }
    }

    private function op(int $op): void { if($op<0||$op>0x7f)throw new SemanticException('JXL opcode must have high bit clear','jxl');$this->code.=chr($op); }
    private function int(int $v): void { $z=$v<0?((-$v)<<1)-1:$v<<1; do{$this->code.=chr(0x80|($z&0x7f));$z>>=7;}while($z>0);$this->code.=chr(0x80); }
    private function slot(string $name): int { $name=strtolower($name); if(!array_key_exists($name,$this->slots))$this->slots[$name]=count($this->slots); return $this->slots[$name]; }
    private function fixed(int $v): void { for($i=0;$i<5;$i++){$this->code.=chr(0x80|($v&0x7f));$v>>=7;} }
    private function fixedPlaceholder(): int { $p=strlen($this->code);$this->fixed(0);return $p; }
    private function patchFixed(int $pos,int $v): void { for($i=0;$i<5;$i++){$this->code[$pos+$i]=chr(0x80|($v&0x7f));$v>>=7;} }
    private function patchLoopBreaks(int $idx,int $target): void { foreach($this->loops[$idx]['breaks'] as $p)$this->patchFixed($p,$target); }
    private function patchLoopContinues(int $idx,int $target): void { foreach($this->loops[$idx]['continues'] as $p)$this->patchFixed($p,$target);$this->loops[$idx]['continues']=[]; }
}

final class JxlVm
{
    /** @var list<int> */ private array $stack=[];
    /** @var list<array{ip:int,locals:array<int,int>}> */ private array $frames=[];
    /** @var array<int,int> */ private array $locals=[];
    private int $ip=0;

    public function run(string $code): int
    {
        $this->stack=[];$this->frames=[];$this->locals=[];$this->ip=0;
        $n=strlen($code);$last=0;
        while($this->ip<$n){
            $op=ord($code[$this->ip++]); if($op&0x80)throw new SemanticException('JXL attachment encountered as opcode','jxl-runtime');
            switch($op){
                case JxlOp::NOP:break;
                case JxlOp::ICONST:$this->stack[]=$this->readInt($code);break;
                case JxlOp::LOAD:$this->stack[]=$this->locals[$this->readInt($code)]??0;break;
                case JxlOp::STORE:$slot=$this->readInt($code);$v=array_pop($this->stack)??0;$this->locals[$slot]=$v;$last=$v;break;
                case JxlOp::POP:$last=array_pop($this->stack)??$last;break;
                case JxlOp::ADD:$this->bin(fn($a,$b)=>$a+$b);break;case JxlOp::SUB:$this->bin(fn($a,$b)=>$a-$b);break;case JxlOp::MUL:$this->bin(fn($a,$b)=>$a*$b);break;case JxlOp::DIV:$this->bin(fn($a,$b)=>intdiv($a,$b));break;case JxlOp::MOD:$this->bin(fn($a,$b)=>$a%$b);break;
                case JxlOp::EQ:$this->bin(fn($a,$b)=>(int)($a===$b));break;case JxlOp::NE:$this->bin(fn($a,$b)=>(int)($a!==$b));break;case JxlOp::LT:$this->bin(fn($a,$b)=>(int)($a<$b));break;case JxlOp::LE:$this->bin(fn($a,$b)=>(int)($a<=$b));break;case JxlOp::GT:$this->bin(fn($a,$b)=>(int)($a>$b));break;case JxlOp::GE:$this->bin(fn($a,$b)=>(int)($a>=$b));break;
                case JxlOp::BAND:$this->bin(fn($a,$b)=>$a&$b);break;case JxlOp::BOR:$this->bin(fn($a,$b)=>$a|$b);break;case JxlOp::BXOR:$this->bin(fn($a,$b)=>$a^$b);break;case JxlOp::SHL:$this->bin(fn($a,$b)=>$a<<$b);break;case JxlOp::SHR:$this->bin(fn($a,$b)=>$a>>$b);break;
                case JxlOp::NEG:$this->stack[]=-(array_pop($this->stack)??0);break;case JxlOp::NOT:$this->stack[]=(int)!(array_pop($this->stack)??0);break;
                case JxlOp::JMP:$this->ip=$this->readFixed($code);break;
                case JxlOp::JZ:$target=$this->readFixed($code);$v=array_pop($this->stack)??0;if(!$v)$this->ip=$target;break;
                case JxlOp::CALL:$argc=$this->readInt($code);$fid=$this->readInt($code);$target=$this->readFixed($code);$args=[];for($i=0;$i<$argc;$i++)array_unshift($args,array_pop($this->stack)??0);$this->frames[]=['ip'=>$this->ip,'locals'=>$this->locals];$this->locals=[];foreach($args as $i=>$v)$this->locals[$i]=$v;$this->ip=$target;break;
                case JxlOp::RET:$ret=array_pop($this->stack)??0;if($this->frames===[])return $ret;$f=array_pop($this->frames);$this->locals=$f['locals'];$this->ip=$f['ip'];$this->stack[]=$ret;$last=$ret;break;
                case JxlOp::HALT:return $last;
                default:throw new SemanticException(sprintf('Unknown JXL opcode 0x%02X',$op),'jxl-runtime');
            }
        }
        return $last;
    }

    private function bin(callable $f): void { $b=array_pop($this->stack)??0;$a=array_pop($this->stack)??0;$this->stack[]=(int)$f($a,$b); }
    private function readInt(string $code): int { $z=0;$shift=0;while(true){if($this->ip>=strlen($code))throw new SemanticException('Truncated JXL integer','jxl-runtime');$b=ord($code[$this->ip++]);if(($b&0x80)===0)throw new SemanticException('JXL integer byte is not attachment','jxl-runtime');$p=$b&0x7f;if($p===0&&$shift>0)break;$z|=$p<<$shift;$shift+=7;if($shift>63)throw new SemanticException('JXL integer too wide','jxl-runtime');}return ($z&1)?-(($z+1)>>1):($z>>1); }
    private function readFixed(string $code): int { $v=0;for($i=0;$i<5;$i++){if($this->ip>=strlen($code))throw new SemanticException('Truncated JXL address','jxl-runtime');$b=ord($code[$this->ip++]);if(($b&0x80)===0)throw new SemanticException('JXL address byte is not attachment','jxl-runtime');$v|=($b&0x7f)<<($i*7);}return $v; }
}

final class Compiler
{
    public function parse(string $source): Program { return (new Parser())->parse($source); }
    public function run(string $source): mixed { return (new Interpreter())->run($this->parse($source)); }
    public function emitJxl(string $source): string { return (new JxlEmitter())->emit($this->parse($source)); }
    public function runJxl(string $source): int { return (new JxlVm())->run($this->emitJxl($source)); }
}
