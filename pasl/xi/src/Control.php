<?php declare(strict_types=1);

/**
 * Contractual window control.
 *
 * Controls are host-neutral descriptions first. The HTML renderer is the
 * current transport, while native hosts can read the same contract and render
 * real OS controls.
 */
final class Control
{
    /** @param array<string, mixed> $props */
    private function __construct(
        private string $id,
        private string $type,
        private array $props = [],
    ) {}

    /** @param array<string, mixed> $props */
    public static function make(string $id, string $type, array $props = []): self
    {
        $id = self::name($id, 'control');
        $type = self::name($type, 'text');
        return new self($id, $type, $props);
    }

    /** @param array<string, mixed> $props */
    public static function text(string $id, string $label, string $value = '', array $props = []): self
    {
        return self::make($id, 'text', $props + ['label' => $label, 'value' => $value]);
    }

    /** @param array<string, mixed> $props */
    public static function spin(string $id, string $label, int|float $value = 0, array $props = []): self
    {
        return self::make($id, 'spin', $props + ['label' => $label, 'value' => $value, 'step' => 1, 'pin' => false]);
    }

    /** @param list<array<string, mixed>> $ops */
    public static function drawing(string $id, string $label, int $width, int $height, array $ops = []): self
    {
        return self::make($id, 'drawing', [
            'label' => $label,
            'width' => max(16, $width),
            'height' => max(16, $height),
            'ops' => $ops,
        ]);
    }

    /** @param array{x:int|float,y:int|float} $start @param array{x:int|float,y:int|float} $finish */
    public static function line(string $refId, array $start, array $finish, bool $pong = false): array
    {
        return [
            'op' => 'line',
            'refId' => self::name($refId, 'line'),
            'start' => ['x' => (int)($start['x'] ?? 0), 'y' => (int)($start['y'] ?? 0)],
            'finish' => ['x' => (int)($finish['x'] ?? 0), 'y' => (int)($finish['y'] ?? 0)],
            'pong' => $pong,
        ];
    }

    /**
     * @param array<string, mixed>|array{x:int|float,y:int|float} $properties
     * @param array<int, array{x:int|float,y:int|float}> $degrees
     */
    public static function curve(string $refId, array ...$degrees): array
    {
        $properties = [];
        if ($degrees !== [] && !array_key_exists('x', $degrees[0]) && !array_key_exists('y', $degrees[0])) {
            $properties = array_shift($degrees);
        }
        $points = [];
        foreach ($degrees as $degree) {
            $points[] = [
                'x' => (int)($degree['x'] ?? 0),
                'y' => (int)($degree['y'] ?? 0),
            ];
        }
        $smooth = max(0.0, min(1.0, (float)($properties['smooth'] ?? 0.0)));
        return [
            'op' => 'curve',
            'refId' => self::name($refId, 'curve'),
            'degrees' => $points,
            'properties' => ['smooth' => $smooth],
        ];
    }

    /** @param array<string, mixed> $props */
    public static function image(string $id, string $label, string $src, string $mime = 'image/*', array $props = []): self
    {
        return self::make($id, 'image', $props + [
            'label' => $label,
            'src' => $src,
            'mime' => $mime,
            'alt' => $label,
        ]);
    }

    /** @return array<string, mixed> */
    public function contract(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'props' => $this->props,
            'events' => $this->events(),
        ];
    }

    public function render(): string
    {
        $c = $this->contract();
        $json = htmlspecialchars((string)json_encode($c, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars((string)($this->props['label'] ?? $this->id), ENT_QUOTES, 'UTF-8');
        $html = '<section class="jx-control" data-control="' . $json . '">';
        $html .= '<label for="' . $id . '"><strong>' . $label . '</strong></label>';

        if ($this->type === 'text') {
            $value = htmlspecialchars((string)($this->props['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<input id="' . $id . '" name="control[' . $id . ']" value="' . $value . '">';
        } elseif ($this->type === 'spin') {
            $value = htmlspecialchars((string)($this->props['value'] ?? 0), ENT_QUOTES, 'UTF-8');
            $step = htmlspecialchars((string)($this->props['step'] ?? 1), ENT_QUOTES, 'UTF-8');
            $pin = !empty($this->props['pin']) ? ' data-pin="true"' : ' data-pin="false"';
            $min = array_key_exists('min', $this->props) ? ' min="' . htmlspecialchars((string)$this->props['min'], ENT_QUOTES, 'UTF-8') . '"' : '';
            $max = array_key_exists('max', $this->props) ? ' max="' . htmlspecialchars((string)$this->props['max'], ENT_QUOTES, 'UTF-8') . '"' : '';
            $html .= '<input id="' . $id . '" type="number" name="control[' . $id . ']" value="' . $value . '" step="' . $step . '"' . $min . $max . $pin . '>';
            if (!empty($this->props['pin'])) {
                $html .= '<input type="hidden" name="control[' . $id . '.pin]" value="1">';
            }
        } elseif ($this->type === 'drawing') {
            $html .= $this->renderDrawing();
        } elseif ($this->type === 'image') {
            $html .= $this->renderImage();
        } else {
            $html .= '<output id="' . $id . '">unsupported control</output>';
        }

        $html .= '<pre class="control-contract">' . $json . '</pre>';
        $html .= '</section>';
        return $html;
    }

    /** @return list<string> */
    private function events(): array
    {
        return match ($this->type) {
            'text' => ['change', 'submit'],
            'spin' => ['change', 'increment', 'decrement', 'submit'],
            'drawing' => ['draw', 'clear', 'submit'],
            'image' => ['load', 'select', 'submit'],
            default => ['submit'],
        };
    }

    private function renderDrawing(): string
    {
        $id = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $w = max(16, (int)($this->props['width'] ?? 320));
        $h = max(16, (int)($this->props['height'] ?? 180));
        $svg = '<svg id="' . $id . '" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" role="img" aria-label="' . htmlspecialchars((string)($this->props['label'] ?? $this->id), ENT_QUOTES, 'UTF-8') . '">';
        $svg .= '<rect width="100%" height="100%" fill="#fff"/>';
        foreach (($this->props['ops'] ?? []) as $op) {
            if (!is_array($op)) {
                continue;
            }
            $kind = (string)($op['op'] ?? '');
            if ($kind === 'line') {
                $start = is_array($op['start'] ?? null) ? $op['start'] : ['x' => $op['x1'] ?? 0, 'y' => $op['y1'] ?? 0];
                $finish = is_array($op['finish'] ?? null) ? $op['finish'] : ['x' => $op['x2'] ?? 0, 'y' => $op['y2'] ?? 0];
                $pong = !empty($op['pong']) ? ' data-pong="true"' : ' data-pong="false"';
                $ref = htmlspecialchars((string)($op['refId'] ?? 'line'), ENT_QUOTES, 'UTF-8');
                $svg .= '<line data-ref="' . $ref . '"' . $pong . ' x1="' . (int)($start['x'] ?? 0) . '" y1="' . (int)($start['y'] ?? 0) . '" x2="' . (int)($finish['x'] ?? 0) . '" y2="' . (int)($finish['y'] ?? 0) . '" stroke="' . self::color((string)($op['stroke'] ?? '#111')) . '" stroke-width="' . max(1, (int)($op['width'] ?? 2)) . '"/>';
            } elseif ($kind === 'circle') {
                $svg .= '<circle cx="' . (int)($op['cx'] ?? 0) . '" cy="' . (int)($op['cy'] ?? 0) . '" r="' . max(1, (int)($op['r'] ?? 8)) . '" fill="' . self::color((string)($op['fill'] ?? '#777')) . '"/>';
            } elseif ($kind === 'rect') {
                $svg .= '<rect x="' . (int)($op['x'] ?? 0) . '" y="' . (int)($op['y'] ?? 0) . '" width="' . max(1, (int)($op['width'] ?? 16)) . '" height="' . max(1, (int)($op['height'] ?? 16)) . '" fill="' . self::color((string)($op['fill'] ?? '#ddd')) . '"/>';
            } elseif ($kind === 'curve') {
                $svg .= $this->renderCurve($op);
            }
        }
        $svg .= '</svg>';
        $svg .= '<input type="hidden" name="control[' . $id . ']" value="drawing-contract">';
        return $svg;
    }

    /** @param array<string, mixed> $op */
    private function renderCurve(array $op): string
    {
        $degrees = is_array($op['degrees'] ?? null) ? array_values($op['degrees']) : [];
        if (count($degrees) < 2) {
            return '';
        }
        $first = $degrees[0];
        $d = 'M ' . (int)($first['x'] ?? 0) . ' ' . (int)($first['y'] ?? 0);
        if (count($degrees) === 2) {
            $p = $degrees[1];
            $d .= ' L ' . (int)($p['x'] ?? 0) . ' ' . (int)($p['y'] ?? 0);
        } elseif (count($degrees) === 3) {
            $c = $degrees[1];
            $p = $degrees[2];
            $d .= ' Q ' . (int)($c['x'] ?? 0) . ' ' . (int)($c['y'] ?? 0) . ' ' . (int)($p['x'] ?? 0) . ' ' . (int)($p['y'] ?? 0);
        } else {
            $c1 = $degrees[1];
            $c2 = $degrees[2];
            $p = $degrees[3];
            $d .= ' C ' . (int)($c1['x'] ?? 0) . ' ' . (int)($c1['y'] ?? 0) . ' ' . (int)($c2['x'] ?? 0) . ' ' . (int)($c2['y'] ?? 0) . ' ' . (int)($p['x'] ?? 0) . ' ' . (int)($p['y'] ?? 0);
            for ($i = 4; $i < count($degrees); $i++) {
                $p = $degrees[$i];
                $d .= ' L ' . (int)($p['x'] ?? 0) . ' ' . (int)($p['y'] ?? 0);
            }
        }
        $ref = htmlspecialchars((string)($op['refId'] ?? 'curve'), ENT_QUOTES, 'UTF-8');
        $properties = is_array($op['properties'] ?? null) ? $op['properties'] : [];
        $smooth = max(0.0, min(1.0, (float)($properties['smooth'] ?? 0.0)));
        return '<path data-ref="' . $ref . '" data-motion="curve" data-smooth="' . htmlspecialchars((string)$smooth, ENT_QUOTES, 'UTF-8') . '" d="' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '" fill="none" stroke="' . self::color((string)($op['stroke'] ?? '#7c3aed')) . '" stroke-width="' . max(1, (int)($op['width'] ?? 3)) . '"/>';
    }

    private function renderImage(): string
    {
        $id = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $src = htmlspecialchars((string)($this->props['src'] ?? ''), ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars((string)($this->props['alt'] ?? $this->id), ENT_QUOTES, 'UTF-8');
        $mime = htmlspecialchars((string)($this->props['mime'] ?? 'image/*'), ENT_QUOTES, 'UTF-8');
        $html = '<figure id="' . $id . '" data-mime="' . $mime . '"><img src="' . $src . '" alt="' . $alt . '" style="max-width:100%;height:auto">';
        $html .= '<figcaption>' . $alt . ' <code>' . $mime . '</code></figcaption></figure>';
        $html .= '<input type="hidden" name="control[' . $id . ']" value="' . $src . '">';
        return $html;
    }

    private static function name(string $value, string $fallback): string
    {
        $value = preg_replace('/[^a-z0-9._-]/i', '', $value) ?? '';
        return $value !== '' ? substr($value, 0, 96) : $fallback;
    }

    private static function color(string $value): string
    {
        return preg_match('/^#[0-9a-f]{3,8}$/i', $value) ? $value : '#111';
    }
}
