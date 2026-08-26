<?php declare(strict_types=1);

namespace jx;

use JsonSerializable;

/**
 * Serializable Control-to-Control binding.
 *
 * This is a host-neutral listener contract. It names the source Control/event,
 * the event-payload field to read, and the target Control/action. Hosts attach
 * the actual DOM/native listeners when a Page is mounted.
 */
final class ControlBinding implements JsonSerializable
{
    private string $id;

    /** @param array<string,mixed> $with */
    public function __construct(
        private string $fromControl,
        private string $event,
        private string $toControl,
        private string $action,
        private ?string $from = 'value',
        private array $with = [],
    ) {
        $this->fromControl = self::name($this->fromControl, 'source control');
        $this->event = self::name($this->event, 'event');
        $this->toControl = self::name($this->toControl, 'target control');
        $this->action = self::name($this->action, 'action');
        $this->from = $this->from === null ? null : self::path($this->from);
        $this->with = self::options($this->with);

        $this->id = substr(hash('sha256', implode("\0", [
            'control-bind-v1',
            $this->fromControl,
            $this->event,
            $this->toControl,
            $this->action,
            $this->from ?? '',
            serialize($this->with),
        ])), 0, 24);
    }

    public function id(): string { return $this->id; }
    public function sourceControl(): string { return $this->fromControl; }
    public function event(): string { return $this->event; }
    public function targetControl(): string { return $this->toControl; }
    public function action(): string { return $this->action; }
    public function valuePath(): ?string { return $this->from; }
    public function options(): array { return $this->with; }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        $from = [
            'control' => $this->fromControl,
            'event' => $this->event,
        ];
        if ($this->from !== null) $from['value'] = $this->from;

        return [
            'kind' => 'control-binding',
            'id' => $this->id,
            'from' => $from,
            'to' => [
                'control' => $this->toControl,
                'action' => $this->action,
            ],
            'with' => $this->with,
        ];
    }

    private static function options(array $with): array
    {
        $with = Boundary::import($with);
        self::assertNoSecrets($with);
        if (isset($with['as'])) {
            $as = strtolower(trim((string)$with['as']));
            if (!in_array($as, ['raw','string','algebra','number','integer','float','boolean','json'], true)) {
                throw new JxException('Unsupported control-binding coercion', 'control.bind', true, ['as' => $as]);
            }
            $with['as'] = $as;
        }
        return $with;
    }

    private static function assertNoSecrets(array $values, string $path = ''): void
    {
        foreach ($values as $key => $value) {
            $name = (string)$key;
            $full = $path === '' ? $name : $path . '.' . $name;
            if (preg_match('/secret|password|token/i', $name)) {
                throw new JxException('Secrets cannot be stored in control bindings', 'control.bind', true, ['key' => $full]);
            }
            if (is_array($value)) self::assertNoSecrets($value, $full);
        }
    }

    private static function name(string $value, string $what): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128 || preg_match('/[^a-z0-9._-]/i', $value)) {
            throw new JxException("Invalid {$what}", 'control.bind', true, [$what => $value]);
        }
        return $value;
    }

    private static function path(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 256 || !preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $value)) {
            throw new JxException('Invalid control-binding value path', 'control.bind', true, ['path' => $value]);
        }
        return $value;
    }
}
