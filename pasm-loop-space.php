<?php declare(strict_types=1);

namespace pasm\lang;

use InvalidArgumentException;

/** Canonical single-operation variable mutations. */
final class PASMVarOp
{
    public const VSET = 'VSET';
    public const VINC = 'VINC';
    public const VDEC = 'VDEC';
    public const VADD = 'VADD';
    public const VSUB = 'VSUB';
    public const VMUL = 'VMUL';
    public const VDIV = 'VDIV';
    public const VMOD = 'VMOD';
    public const VAND = 'VAND';
    public const VOR  = 'VOR';
    public const VXOR = 'VXOR';
    public const VSHL = 'VSHL';
    public const VSHR = 'VSHR';
    public const VALG = 'VALG'; // recursive/fused algebra tree

    /** @return array{op:string,var:string,args:list<string>,source:string} */
    public static function parse(string $statement): array
    {
        $s = trim(rtrim($statement, ';'));

        if (preg_match('/^\$?([A-Za-z_]\w*)\s*\+\+$/', $s, $m)) {
            return ['op'=>self::VINC,'var'=>strtolower($m[1]),'args'=>[],'source'=>$statement];
        }
        if (preg_match('/^\+\+\s*\$?([A-Za-z_]\w*)$/', $s, $m)) {
            return ['op'=>self::VINC,'var'=>strtolower($m[1]),'args'=>[],'source'=>$statement];
        }
        if (preg_match('/^\$?([A-Za-z_]\w*)\s*--$/', $s, $m)) {
            return ['op'=>self::VDEC,'var'=>strtolower($m[1]),'args'=>[],'source'=>$statement];
        }
        if (preg_match('/^--\s*\$?([A-Za-z_]\w*)$/', $s, $m)) {
            return ['op'=>self::VDEC,'var'=>strtolower($m[1]),'args'=>[],'source'=>$statement];
        }
        if (preg_match('/^\$?([A-Za-z_]\w*)\s*(\+=|-=|\*=|\/=|%=|&=|\|=|\^=|<<=|>>=)\s*(.+)$/', $s, $m)) {
            $op = match ($m[2]) {
                '+='=>self::VADD, '-='=>self::VSUB, '*='=>self::VMUL, '/='=>self::VDIV,
                '%='=>self::VMOD, '&='=>self::VAND, '|='=>self::VOR, '^='=>self::VXOR,
                '<<='=>self::VSHL, '>>='=>self::VSHR,
            };
            return ['op'=>$op,'var'=>strtolower($m[1]),'args'=>[trim($m[3])],'source'=>$statement];
        }
        if (preg_match('/^\$?([A-Za-z_]\w*)\s*=\s*(.+)$/', $s, $m)) {
            $rhs = trim($m[2]);
            // Recursive algebra remains one canonical mutation; lowering may fuse/tree-walk at compile time.
            $isAlgebra = preg_match('/[+\-*\/%&|^<>]/', $rhs) === 1;
            return ['op'=>$isAlgebra ? self::VALG : self::VSET,'var'=>strtolower($m[1]),'args'=>[$rhs],'source'=>$statement];
        }
        throw new InvalidArgumentException("Not a variable mutation: {$statement}");
    }

    /** Approximate native shadow intent, not target-specific final assembly. */
    public static function lowering(array $m): array
    {
        return match ($m['op']) {
            self::VINC => ['asm'=>['inc ['.$m['var'].']']],
            self::VDEC => ['asm'=>['dec ['.$m['var'].']']],
            self::VADD => ['asm'=>['add ['.$m['var'].'], '.$m['args'][0]]],
            self::VSUB => ['asm'=>['sub ['.$m['var'].'], '.$m['args'][0]]],
            self::VMUL => ['asm'=>['imul ['.$m['var'].'], '.$m['args'][0]]],
            self::VAND => ['asm'=>['and ['.$m['var'].'], '.$m['args'][0]]],
            self::VOR  => ['asm'=>['or ['.$m['var'].'], '.$m['args'][0]]],
            self::VXOR => ['asm'=>['xor ['.$m['var'].'], '.$m['args'][0]]],
            self::VSHL => ['asm'=>['shl ['.$m['var'].'], '.$m['args'][0]]],
            self::VSHR => ['asm'=>['shr ['.$m['var'].'], '.$m['args'][0]]],
            self::VDIV,self::VMOD,self::VSET,self::VALG => ['asm'=>[]],
            default => ['asm'=>[]],
        };
    }
}

final class PASMLoopKind
{
    public const FOR = 'for';
    public const WHILE = 'while';
    public const DO_WHILE = 'do-while';
    public const FOREACH = 'foreach';
    public const REPEAT = 'repeat';
}

/** Immutable compiled loop body descriptor. */
final class PASMLoopBlock
{
    public function __construct(
        public readonly int $slot,
        public readonly string $kind,
        public readonly string $condition,
        public readonly ?string $init,
        public readonly ?string $step,
        public readonly string $body,
        public readonly string $bodySymbol,
        public readonly ?string $stepSymbol,
        public readonly int $depth,
    ) {}

    public function controller(): array
    {
        // Core invariant: iteration controller is guard/check + direct call.
        $ops = [
            ['op'=>'LCHECK','condition'=>$this->condition],
            ['op'=>'LCALL','target'=>$this->bodySymbol],
        ];
        if ($this->stepSymbol !== null) {
            $ops[] = ['op'=>'LCALL','target'=>$this->stepSymbol];
        }
        $ops[] = ['op'=>'LREPEAT','slot'=>$this->slot];
        return $ops;
    }
}

/**
 * Bounded loop-space allocator.
 * Loops are compiled into callable blocks and invoked by small controllers.
 * Nesting is bounded explicitly; sequential loops reuse slots when scopes exit.
 */
final class PASMLoopSpace
{
    public const DEFAULT_MAX_DEPTH = 8;

    /** @var list<PASMLoopBlock> */
    private array $active = [];
    /** @var array<int,PASMLoopBlock> */
    private array $compiled = [];
    private int $nextSymbol = 0;

    public function __construct(private readonly int $maxDepth = self::DEFAULT_MAX_DEPTH)
    {
        if ($maxDepth < 1 || $maxDepth > 64) {
            throw new InvalidArgumentException('Loop nesting limit must be 1..64');
        }
    }

    public function depth(): int { return count($this->active); }
    public function maxDepth(): int { return $this->maxDepth; }

    public function enter(
        string $kind,
        string $condition,
        string $body,
        ?string $init = null,
        ?string $step = null,
    ): PASMLoopBlock {
        $depth = $this->depth();
        if ($depth >= $this->maxDepth) {
            throw new InvalidArgumentException("Loop nesting exceeds limit {$this->maxDepth}");
        }
        $slot = $depth; // invoke a series of nested loops in bounded loop space.
        $id = $this->nextSymbol++;
        $block = new PASMLoopBlock(
            slot: $slot,
            kind: $kind,
            condition: trim($condition),
            init: $init === null ? null : trim($init),
            step: $step === null ? null : trim($step),
            body: $body,
            bodySymbol: "__jx_loop_body_{$id}",
            stepSymbol: ($step !== null && trim($step) !== '') ? "__jx_loop_step_{$id}" : null,
            depth: $depth,
        );
        $this->active[] = $block;
        $this->compiled[$id] = $block;
        return $block;
    }

    public function leave(): PASMLoopBlock
    {
        $block = array_pop($this->active);
        if (!$block instanceof PASMLoopBlock) {
            throw new InvalidArgumentException('Loop-space underflow');
        }
        return $block;
    }

    /** @return array<int,PASMLoopBlock> */
    public function compiled(): array { return $this->compiled; }
}

/** Canonical lowering helper shared by future PASL/native compiler passes. */
final class PASMLoopLowering
{
    public static function controllerAsm(PASMLoopBlock $loop): array
    {
        $asm = [
            '; slot '.$loop->slot.' depth '.$loop->depth.' '.$loop->kind,
            'CHECK '.$loop->condition.' -> .leave_'.$loop->slot,
            'CALL '.$loop->bodySymbol,
        ];
        if ($loop->stepSymbol !== null) $asm[] = 'CALL '.$loop->stepSymbol;
        $asm[] = 'JMP .loop_'.$loop->slot;
        return $asm;
    }
}
