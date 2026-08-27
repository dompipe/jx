<?php declare(strict_types=1);

namespace pasm;

use InvalidArgumentException;

/**
 * Ordered register-command ABI.
 *
 * PASM has eight hot registers, therefore a register selector is exactly
 * three bits.  An opcode defines the operand arity and semantic order; the
 * following register payload is only the packed selector tuple.
 *
 * No register names, operand tags, or per-operand type bytes survive into
 * this command stream.
 */
final class PASMRegisterCommand
{
    public const REG_BITS = 3;
    public const REG_MASK = 0x07;

    /**
     * Canonical operand order.  This table is the execution contract.
     *
     * @var array<int,array{name:string,roles:list<string>}>
     */
    private const SPEC = [
        PASMBC::MOVR => ['name'=>'MOVR', 'roles'=>['dst','src']],
        PASMBC::ADD  => ['name'=>'ADD',  'roles'=>['dst','a','b']],
        PASMBC::SUB  => ['name'=>'SUB',  'roles'=>['dst','a','b']],
        PASMBC::MUL  => ['name'=>'MUL',  'roles'=>['dst','a','b']],
        PASMBC::DIV  => ['name'=>'DIV',  'roles'=>['dst','a','b']],
        PASMBC::MOD  => ['name'=>'MOD',  'roles'=>['dst','a','b']],
        PASMBC::AND  => ['name'=>'AND',  'roles'=>['dst','a','b']],
        PASMBC::OR   => ['name'=>'OR',   'roles'=>['dst','a','b']],
        PASMBC::XOR  => ['name'=>'XOR',  'roles'=>['dst','a','b']],
        PASMBC::SHL  => ['name'=>'SHL',  'roles'=>['dst','a','b']],
        PASMBC::SHR  => ['name'=>'SHR',  'roles'=>['dst','a','b']],
        PASMBC::CMP  => ['name'=>'CMP',  'roles'=>['a','b']],
        PASMBC::PUSH => ['name'=>'PUSH', 'roles'=>['src']],
        PASMBC::POP  => ['name'=>'POP',  'roles'=>['dst']],
        PASMBC::INC  => ['name'=>'INC',  'roles'=>['dst']],
        PASMBC::DEC  => ['name'=>'DEC',  'roles'=>['dst']],
        PASMBC::NEG  => ['name'=>'NEG',  'roles'=>['dst']],
        PASMBC::RET  => ['name'=>'RET',  'roles'=>['src']],
    ];

    public static function arity(int $opcode): int
    {
        return count(self::spec($opcode)['roles']);
    }

    /** @return array{name:string,roles:list<string>} */
    public static function spec(int $opcode): array
    {
        return self::SPEC[$opcode]
            ?? throw new InvalidArgumentException(sprintf('Opcode 0x%02X has no register-tuple spec', $opcode));
    }

    /** Number of payload bytes required by the ordered register tuple. */
    public static function payloadBytes(int $opcode): int
    {
        return intdiv(self::arity($opcode) * self::REG_BITS + 7, 8);
    }

    /** Complete compact command size: one opcode byte + packed register tuple. */
    public static function commandBytes(int $opcode): int
    {
        return 1 + self::payloadBytes($opcode);
    }

    /**
     * @param list<int|string> $registers Registers in opcode-defined order.
     */
    public static function encode(int $opcode, array $registers): string
    {
        $arity = self::arity($opcode);
        if (count($registers) !== $arity) {
            throw new InvalidArgumentException(self::spec($opcode)['name']." expects {$arity} registers");
        }

        $word = 0;
        foreach (array_values($registers) as $i => $reg) {
            $id = is_int($reg) ? $reg : PASMBC::regId(strtolower($reg));
            if ($id < 0 || $id > self::REG_MASK) {
                throw new InvalidArgumentException("Register id {$id} is outside the 3-bit hot register file");
            }
            $word |= ($id & self::REG_MASK) << ($i * self::REG_BITS);
        }

        $payload = '';
        for ($i = 0, $n = self::payloadBytes($opcode); $i < $n; $i++) {
            $payload .= chr(($word >> ($i * 8)) & 0xff);
        }
        return chr($opcode & 0xff) . $payload;
    }

    /**
     * Decode one command at $pc.  The opcode alone tells the decoder exactly
     * how many register bits follow and in which semantic order to read them.
     *
     * @return array{opcode:int,name:string,roles:list<string>,registers:list<int>,bytes:int}
     */
    public static function decode(string $code, int $pc = 0): array
    {
        if ($pc < 0 || $pc >= strlen($code)) throw new InvalidArgumentException('Command pc out of range');
        $opcode = ord($code[$pc]);
        $spec = self::spec($opcode);
        $payloadBytes = self::payloadBytes($opcode);
        if ($pc + 1 + $payloadBytes > strlen($code)) throw new InvalidArgumentException('Truncated register command');

        $word = 0;
        for ($i = 0; $i < $payloadBytes; $i++) {
            $word |= ord($code[$pc + 1 + $i]) << ($i * 8);
        }

        $regs = [];
        foreach ($spec['roles'] as $i => $_role) {
            $regs[] = ($word >> ($i * self::REG_BITS)) & self::REG_MASK;
        }

        return [
            'opcode'=>$opcode,
            'name'=>$spec['name'],
            'roles'=>$spec['roles'],
            'registers'=>$regs,
            'bytes'=>1 + $payloadBytes,
        ];
    }

    /** Human-readable ordered bindings, useful for provenance/debug only. */
    public static function bindings(string $code, int $pc = 0): array
    {
        $d = self::decode($code, $pc);
        $out = [];
        foreach ($d['roles'] as $i => $role) $out[$role] = PASMBC::regName($d['registers'][$i]);
        return $out;
    }
}

/**
 * Executes the compact register-command stream directly over an ordered
 * register vector.  This is deliberately separate from named/static PASM
 * state: decode -> ordered tuple -> operation.
 */
final class PASMRegisterCommandVM
{
    /** @var array<int,int> */
    private array $r = [0,0,0,0,0,0,0,0];
    /** @var list<int> */
    private array $stack = [];
    private bool $zero = false;

    /** @param array<int,int> $registers */
    public function __construct(array $registers = [])
    {
        foreach ($registers as $id=>$value) {
            if ($id < 0 || $id > 7) throw new InvalidArgumentException('Bad register id');
            $this->r[$id] = (int)$value;
        }
    }

    public function get(int|string $reg): int
    {
        $id = is_int($reg) ? $reg : PASMBC::regId(strtolower($reg));
        return $this->r[$id];
    }

    /** Execute a stream containing only compact register commands. */
    public function run(string $code): ?int
    {
        $pc = 0; $ret = null; $n = strlen($code);
        while ($pc < $n) {
            $d = PASMRegisterCommand::decode($code, $pc);
            $pc += $d['bytes'];
            $q = $d['registers'];

            switch ($d['opcode']) {
                case PASMBC::MOVR: $this->r[$q[0]] = $this->r[$q[1]]; break;
                case PASMBC::ADD:  $this->r[$q[0]] = $this->r[$q[1]] + $this->r[$q[2]]; break;
                case PASMBC::SUB:  $this->r[$q[0]] = $this->r[$q[1]] - $this->r[$q[2]]; break;
                case PASMBC::MUL:  $this->r[$q[0]] = $this->r[$q[1]] * $this->r[$q[2]]; break;
                case PASMBC::DIV:
                    if ($this->r[$q[2]] === 0) throw new \RuntimeException('Division by zero');
                    $this->r[$q[0]] = intdiv($this->r[$q[1]], $this->r[$q[2]]); break;
                case PASMBC::MOD:
                    if ($this->r[$q[2]] === 0) throw new \RuntimeException('Modulo by zero');
                    $this->r[$q[0]] = $this->r[$q[1]] % $this->r[$q[2]]; break;
                case PASMBC::AND: $this->r[$q[0]] = $this->r[$q[1]] & $this->r[$q[2]]; break;
                case PASMBC::OR:  $this->r[$q[0]] = $this->r[$q[1]] | $this->r[$q[2]]; break;
                case PASMBC::XOR: $this->r[$q[0]] = $this->r[$q[1]] ^ $this->r[$q[2]]; break;
                case PASMBC::SHL: $this->r[$q[0]] = $this->r[$q[1]] << $this->r[$q[2]]; break;
                case PASMBC::SHR: $this->r[$q[0]] = $this->r[$q[1]] >> $this->r[$q[2]]; break;
                case PASMBC::CMP: $this->zero = ($this->r[$q[0]] === $this->r[$q[1]]); break;
                case PASMBC::PUSH: $this->stack[] = $this->r[$q[0]]; break;
                case PASMBC::POP:
                    if ($this->stack === []) throw new \RuntimeException('Register-command stack underflow');
                    $this->r[$q[0]] = array_pop($this->stack); break;
                case PASMBC::INC: $this->r[$q[0]]++; break;
                case PASMBC::DEC: $this->r[$q[0]]--; break;
                case PASMBC::NEG: $this->r[$q[0]] = -$this->r[$q[0]]; break;
                case PASMBC::RET: $ret = $this->r[$q[0]]; return $ret;
                default: throw new InvalidArgumentException('Unsupported compact register opcode '.$d['opcode']);
            }
        }
        return $ret;
    }
}
