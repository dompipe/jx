<?php declare(strict_types=1);

namespace jx\semantic;

require_once __DIR__ . '/jx-semantic.php';
require_once __DIR__ . '/jx-jxl-containers.php';

/**
 * Canonical semantic IR -> normalized prepared IR -> JXL.
 *
 * This pass owns lowering rewrites that should not leak into parser meaning or
 * the VM. Internal names contain NUL, which canonical source identifiers can
 * never spell, making generated temporaries hygienic.
 *
 * Container preparation is deliberately explicit at this layer: the compiler
 * resolves Bag discipline + canonical operation once, receives an
 * operation-specific native binding, and emits fixed-width JXL container
 * instructions that carry only binding IDs and local register selectors.
 */
final class PreparedCompiler
{
    private int $temporary = 0;
    private PreparedContainerBindings $containerBindings;

    public function __construct(private readonly Compiler $semantic = new Compiler())
    {
        $this->containerBindings = new PreparedContainerBindings();
    }

    public function parse(string $source): Program
    {
        return $this->semantic->parse($source);
    }

    public function run(string $source): mixed
    {
        return $this->semantic->run($source);
    }

    public function emitJxl(string $source): string
    {
        $this->temporary = 0;
        $program = $this->normalizeProgram($this->semantic->parse($source));
        return (new JxlEmitter())->emit($program);
    }

    public function emitProgram(Program $program): string
    {
        $this->temporary = 0;
        return (new JxlEmitter())->emit($this->normalizeProgram($program));
    }

    public function resetContainerBindings(): void
    {
        $this->containerBindings = new PreparedContainerBindings();
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
