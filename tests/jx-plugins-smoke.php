<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/jx/bootstrap.php';

use jx\Bag;
use jx\JxException;
use jx\Plugins;
use jx\plugins\AudioAnalysisPlugin;
use jx\plugins\ChartsPlugin;
use jx\plugins\MediaPlugin;

function pluginSmoke(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('plugin smoke failed: ' . $message);
}

pluginSmoke(Plugins::has('charts'), 'Charts plugin registered');
pluginSmoke(Plugins::has('media'), 'Media plugin registered');
pluginSmoke(Plugins::has('audio-fx'), 'AudioFX plugin registered');
pluginSmoke(Plugins::has('audio-analysis'), 'Audio analysis plugin registered');
pluginSmoke(Plugins::isExtensionOf('audio-fx', 'media'), 'AudioFX extends Media');
pluginSmoke(in_array('chart.candles', Plugins::get('charts')->capabilities(), true), 'candles capability');
pluginSmoke(in_array('media.mp4', Plugins::get('media')->capabilities(), true), 'mp4 capability');
pluginSmoke(in_array('audio.frequency-buckets', Plugins::get('audio-analysis')->capabilities(), true), 'audio bucket capability');

$market = Bag::underwrite(32_768);
$binding = $market->bind('sql.market', 'ohlc-5m', 'candles', 'auto');

$candles = ChartsPlugin::candles(
    'price', 'market', 'candles',
    'time', 'open', 'high', 'low', 'close',
)->boundBy($binding);
$candleDesc = $candles->jsonSerialize();
pluginSmoke($candleDesc['type'] === 'candles', 'candles type');
pluginSmoke(($candleDesc['source']['binding'] ?? null) === $binding, 'candles binding id');
pluginSmoke(($candleDesc['source']['reactive'] ?? false) === true, 'candles reactive source');
pluginSmoke($candleDesc['fields']['close'] === 'close', 'candles close field');

$line = ChartsPlugin::line('trend', 'market', 'series', 'time', [
    ['field' => 'close', 'label' => 'Close'],
    ['field' => 'average', 'label' => 'Average'],
]);
pluginSmoke(count($line->fields()['series']) === 2, 'line multi-series');

$bar = ChartsPlugin::bar('volume', 'market', 'bars', 'time', 'volume');
pluginSmoke($bar->fields()['series'][0]['field'] === 'volume', 'bar series field');

$pie = ChartsPlugin::pie('mix', 'portfolio', 'allocation', 'symbol', 'value');
pluginSmoke($pie->fields()['label'] === 'symbol', 'pie label field');

$mp3 = MediaPlugin::mp3('theme', '/assets/theme.mp3', [
    'controls' => true,
    'loop' => true,
    'volume' => 0.75,
]);
$mp3Desc = $mp3->jsonSerialize();
pluginSmoke($mp3Desc['type'] === 'audio', 'mp3 becomes audio control');
pluginSmoke($mp3Desc['mime'] === 'audio/mpeg', 'mp3 MIME');
pluginSmoke(($mp3Desc['with']['volume'] ?? null) === 0.75, 'mp3 volume');
pluginSmoke(($mp3Desc['extensions'] ?? null) === [], 'base mp3 has no processing extensions');

$enhanced = $mp3->extend('audio-fx', [
    'gain_db' => 1.5,
    'bass_db' => 2.0,
    'treble_db' => 1.0,
    'stereo_width' => 1.1,
    'compressor' => true,
    // Deliberately unknown today: proves the extension owns open future fields.
    'future_tone_model' => 'warm-v2',
]);
$enhancedDesc = $enhanced->jsonSerialize();
pluginSmoke(count($enhancedDesc['extensions']) === 1, 'enhanced mp3 has one extension');
pluginSmoke(($enhancedDesc['extensions'][0]['plugin'] ?? null) === 'audio-fx', 'AudioFX extension id');
pluginSmoke(($enhancedDesc['extensions'][0]['with']['gain_db'] ?? null) === 1.5, 'AudioFX gain normalized');
pluginSmoke(($enhancedDesc['extensions'][0]['with']['future_tone_model'] ?? null) === 'warm-v2', 'AudioFX preserves future fields');

// Simple spectrum form: bucket count only, host chooses 0..Nyquist from the decoded stream.
$spectrum = AudioAnalysisPlugin::spectrum('theme', 'audio', 'spectrum', 24, [
    'measure' => 'db',
    'smoothing' => 0.75,
]);
$spectrumDesc = $spectrum->jsonSerialize();
pluginSmoke(($spectrumDesc['frequency']['buckets'] ?? null) === 24, 'bucket-only spectrum count');
pluginSmoke(($spectrumDesc['frequency']['range'] ?? null) === 'available', 'bucket-only spectrum uses available range');
pluginSmoke(!isset($spectrumDesc['frequency']['from'], $spectrumDesc['frequency']['to']), 'bucket-only spectrum omits named frequencies');
pluginSmoke(($spectrumDesc['target']['bag'] ?? null) === 'audio', 'spectrum targets Bag');
pluginSmoke(($spectrumDesc['target']['at'] ?? null) === 'spectrum', 'spectrum targets Bag node');

// Precision form: explicitly name a frequency window and still choose bucket count.
$rangedSpectrum = AudioAnalysisPlugin::spectrumRange(
    'theme', 'audio', 'voice', 80.0, 1200.0, 16,
    ['scale' => 'log', 'measure' => 'power'],
);
$rangedDesc = $rangedSpectrum->jsonSerialize();
pluginSmoke(($rangedDesc['frequency']['from'] ?? null) === 80.0, 'ranged spectrum from Hz');
pluginSmoke(($rangedDesc['frequency']['to'] ?? null) === 1200.0, 'ranged spectrum to Hz');
pluginSmoke(($rangedDesc['frequency']['buckets'] ?? null) === 16, 'ranged spectrum bucket count');
pluginSmoke(($rangedDesc['frequency']['scale'] ?? null) === 'log', 'ranged spectrum log scale');

// A chart consumes the analysis Bag; it does not know or care that Media produced it.
$spectrumBars = ChartsPlugin::bar(
    'spectrum-bars', 'audio', 'spectrum', 'bucket', 'value',
);
$spectrumBarDesc = $spectrumBars->jsonSerialize();
pluginSmoke(($spectrumBarDesc['source']['bag'] ?? null) === 'audio', 'spectrum bar uses analysis Bag');
pluginSmoke($spectrumBarDesc['fields']['x'] === 'bucket', 'spectrum bar x is bucket');
pluginSmoke($spectrumBarDesc['fields']['series'][0]['field'] === 'value', 'spectrum bar y is value');

$media = Bag::underwrite(16_384);
$mediaBinding = $media->bind('sql.library', 'current-video', 'uri', 'auto', ['as' => 'string']);
$mp4 = MediaPlugin::mp4FromBag('player', 'media', 'uri', [
    'controls' => true,
    'preload' => 'metadata',
    'poster' => '/assets/poster.webp',
])->boundBy($mediaBinding);
$mp4Desc = $mp4->jsonSerialize();
pluginSmoke($mp4Desc['type'] === 'video', 'mp4 becomes video control');
pluginSmoke($mp4Desc['mime'] === 'video/mp4', 'mp4 MIME');
pluginSmoke(($mp4Desc['source']['bag'] ?? null) === 'media', 'mp4 Bag source');
pluginSmoke(($mp4Desc['source']['binding'] ?? null) === $mediaBinding, 'mp4 binding id');
pluginSmoke(($mp4Desc['source']['reactive'] ?? false) === true, 'mp4 reactive source');
pluginSmoke(in_array('seek', $mp4Desc['events'], true), 'mp4 seek event');

$failed = false;
try {
    MediaPlugin::mp3('bad', 'javascript:alert(1)');
} catch (JxException) {
    $failed = true;
}
pluginSmoke($failed, 'executable media URI rejected');

$failed = false;
try {
    MediaPlugin::mp4FromBag('bad-binding', 'media', 'uri')->boundBy('not-a-binding');
} catch (JxException) {
    $failed = true;
}
pluginSmoke($failed, 'invalid media binding id rejected');

$failed = false;
try {
    $mp3->extend('audio-fx', ['bass_db' => 100]);
} catch (JxException) {
    $failed = true;
}
pluginSmoke($failed, 'invalid AudioFX range rejected');

$failed = false;
try {
    AudioAnalysisPlugin::spectrumRange('theme', 'audio', 'bad', 1000.0, 100.0, 8);
} catch (JxException) {
    $failed = true;
}
pluginSmoke($failed, 'invalid reversed frequency range rejected');

echo "jx-plugins-smoke: ok\n";
