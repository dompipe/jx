<?php declare(strict_types=1);

namespace pasm;

use InvalidArgumentException;

/**
 * Direct executor for the packed ordered-register stream.
 *
 * This deliberately does not call PASMRegisterCommand::decode(). The opcode
 * itself determines exactly how many payload bytes follow and each hot case
 * extracts its register selectors directly from those bytes.
 */
final class PASMRegisterFastVM
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

    public function get(int $id): int { return $this->r[$id]; }

    public function run(string $code): ?int
    {
        $pc = 0;
        $n = strlen($code);
        $r =& $this->r;
        $stack =& $this->stack;
        $zero =& $this->zero;

        while ($pc < $n) {
            $op = ord($code[$pc++]);

            switch ($op) {
                // 2-register commands: one payload byte, 3 bits each.
                case PASMBC::MOVR:
                    $w = ord($code[$pc++]);
                    $d = $w & 7; $s = ($w >> 3) & 7;
                    $r[$d] = $r[$s];
                    break;

                case PASMBC::CMP:
                    $w = ord($code[$pc++]);
                    $a = $w & 7; $b = ($w >> 3) & 7;
                    $zero = ($r[$a] === $r[$b]);
                    break;

                // 3-register ALU commands: two payload bytes, packed 9-bit tuple.
                case PASMBC::ADD:
                case PASMBC::SUB:
                case PASMBC::MUL:
                case PASMBC::DIV:
                case PASMBC::MOD:
                case PASMBC::AND:
                case PASMBC::OR:
                case PASMBC::XOR:
                case PASMBC::SHL:
                case PASMBC::SHR:
                    $w0 = ord($code[$pc++]);
                    $w1 = ord($code[$pc++]);
                    $d = $w0 & 7;
                    $a = ($w0 >> 3) & 7;
                    $b = (($w0 >> 6) | ($w1 << 2)) & 7;
                    $av = $r[$a]; $bv = $r[$b];
                    switch ($op) {
                        case PASMBC::ADD: $r[$d] = $av + $bv; break;
                        case PASMBC::SUB: $r[$d] = $av - $bv; break;
                        case PASMBC::MUL: $r[$d] = $av * $bv; break;
                        case PASMBC::DIV:
                            if ($bv === 0) throw new \RuntimeException('Division by zero');
                            $r[$d] = intdiv($av, $bv); break;
                        case PASMBC::MOD:
                            if ($bv === 0) throw new \RuntimeException('Modulo by zero');
                            $r[$d] = $av % $bv; break;
                        case PASMBC::AND: $r[$d] = $av & $bv; break;
                        case PASMBC::OR:  $r[$d] = $av | $bv; break;
                        case PASMBC::XOR: $r[$d] = $av ^ $bv; break;
                        case PASMBC::SHL: $r[$d] = $av << $bv; break;
                        case PASMBC::SHR: $r[$d] = $av >> $bv; break;
                    }
                    break;

                // 1-register commands: one payload byte; low 3 bits are the register.
                case PASMBC::PUSH:
                    $q = ord($code[$pc++]) & 7;
                    $stack[] = $r[$q];
                    break;
                case PASMBC::POP:
                    $q = ord($code[$pc++]) & 7;
                    if ($stack === []) throw new \RuntimeException('Register-command stack underflow');
                    $r[$q] = array_pop($stack);
                    break;
                case PASMBC::INC:
                    $q = ord($code[$pc++]) & 7; ++$r[$q]; break;
                case PASMBC::DEC:
                    $q = ord($code[$pc++]) & 7; --$r[$q]; break;
                case PASMBC::NEG:
                    $q = ord($code[$pc++]) & 7; $r[$q] = -$r[$q]; break;
                case PASMBC::RET:
                    $q = ord($code[$pc++]) & 7;
                    return $r[$q];

                default:
                    throw new InvalidArgumentException(sprintf('Unsupported packed opcode 0x%02X at %d', $op, $pc-1));
            }
        }
        return null;
    }
}
