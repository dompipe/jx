<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/Plugin.php';
}

namespace jx\plugins {

use jx\JxException;
use jx\JxPluginExtension;
use jx\Plugins;

/**
 * Optional sound-processing extension for the Media plugin.
 *
 * This does not play audio by itself. A browser/native host may map these
 * descriptors to Web Audio, CoreAudio, WASAPI, PulseAudio/PipeWire, or another
 * processing backend. Unknown non-secret fields are deliberately preserved so
 * later versions can add processing vocabulary without changing MediaControl.
 */
final class AudioFxPlugin implements JxPluginExtension
{
    public const VERSION = 'jx.audio-fx/1';

    public function id(): string { return 'audio-fx'; }
    public function version(): string { return self::VERSION; }
    public function extendsPlugin(): string { return 'media'; }

    public function capabilities(): array
    {
        return [
            'audio.gain',
            'audio.eq',
            'audio.compressor',
            'audio.stereo-width',
            'audio.future-fields',
        ];
    }

    public function normalizeExtensionOptions(array $with): array
    {
        if (array_key_exists('enabled', $with)) $with['enabled'] = (bool)$with['enabled'];

        foreach (['gain_db' => [-60.0, 24.0], 'bass_db' => [-24.0, 24.0], 'mid_db' => [-24.0, 24.0], 'treble_db' => [-24.0, 24.0]] as $key => [$min, $max]) {
            if (!array_key_exists($key, $with)) continue;
            $value = (float)$with[$key];
            if (!is_finite($value) || $value < $min || $value > $max) {
                throw new JxException("AudioFX {$key} is out of range", 'plugin.audio-fx', true, ['key' => $key, 'value' => $with[$key]]);
            }
            $with[$key] = $value;
        }

        if (array_key_exists('stereo_width', $with)) {
            $width = (float)$with['stereo_width'];
            if (!is_finite($width) || $width < 0.0 || $width > 2.0) {
                throw new JxException('AudioFX stereo_width must be between 0 and 2', 'plugin.audio-fx', true);
            }
            $with['stereo_width'] = $width;
        }

        if (array_key_exists('compressor', $with)) {
            if (is_bool($with['compressor'])) {
                // true asks the host for its conservative/default compressor.
            } elseif (is_array($with['compressor'])) {
                $with['compressor'] = self::compressor($with['compressor']);
            } else {
                throw new JxException('AudioFX compressor must be boolean or an option map', 'plugin.audio-fx', true);
            }
        }

        return $with;
    }

    /** @param array<string,mixed> $with
     *  @return array<string,mixed>
     */
    private static function compressor(array $with): array
    {
        $ranges = [
            'threshold_db' => [-100.0, 0.0],
            'knee_db' => [0.0, 40.0],
            'ratio' => [1.0, 20.0],
            'attack' => [0.0, 1.0],
            'release' => [0.0, 1.0],
        ];
        foreach ($ranges as $key => [$min, $max]) {
            if (!array_key_exists($key, $with)) continue;
            $value = (float)$with[$key];
            if (!is_finite($value) || $value < $min || $value > $max) {
                throw new JxException("AudioFX compressor {$key} is out of range", 'plugin.audio-fx', true, ['key' => $key]);
            }
            $with[$key] = $value;
        }
        return $with;
    }
}

Plugins::register(new AudioFxPlugin());

}
