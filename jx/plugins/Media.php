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
 * Host-neutral MP3/MP4 media Control.
 *
 * Media may come from an application asset/URL or from a Bag node. A Bag can
 * itself be bound to SQL, NoSQL, a channel, or another host data source. The
 * plugin never stores a live database/client handle.
 */
final class MediaControl implements JsonSerializable
{
    public const TYPES = ['audio', 'video'];

    /** @param array<string,mixed> $source
     *  @param array<string,mixed> $with
     */
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
        $this->mime = self::mime($this->mime, $this->type);
        $this->source = self::source($this->source);
        $this->with = self::options($this->with, $this->type);
    }

    public function id(): string { return $this->id; }
    public function type(): string { return $this->type; }
    public function mime(): string { return $this->mime; }
    public function source(): array { return $this->source; }
    public function options(): array { return $this->with; }

    /** Name the exact Bag binding returned by Bag::bind(). */
    public function boundBy(string $bindingId): self
    {
        if (($this->source['kind'] ?? '') !== 'bag') {
            throw new JxException('Only Bag-backed media can name a Bag binding', 'plugin.media', true);
        }
        $copy = clone $this;
        $copy->source['binding'] = self::bindingId($bindingId);
        return $copy;
    }

    public function styledBy(string $style): self
    {
        $copy = clone $this;
        $copy->with['style'] = self::name($style, 'style');
        return $copy;
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'kind' => 'control',
            'control' => 'media',
            'plugin' => 'media',
            'version' => MediaPlugin::VERSION,
            'id' => $this->id,
            'type' => $this->type,
            'mime' => $this->mime,
            'source' => $this->source,
            'with' => $this->with,
            'events' => ['play', 'pause', 'ended', 'time', 'seek', 'volume', 'error'],
        ];
    }

    /** @param array<string,mixed> $source
     *  @return array<string,mixed>
     */
    private static function source(array $source): array
    {
        $kind = strtolower(trim((string)($source['kind'] ?? '')));
        if ($kind === 'asset') {
            $uri = trim((string)($source['uri'] ?? ''));
            if ($uri === '' || strlen($uri) > 4096 || str_contains($uri, "\0")) {
                throw new JxException('Invalid media asset URI', 'plugin.media', true);
            }
            if (preg_match('/^\s*(?:javascript|vbscript):/i', $uri)) {
                throw new JxException('Executable URI schemes are not media sources', 'plugin.media', true);
            }
            return ['kind' => 'asset', 'uri' => $uri];
        }

        if ($kind === 'bag') {
            $bag = self::name((string)($source['bag'] ?? ''), 'Bag');
            $at = trim((string)($source['at'] ?? '_default'));
            if ($at === '' || strlen($at) > 256 || str_contains($at, "\0")) {
                throw new JxException('Invalid media Bag node', 'plugin.media', true);
            }
            $out = ['kind' => 'bag', 'bag' => $bag, 'at' => $at, 'reactive' => true];
            if (isset($source['binding'])) $out['binding'] = self::bindingId((string)$source['binding']);
            return $out;
        }

        throw new JxException('Media source must be asset or Bag', 'plugin.media', true, ['kind' => $kind]);
    }

    /** @param array<string,mixed> $with
     *  @return array<string,mixed>
     */
    private static function options(array $with, string $type): array
    {
        $with = Boundary::import($with);
        foreach (array_keys($with) as $key) {
            if (preg_match('/secret|password|token/i', (string)$key)) {
                throw new JxException('Secrets cannot be stored in media options', 'plugin.media', true, ['key' => $key]);
            }
        }

        foreach (['controls', 'autoplay', 'loop', 'muted'] as $flag) {
            if (array_key_exists($flag, $with)) $with[$flag] = (bool)$with[$flag];
        }

        if (isset($with['volume'])) {
            $volume = (float)$with['volume'];
            if (!is_finite($volume) || $volume < 0.0 || $volume > 1.0) {
                throw new JxException('Media volume must be between 0 and 1', 'plugin.media', true);
            }
            $with['volume'] = $volume;
        }

        if (isset($with['start'])) {
            $start = (float)$with['start'];
            if (!is_finite($start) || $start < 0.0) {
                throw new JxException('Media start time must be non-negative', 'plugin.media', true);
            }
            $with['start'] = $start;
        }

        if ($type === 'audio') unset($with['poster']);
        if (isset($with['preload']) && !in_array($with['preload'], ['none', 'metadata', 'auto'], true)) {
            throw new JxException('Unsupported media preload mode', 'plugin.media', true);
        }
        return $with;
    }

    private static function mime(string $mime, string $type): string
    {
        $mime = strtolower(trim($mime));
        $allowed = $type === 'audio'
            ? ['audio/mpeg', 'audio/mp3']
            : ['video/mp4'];
        if (!in_array($mime, $allowed, true)) {
            throw new JxException('Unsupported media MIME type', 'plugin.media', true, ['mime' => $mime]);
        }
        return $mime === 'audio/mp3' ? 'audio/mpeg' : $mime;
    }

    private static function name(string $value, string $what): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128 || preg_match('/[^a-z0-9._-]/i', $value)) {
            throw new JxException("Invalid {$what}", 'plugin.media', true, [$what => $value]);
        }
        return $value;
    }

    private static function bindingId(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-f0-9]{24}$/', $value)) {
            throw new JxException('Invalid Bag binding id for media', 'plugin.media', true, ['binding' => $value]);
        }
        return $value;
    }
}

final class MediaPlugin implements JxPlugin
{
    public const VERSION = 'jx.media/1';

    public function id(): string { return 'media'; }
    public function version(): string { return self::VERSION; }
    public function capabilities(): array { return ['media.mp3', 'media.mp4', 'media.audio', 'media.video']; }

    /** mp3($named, $from, $with): direct asset/URL. */
    public static function mp3(string $named, string $from, array $with = []): MediaControl
    {
        return new MediaControl($named, 'audio', 'audio/mpeg', ['kind' => 'asset', 'uri' => $from], $with);
    }

    /** mp4($named, $from, $with): direct asset/URL. */
    public static function mp4(string $named, string $from, array $with = []): MediaControl
    {
        return new MediaControl($named, 'video', 'video/mp4', ['kind' => 'asset', 'uri' => $from], $with);
    }

    /** mp3FromBag($named, $fromBag, $at, $with): reactive Bag URI/path. */
    public static function mp3FromBag(string $named, string $fromBag, string $at = '_default', array $with = []): MediaControl
    {
        return new MediaControl($named, 'audio', 'audio/mpeg', [
            'kind' => 'bag', 'bag' => $fromBag, 'at' => $at,
        ], $with);
    }

    /** mp4FromBag($named, $fromBag, $at, $with): reactive Bag URI/path. */
    public static function mp4FromBag(string $named, string $fromBag, string $at = '_default', array $with = []): MediaControl
    {
        return new MediaControl($named, 'video', 'video/mp4', [
            'kind' => 'bag', 'bag' => $fromBag, 'at' => $at,
        ], $with);
    }
}

Plugins::register(new MediaPlugin());

}
