<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\JxException;
use jx\Plugins;
use jx\plugins\ChartsPlugin;
use jx\plugins\MediaPlugin;
use jx\plugins\AudioAnalysisPlugin;
use jx\plugins\VideoAnalysisPlugin;

function media_test(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('media access test failed: ' . $message);
}

media_test(Plugins::has('media'), 'media registered');
media_test(Plugins::has('audio-analysis'), 'audio analysis registered');
media_test(Plugins::has('video-analysis'), 'video analysis registered');
media_test(Plugins::has('charts'), 'charts registered');

$flac = MediaPlugin::audio('lossless', '/music/master.flac', 'audio/flac');
media_test($flac->mime() === 'audio/flac', 'FLAC accepted');
media_test($flac->type() === 'audio', 'FLAC is audio family');

$wav = MediaPlugin::audio('wave', '/music/raw.wav', 'audio/wav');
media_test($wav->mime() === 'audio/wav', 'WAV accepted');

$opus = MediaPlugin::audio('voice', '/music/voice.opus', 'audio/opus');
media_test($opus->mime() === 'audio/opus', 'Opus accepted');

$webm = MediaPlugin::video('movie', '/video/movie.webm', 'video/webm');
media_test($webm->mime() === 'video/webm', 'WebM video accepted');

$quicktime = MediaPlugin::video('editorial', '/video/cut.mov', 'video/quicktime');
media_test($quicktime->mime() === 'video/quicktime', 'QuickTime accepted');

$stream = MediaPlugin::audioStream('radio', 'https://example.test/live.ogg', 'audio/ogg');
$streamDesc = $stream->jsonSerialize();
media_test(($streamDesc['source']['kind'] ?? null) === 'stream', 'stream source retained');

$mic = MediaPlugin::microphone('mic', ['autoplay' => true]);
$micDesc = $mic->jsonSerialize();
media_test(($micDesc['source']['kind'] ?? null) === 'device', 'microphone is device');
media_test(($micDesc['source']['device'] ?? null) === 'microphone', 'microphone device kind');
media_test($micDesc['type'] === 'audio', 'microphone is audio');

$camera = MediaPlugin::camera('camera', ['playsinline' => true]);
$cameraDesc = $camera->jsonSerialize();
media_test(($cameraDesc['source']['device'] ?? null) === 'camera', 'camera device kind');
media_test($cameraDesc['type'] === 'video', 'camera is video');

$screen = MediaPlugin::screen('screen');
$screenDesc = $screen->jsonSerialize();
media_test(($screenDesc['source']['device'] ?? null) === 'screen', 'screen capture device kind');

$spectrum = AudioAnalysisPlugin::spectrum('mic', 'analysis', 'spectrum', 64, ['measure' => 'db']);
$spectrumDesc = $spectrum->jsonSerialize();
media_test(($spectrumDesc['binding'] ?? null) === 'media.spectrum', 'spectrum binding kind');
media_test(($spectrumDesc['frequency']['buckets'] ?? null) === 64, 'spectrum bucket count');

$frames = VideoAnalysisPlugin::frames(
    'camera', 'analysis', 'frames', 50.0,
    ['brightness', 'luma', 'rgb', 'motion', 'scene-change'],
    ['history' => 120, 'scene_threshold' => 0.30],
);
$frameDesc = $frames->jsonSerialize();
media_test(($frameDesc['binding'] ?? null) === 'media.video.frames', 'video binding kind');
media_test(($frameDesc['sampling']['every_ms'] ?? null) === 50.0, 'video sampling interval');
media_test(in_array('motion', $frameDesc['measures'], true), 'video motion measure');
media_test(($frameDesc['rows']['scene_change'] ?? null) === 'scene_change', 'scene change output row');

$waveform = ChartsPlugin::waveform('waveform', 'analysis', 'wave', 'time', 'value');
media_test($waveform->type() === 'waveform', 'waveform chart type');
media_test($waveform->fields()['x'] === 'time', 'waveform time axis');

$spectrogram = ChartsPlugin::spectrogram('spectrogram', 'analysis', 'spectrum-history');
media_test($spectrogram->type() === 'heatmap', 'spectrogram lowers to heatmap');
media_test(($spectrogram->options()['semantic'] ?? null) === 'spectrogram', 'spectrogram semantic retained');
media_test($spectrogram->fields()['y'] === 'center', 'spectrogram frequency field');

$map = ChartsPlugin::vectormap('motion-map', 'analysis', 'geo', 'lat', 'lon', 'motion');
media_test($map->type() === 'vectormap', 'vectormap chart type');
media_test($map->fields()['latitude'] === 'lat', 'vectormap latitude field');

$failed = false;
try { MediaPlugin::audio('bad', '/bad.exe', 'application/octet-stream'); }
catch (JxException) { $failed = true; }
media_test($failed, 'unsupported MIME rejected');

$failed = false;
try { VideoAnalysisPlugin::frames('camera', 'analysis', 'bad', 0); }
catch (JxException) { $failed = true; }
media_test($failed, 'zero video sampling interval rejected');

$failed = false;
try { VideoAnalysisPlugin::frames('camera', 'analysis', 'bad', 100, 'telepathy'); }
catch (JxException) { $failed = true; }
media_test($failed, 'unknown video measure rejected');

echo "test-jx-media-access: ok\n";
