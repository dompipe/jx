<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/jx/bootstrap.php';

use jx\Bag;
use jx\JxException;
use jx\Plugins;
use jx\plugins\ChartsPlugin;
use jx\plugins\MediaPlugin;

function pluginSmoke(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('plugin smoke failed: ' . $message);
}

pluginSmoke(Plugins::has('charts'), 'Charts plugin registered');
pluginSmoke(Plugins::has('media'), 'Media plugin registered');
pluginSmoke(in_array('chart.candles', Plugins::get('charts')->capabilities(), true), 'candles capability');
pluginSmoke(in_array('media.mp4', Plugins::get('media')->capabilities(), true), 'mp4 capability');

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

echo "jx-plugins-smoke: ok\n";
