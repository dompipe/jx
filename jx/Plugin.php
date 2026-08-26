<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/jx.php';
}

namespace jx {

/**
 * Minimal first-class plugin contract.
 *
 * Plugins extend JX without becoming part of the core object ontology. A
 * plugin advertises a stable id/version and exposes host-neutral capabilities.
 */
interface JxPlugin
{
    public function id(): string;
    public function version(): string;

    /** @return list<string> */
    public function capabilities(): array;
}

/**
 * Optional contract for a plugin that augments another plugin.
 *
 * Example: an audio-effects plugin may extend `media` without making equalizers,
 * compressors, or spatial processing part of the base MP3/MP4 contract.
 */
interface JxPluginExtension extends JxPlugin
{
    public function extendsPlugin(): string;

    /** @param array<string,mixed> $with
     *  @return array<string,mixed>
     */
    public function normalizeExtensionOptions(array $with): array;
}

final class Plugins
{
    /** @var array<string,JxPlugin> */
    private static array $plugins = [];

    public static function register(JxPlugin $plugin): void
    {
        $id = self::name($plugin->id());
        self::$plugins[$id] = $plugin;
    }

    public static function has(string $id): bool
    {
        return isset(self::$plugins[self::name($id)]);
    }

    public static function get(string $id): JxPlugin
    {
        $id = self::name($id);
        return self::$plugins[$id] ?? throw new JxException("Unknown JX plugin {$id}", 'plugin', true);
    }

    public static function isExtensionOf(string $child, string $parent): bool
    {
        $plugin = self::get($child);
        return $plugin instanceof JxPluginExtension
            && self::name($plugin->extendsPlugin()) === self::name($parent);
    }

    /** @return list<JxPluginExtension> */
    public static function extensionsFor(string $parent): array
    {
        $parent = self::name($parent);
        $out = [];
        foreach (self::$plugins as $plugin) {
            if ($plugin instanceof JxPluginExtension
                && self::name($plugin->extendsPlugin()) === $parent) {
                $out[] = $plugin;
            }
        }
        return $out;
    }

    /** @return array<string,array<string,mixed>> */
    public static function describe(): array
    {
        $out = [];
        foreach (self::$plugins as $id => $plugin) {
            $descriptor = [
                'id' => $id,
                'version' => $plugin->version(),
                'capabilities' => array_values($plugin->capabilities()),
            ];
            if ($plugin instanceof JxPluginExtension) {
                $descriptor['extends'] = self::name($plugin->extendsPlugin());
            }
            $out[$id] = $descriptor;
        }
        return $out;
    }

    private static function name(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || strlen($value) > 96 || preg_match('/[^a-z0-9._-]/', $value)) {
            throw new JxException('Invalid JX plugin id', 'plugin', true, ['plugin' => $value]);
        }
        return $value;
    }
}

}
