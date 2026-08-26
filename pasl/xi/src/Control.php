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
    public const XY_CENTER = 'XY_CENTER';
    public const XY_LT = 'XY_LT';
    public const XY_RT = 'XY_RT';
    public const XY_LB = 'XY_LB';
    public const XY_RB = 'XY_RB';

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
        if (array_key_exists('theme', $props)) {
            $props['theme'] = Theme::from($props['theme']);
        }
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

    /** @param array<string, mixed> $props */
    public static function toggle(string $id, string $label, bool $value = false, array $props = []): self
    {
        return self::make($id, 'toggle', $props + ['label' => $label, 'value' => $value]);
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

    /**
     * @param array{x:int|float,y:int|float} $start
     * @param array{x:int|float,y:int|float} $finish
     * @param array<string, mixed>|null $image
     */
    public static function line(string $refId, array $start, array $finish, bool $pong = false, ?array $image = null): array
    {
        $line = [
            'op' => 'line',
            'refId' => self::name($refId, 'line'),
            'start' => ['x' => (int)($start['x'] ?? 0), 'y' => (int)($start['y'] ?? 0)],
            'finish' => ['x' => (int)($finish['x'] ?? 0), 'y' => (int)($finish['y'] ?? 0)],
            'pong' => $pong,
        ];
        if ($image !== null) {
            $line['image'] = $image;
        }
        return $line;
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
        $outProperties = ['smooth' => $smooth];
        foreach (['spin', 'zoom', 'mash'] as $key) {
            if (is_array($properties[$key] ?? null)) {
                $outProperties[$key] = Theme::from($properties[$key]);
            }
        }
        return [
            'op' => 'curve',
            'refId' => self::name($refId, 'curve'),
            'degrees' => $points,
            'properties' => $outProperties,
        ];
    }

    /** @param array<string, mixed> $props */
    public static function image(string $id, string $label, string $src, string $mime = 'image/*', array $props = []): self
    {
        $props['pin'] = self::imagePinFrom($props['pin'] ?? []);
        $props['display'] = self::imageDisplayFrom($props['display'] ?? []);
        $props['imageSet'] = Image::setFrom($props['imageSet'] ?? []);
        $props['tradeOffs'] = Image::tradeOffsFrom($props['tradeOffs'] ?? []);
        return self::make($id, 'image', $props + [
            'label' => $label,
            'src' => $src,
            'mime' => $mime,
            'alt' => $label,
        ]);
    }

    /** @param array<string, mixed> $paintControl @return array{point:string,paintControl:array<string, mixed>} */
    public static function paintPoint(string $point = self::XY_CENTER, array $paintControl = []): array
    {
        return [
            'point' => self::xy($point),
            'paintControl' => $paintControl,
        ];
    }

    /** @return array{visible:bool,blur:int,cover:bool,coverLabel:string} */
    public static function imageDisplay(bool $visible = true, int|float $blur = 0, bool $cover = false, string $coverLabel = 'View hidden'): array
    {
        return [
            'visible' => $visible,
            'blur' => max(0, min(40, (int)$blur)),
            'cover' => $cover,
            'coverLabel' => $coverLabel,
        ];
    }

    /**
     * @param string|array<string, mixed> $paintingPoint
     * @param array<string, mixed>|null $paintControl
     * @return array{turningPoint:string,pathPoint:string,paintingPoint:string,paintControl:array<string, mixed>}
     */
    public static function imagePin(string $turningPoint = self::XY_CENTER, string $pathPoint = self::XY_CENTER, string|array $paintingPoint = self::XY_CENTER, ?array $paintControl = null): array
    {
        if (is_array($paintingPoint)) {
            $paintControl ??= is_array($paintingPoint['paintControl'] ?? null) ? $paintingPoint['paintControl'] : [];
            $paintingPoint = (string)($paintingPoint['point'] ?? self::XY_CENTER);
        }
        return [
            'turningPoint' => self::xy($turningPoint),
            'pathPoint' => self::xy($pathPoint),
            'paintingPoint' => self::xy($paintingPoint),
            'paintControl' => $paintControl ?? [],
        ];
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
        if (is_array($this->props['theme'] ?? null)) {
            $html = '<section class="jx-control" data-control="' . $json . '" data-theme="' . htmlspecialchars((string)json_encode($this->props['theme'], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '">';
        }
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
        } elseif ($this->type === 'toggle') {
            $checked = !empty($this->props['value']) ? ' checked' : '';
            $html .= '<input type="hidden" name="control[' . $id . ']" value="0">';
            $html .= '<input id="' . $id . '" type="checkbox" role="switch" name="control[' . $id . ']" value="1"' . $checked . '>';
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
            'toggle' => ['change', 'submit'],
            'drawing' => ['draw', 'clear', 'submit'],
            'image' => ['load', 'select', 'show', 'hide', 'blur', 'cover', 'submit'],
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
                $image = is_array($op['image'] ?? null) ? htmlspecialchars((string)json_encode($op['image'], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') : '';
                $imageAttr = $image !== '' ? ' data-image="' . $image . '"' : '';
                $theme = is_array($op['theme'] ?? null) ? htmlspecialchars((string)json_encode(Theme::from($op['theme']), JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') : '';
                $themeAttr = $theme !== '' ? ' data-motion-theme="' . $theme . '"' : '';
                $svg .= '<line data-ref="' . $ref . '"' . $pong . $imageAttr . $themeAttr . ' x1="' . (int)($start['x'] ?? 0) . '" y1="' . (int)($start['y'] ?? 0) . '" x2="' . (int)($finish['x'] ?? 0) . '" y2="' . (int)($finish['y'] ?? 0) . '" stroke="' . self::color((string)($op['stroke'] ?? '#111')) . '" stroke-width="' . max(1, (int)($op['width'] ?? 2)) . '"/>';
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
        $themePayload = array_intersect_key($properties, array_flip(['spin', 'zoom', 'mash']));
        $theme = $themePayload !== [] ? ' data-motion-theme="' . htmlspecialchars((string)json_encode(Theme::from($themePayload), JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '"' : '';
        return '<path data-ref="' . $ref . '" data-motion="curve" data-smooth="' . htmlspecialchars((string)$smooth, ENT_QUOTES, 'UTF-8') . '"' . $theme . ' d="' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '" fill="none" stroke="' . self::color((string)($op['stroke'] ?? '#7c3aed')) . '" stroke-width="' . max(1, (int)($op['width'] ?? 3)) . '"/>';
    }

    private function renderImage(): string
    {
        $id = htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8');
        $src = htmlspecialchars((string)($this->props['src'] ?? ''), ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars((string)($this->props['alt'] ?? $this->id), ENT_QUOTES, 'UTF-8');
        $mime = htmlspecialchars((string)($this->props['mime'] ?? 'image/*'), ENT_QUOTES, 'UTF-8');
        $pin = self::imagePinFrom($this->props['pin'] ?? []);
        $display = self::imageDisplayFrom($this->props['display'] ?? []);
        $turning = htmlspecialchars($pin['turningPoint'], ENT_QUOTES, 'UTF-8');
        $path = htmlspecialchars($pin['pathPoint'], ENT_QUOTES, 'UTF-8');
        $paint = htmlspecialchars($pin['paintingPoint'], ENT_QUOTES, 'UTF-8');
        $imageSet = Image::setFrom($this->props['imageSet'] ?? []);
        $tradeOffs = Image::tradeOffsFrom($this->props['tradeOffs'] ?? []);
        $imageSetJson = htmlspecialchars((string)json_encode($imageSet, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $tradeOffsJson = htmlspecialchars((string)json_encode($tradeOffs, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $visible = $display['visible'] ? 'true' : 'false';
        $cover = $display['cover'] ? 'true' : 'false';
        $coverLabel = htmlspecialchars($display['coverLabel'], ENT_QUOTES, 'UTF-8');
        $imgStyle = 'max-width:100%;height:auto';
        if ($display['blur'] > 0) {
            $imgStyle .= ';filter:blur(' . $display['blur'] . 'px)';
        }
        if (!$display['visible']) {
            $imgStyle .= ';visibility:hidden';
        }
        $paintControl = is_array($pin['paintControl'] ?? null) ? $pin['paintControl'] : [];
        $paintControlJson = htmlspecialchars((string)json_encode($paintControl, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
        $html = '<figure id="' . $id . '" data-mime="' . $mime . '" data-turning-point="' . $turning . '" data-path-point="' . $path . '" data-painting-point="' . $paint . '" data-paint-control="' . $paintControlJson . '" data-image-set="' . $imageSetJson . '" data-image-trade-offs="' . $tradeOffsJson . '" data-visible="' . $visible . '" data-blur="' . $display['blur'] . '" data-cover="' . $cover . '">';
        $html .= $this->renderPaintPreview($paintControl);
        $html .= '<img src="' . $src . '" alt="' . $alt . '" style="' . $imgStyle . '">';
        if ($display['cover']) {
            $html .= '<div class="image-cover" data-cover-label="' . $coverLabel . '">' . $coverLabel . '</div>';
        }
        $html .= '<figcaption>' . $alt . ' <code>' . $mime . '</code></figcaption></figure>';
        $html .= '<input type="hidden" name="control[' . $id . ']" value="' . $src . '">';
        return $html;
    }

    /** @param array<string, mixed> $paintControl */
    private function renderPaintPreview(array $paintControl): string
    {
        if ($paintControl === []) {
            return '';
        }
        $kind = (string)($paintControl['op'] ?? '');
        if ($kind !== 'line') {
            return '';
        }
        $start = is_array($paintControl['start'] ?? null) ? $paintControl['start'] : ['x' => 0, 'y' => 42];
        $finish = is_array($paintControl['finish'] ?? null) ? $paintControl['finish'] : ['x' => 220, 'y' => 42];
        $image = is_array($paintControl['image'] ?? null) ? $paintControl['image'] : [];
        $stroke = self::color((string)($paintControl['stroke'] ?? '#00f5ff'));
        $width = max(1, (int)($paintControl['width'] ?? 5));
        $glow = max(0.0, min(1.0, (float)($image['glow'] ?? $paintControl['glow'] ?? 0.75)));
        $ref = htmlspecialchars((string)($paintControl['refId'] ?? 'paint-line'), ENT_QUOTES, 'UTF-8');
        $pong = !empty($paintControl['pong']) ? ' data-pong="true"' : ' data-pong="false"';
        $imageJson = $image !== [] ? ' data-image="' . htmlspecialchars((string)json_encode($image, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '"' : '';
        $opacity = htmlspecialchars((string)(0.25 + ($glow * 0.55)), ENT_QUOTES, 'UTF-8');
        $svg = '<svg class="image-paint-preview" viewBox="0 0 240 56" width="240" height="56" aria-hidden="true">';
        $svg .= '<filter id="' . $ref . '-glow"><feGaussianBlur stdDeviation="' . htmlspecialchars((string)(2 + ($glow * 5)), ENT_QUOTES, 'UTF-8') . '" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>';
        $svg .= '<line data-ref="' . $ref . '"' . $pong . $imageJson . ' x1="' . (int)($start['x'] ?? 0) . '" y1="' . (int)($start['y'] ?? 0) . '" x2="' . (int)($finish['x'] ?? 0) . '" y2="' . (int)($finish['y'] ?? 0) . '" stroke="' . $stroke . '" stroke-width="' . ($width + 8) . '" opacity="' . $opacity . '" filter="url(#' . $ref . '-glow)"/>';
        $svg .= '<line data-ref="' . $ref . '-core" x1="' . (int)($start['x'] ?? 0) . '" y1="' . (int)($start['y'] ?? 0) . '" x2="' . (int)($finish['x'] ?? 0) . '" y2="' . (int)($finish['y'] ?? 0) . '" stroke="#ffffff" stroke-width="' . max(1, (int)ceil($width / 2)) . '"/>';
        $svg .= '</svg>';
        return $svg;
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

    /** @param mixed $display @return array{visible:bool,blur:int,cover:bool,coverLabel:string} */
    private static function imageDisplayFrom(mixed $display): array
    {
        $display = is_array($display) ? $display : [];
        return self::imageDisplay(
            array_key_exists('visible', $display) ? (bool)$display['visible'] : true,
            (int)($display['blur'] ?? 0),
            (bool)($display['cover'] ?? false),
            (string)($display['coverLabel'] ?? 'View hidden'),
        );
    }

    /** @param mixed $pin @return array{turningPoint:string,pathPoint:string,paintingPoint:string,paintControl:array<string, mixed>} */
    private static function imagePinFrom(mixed $pin): array
    {
        $pin = is_array($pin) ? $pin : [];
        return self::imagePin(
            (string)($pin['turningPoint'] ?? self::XY_CENTER),
            (string)($pin['pathPoint'] ?? self::XY_CENTER),
            is_array($pin['paintingPoint'] ?? null) ? $pin['paintingPoint'] : (string)($pin['paintingPoint'] ?? self::XY_CENTER),
            is_array($pin['paintControl'] ?? null) ? $pin['paintControl'] : null,
        );
    }

    private static function xy(string $value): string
    {
        $value = strtoupper(trim($value));
        return in_array($value, [self::XY_CENTER, self::XY_LT, self::XY_RT, self::XY_LB, self::XY_RB], true)
            ? $value
            : self::XY_CENTER;
    }
}

/**
 * Image-family payloads that can be attached to controls or drawing ops.
 */
final class Image
{
    public const IMG_DOTTED = 'IMG_DOTTED';
    public const IMG_BLUR = 'IMG_BLUR';
    public const ROLE_DIAL = 'dial';
    public const ROLE_BUTTON = 'button';
    public const ROLE_SWITCH = 'switch';

    /** @param array<string, mixed> $props @return array<string, mixed> */
    public static function img(string $filename, string $mime = 'image/*', array $props = []): array
    {
        return [
            'family' => 'image',
            'kind' => 'img',
            'filename' => self::filename($filename),
            'mime' => $mime !== '' ? $mime : 'image/*',
        ] + $props;
    }

    /** @param array<string, mixed> $props @return array<string, mixed> */
    public static function dotted(string $filename, string $mime = 'image/*', int|float $spacing = 24, array $props = []): array
    {
        return self::img($filename, $mime, $props + [
            'mode' => self::IMG_DOTTED,
            'spacing' => self::spacing($spacing),
        ]);
    }

    /** @param array<string, mixed> $props @return array<string, mixed> */
    public static function blur(string $filename, string $mime = 'image/*', int|float $every = 8, array $props = []): array
    {
        return self::img($filename, $mime, $props + [
            'mode' => self::IMG_BLUR,
            'every' => self::spacing($every),
        ]);
    }

    /** @param array<string, array<string, mixed>> $states @return array<string, mixed> */
    public static function replacementSet(string $role, array $states): array
    {
        $out = [
            'family' => 'image',
            'kind' => 'replacementSet',
            'role' => self::role($role),
            'states' => [],
        ];
        foreach ($states as $state => $image) {
            if (is_array($image)) {
                $out['states'][self::state((string)$state)] = self::imgFrom($image);
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $from @param array<string, mixed> $to @return array<string, mixed> */
    public static function tradeOff(string $eventId, string $event, array $from, array $to, string $reason = ''): array
    {
        return [
            'family' => 'image',
            'kind' => 'tradeOff',
            'eventId' => self::state($eventId),
            'event' => self::state($event),
            'from' => self::imgFrom($from),
            'to' => self::imgFrom($to),
            'reason' => $reason,
        ];
    }

    /** @param mixed $set @return array<string, mixed> */
    public static function setFrom(mixed $set): array
    {
        if (!is_array($set)) {
            return [];
        }
        if (($set['kind'] ?? '') === 'replacementSet') {
            $states = is_array($set['states'] ?? null) ? $set['states'] : [];
            return self::replacementSet((string)($set['role'] ?? self::ROLE_BUTTON), $states);
        }
        return $set;
    }

    /** @param mixed $tradeOffs @return list<array<string, mixed>> */
    public static function tradeOffsFrom(mixed $tradeOffs): array
    {
        if (!is_array($tradeOffs)) {
            return [];
        }
        $out = [];
        foreach ($tradeOffs as $tradeOff) {
            if (!is_array($tradeOff)) {
                continue;
            }
            if (($tradeOff['kind'] ?? '') === 'tradeOff') {
                $out[] = self::tradeOff(
                    (string)($tradeOff['eventId'] ?? 'event'),
                    (string)($tradeOff['event'] ?? 'image.trade'),
                    is_array($tradeOff['from'] ?? null) ? $tradeOff['from'] : [],
                    is_array($tradeOff['to'] ?? null) ? $tradeOff['to'] : [],
                    (string)($tradeOff['reason'] ?? ''),
                );
            }
        }
        return $out;
    }

    private static function filename(string $filename): string
    {
        $filename = trim($filename);
        return $filename !== '' ? substr($filename, 0, 512) : 'image';
    }

    private static function spacing(int|float $value): int
    {
        return max(1, min(1024, (int)$value));
    }

    /** @param array<string, mixed> $image @return array<string, mixed> */
    private static function imgFrom(array $image): array
    {
        return self::img(
            (string)($image['filename'] ?? 'image'),
            (string)($image['mime'] ?? 'image/*'),
            array_diff_key($image, array_flip(['family', 'kind', 'filename', 'mime'])),
        );
    }

    private static function role(string $role): string
    {
        $role = strtolower(trim($role));
        return in_array($role, [self::ROLE_DIAL, self::ROLE_BUTTON, self::ROLE_SWITCH], true)
            ? $role
            : self::ROLE_BUTTON;
    }

    private static function state(string $state): string
    {
        $state = preg_replace('/[^a-z0-9._:-]/i', '', trim($state)) ?? '';
        return $state !== '' ? substr($state, 0, 96) : 'state';
    }
}

/**
 * Theme-family motion contracts shared by controls and drawing paths.
 */
final class Theme
{
    /** @return array<string, mixed> */
    public static function spinClicks(string $controlId, int $fromDegree, int $toDegree, int $clicks, array $props = []): array
    {
        return [
            'family' => 'theme',
            'kind' => 'spinClicks',
            'controlId' => self::id($controlId),
            'fromDegree' => $fromDegree,
            'toDegree' => $toDegree,
            'clicks' => max(0, $clicks),
            'clicksPerDegree' => $toDegree !== $fromDegree ? abs($clicks / ($toDegree - $fromDegree)) : 0,
        ] + $props;
    }

    /** @return array<string, mixed> */
    public static function zoom(int|float $fromScale = 1.0, int|float $toScale = 1.0, string $easing = 'linear'): array
    {
        return [
            'family' => 'theme',
            'kind' => 'zoom',
            'fromScale' => self::scale($fromScale),
            'toScale' => self::scale($toScale),
            'easing' => self::id($easing),
        ];
    }

    /** @param list<array<string, mixed>> $motions @return array<string, mixed> */
    public static function mash(string $name, array $motions, string $mode = 'snowball'): array
    {
        $out = [
            'family' => 'theme',
            'kind' => 'mash',
            'name' => self::id($name),
            'mode' => self::id($mode),
            'motions' => [],
        ];
        foreach ($motions as $motion) {
            if (is_array($motion)) {
                $out['motions'][] = self::from($motion);
            }
        }
        return $out;
    }

    /** @param mixed $theme @return array<string, mixed> */
    public static function from(mixed $theme): array
    {
        if (!is_array($theme)) {
            return [];
        }
        $kind = (string)($theme['kind'] ?? '');
        if ($kind === 'spinClicks') {
            return self::spinClicks(
                (string)($theme['controlId'] ?? 'spin'),
                (int)($theme['fromDegree'] ?? 0),
                (int)($theme['toDegree'] ?? 0),
                (int)($theme['clicks'] ?? 0),
                array_diff_key($theme, array_flip(['family', 'kind', 'controlId', 'fromDegree', 'toDegree', 'clicks', 'clicksPerDegree'])),
            );
        }
        if ($kind === 'zoom') {
            return self::zoom(
                (float)($theme['fromScale'] ?? 1.0),
                (float)($theme['toScale'] ?? 1.0),
                (string)($theme['easing'] ?? 'linear'),
            );
        }
        if ($kind === 'mash') {
            return self::mash(
                (string)($theme['name'] ?? 'motion'),
                is_array($theme['motions'] ?? null) ? $theme['motions'] : [],
                (string)($theme['mode'] ?? 'snowball'),
            );
        }
        return $theme;
    }

    private static function id(string $value): string
    {
        $value = preg_replace('/[^a-z0-9._:-]/i', '', trim($value)) ?? '';
        return $value !== '' ? substr($value, 0, 96) : 'theme';
    }

    private static function scale(int|float $value): float
    {
        return max(0.01, min(100.0, (float)$value));
    }
}
