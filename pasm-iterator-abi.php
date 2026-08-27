<?php declare(strict_types=1);

namespace pasm;

use InvalidArgumentException;
use RuntimeException;

/**
 * PASM compact iterator ABI.
 *
 * Repeated bytecode is exactly two bytes:
 *   ITERF <u8-slot>  => 0x19 slot
 *   ITERR <u8-slot>  => 0x1A slot
 *
 * Loop entry may issue one equally compact IRESET <slot> (0x21 slot). Reset is
 * not part of the repeated hot path; it makes nested re-entry and early break
 * deterministic without widening ITERF/ITERR.
 */
final class PASMIterBC
{
    public const ITERF = 0x19;
    public const ITERR = 0x1A;
    public const IRESET = 0x21;
    public const WIDTH = 2;

    public static function encodeForward(int $slot): string { return chr(self::ITERF) . chr(self::slot($slot)); }
    public static function encodeReverse(int $slot): string { return chr(self::ITERR) . chr(self::slot($slot)); }
    public static function encodeReset(int $slot): string { return chr(self::IRESET) . chr(self::slot($slot)); }

    /** @return array{opcode:int,slot:int,reverse:bool} */
    public static function decode(string $code, int $offset = 0): array
    {
        if (strlen($code) < $offset + self::WIDTH) throw new InvalidArgumentException('Iterator bytecode requires exactly 2 readable bytes');
        $op = ord($code[$offset]);
        if ($op !== self::ITERF && $op !== self::ITERR) throw new InvalidArgumentException(sprintf('Not an iterator step opcode: 0x%02X', $op));
        return ['opcode'=>$op, 'slot'=>ord($code[$offset + 1]), 'reverse'=>$op === self::ITERR];
    }

    private static function slot(int $slot): int
    {
        if ($slot < 0 || $slot > 255) throw new InvalidArgumentException('PASM iterator slot must fit one byte (0..255)');
        return $slot;
    }
}

/** Prelinked iterator descriptor. This state is NOT repeated in bytecode. */
final class PASMIteratorDescriptor
{
    public int $cursor;
    public bool $started = false;
    public ?int $valueRegister = null;
    public ?int $keyRegister = null;

    /** @param callable(int):mixed $read @param null|callable(int):mixed $readKey */
    public function __construct(
        public readonly int $slot,
        public readonly int $count,
        public readonly \Closure $read,
        public readonly ?\Closure $readKey = null,
    ) {
        if ($slot < 0 || $slot > 255) throw new InvalidArgumentException('iterator slot 0..255');
        if ($count < 0) throw new InvalidArgumentException('iterator count cannot be negative');
        $this->cursor = 0;
    }

    public function targets(?int $valueRegister, ?int $keyRegister = null): self
    {
        foreach (['value'=>$valueRegister, 'key'=>$keyRegister] as $name=>$reg) {
            if ($reg !== null && ($reg < 0 || $reg > 7)) throw new InvalidArgumentException("{$name} iterator register must be 0..7");
        }
        $this->valueRegister = $valueRegister;
        $this->keyRegister = $keyRegister;
        return $this;
    }

    public function reset(): void { $this->cursor = 0; $this->started = false; }
    public function resetForward(): void { $this->reset(); }
    public function resetReverse(): void { $this->cursor = $this->count - 1; $this->started = false; }
}

final class PASMIteratorResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly mixed $value = null,
        public readonly mixed $key = null,
        public readonly int $index = -1,
    ) {}
}

/** 256-entry prelinked iterator lookup. The hot operation receives only slot id. */
final class PASMIteratorTable
{
    /** @var array<int,PASMIteratorDescriptor> */
    private array $slots = [];

    public function bind(PASMIteratorDescriptor $iterator): void
    {
        if (isset($this->slots[$iterator->slot])) throw new InvalidArgumentException("Iterator slot {$iterator->slot} already bound");
        $this->slots[$iterator->slot] = $iterator;
    }
    public function replace(PASMIteratorDescriptor $iterator): void { $this->slots[$iterator->slot] = $iterator; }
    public function unbind(int $slot): void { unset($this->slots[$slot]); }
    public function descriptor(int $slot): PASMIteratorDescriptor { return $this->slots[$slot] ?? throw new RuntimeException("Unbound iterator slot {$slot}"); }
    public function reset(int $slot): void { $this->descriptor($slot)->reset(); }

    public function forward(int $slot): PASMIteratorResult
    {
        $it = $this->descriptor($slot);
        if (!$it->started) { $it->cursor = 0; $it->started = true; }
        $i = $it->cursor;
        if ($i < 0 || $i >= $it->count) return new PASMIteratorResult(false);
        $value = ($it->read)($i);
        $key = $it->readKey !== null ? ($it->readKey)($i) : $i;
        $it->cursor = $i + 1;
        return new PASMIteratorResult(true, $value, $key, $i);
    }

    public function reverse(int $slot): PASMIteratorResult
    {
        $it = $this->descriptor($slot);
        if (!$it->started) { $it->cursor = $it->count - 1; $it->started = true; }
        $i = $it->cursor;
        if ($i < 0 || $i >= $it->count) return new PASMIteratorResult(false);
        $value = ($it->read)($i);
        $key = $it->readKey !== null ? ($it->readKey)($i) : $i;
        $it->cursor = $i - 1;
        return new PASMIteratorResult(true, $value, $key, $i);
    }

    public function execute(string $instruction): PASMIteratorResult
    {
        $d = PASMIterBC::decode($instruction);
        return $d['reverse'] ? $this->reverse($d['slot']) : $this->forward($d['slot']);
    }
}
