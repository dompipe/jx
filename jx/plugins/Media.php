<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/Plugin.php';
    require_once dirname(__DIR__) . '/ControlBinding.php';
}

namespace jx\plugins {

use JsonSerializable;
use jx\Boundary;
use jx\ControlBinding;
use jx\JxException;
use jx\JxPlugin;
use jx\JxPluginExtension;
use jx\Plugins;

/**
 * Host-neutral audio/video Media control.
 *
 * Canonical Media is intentionally about media families, not individual codecs.
 * Convenience aliases such as mp3()/mp4() remain, while audio()/video() accept
 * any MIME family explicitly supported by the language contract. Hosts decide
 * which codecs they can decode and must report unsupported native capabilities.
 */
final class MediaControl implements JsonSerializable
{
    public const TYPES = ['audio', 'video'];
    public const AUDIO_MIMES = [
        'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/flac',
        'audio/ogg', 'audio/opus', 'audio/aac', 'audio/mp4', 'audio/webm',
    ];
    public const VIDEO_MIMES = [
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-m4v',
    ];
    public const ACTIONS = [
        'play', 'pause', 'toggle', 'stop',
        'seek', 'volume', 'muted', 'loop', 'rate', 'source',
    ];

    /** @var list<array{plugin:string,version:string,with:array<string,mixed>}> */
    private array $extensions = [];
    /** @var array<string,ControlBinding> */
    private array $controlBindings = [];

    /** @param array<string,mixed> $source @param array<string,mixed> $with */
    public function __construct(
        private string $id,
        private string $type,
        private string $mime,
        private array $source,
        private array $with = [],
    ) {
        $this->id = self::name($this->id, 'media id');
        $this->type = strtolower(trim($this->type));
        if (!in_array($this->type, self::TYPES, true)) {
            throw new JxException('Unsupported media type', 'plugin.media', true, ['type' => $this->type]);
        }
        $this->mime = self::normalizeMime($this->mime, $this->type);
        $this->source = self::normalizeSource($this->source);
        $this->with = self::normalizeOptions($this->with, $this->type);
    }

    public function id(): string { return $this->id; }
    public function type(): string { return $this->type; }
    public function mime(): string { return $this->mime; }
    public function source(): array { return $this->source; }
    public function options(): array { return $this->with; }
    public function extensions(): array { return $this->extensions; }
    /** @return list<ControlBinding> */
    public function controlBindings(): array { return array_values($this->controlBindings); }

    public function boundBy(string $bindingId): self
    {
        if (($this->source['kind'] ?? '') !== 'bag') {
            throw new JxException('Only Bag-backed media can name a Bag binding', 'plugin.media', true);
        }
        $copy = clone $this;
        $copy->source['binding'] = self::bindingId($bindingId);
        return $copy;
    }

    /** @param array<string,mixed> $with */
    public function listenTo(string $control, string $event, string $action, ?string $from = 'value', array $with = []): self
    {
        $action = strtolower(trim($action));
        if (!in_array($action, self::ACTIONS, true)) {
            throw new JxException('Unsupported Media control-binding action', 'plugin.media.control', true, ['action' => $action]);
        }
        if (in_array($action, ['play', 'pause', 'toggle', 'stop'], true)) $from = null;
        elseif ($from === null) throw new JxException('Media value action needs a Control value path', 'plugin.media.control', true, ['action' => $action]);

        if (!array_key_exists('as', $with)) {
            $with['as'] = match ($action) {
                'volume', 'seek', 'rate' => 'float',
                'muted', 'loop' => 'boolean',
                'source' => 'string',
                default => 'raw',
            };
        }
        $binding = new ControlBinding($control, $event, $this->id, $action, $from, $with);
        $copy = clone $this;
        $copy->controlBindings[$binding->id()] = $binding;
        return $copy;
    }

    public function listen(string $control, string $event, string $action, ?string $from = 'value', array $with = []): self
    { return $this->listenTo($control, $event, $action, $from, $with); }

    public function unlisten(string $bindingId): self
    {
        $bindingId = self::bindingId($bindingId);
        $copy = clone $this;
        unset($copy->controlBindings[$bindingId]);
        return $copy;
    }

    public function extend(string $plugin, array $with = []): self
    {
        $plugin = self::name($plugin, 'plugin');
        $descriptor = Plugins::get($plugin);
        if (!$descriptor instanceof JxPluginExtension || !Plugins::isExtensionOf($plugin, 'media')) {
            throw new JxException('Plugin does not extend Media', 'plugin.media', true, ['plugin' => $plugin]);
        }
        $options = $descriptor->normalizeExtensionOptions(self::safeOptions($with));
        $copy = clone $this;
        $copy->extensions[] = ['plugin' => $plugin, 'version' => $descriptor->version(), 'with' => $options];
        return $copy;
    }

    public function styledBy(string $style): self
    {
        $copy = clone $this;
        $copy->with['style'] = self::name($style, 'style');
        return $copy;
    }

    public function jsonSerialize(): array
    {
        return [
            'kind' => 'control', 'control' => 'media', 'plugin' => 'media',
            'version' => MediaPlugin::VERSION, 'id' => $this->id, 'type' => $this->type,
            'mime' => $this->mime, 'source' => $this->source, 'with' => $this->with,
            'controlBindings' => array_map(static fn(ControlBinding $b): array => $b->jsonSerialize(), array_values($this->controlBindings)),
            'extensions' => $this->extensions,
            'events' => ['play', 'pause', 'ended', 'time', 'seek', 'volume', 'metadata', 'frame', 'error'],
        ];
    }

    /** @param array<string,mixed> $source */
    private static function normalizeSource(array $source): array
    {
        $kind = strtolower(trim((string)($source['kind'] ?? '')));
        if ($kind === 'asset' || $kind === 'stream') {
            $uri = self::safeUri((string)($source['uri'] ?? ''));
            return ['kind' => $kind, 'uri' => $uri];
        }
        if ($kind === 'bag') {
            $bag = self::name((string)($source['bag'] ?? ''), 'Bag');
            $at = self::node((string)($source['at'] ?? '_default'), 'media Bag node');
            $out = ['kind' => 'bag', 'bag' => $bag, 'at' => $at, 'reactive' => true];
            if (isset($source['binding'])) $out['binding'] = self::bindingId((string)$source['binding']);
            return $out;
        }
        if ($kind === 'device') {
            $device = strtolower(trim((string)($source['device'] ?? '')));
            if (!in_array($device, ['microphone', 'camera', 'screen'], true)) {
                throw new JxException('Unsupported media device', 'plugin.media', true, ['device' => $device]);
            }
            $constraints = self::safeOptions(is_array($source['with'] ?? null) ? $source['with'] : []);
            return ['kind' => 'device', 'device' => $device, 'with' => $constraints, 'reactive' => true];
        }
        throw new JxException('Media source must be asset, stream, Bag, or device', 'plugin.media', true, ['kind' => $kind]);
    }

    private static function safeUri(string $uri): string
    {
        $uri = trim($uri);
        if ($uri === '' || strlen($uri) > 4096 || str_contains($uri, "\0")) throw new JxException('Invalid media URI', 'plugin.media', true);
        if (preg_match('/^\s*(?:javascript|vbscript):/i', $uri)) throw new JxException('Executable URI schemes are not media sources', 'plugin.media', true);
        return $uri;
    }

    private static function normalizeOptions(array $with, string $type): array
    {
        $with = self::safeOptions($with);
        foreach (['controls', 'autoplay', 'loop', 'muted', 'playsinline'] as $flag) if (array_key_exists($flag, $with)) $with[$flag] = (bool)$with[$flag];
        if (isset($with['volume'])) {
            $v = (float)$with['volume'];
            if (!is_finite($v) || $v < 0.0 || $v > 1.0) throw new JxException('Media volume must be between 0 and 1', 'plugin.media', true);
            $with['volume'] = $v;
        }
        if (isset($with['start'])) {
            $v = (float)$with['start'];
            if (!is_finite($v) || $v < 0.0) throw new JxException('Media start time must be non-negative', 'plugin.media', true);
            $with['start'] = $v;
        }
        if ($type === 'audio') unset($with['poster']);
        if (isset($with['preload']) && !in_array($with['preload'], ['none', 'metadata', 'auto'], true)) throw new JxException('Unsupported media preload mode', 'plugin.media', true);
        return $with;
    }

    private static function safeOptions(array $with): array
    {
        $with = Boundary::import($with);
        self::assertNoSecrets($with);
        return $with;
    }

    private static function assertNoSecrets(array $values, string $path = ''): void
    {
        foreach ($values as $key => $value) {
            $name = (string)$key; $full = $path === '' ? $name : $path . '.' . $name;
            if (preg_match('/secret|password|token/i', $name)) throw new JxException('Secrets cannot be stored in media descriptors', 'plugin.media', true, ['key' => $full]);
            if (is_array($value)) self::assertNoSecrets($value, $full);
        }
    }

    private static function normalizeMime(string $mime, string $type): string
    {
        $mime = strtolower(trim($mime));
        if ($mime === 'audio/mp3') $mime = 'audio/mpeg';
        $allowed = $type === 'audio' ? self::AUDIO_MIMES : self::VIDEO_MIMES;
        if (!in_array($mime, $allowed, true)) throw new JxException('Unsupported media MIME type', 'plugin.media', true, ['mime' => $mime, 'type' => $type]);
        return $mime;
    }

    private static function name(string $value, string $what): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128 || preg_match('/[^a-z0-9._-]/i', $value)) throw new JxException("Invalid {$what}", 'plugin.media', true, [$what => $value]);
        return $value;
    }

    private static function node(string $value, string $what): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 256 || str_contains($value, "\0")) throw new JxException("Invalid {$what}", 'plugin.media', true);
        return $value;
    }

    private static function bindingId(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-f0-9]{24}$/', $value)) throw new JxException('Invalid binding id for Media', 'plugin.media', true, ['binding' => $value]);
        return $value;
    }
}

final class MediaPlugin implements JxPlugin
{
    public const VERSION = 'jx.media/2';
    public function id(): string { return 'media'; }
    public function version(): string { return self::VERSION; }
    public function capabilities(): array
    {
        return [
            'media.audio', 'media.video', 'media.asset', 'media.stream', 'media.device',
            'media.microphone', 'media.camera', 'media.screen', 'media.bag-source',
            'media.mp3', 'media.mp4', 'media.control-bindings', 'media.extensions',
        ];
    }

    public static function audio(string $named, string $from, string $mime, array $with = []): MediaControl
    { return new MediaControl($named, 'audio', $mime, ['kind' => 'asset', 'uri' => $from], $with); }

    public static function video(string $named, string $from, string $mime, array $with = []): MediaControl
    { return new MediaControl($named, 'video', $mime, ['kind' => 'asset', 'uri' => $from], $with); }

    public static function audioStream(string $named, string $from, string $mime, array $with = []): MediaControl
    { return new MediaControl($named, 'audio', $mime, ['kind' => 'stream', 'uri' => $from], $with); }

    public static function videoStream(string $named, string $from, string $mime, array $with = []): MediaControl
    { return new MediaControl($named, 'video', $mime, ['kind' => 'stream', 'uri' => $from], $with); }

    public static function microphone(string $named, array $with = []): MediaControl
    { return new MediaControl($named, 'audio', 'audio/webm', ['kind' => 'device', 'device' => 'microphone', 'with' => $with], $with); }

    public static function camera(string $named, array $with = []): MediaControl
    { return new MediaControl($named, 'video', 'video/webm', ['kind' => 'device', 'device' => 'camera', 'with' => $with], $with); }

    public static function screen(string $named, array $with = []): MediaControl
    { return new MediaControl($named, 'video', 'video/webm', ['kind' => 'device', 'device' => 'screen', 'with' => $with], $with); }

    public static function mp3(string $named, string $from, array $with = []): MediaControl
    { return self::audio($named, $from, 'audio/mpeg', $with); }

    public static function mp4(string $named, string $from, array $with = []): MediaControl
    { return self::video($named, $from, 'video/mp4', $with); }

    public static function audioFromBag(string $named, string $fromBag, string $at, string $mime, array $with = []): MediaControl
    { return new MediaControl($named, 'audio', $mime, ['kind' => 'bag', 'bag' => $fromBag, 'at' => $at], $with); }

    public static function videoFromBag(string $named, string $fromBag, string $at, string $mime, array $with = []): MediaControl
    { return new MediaControl($named, 'video', $mime, ['kind' => 'bag', 'bag' => $fromBag, 'at' => $at], $with); }

    public static function mp3FromBag(string $named, string $fromBag, string $at = '_default', array $with = []): MediaControl
    { return self::audioFromBag($named, $fromBag, $at, 'audio/mpeg', $with); }

    public static function mp4FromBag(string $named, string $fromBag, string $at = '_default', array $with = []): MediaControl
    { return self::videoFromBag($named, $fromBag, $at, 'video/mp4', $with); }
}

Plugins::register(new MediaPlugin());

}
