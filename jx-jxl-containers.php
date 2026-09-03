<?php declare(strict_types=1);

namespace jx\semantic;

use InvalidArgumentException;

/**
 * Prepared JXL container operations.
 *
 * Canonical JX/container names disappear before this layer. A JXL instruction
 * references a binding that was resolved at admission to one native x86-64
 * routine. The repeat path therefore never asks which discipline, alias, type,
 * or method is being used.
 */
final class JxlContainerOpcode
{
    public const PUSH    = 0x40;
    public const POP     = 0x41;
    public const PUSHF   = 0x42;
    public const PUSHB   = 0x43;
    public const POPF    = 0x44;
    public const POPB    = 0x45;
    public const EMPLACE = 0x46;
    public const GET     = 0x47;
    public const PUT     = 0x48;
    public const HAS     = 0x49;
    public const REMOVE  = 0x4A;
    public const PEEK    = 0x4B;
    public const PEEKF   = 0x4C;
    public const PEEKB   = 0x4D;
    public const RESERVE = 0x4E;
    public const DIRTY   = 0x4F;
    public const SYNC    = 0x50;

    /** @return array<int,array{name:string,args:int}> */
    public static function specs(): array
    {
        return [
            self::PUSH    => ['name'=>'PUSH','args'=>1],
            self::POP     => ['name'=>'POP','args'=>1],
            self::PUSHF   => ['name'=>'PUSHF','args'=>1],
            self::PUSHB   => ['name'=>'PUSHB','args'=>1],
            self::POPF    => ['name'=>'POPF','args'=>1],
            self::POPB    => ['name'=>'POPB','args'=>1],
            self::EMPLACE => ['name'=>'EMPLACE','args'=>2],
            self::GET     => ['name'=>'GET','args'=>2],
            self::PUT     => ['name'=>'PUT','args'=>2],
            self::HAS     => ['name'=>'HAS','args'=>2],
            self::REMOVE  => ['name'=>'REMOVE','args'=>2],
            self::PEEK    => ['name'=>'PEEK','args'=>1],
            self::PEEKF   => ['name'=>'PEEKF','args'=>1],
            self::PEEKB   => ['name'=>'PEEKB','args'=>1],
            self::RESERVE => ['name'=>'RESERVE','args'=>1],
            self::DIRTY   => ['name'=>'DIRTY','args'=>0],
            self::SYNC    => ['name'=>'SYNC','args'=>0],
        ];
    }

    public static function name(int $opcode): string
    {
        $spec = self::specs()[$opcode] ?? null;
        if ($spec === null) throw new InvalidArgumentException("Unknown JXL container opcode 0x" . dechex($opcode));
        return $spec['name'];
    }

    public static function opcode(string $name): int
    {
        $name = strtoupper(trim($name));
        foreach (self::specs() as $opcode => $spec) if ($spec['name'] === $name) return $opcode;
        throw new InvalidArgumentException("Unknown JXL container operation {$name}");
    }

    public static function argCount(int $opcode): int
    {
        $spec = self::specs()[$opcode] ?? null;
        if ($spec === null) throw new InvalidArgumentException("Unknown JXL container opcode");
        return $spec['args'];
    }

    public static function isContainer(int $opcode): bool
    {
        return isset(self::specs()[$opcode]);
    }
}

final class JxlContainerSemantic
{
    private const ALIASES = [
        'BPUSH'=>'PUSH','PUSH'=>'PUSH','APPEND'=>'PUSH','ADD'=>'PUSH','ENQUEUE'=>'PUSH','ENQ'=>'PUSH','QPUSH'=>'PUSH','SPUSH'=>'PUSH','VAPPEND'=>'PUSH',
        'BPOP'=>'POP','POP'=>'POP','TAKE'=>'POP','DEQUEUE'=>'POP','DEQ'=>'POP','QPOP'=>'POP','SPOP'=>'POP','VPOP'=>'POP',
        'BPUSHF'=>'PUSHF','PUSHF'=>'PUSHF','PUSHFRONT'=>'PUSHF','UNSHIFT'=>'PUSHF','DPUSHF'=>'PUSHF',
        'BPUSHB'=>'PUSHB','PUSHB'=>'PUSHB','PUSHBACK'=>'PUSHB','DPUSHB'=>'PUSHB',
        'BPOPF'=>'POPF','POPF'=>'POPF','POPFRONT'=>'POPF','SHIFT'=>'POPF','DPOPF'=>'POPF',
        'BPOPB'=>'POPB','POPB'=>'POPB','POPBACK'=>'POPB','DPOPB'=>'POPB',
        'BEMPLACE'=>'EMPLACE','EMPLACE'=>'EMPLACE','INSERT'=>'EMPLACE','PUTIFABSENT'=>'EMPLACE','ADDIFABSENT'=>'EMPLACE',
        'BGET'=>'GET','GET'=>'GET','READ'=>'GET','LOOKUP'=>'GET',
        'BPUT'=>'PUT','PUT'=>'PUT','WRITE'=>'PUT','SET'=>'PUT',
        'BHAS'=>'HAS','HAS'=>'HAS','CONTAINS'=>'HAS','EXISTS'=>'HAS',
        'BREMOVE'=>'REMOVE','REMOVE'=>'REMOVE','DELETE'=>'REMOVE','ERASE'=>'REMOVE',
        'BPEEK'=>'PEEK','PEEK'=>'PEEK','TOP'=>'PEEK','FRONT'=>'PEEK',
        'BPEEKF'=>'PEEKF','PEEKF'=>'PEEKF','FRONTPEEK'=>'PEEKF',
        'BPEEKB'=>'PEEKB','PEEKB'=>'PEEKB','BACK'=>'PEEKB','BACKPEEK'=>'PEEKB',
        'BRESERVE'=>'RESERVE','RESERVE'=>'RESERVE','ENSURE'=>'RESERVE',
        'BDIRTY'=>'DIRTY','DIRTY'=>'DIRTY',
        'BSYNC'=>'SYNC','SYNC'=>'SYNC','CHECKPOINT'=>'SYNC','COMMITBAG'=>'SYNC',
    ];

    private const VALID = [
        'record' => ['GET','PUT','DIRTY','SYNC'],
        'vector' => ['PUSH','POP','EMPLACE','GET','PUT','PEEK','RESERVE','DIRTY','SYNC'],
        'stack'  => ['PUSH','POP','EMPLACE','PEEK','RESERVE','DIRTY','SYNC'],
        'queue'  => ['PUSH','POP','PEEK','RESERVE','DIRTY','SYNC'],
        'deque'  => ['PUSH','POP','PUSHF','PUSHB','POPF','POPB','PEEK','PEEKF','PEEKB','RESERVE','DIRTY','SYNC'],
        'map'    => ['EMPLACE','GET','PUT','HAS','REMOVE','RESERVE','DIRTY','SYNC'],
        'set'    => ['EMPLACE','HAS','REMOVE','RESERVE','DIRTY','SYNC'],
    ];

    public static function canonical(string $operation, string $discipline): string
    {
        $key = strtoupper(trim($operation));
        $op = self::ALIASES[$key] ?? null;
        if ($op === null) throw new InvalidArgumentException("Unknown container operation {$operation}");
        $discipline = strtolower(trim($discipline));
        $valid = self::VALID[$discipline] ?? null;
        if ($valid === null) throw new InvalidArgumentException("Unknown Bag discipline {$discipline}");

        // Discipline-specific aliases collapse before the binding is emitted.
        if ($discipline === 'deque') {
            if ($op === 'PUSH') $op = 'PUSHB';
            if ($op === 'POP') $op = 'POPF';
            if ($op === 'PEEK') $op = 'PEEKF';
        }
        if ($discipline === 'set' && $op === 'PUT') $op = 'EMPLACE';

        if (!in_array($op, $valid, true)) {
            throw new InvalidArgumentException("{$operation} is not valid for {$discipline}");
        }
        return $op;
    }
}

final class JxlContainerNative
{
    public const VECTOR_PUSH = 1;
    public const VECTOR_POP = 2;
    public const VECTOR_GET = 3;
    public const VECTOR_PUT = 4;
    public const VECTOR_EMPLACE = 5;
    public const VECTOR_PEEK = 6;
    public const RECORD_GET = 7;
    public const RECORD_PUT = 8;
    public const QUEUE_PUSH = 9;
    public const QUEUE_POP = 10;
    public const QUEUE_PEEK = 11;
    public const DEQUE_PUSHF = 12;
    public const DEQUE_PUSHB = 13;
    public const DEQUE_POPF = 14;
    public const DEQUE_POPB = 15;
    public const DEQUE_PEEKF = 16;
    public const DEQUE_PEEKB = 17;
    public const MAP_EMPLACE = 18;
    public const MAP_GET = 19;
    public const MAP_PUT = 20;
    public const MAP_HAS = 21;
    public const MAP_REMOVE = 22;
    public const SET_ADD = 23;
    public const SET_HAS = 24;
    public const SET_REMOVE = 25;
    public const VECTOR_RESERVE = 26;
    public const RING_RESERVE = 27;
    public const HASH_RESERVE = 28;
    public const BAG_DIRTY = 29;
    public const BAG_SYNC = 30;

    /** @return array<int,string> */
    public static function symbols(): array
    {
        return [
            self::VECTOR_PUSH=>'jx_vector_push_u64', self::VECTOR_POP=>'jx_vector_pop_u64',
            self::VECTOR_GET=>'jx_vector_get_u64', self::VECTOR_PUT=>'jx_vector_put_u64',
            self::VECTOR_EMPLACE=>'jx_vector_emplace_u64', self::VECTOR_PEEK=>'jx_vector_peek_u64',
            self::RECORD_GET=>'jx_record_get_u64', self::RECORD_PUT=>'jx_record_put_u64',
            self::QUEUE_PUSH=>'jx_queue_push_u64', self::QUEUE_POP=>'jx_queue_pop_u64', self::QUEUE_PEEK=>'jx_queue_peek_u64',
            self::DEQUE_PUSHF=>'jx_deque_push_front_u64', self::DEQUE_PUSHB=>'jx_deque_push_back_u64',
            self::DEQUE_POPF=>'jx_deque_pop_front_u64', self::DEQUE_POPB=>'jx_deque_pop_back_u64',
            self::DEQUE_PEEKF=>'jx_deque_peek_front_u64', self::DEQUE_PEEKB=>'jx_deque_peek_back_u64',
            self::MAP_EMPLACE=>'jx_map_emplace_u64', self::MAP_GET=>'jx_map_get_u64', self::MAP_PUT=>'jx_map_put_u64',
            self::MAP_HAS=>'jx_map_has_u64', self::MAP_REMOVE=>'jx_map_remove_u64',
            self::SET_ADD=>'jx_set_add_u64', self::SET_HAS=>'jx_set_has_u64', self::SET_REMOVE=>'jx_set_remove_u64',
            self::VECTOR_RESERVE=>'jx_vector_reserve_u64', self::RING_RESERVE=>'jx_ring_reserve_u64', self::HASH_RESERVE=>'jx_hash_reserve_u64',
            self::BAG_DIRTY=>'jx_bag_dirty', self::BAG_SYNC=>'jx_bag_sync',
        ];
    }

    /** @return array{id:int,symbol:string} */
    public static function resolve(string $discipline, string $operation, int $width = 8): array
    {
        $discipline = strtolower($discipline);
        $op = JxlContainerSemantic::canonical($operation, $discipline);
        if ($width !== 8) throw new InvalidArgumentException('JXL native container v1 binds fixed 64-bit payloads; use a prepared copy helper for wider records');

        $id = match ($discipline) {
            'record' => match ($op) { 'GET'=>self::RECORD_GET, 'PUT'=>self::RECORD_PUT, 'DIRTY'=>self::BAG_DIRTY, 'SYNC'=>self::BAG_SYNC },
            'vector' => match ($op) {
                'PUSH'=>self::VECTOR_PUSH,'POP'=>self::VECTOR_POP,'GET'=>self::VECTOR_GET,'PUT'=>self::VECTOR_PUT,
                'EMPLACE'=>self::VECTOR_EMPLACE,'PEEK'=>self::VECTOR_PEEK,'RESERVE'=>self::VECTOR_RESERVE,
                'DIRTY'=>self::BAG_DIRTY,'SYNC'=>self::BAG_SYNC,
            },
            'stack' => match ($op) {
                'PUSH'=>self::VECTOR_PUSH,'POP'=>self::VECTOR_POP,'EMPLACE'=>self::VECTOR_EMPLACE,'PEEK'=>self::VECTOR_PEEK,
                'RESERVE'=>self::VECTOR_RESERVE,'DIRTY'=>self::BAG_DIRTY,'SYNC'=>self::BAG_SYNC,
            },
            'queue' => match ($op) {
                'PUSH'=>self::QUEUE_PUSH,'POP'=>self::QUEUE_POP,'PEEK'=>self::QUEUE_PEEK,'RESERVE'=>self::RING_RESERVE,
                'DIRTY'=>self::BAG_DIRTY,'SYNC'=>self::BAG_SYNC,
            },
            'deque' => match ($op) {
                'PUSHF'=>self::DEQUE_PUSHF,'PUSHB'=>self::DEQUE_PUSHB,'POPF'=>self::DEQUE_POPF,'POPB'=>self::DEQUE_POPB,
                'PEEKF'=>self::DEQUE_PEEKF,'PEEKB'=>self::DEQUE_PEEKB,'RESERVE'=>self::RING_RESERVE,
                'DIRTY'=>self::BAG_DIRTY,'SYNC'=>self::BAG_SYNC,
            },
            'map' => match ($op) {
                'EMPLACE'=>self::MAP_EMPLACE,'GET'=>self::MAP_GET,'PUT'=>self::MAP_PUT,'HAS'=>self::MAP_HAS,'REMOVE'=>self::MAP_REMOVE,
                'RESERVE'=>self::HASH_RESERVE,'DIRTY'=>self::BAG_DIRTY,'SYNC'=>self::BAG_SYNC,
            },
            'set' => match ($op) {
                'EMPLACE'=>self::SET_ADD,'HAS'=>self::SET_HAS,'REMOVE'=>self::SET_REMOVE,'RESERVE'=>self::HASH_RESERVE,
                'DIRTY'=>self::BAG_DIRTY,'SYNC'=>self::BAG_SYNC,
            },
            default => throw new InvalidArgumentException("Unknown Bag discipline {$discipline}"),
        };
        return ['id'=>$id, 'symbol'=>self::symbols()[$id]];
    }
}

final readonly class PreparedContainerBinding
{
    public function __construct(
        public int $id,
        public int $bagHandle,
        public string $discipline,
        public string $operation,
        public int $opcode,
        public int $nativeId,
        public string $nativeSymbol,
        public int $width,
        public int $capacity,
        public int $mask,
        public int $flags = 0,
    ) {}

    public function metadata(): array
    {
        return [
            'id'=>$this->id,'bag_handle'=>$this->bagHandle,'discipline'=>$this->discipline,'operation'=>$this->operation,
            'opcode'=>$this->opcode,'native_id'=>$this->nativeId,'native_symbol'=>$this->nativeSymbol,
            'width'=>$this->width,'capacity'=>$this->capacity,'mask'=>$this->mask,'flags'=>$this->flags,
        ];
    }
}

final class PreparedContainerBindings
{
    public const FORMAT = 'JXCBIND1';
    public const MAX_BINDINGS = 0x3FFF;
    /** @var list<PreparedContainerBinding> */ private array $bindings = [];
    /** @var array<string,int> */ private array $dedupe = [];

    public function bind(
        int $bagHandle,
        string $discipline,
        string $operation,
        *,
        int $width = 8,
        int $capacity = 0,
        int $mask = 0,
        int $flags = 0,
    ): PreparedContainerBinding {
        if ($bagHandle < 0) throw new InvalidArgumentException('bagHandle must be non-negative');
        if ($capacity < 0 || $mask < 0) throw new InvalidArgumentException('capacity/mask must be non-negative');
        $discipline = strtolower(trim($discipline));
        $op = JxlContainerSemantic::canonical($operation, $discipline);
        if (in_array($discipline, ['queue','deque','map','set'], true) && $capacity > 0) {
            if (($capacity & ($capacity - 1)) !== 0) throw new InvalidArgumentException("{$discipline} capacity must be a power of two");
            if ($mask === 0) $mask = $capacity - 1;
        }
        $native = JxlContainerNative::resolve($discipline, $op, $width);
        $key = implode('|', [$bagHandle,$discipline,$op,$width,$capacity,$mask,$flags,$native['id']]);
        if (isset($this->dedupe[$key])) return $this->bindings[$this->dedupe[$key]];
        $id = count($this->bindings);
        if ($id > self::MAX_BINDINGS) throw new InvalidArgumentException('JXL container binding table exhausted (14-bit binding id)');
        $binding = new PreparedContainerBinding(
            $id,$bagHandle,$discipline,$op,JxlContainerOpcode::opcode($op),$native['id'],$native['symbol'],$width,$capacity,$mask,$flags
        );
        $this->dedupe[$key] = $id;
        $this->bindings[] = $binding;
        return $binding;
    }

    /** @return list<PreparedContainerBinding> */
    public function all(): array { return $this->bindings; }

    public function metadata(): array
    {
        return [
            'format'=>'jx.jxl-container-bindings/1',
            'target'=>'x86_64-sysv',
            'payload'=>'u64',
            'bindings'=>array_map(static fn(PreparedContainerBinding $b): array => $b->metadata(), $this->bindings),
        ];
    }

    public function json(): string
    {
        return json_encode($this->metadata(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Relocation-friendly Book record. Runtime pointers are intentionally absent.
     * Admission converts native_id to a function pointer and Bag handle to a
     * runtime base/head/tail/generation binding.
     */
    public function binary(): string
    {
        $out = self::FORMAT . pack('v', 1) . pack('v', count($this->bindings));
        foreach ($this->bindings as $b) {
            $out .= pack('vCCCC', $b->id, self::disciplineId($b->discipline), $b->opcode, $b->width, 0);
            $out .= self::u64le($b->bagHandle);
            $out .= pack('V2v2', $b->capacity, $b->mask, $b->nativeId, $b->flags);
        }
        return $out;
    }

    private static function disciplineId(string $discipline): int
    {
        return match ($discipline) {
            'record'=>1,'vector'=>2,'stack'=>3,'queue'=>4,'deque'=>5,'map'=>6,'set'=>7,
            default=>throw new InvalidArgumentException("Unknown discipline {$discipline}"),
        };
    }

    private static function u64le(int $value): string
    {
        if ($value < 0) throw new InvalidArgumentException('u64 value must be non-negative');
        $lo = $value & 0xFFFFFFFF;
        $hi = ($value >> 32) & 0xFFFFFFFF;
        return pack('V2', $lo, $hi);
    }
}

final class JxlContainerInstruction
{
    public static function emit(PreparedContainerBinding $binding, int ...$selectors): string
    {
        $opcode = $binding->opcode;
        $expected = JxlContainerOpcode::argCount($opcode);
        if (count($selectors) !== $expected) {
            throw new InvalidArgumentException("{$binding->operation} expects {$expected} JXL local selector(s)");
        }
        $out = chr($opcode);
        $out .= self::attachment($binding->id & 0x7F);
        $out .= self::attachment(($binding->id >> 7) & 0x7F);
        foreach ($selectors as $selector) {
            if ($selector < 0 || $selector > 7) throw new InvalidArgumentException('JXL local register selector must be 0..7');
            $out .= self::attachment($selector);
        }
        return $out;
    }

    /** @return array{opcode:int,operation:string,binding_id:int,selectors:list<int>,next:int} */
    public static function decode(string $bytes, int $offset = 0): array
    {
        if (!isset($bytes[$offset])) throw new InvalidArgumentException('Missing JXL container opcode');
        $opcode = ord($bytes[$offset]);
        if (($opcode & 0x80) !== 0 || !JxlContainerOpcode::isContainer($opcode)) {
            throw new InvalidArgumentException('Not a JXL container opcode');
        }
        $p = $offset + 1;
        $lo = self::readAttachment($bytes, $p++);
        $hi = self::readAttachment($bytes, $p++);
        $selectors = [];
        for ($i=0,$n=JxlContainerOpcode::argCount($opcode); $i<$n; $i++) {
            $selector = self::readAttachment($bytes, $p++);
            if ($selector > 7) throw new InvalidArgumentException('Prepared selector outside local register window');
            $selectors[] = $selector;
        }
        return [
            'opcode'=>$opcode,'operation'=>JxlContainerOpcode::name($opcode),
            'binding_id'=>$lo | ($hi << 7),'selectors'=>$selectors,'next'=>$p,
        ];
    }

    private static function attachment(int $payload): string
    {
        if ($payload < 0 || $payload > 0x7F) throw new InvalidArgumentException('JXL attachment payload must fit 7 bits');
        return chr(0x80 | $payload);
    }

    private static function readAttachment(string $bytes, int $offset): int
    {
        if (!isset($bytes[$offset])) throw new InvalidArgumentException('Truncated JXL container attachment');
        $b = ord($bytes[$offset]);
        if (($b & 0x80) === 0) throw new InvalidArgumentException('JXL container operand is not an attached byte');
        return $b & 0x7F;
    }
}
