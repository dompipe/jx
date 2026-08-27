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
 * Host-neutral live audio spectrum binding.
 *
 * Source: a Media control id.
 * Target: a named Bag/node.
 *
 * The simple form names only the number of buckets. The host divides the
 * available decoded spectrum from 0 Hz through Nyquist (sampleRate / 2).
 * The precise form may additionally name an explicit from/to frequency range.
 *
 * Hosts perform the FFT/analyser work and publish rows into the target Bag.
 * Charts consume those rows like any other reactive Bag data.
 */
final class SpectrumBinding implements JsonSerializable
{
    private string $id;
    private string $media;
    private string $bag;
    private string $at;
    private int $buckets;
    private ?float $fromHz;
    private ?float $toHz;
    /** @var array<string,mixed> */
    private array $with;

    /** @param array<string,mixed> $with */
    public function __construct(
        string $media,
        string $bag,
        string $at = '_default',
        int $buckets = 32,
        ?float $fromHz = null,
        ?float $toHz = null,
        array $with = [],
    ) {
        $this->media = self::name($media, 'media');
        $this->bag = self::name($bag, 'Bag');
        $this->at = self::node($at);

        if ($buckets < 1 || $buckets > 4096) {
            throw new JxException('Audio spectrum buckets must be between 1 and 4096', 'plugin.audio-analysis', true);
        }
        $this->buckets = $buckets;

        if (($fromHz === null) !== ($toHz === null)) {
            throw new JxException('Audio frequency range requires both from and to', 'plugin.audio-analysis', true);
        }
        if ($fromHz !== null && $toHz !== null) {
            if (!is_finite($fromHz) || !is_finite($toHz) || $fromHz < 0.0 || $toHz <= $fromHz) {
                throw new JxException('Invalid audio frequency range', 'plugin.audio-analysis', true, [
                    'from' => $fromHz,
                    'to' => $toHz,
                ]);
            }
            if ($toHz > 384000.0) {
                throw new JxException('Audio frequency range is unreasonably high', 'plugin.audio-analysis', true, ['to' => $toHz]);
            }
        }
        $this->fromHz = $fromHz;
        $this->toHz = $toHz;
        $this->with = self::normalizeOptions($with);

        $shape = [
            'media' => $this->media,
            'bag' => $this->bag,
            'at' => $this->at,
            'buckets' => $this->buckets,
            'from' => $this->fromHz,
            'to' => $this->toHz,
            'with' => $this->with,
        ];
        $this->id = substr(hash('sha256', 'audio-spectrum-v1\0' . serialize($shape)), 0, 24);
    }

    public function id(): string { return $this->id; }
    public function media(): string { return $this->media; }
    public function bag(): string { return $this->bag; }
    public function at(): string { return $this->at; }
    public function buckets(): int { return $this->buckets; }
    public function fromHz(): ?float { return $this->fromHz; }
    public function toHz(): ?float { return $this->toHz; }
    public function options(): array { return $this->with; }

    public function ranged(): bool
    {
        return $this->fromHz !== null && $this->toHz !== null;
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        $frequency = [
            'buckets' => $this->buckets,
            'scale' => $this->with['scale'],
        ];
        if ($this->ranged()) {
            $frequency['from'] = $this->fromHz;
            $frequency['to'] = $this->toHz;
        } else {
            $frequency['range'] = 'available';
        }

        return [
            'kind' => 'binding',
            'binding' => 'media.spectrum',
            'plugin' => 'audio-analysis',
            'version' => AudioAnalysisPlugin::VERSION,
            'id' => $this->id,
            'source' => ['media' => $this->media],
            'target' => ['bag' => $this->bag, 'at' => $this->at],
            'frequency' => $frequency,
            'measure' => $this->with['measure'],
            'smoothing' => $this->with['smoothing'],
            'reactive' => true,
            'rows' => [
                'bucket' => 'bucket',
                'from' => 'from',
                'to' => 'to',
                'center' => 'center',
                'value' => 'value',
            ],
        ];
    }

    /** @param array<string,mixed> $with
     *  @return array<string,mixed>
     */
    private static function normalizeOptions(array $with): array
    {
        $with = Boundary::import($with);
        foreach (array_keys($with) as $key) {
            if (preg_match('/secret|password|token/i', (string)$key)) {
                throw new JxException('Secrets cannot be stored in audio analysis options', 'plugin.audio-analysis', true, ['key' => $key]);
            }
        }

        $scale = strtolower(trim((string)($with['scale'] ?? 'linear')));
        if (!in_array($scale, ['linear', 'log'], true)) {
            throw new JxException('Audio spectrum scale must be linear or log', 'plugin.audio-analysis', true);
        }

        $measure = strtolower(trim((string)($with['measure'] ?? 'db')));
        if (!in_array($measure, ['amplitude', 'power', 'db'], true)) {
            throw new JxException('Unsupported audio spectrum measure', 'plugin.audio-analysis', true);
        }

        $smoothing = (float)($with['smoothing'] ?? 0.8);
        if (!is_finite($smoothing) || $smoothing < 0.0 || $smoothing > 1.0) {
            throw new JxException('Audio spectrum smoothing must be between 0 and 1', 'plugin.audio-analysis', true);
        }

        return [
            'scale' => $scale,
            'measure' => $measure,
            'smoothing' => $smoothing,
        ];
    }

    private static function name(string $value, string $what): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128 || preg_match('/[^a-z0-9._-]/i', $value)) {
            throw new JxException("Invalid {$what} name", 'plugin.audio-analysis', true, [$what => $value]);
        }
        return $value;
    }

    private static function node(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 256 || str_contains($value, "\0")) {
            throw new JxException('Invalid audio analysis Bag node', 'plugin.audio-analysis', true);
        }
        return $value;
    }
}

final class AudioAnalysisPlugin implements JxPlugin
{
    public const VERSION = 'jx.audio-analysis/1';

    public function id(): string { return 'audio-analysis'; }
    public function version(): string { return self::VERSION; }
    public function capabilities(): array { return ['audio.spectrum', 'audio.frequency-buckets', 'audio.frequency-range']; }

    /**
     * spectrum($fromMedia, $toBag, $at, $buckets, $with)
     *
     * No frequency names required: divide the host's available spectrum into
     * the requested number of buckets.
     */
    public static function spectrum(
        string $fromMedia,
        string $toBag,
        string $at = '_default',
        int $buckets = 32,
        array $with = [],
    ): SpectrumBinding {
        return new SpectrumBinding($fromMedia, $toBag, $at, $buckets, null, null, $with);
    }

    /**
     * spectrumRange($fromMedia, $toBag, $at, $fromHz, $toHz, $buckets, $with)
     */
    public static function spectrumRange(
        string $fromMedia,
        string $toBag,
        string $at,
        float $fromHz,
        float $toHz,
        int $buckets = 32,
        array $with = [],
    ): SpectrumBinding {
        return new SpectrumBinding($fromMedia, $toBag, $at, $buckets, $fromHz, $toHz, $with);
    }
}

Plugins::register(new AudioAnalysisPlugin());

}
