<?php declare(strict_types=1);

namespace jx;

use JsonSerializable;

/**
 * Canonical desktop/window/input contract.
 *
 * The language describes a desktop. A host owns the physical display/input
 * mechanism (X11/XCB, Win32, later Wayland, browser/nested test host).
 * No XID, HWND, XCB pointer, HANDLE, HDC, or host-specific callback enters
 * canonical JX state.
 */
final class Desktop implements JsonSerializable
{
    public const VERSION = 'jx.desktop/1';
    public const MODES = ['shell', 'window-manager', 'nested'];

    /** @var array<string,DesktopIcon> */
    private array $icons = [];
    /** @var array<string,DesktopShortcut> */
    private array $shortcuts = [];

    /** @param array<string,mixed> $with */
    public function __construct(
        private string $id = 'desktop',
        private string $mode = 'shell',
        private array $with = [],
    ) {
        $this->id = self::name($id, 'desktop id');
        $this->mode = strtolower(trim($mode));
        if (!in_array($this->mode, self::MODES, true)) {
            throw new JxException('Unsupported desktop mode', 'desktop', true, ['mode' => $mode]);
        }
        $this->with = self::options($with);
    }

    public static function shell(string $id = 'desktop', array $with = []): self
    { return new self($id, 'shell', $with); }

    public static function windowManager(string $id = 'desktop', array $with = []): self
    { return new self($id, 'window-manager', $with); }

    public static function nested(string $id = 'desktop', array $with = []): self
    { return new self($id, 'nested', $with); }

    public function icon(DesktopIcon $icon): self
    {
        $copy = clone $this;
        $copy->icons[$icon->id()] = $icon;
        return $copy;
    }

    public function shortcut(DesktopShortcut $shortcut): self
    {
        $copy = clone $this;
        $copy->shortcuts[$shortcut->id()] = $shortcut;
        return $copy;
    }

    public function jsonSerialize(): array
    {
        return [
            'kind' => 'desktop',
            'version' => self::VERSION,
            'id' => $this->id,
            'mode' => $this->mode,
            'with' => $this->with,
            'icons' => array_map(static fn(DesktopIcon $i): array => $i->jsonSerialize(), array_values($this->icons)),
            'shortcuts' => array_map(static fn(DesktopShortcut $s): array => $s->jsonSerialize(), array_values($this->shortcuts)),
            'events' => [
                'ready', 'pointer', 'click', 'double-click', 'key',
                'window-open', 'window-close', 'window-focus', 'window-move',
                'window-resize', 'display-change', 'error',
            ],
        ];
    }

    private static function options(array $with): array
    {
        $with = Boundary::import($with);
        self::noSecrets($with);
        foreach (['background', 'style', 'window_bag', 'input_bag'] as $key) {
            if (isset($with[$key])) $with[$key] = self::text((string)$with[$key], $key, 4096);
        }
        if (isset($with['workspaces'])) {
            $n = (int)$with['workspaces'];
            if ($n < 1 || $n > 256) throw new JxException('Desktop workspaces must be 1..256', 'desktop', true);
            $with['workspaces'] = $n;
        }
        return $with;
    }

    private static function noSecrets(array $v, string $path = ''): void
    {
        foreach ($v as $k => $value) {
            $key = (string)$k; $full = $path === '' ? $key : $path.'.'.$key;
            if (preg_match('/secret|password|token/i', $key)) {
                throw new JxException('Secrets cannot be stored in Desktop descriptors', 'desktop', true, ['key' => $full]);
            }
            if (is_array($value)) self::noSecrets($value, $full);
        }
    }

    public static function name(string $v, string $what): string
    {
        $v = trim($v);
        if ($v === '' || strlen($v) > 128 || preg_match('/[^a-z0-9._-]/i', $v)) {
            throw new JxException("Invalid {$what}", 'desktop', true, [$what => $v]);
        }
        return $v;
    }

    public static function text(string $v, string $what, int $max = 1024): string
    {
        $v = trim($v);
        if ($v === '' || strlen($v) > $max || str_contains($v, "\0")) {
            throw new JxException("Invalid {$what}", 'desktop', true);
        }
        return $v;
    }
}

final class DesktopIcon implements JsonSerializable
{
    /** @param array<string,mixed> $with */
    public function __construct(
        private string $id,
        private string $label,
        private string $image,
        private DesktopLaunch $launch,
        private int $x = 0,
        private int $y = 0,
        private array $with = [],
    ) {
        $this->id = Desktop::name($id, 'icon id');
        $this->label = Desktop::text($label, 'icon label', 256);
        $this->image = Desktop::text($image, 'icon image', 4096);
        if ($x < -1_000_000 || $x > 1_000_000 || $y < -1_000_000 || $y > 1_000_000) {
            throw new JxException('Desktop icon coordinates are out of range', 'desktop.icon', true);
        }
    }
    public function id(): string { return $this->id; }
    public function jsonSerialize(): array
    {
        return ['id'=>$this->id,'label'=>$this->label,'image'=>$this->image,'x'=>$this->x,'y'=>$this->y,'launch'=>$this->launch->jsonSerialize(),'with'=>Boundary::import($this->with)];
    }
}

final class DesktopLaunch implements JsonSerializable
{
    /** @param list<string> $args @param array<string,string> $env */
    public function __construct(
        private string $program,
        private array $args = [],
        private ?string $cwd = null,
        private array $env = [],
        private ?string $book = null,
        private string $isolation = 'host',
    ) {
        $this->program = Desktop::text($program, 'launch program', 4096);
        if (!in_array($isolation, ['host', 'book', 'sandbox'], true)) {
            throw new JxException('Unsupported launch isolation mode', 'desktop.launch', true, ['isolation'=>$isolation]);
        }
        $this->isolation = $isolation;
        $this->args = array_map(static fn($v): string => Desktop::text((string)$v, 'launch argument', 4096), array_values($args));
        if ($cwd !== null) $this->cwd = Desktop::text($cwd, 'launch cwd', 4096);
        if ($book !== null) $this->book = Desktop::name($book, 'launch Book');
        $cleanEnv = [];
        foreach ($env as $k=>$v) {
            $key = Desktop::name((string)$k, 'environment key');
            if (preg_match('/secret|password|token/i', $key)) throw new JxException('Secrets cannot be embedded in Desktop launch environment', 'desktop.launch', true, ['key'=>$key]);
            $cleanEnv[$key] = (string)$v;
        }
        $this->env = $cleanEnv;
    }

    public function jsonSerialize(): array
    {
        return ['program'=>$this->program,'args'=>$this->args,'cwd'=>$this->cwd,'env'=>$this->env,'book'=>$this->book,'isolation'=>$this->isolation];
    }
}

final class DesktopShortcut implements JsonSerializable
{
    public function __construct(
        private string $id,
        private string $key,
        private string $action,
        private ?DesktopLaunch $launch = null,
    ) {
        $this->id = Desktop::name($id, 'shortcut id');
        $this->key = Desktop::text($key, 'shortcut key', 128);
        $this->action = Desktop::name($action, 'shortcut action');
    }
    public function id(): string { return $this->id; }
    public function jsonSerialize(): array
    { return ['id'=>$this->id,'key'=>$this->key,'action'=>$this->action,'launch'=>$this->launch?->jsonSerialize()]; }
}

/** Host-facing window snapshot. Hosts publish these into the configured window Bag. */
final class DesktopWindowState
{
    public static function row(
        string $hostId, ?int $pid, string $title, string $class,
        int $x, int $y, int $width, int $height,
        bool $focused=false, bool $mapped=true, int $workspace=0,
    ): array {
        return [
            'host_id'=>$hostId,'pid'=>$pid,'title'=>$title,'class'=>$class,
            'x'=>$x,'y'=>$y,'width'=>$width,'height'=>$height,
            'focused'=>$focused,'mapped'=>$mapped,'workspace'=>$workspace,
        ];
    }
}
