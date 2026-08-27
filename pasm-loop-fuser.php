<?php declare(strict_types=1);

namespace pasm\lang;

require_once __DIR__ . '/pasm-surface-loops.php';

/**
 * Post-lowering loop-block fuser.
 *
 * The bounded loop compiler intentionally emits semantically simple out-of-line
 * blocks first. This pass removes avoidable inter-block transfers without
 * changing canonical loop-space meaning.
 *
 * Current optimization:
 *   body -> JMP step_block
 *   step_block: <step>; JMP check
 * becomes:
 *   body -> fused_step: <step>; JMP check
 *
 * `continue` remains correct because all jumps to the former step symbol are
 * rewritten to the internal fused-step label.
 */
final class PASMLoopFuser
{
    public static function fuse(string $asm): string
    {
        $lines = preg_split('/\R/', $asm) ?: [];
        $blocks = self::indexBlocks($lines);

        foreach ($blocks as $label => $range) {
            if (!str_starts_with($label, '__jx_loop_step_')) continue;

            [$start, $end] = $range;
            $stepLines = array_slice($lines, $start + 1, $end - $start - 1);
            if ($stepLines === []) continue;

            $lastIndex = count($stepLines) - 1;
            while ($lastIndex >= 0 && trim($stepLines[$lastIndex]) === '') $lastIndex--;
            if ($lastIndex < 0) continue;
            if (!preg_match('/^\s*JMP\s+(\S+)\s*$/i', $stepLines[$lastIndex], $m)) continue;
            $continuation = $m[1];
            $payload = array_slice($stepLines, 0, $lastIndex);

            $bodyLabel = str_replace('__jx_loop_step_', '__jx_loop_body_', $label);
            if (!isset($blocks[$bodyLabel])) continue;
            [$bodyStart, $bodyEnd] = $blocks[$bodyLabel];

            $bodyJump = null;
            for ($i = $bodyEnd - 1; $i > $bodyStart; $i--) {
                if (trim($lines[$i]) === '') continue;
                if (preg_match('/^\s*JMP\s+' . preg_quote($label, '/') . '\s*$/i', $lines[$i])) {
                    $bodyJump = $i;
                }
                break;
            }
            if ($bodyJump === null) continue;

            $fusedStep = $label . '__fused';

            foreach ($lines as &$line) {
                $line = preg_replace(
                    '/(\bJMP\s+)' . preg_quote($label, '/') . '\b/i',
                    '$1' . $fusedStep,
                    $line
                ) ?? $line;
            }
            unset($line);

            $replacement = [$fusedStep . ':'];
            foreach ($payload as $p) $replacement[] = $p;
            $replacement[] = '        JMP   ' . $continuation;
            array_splice($lines, $bodyJump, 1, $replacement);

            $blocks = self::indexBlocks($lines);
            if (isset($blocks[$label])) {
                [$deadStart, $deadEnd] = $blocks[$label];
                array_splice($lines, $deadStart, $deadEnd - $deadStart);
            }

            return self::fuse(implode("\n", $lines));
        }

        return implode("\n", $lines);
    }

    /** @return array<string,array{0:int,1:int}> label => [start,endExclusive] */
    private static function indexBlocks(array $lines): array
    {
        $labels = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*):\s*$/', trim($line), $m)) {
                $labels[] = [$m[1], $i];
            }
        }
        $out = [];
        $n = count($lines);
        foreach ($labels as $k => [$label, $start]) {
            $end = $labels[$k + 1][1] ?? $n;
            $out[$label] = [$start, $end];
        }
        return $out;
    }
}

/** Active compiler facade: surface canonicalization -> bounded loops -> fusion. */
final class PASMFusedCompiler
{
    private Compiler $raw;
    private bool $optimize;
    private bool $verbose;

    public function __construct(
        bool $optimize = true,
        bool $verbose = false,
        int $maxLoopDepth = PASMLoopSpace::DEFAULT_MAX_DEPTH,
    ) {
        $this->raw = new Compiler($optimize, false, $maxLoopDepth);
        $this->optimize = $optimize;
        $this->verbose = $verbose;
    }

    public function compile(string $source): string
    {
        $source = PASLSurfaceLoops::lower($source);
        $asm = $this->raw->compile($source);
        if ($this->optimize) $asm = PASMLoopFuser::fuse($asm);
        if ($this->verbose) fwrite(STDERR, $asm . "\n");
        return $asm;
    }

    public function compileToBytecode(string $source): string
    {
        $asm = $this->compile($source);
        $assembler = $this->optimize
            ? new \pasm\PASMOptimizingAssembler(true)
            : new \pasm\PASMAssembler();
        try {
            return $assembler->compile($asm);
        } catch (\Throwable $e) {
            throw new LangException('Assemble failed: ' . $e->getMessage(), 'assemble', null, $e);
        }
    }

    public function compileToFile(string $source, string $path): string
    {
        $code = $this->compileToBytecode($source);
        $flags = $this->optimize ? PbcFile::FLAG_OPTIMIZED : 0;
        PbcFile::write($path, $code, $flags);
        return $code;
    }

    public function varMap(): array { return $this->raw->varMap(); }
    public function loopStats(): array { return $this->raw->loopStats(); }
}
