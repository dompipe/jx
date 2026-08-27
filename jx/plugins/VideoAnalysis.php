<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/Plugin.php';
}

namespace jx\plugins {

use JsonSerializable;
use jx\Boundary;
use jx\JxException;
use jx\JxPlugin;
use jx\Plugins;

/**
 * Host-neutral video frame analysis binding.
 *
 * Source: a Media control id.
 * Target: a Bag/node.
 * Host: performs frame sampling and publishes normalized rows.
 *
 * The Bag boundary keeps Charts, PASM and application logic independent from
 * browser Canvas/WebCodecs, Win32 Media Foundation, FFmpeg, GStreamer, etc.
 */
final class VideoFrameBinding implements JsonSerializable
{
    public const MEASURES = [
        'brightness', 'contrast', 'luma', 'rgb', 'motion', 'scene-change', 'dimensions', 'fps',
    ];

    private string $id;
    /** @var list<string> */
    private array $measures;
    /** @var array<string,mixed> */
    private array $with;

    /** @param string|list<string> $measures @param array<string,mixed> $with */
    public function __construct(
        private string $media,
        private string $bag,
        private string $at = '_default',
        private float $everyMs = 100.0,
        string|array $measures = ['brightness', 'luma', 'rgb', 'motion'],
        array $with = [],
    ) {
        $this->media = self::name($this->media, 'media');
        $this->bag = self::name($this->bag, 'Bag');
        $this->at = self::node($this->at);
        if (!is_finite($this->everyMs) || $this->everyMs <= 0.0 || $this->everyMs > 60_000.0) {
            throw new JxException('Video analysis interval must be > 0 and <= 60000 ms', 'plugin.video-analysis', true);
        }
        $list = is_array($measures) ? array_values($measures) : [$measures];
        if ($list === []) throw new JxException('Video analysis requires at least one measure', 'plugin.video-analysis', true);
        $clean = [];
        foreach ($list as $measure) {
            $measure = strtolower(trim((string)$measure));
            if (!in_array($measure, self::MEASURES, true)) {
                throw new JxException('Unsupported video analysis measure', 'plugin.video-analysis', true, ['measure' => $measure]);
            }
            $clean[$measure] = $measure;
        }
        $this->measures = array_values($clean);
        $this->with = self::normalizeOptions($with);
        $shape = [$this->media, $this->bag, $this->at, $this->everyMs, $this->measures, $this->with];
        $this->id = substr(hash('sha256', "video-frames-v1\0" . serialize($shape)), 0, 24);
    }

    public function id(): string { return $this->id; }
    public function media(): string { return $this->media; }
    public function bag(): string { return $this->bag; }
    public function at(): string { return $this->at; }
    public function everyMs(): float { return $this->everyMs; }
    public function measures(): array { return $this->measures; }
    public function options(): array { return $this->with; }

    public function jsonSerialize(): array
    {
        return [
            'kind' => 'binding',
            'binding' => 'media.video.frames',
            'plugin' => 'video-analysis',
            'version' => VideoAnalysisPlugin::VERSION,
            'id' => $this->id,
            'source' => ['media' => $this->media],
            'target' => ['bag' => $this->bag, 'at' => $this->at],
            'sampling' => ['every_ms' => $this->everyMs],
            'measures' => $this->measures,
            'reactive' => true,
            'rows' => [
                'frame' => 'frame',
                'time' => 'time',
                'fps' => 'fps',
                'width' => 'width',
                'height' => 'height',
                'brightness' => 'brightness',
                'contrast' => 'contrast',
                'luma' => 'luma',
                'red' => 'red',
                'green' => 'green',
                'blue' => 'blue',
                'motion' => 'motion',
                'scene_change' => 'scene_change',
            ],
            'with' => $this->with,
        ];
    }

    private static function normalizeOptions(array $with): array
    {
        $with = Boundary::import($with);
        foreach ($with as $key => $value) {
            if (preg_match('/secret|password|token/i', (string)$key)) {
                throw new JxException('Secrets cannot be stored in video analysis options', 'plugin.video-analysis', true, ['key' => $key]);
            }
        }
        $history = (int)($with['history'] ?? 1);
        if ($history < 1 || $history > 100_000) throw new JxException('Video analysis history must be 1..100000', 'plugin.video-analysis', true);
        $sceneThreshold = (float)($with['scene_threshold'] ?? 0.25);
        if (!is_finite($sceneThreshold) || $sceneThreshold < 0.0 || $sceneThreshold > 1.0) {
            throw new JxException('Video scene threshold must be between 0 and 1', 'plugin.video-analysis', true);
        }
        return ['history' => $history, 'scene_threshold' => $sceneThreshold];
    }

    private static function name(string $value, string $what): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128 || preg_match('/[^a-z0-9._-]/i', $value)) {
            throw new JxException("Invalid {$what} name", 'plugin.video-analysis', true, [$what => $value]);
        }
        return $value;
    }

    private static function node(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 256 || str_contains($value, "\0")) {
            throw new JxException('Invalid video analysis Bag node', 'plugin.video-analysis', true);
        }
        return $value;
    }
}

final class VideoAnalysisPlugin implements JxPlugin
{
    public const VERSION = 'jx.video-analysis/1';
    public function id(): string { return 'video-analysis'; }
    public function version(): string { return self::VERSION; }
    public function capabilities(): array
    {
        return [
            'video.frames', 'video.brightness', 'video.luma', 'video.rgb',
            'video.motion', 'video.scene-change', 'video.dimensions', 'video.fps',
        ];
    }

    /** @param string|list<string> $measures @param array<string,mixed> $with */
    public static function frames(
        string $fromMedia,
        string $toBag,
        string $at = '_default',
        float $everyMs = 100.0,
        string|array $measures = ['brightness', 'luma', 'rgb', 'motion'],
        array $with = [],
    ): VideoFrameBinding {
        return new VideoFrameBinding($fromMedia, $toBag, $at, $everyMs, $measures, $with);
    }
}

Plugins::register(new VideoAnalysisPlugin());

}
