<?php declare(strict_types=1);

namespace jx\semantic;

/**
 * Inserts the Bag revision law into an already-prepared container stream.
 *
 * A successful first mutation in a native region is followed by exactly one
 * DIRTY instruction for that Bag. Further mutations remain hot. SYNC closes the
 * region so a later mutation starts a new dirty region. Placing DIRTY after the
 * first mutation means a failed mutator cannot dirty an unchanged Bag.
 */
final class PreparedContainerDirtyPass
{
    private const MUTATING = [
        'PUSH'=>true,
        'POP'=>true,
        'PUSHF'=>true,
        'PUSHB'=>true,
        'POPF'=>true,
        'POPB'=>true,
        'EMPLACE'=>true,
        'PUT'=>true,
        'REMOVE'=>true,
    ];

    public static function apply(PreparedContainerSourceCompilation $compiled): PreparedContainerSourceCompilation
    {
        $bindings = $compiled->bindings;
        $byId = [];
        foreach ($bindings->all() as $binding) $byId[$binding->id] = $binding;

        /** @var array<int,true> $dirty */
        $dirty = [];
        $out = '';
        $code = $compiled->jxl;
        $length = strlen($code);

        for ($offset = 0; $offset < $length; $offset += JxlContainerInstruction::BYTES) {
            $decoded = JxlContainerInstruction::decode($code, $offset);
            $binding = $byId[$decoded['binding_id']] ?? null;
            if (!$binding instanceof PreparedContainerBinding) {
                throw new SemanticException('Container stream references an unknown prepared binding', 'jxl-container-dirty');
            }

            $instruction = substr($code, $offset, JxlContainerInstruction::BYTES);
            $out .= $instruction;
            $bagHandle = $binding->bagHandle;
            $operation = $binding->operation;

            if ($operation === 'DIRTY') {
                $dirty[$bagHandle] = true;
                continue;
            }

            if (isset(self::MUTATING[$operation]) && !isset($dirty[$bagHandle])) {
                $dirtyBinding = $bindings->bind(
                    $binding->bagHandle,
                    $binding->discipline,
                    'DIRTY',
                    $binding->width,
                    $binding->capacity,
                    $binding->mask,
                    $binding->flags,
                );
                $byId[$dirtyBinding->id] = $dirtyBinding;
                $out .= JxlContainerInstruction::emit($dirtyBinding);
                $dirty[$bagHandle] = true;
                continue;
            }

            if ($operation === 'SYNC') unset($dirty[$bagHandle]);
        }

        return new PreparedContainerSourceCompilation(
            $out,
            $bindings,
            $compiled->bags,
            $compiled->registers,
            $compiled->constants,
            $compiled->initialRegisters,
        );
    }
}
