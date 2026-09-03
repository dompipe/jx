<?php declare(strict_types=1);

namespace jx\semantic;

require_once __DIR__ . '/jx-semantic.php';
require_once __DIR__ . '/jx-jxl-containers.php';
require_once __DIR__ . '/jx-jxl-bag-source.php';

/**
 * Canonical semantic IR -> normalized prepared IR -> JXL.
 *
 * This pass owns lowering rewrites that should not leak into parser meaning or
 * the VM. Internal names contain NUL, which canonical source identifiers can
 * never spell, making generated temporaries hygienic.
 *
 * Container preparation is operation-specific. Canonical Bag blocks and member
 * calls are resolved before executable JXL is emitted, so the hot path carries
 * only a prepared binding ID and eight-register-window selectors.
 */
final class PreparedCompiler
{
    private int $temporary = 0;
    private PreparedContainerBindings $containerBindings;
    private ?PreparedContainerSourceCompilation $lastContainerCompilation = null;

    public function __construct(private readonly Compiler $semantic = new Compiler())
    {
        $this->containerBindings = new PreparedContainerBindings();
    }

    /**
     * Prepared parsing accepts canonical `bag Name { ... }` blocks by extracting
     * their compile-time discipline metadata and feeding `bag Name;` to the
     * existing typed semantic parser.
     */
    public function parse(string $source): Program
    {
        $unit = PreparedBagSource::prepare($source);
        return $this->semantic->parse($unit->rewrittenSource);
    }

    public function run(string $source): mixed
    {
        $unit = PreparedBagSource::prepare($source);
        if ($unit->bags !== []) {
            throw new SemanticException(
                'Canonical Bag discipline blocks use prepared/native container execution; compile with emitJxl() or compileContainerSource()',
                'runtime'
            );
        }
        return $this->semantic->run($source);
    }

    public function emitJxl(string $source): string
    {
        $unit = PreparedBagSource::prepare($source);
        if ($unit->bags !== []) return $this->compileContainerSourceUnit($unit)->jxl;

        $this->temporary = 0;
        $this->lastContainerCompilation = null;
        $program = $this->normalizeProgram($this->semantic->parse($source));
        return (new JxlEmitter())->emit($program);
    }

    public function emitProgram(Program $program): string
    {
        $this->temporary = 0;
        $this->lastContainerCompilation = null;
        return (new JxlEmitter())->emit($this->normalizeProgram($program));
    }

    /** Compile a canonical Bag/member-call source unit into pure container JXL. */
    public function compileContainerSource(string $source): PreparedContainerSourceCompilation
    {
        return $this->compileContainerSourceUnit(PreparedBagSource::prepare($source));
    }

    private function compileContainerSourceUnit(PreparedBagSourceUnit $unit): PreparedContainerSourceCompilation
    {
        if ($unit->bags === []) throw new SemanticException('No canonical Bag discipline declarations found', 'bag-source');
        $this->temporary = 0;
        $this->containerBindings = new PreparedContainerBindings();
        $program = $this->semantic->parse($unit->rewrittenSource);
        $compiled = (new PreparedContainerSourceCompiler($this->containerBindings))->compile($unit, $program);
        $this->lastContainerCompilation = $compiled;
        return $compiled;
    }

    public function lastContainerCompilation(): ?PreparedContainerSourceCompilation
    {
        return $this->lastContainerCompilation;
    }

    public function resetContainerBindings(): void
    {
        $this->containerBindings = new PreparedContainerBindings();
        $this->lastContainerCompilation = null;
    }

    public function containerBindings(): PreparedContainerBindings
    {
        return $this->containerBindings;
    }

    public function bindContainer(
        int $bagHandle,
        string $discipline,
        string $operation,
        int $width = 8,
        int $capacity = 0,
        int $mask = 0,
        int $flags = 0,
    ): PreparedContainerBinding {
        return $this->containerBindings->bind(
            $bagHandle,
            $discipline,
            $operation,
            $width,
            $capacity,
            $mask,
            $flags,
        );
    }

    public function emitContainer(
        PreparedContainerBinding $binding,
        ?int $src0 = null,
        ?int $src1 = null,
        ?int $dst = null,
    ): string {
        return JxlContainerInstruction::emit($binding, $src0, $src1, $dst);
    }

    public function containerBindingBinary(): string
    {
        return $this->containerBindings->binary();
    }

    public function containerBindingJson(): string
    {
        return $this->containerBindings->json();
    }

    public function normalizeProgram(Program $program): Program
    {
        $statements = array_map(fn(Node $n): Node => $this->node($n), $program->statements);
        $functions = [];
        foreach ($program->functions as $name => $fn) $functions[$name] = $this->node($fn);
        $classes = [];
        foreach ($program->classes as $name => $cl) $classes[$name] = $this->node($cl);
        return new Program($statements, $functions, $classes, $program->namespace, $program->imports);
    }

    private function node(Node $node): Node
    {
        if ($node->op === 'repeat') return $this->lowerRepeat($node);

        $data = [];
        foreach ($node->data as $key => $value) $data[$key] = $this->value($value);
        return new Node($node->op, $data, $node->type, $node->line);
    }

    private function value(mixed $value): mixed
    {
        if ($value instanceof Node) return $this->node($value);
        if (!is_array($value)) return $value;
        $out = [];
        foreach ($value as $key => $item) $out[$key] = $this->value($item);
        return $out;
    }

    private function lowerRepeat(Node $node): Node
    {
        // Evaluate the count once, exactly as semantic repeat does. The hidden
        // counter is decremented by the for-step, so continue still advances
        // the repeat and break still exits immediately.
        $name = "\0jx.repeat." . $this->temporary++;
        $var = fn(): Node => new Node('var', ['name' => $name], Type::INT, $node->line);
        $literal = fn(int $v): Node => new Node('literal', ['value' => $v], Type::INT, $node->line);

        $init = new Node('assign', [
            'target' => $var(),
            'operator' => '=',
            'value' => $this->node($node->data['count']),
        ], Type::INT, $node->line);

        $cond = new Node('binary', [
            'left' => $var(),
            'operator' => '>',
            'right' => $literal(0),
        ], Type::BOOL, $node->line);

        $step = new Node('assign', [
            'target' => $var(),
            'operator' => '-=',
            'value' => $literal(1),
        ], Type::INT, $node->line);

        return new Node('for', [
            'init' => $init,
            'cond' => $cond,
            'step' => $step,
            'body' => $this->node($node->data['body']),
        ], Type::VOID, $node->line);
    }
}
