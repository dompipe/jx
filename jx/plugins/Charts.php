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
 * Host-neutral chart Control.
 *
 * Charts consume Bag rows. They intentionally do not know whether those rows
 * came from SQL, PASM, audio analysis, video analysis, sensors, or application
 * state. Rendering remains a host concern.
 */
final class ChartControl implements JsonSerializable
{
    public const TYPES = ['pie', 'candles', 'bar', 'line', 'waveform', 'heatmap', 'vectormap'];
    private ?string $binding = null;

    /** @param array<string,mixed> $fields @param array<string,mixed> $with */
    public function __construct(
        private string $id,
        private string $type,
        private string $bag,
        private string $at,
        private array $fields,
        private array $with = [],
    ) {
        $this->id = self::name($this->id, 'chart id');
        $this->type = strtolower(trim($this->type));
        if (!in_array($this->type, self::TYPES, true)) throw new JxException('Unsupported chart type', 'plugin.charts', true, ['type' => $this->type]);
        $this->bag = self::name($this->bag, 'Bag');
        $this->at = self::node($this->at);
        $this->fields = self::validateFields($this->type, $this->fields);
        $this->with = self::validateOptions($this->with);
        if (isset($this->with['binding'])) {
            $this->binding = self::bindingId((string)$this->with['binding']);
            unset($this->with['binding']);
        }
    }

    public function id(): string { return $this->id; }
    public function type(): string { return $this->type; }
    public function bag(): string { return $this->bag; }
    public function at(): string { return $this->at; }
    public function binding(): ?string { return $this->binding; }
    public function fields(): array { return $this->fields; }
    public function options(): array { return $this->with; }

    public function boundBy(string $bindingId): self
    {
        $copy = clone $this; $copy->binding = self::bindingId($bindingId); return $copy;
    }

    public function styledBy(string $style): self
    {
        $copy = clone $this; $copy->with['style'] = self::name($style, 'style'); return $copy;
    }

    public function jsonSerialize(): array
    {
        $source = ['bag' => $this->bag, 'at' => $this->at, 'reactive' => true];
        if ($this->binding !== null) $source['binding'] = $this->binding;
        return [
            'kind' => 'control', 'control' => 'chart', 'plugin' => 'charts',
            'version' => ChartsPlugin::VERSION, 'id' => $this->id, 'type' => $this->type,
            'source' => $source, 'fields' => $this->fields, 'with' => $this->with,
        ];
    }

    private static function validateFields(string $type, array $fields): array
    {
        $required = match ($type) {
            'pie' => ['label', 'value'],
            'candles' => ['time', 'open', 'high', 'low', 'close'],
            'bar', 'line', 'waveform' => ['x', 'series'],
            'heatmap' => ['x', 'y', 'value'],
            'vectormap' => ['latitude', 'longitude', 'value'],
        };
        foreach ($required as $key) if (!array_key_exists($key, $fields)) throw new JxException("Chart field is required: {$key}", 'plugin.charts', true, ['type' => $type]);

        $clean = [];
        foreach ($fields as $key => $value) {
            $key = self::name((string)$key, 'field role');
            if ($key === 'series') {
                $series = is_array($value) ? array_values($value) : [$value];
                if ($series === []) throw new JxException('Chart series cannot be empty', 'plugin.charts', true);
                $out = [];
                foreach ($series as $item) {
                    if (is_string($item)) { $out[] = ['field' => self::path($item), 'label' => $item]; continue; }
                    if (!is_array($item) || !isset($item['field'])) throw new JxException('Chart series needs a field', 'plugin.charts', true);
                    $row = ['field' => self::path((string)$item['field']), 'label' => isset($item['label']) ? (string)$item['label'] : (string)$item['field']];
                    if (isset($item['axis'])) $row['axis'] = self::name((string)$item['axis'], 'axis');
                    $out[] = $row;
                }
                $clean[$key] = $out;
            } else {
                $clean[$key] = self::path((string)$value);
            }
        }
        return $clean;
    }

    private static function validateOptions(array $with): array
    {
        $with = Boundary::import($with);
        foreach (array_keys($with) as $key) if (preg_match('/secret|password|token/i', (string)$key)) throw new JxException('Secrets cannot be stored in chart options', 'plugin.charts', true, ['key' => $key]);
        return $with;
    }

    private static function name(string $value, string $what): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128 || preg_match('/[^a-z0-9._-]/i', $value)) throw new JxException("Invalid {$what}", 'plugin.charts', true, [$what => $value]);
        return $value;
    }
    private static function bindingId(string $value): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-f0-9]{24}$/', $value)) throw new JxException('Invalid Bag binding id for chart', 'plugin.charts', true, ['binding' => $value]);
        return $value;
    }
    private static function node(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 256 || str_contains($value, "\0")) throw new JxException('Invalid chart Bag node', 'plugin.charts', true);
        return $value;
    }
    private static function path(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 256 || !preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $value)) throw new JxException('Invalid chart data field', 'plugin.charts', true, ['field' => $value]);
        return $value;
    }
}

final class ChartsPlugin implements JxPlugin
{
    public const VERSION = 'jx.charts/2';
    public function id(): string { return 'charts'; }
    public function version(): string { return self::VERSION; }
    public function capabilities(): array
    {
        return ['chart.pie', 'chart.candles', 'chart.bar', 'chart.line', 'chart.waveform', 'chart.heatmap', 'chart.spectrogram', 'chart.vectormap'];
    }

    public static function pie(string $named, string $fromBag, string $at, string $label, string $value, array $with = []): ChartControl
    { return new ChartControl($named, 'pie', $fromBag, $at, ['label' => $label, 'value' => $value], $with); }

    public static function candles(string $named, string $fromBag, string $at, string $time, string $open, string $high, string $low, string $close, array $with = []): ChartControl
    { return new ChartControl($named, 'candles', $fromBag, $at, compact('time', 'open', 'high', 'low', 'close'), $with); }

    /** @param string|array<int,string|array<string,mixed>> $series */
    public static function bar(string $named, string $fromBag, string $at, string $x, string|array $series, array $with = []): ChartControl
    { return new ChartControl($named, 'bar', $fromBag, $at, ['x' => $x, 'series' => $series], $with); }

    /** @param string|array<int,string|array<string,mixed>> $series */
    public static function line(string $named, string $fromBag, string $at, string $x, string|array $series, array $with = []): ChartControl
    { return new ChartControl($named, 'line', $fromBag, $at, ['x' => $x, 'series' => $series], $with); }

    /** Waveform is a line-family semantic optimized for ordered sample data. */
    public static function waveform(string $named, string $fromBag, string $at, string $x = 'time', string|array $series = 'value', array $with = []): ChartControl
    { return new ChartControl($named, 'waveform', $fromBag, $at, ['x' => $x, 'series' => $series], $with); }

    public static function heatmap(string $named, string $fromBag, string $at, string $x, string $y, string $value, array $with = []): ChartControl
    { return new ChartControl($named, 'heatmap', $fromBag, $at, ['x' => $x, 'y' => $y, 'value' => $value], $with); }

    /** Spectrogram is a heatmap semantic: time x frequency -> measured magnitude. */
    public static function spectrogram(string $named, string $fromBag, string $at, string $time = 'time', string $frequency = 'center', string $value = 'value', array $with = []): ChartControl
    { return self::heatmap($named, $fromBag, $at, $time, $frequency, $value, $with + ['semantic' => 'spectrogram']); }

    public static function vectormap(string $named, string $fromBag, string $at, string $latitude, string $longitude, string $value, array $with = []): ChartControl
    { return new ChartControl($named, 'vectormap', $fromBag, $at, ['latitude' => $latitude, 'longitude' => $longitude, 'value' => $value], $with); }
}

Plugins::register(new ChartsPlugin());

}
