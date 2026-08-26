<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/jx.php';

    $control = dirname(__DIR__) . '/pasl/xi/src/Control.php';
    if (is_file($control)) {
        require_once $control;
    }

    $pasl = dirname(__DIR__) . '/pasl/pasl.php';
    if (is_file($pasl)) {
        require_once $pasl;
    }
}

namespace jx {

/**
 * JX v0.1 rhetorical surface.
 *
 * Flow does not replace the engine APIs. It gives public calls a predictable
 * reading order and then lowers them to the existing runtime contracts.
 *
 * The core order is:
 *
 *   subject -> from -> through -> to -> like -> with
 *
 * A programmer should be able to read a call aloud without translating the
 * parameter positions first.
 */
final class Flow
{
    private const ROLE = '__jx_role';
    private const VALUE = 'value';

    /** A geometric beginning: from(x, y). */
    public static function from(int|float $x, int|float $y): array
    {
        return self::point('from', $x, $y);
    }

    /** A geometric middle: through(x, y). */
    public static function through(int|float $x, int|float $y): array
    {
        return self::point('through', $x, $y);
    }

    /** A geometric destination: to(x, y). */
    public static function to(int|float $x, int|float $y): array
    {
        return self::point('to', $x, $y);
    }

    /** Describe manner or behavior: like(...). */
    public static function like(mixed $value): array
    {
        return self::role('like', $value);
    }

    /** Attach payload, paint, style, or supporting material: with(...). */
    public static function with(mixed $value): array
    {
        return self::role('with', $value);
    }

    /** Describe an anchor or location: at(...). */
    public static function at(mixed $value): array
    {
        return self::role('at', $value);
    }

    /**
     * Draw a line: line(named, from, to, like, with).
     *
     * Example:
     *   Flow::line('trail', Flow::from(10, 20), Flow::to(200, 20),
     *       Flow::like(Control::pong()), Flow::with(Image::blur(...)));
     */
    public static function line(
        string $named,
        array $from,
        array $to,
        bool|array $like = false,
        ?array $with = null,
    ): array {
        self::needControl();

        $behavior = self::unrole($like, 'like');
        $paint = $with === null ? null : self::unrole($with, 'with');

        if (!is_bool($behavior) && !is_array($behavior)) {
            throw new JxException('line(... like ...) must describe run behavior', 'flow', true);
        }
        if ($paint !== null && !is_array($paint)) {
            throw new JxException('line(... with ...) must describe paint/image data', 'flow', true);
        }

        return \Control::line(
            $named,
            self::pointValue($from, 'from'),
            self::pointValue($to, 'to'),
            $behavior,
            $paint,
        );
    }

    /**
     * Draw a curve in reading order:
     *
     *   curve(named, from, through..., to, like)
     *
     * Flow deliberately accepts the style/behavior last, then lowers it to the
     * older Control::curve() storage order internally.
     */
    public static function curve(string $named, array $from, array ...$rest): array
    {
        self::needControl();

        $points = [self::pointValue($from, 'from')];
        $properties = [];
        $sawTo = false;

        foreach ($rest as $part) {
            $role = $part[self::ROLE] ?? null;

            if ($role === 'like') {
                $value = $part[self::VALUE] ?? [];
                if (!is_array($value)) {
                    throw new JxException('curve(... like ...) must be property data', 'flow', true);
                }
                $properties = $value;
                continue;
            }

            if ($role === 'through' || $role === 'to' || $role === 'from') {
                $points[] = self::pointValue($part, $role);
                if ($role === 'to') {
                    $sawTo = true;
                }
                continue;
            }

            // Compatibility: a plain x/y point remains a point; a plain
            // associative array remains curve properties.
            if (array_key_exists('x', $part) || array_key_exists('y', $part)) {
                $points[] = self::pointValue($part, 'point');
            } else {
                $properties = $part;
            }
        }

        if (count($points) < 2) {
            throw new JxException('curve() needs a beginning and a destination', 'flow', true);
        }

        // A plain final point is still accepted for compatibility, but the
        // rhetorical surface encourages an explicit to().
        unset($sawTo);

        return \Control::curve($named, $properties, ...$points);
    }

    /** Emit output: output(callback, at, with). */
    public static function output(
        string $callback,
        string|array $at = 'XY_CENTER',
        array $with = [],
    ): array {
        self::needControl();

        $anchor = is_array($at) ? self::unrole($at, 'at') : $at;
        $bag = self::isRole($with, 'with') ? self::unrole($with, 'with') : $with;

        if (!is_string($anchor)) {
            throw new JxException('output(... at ...) must be a control anchor', 'flow', true);
        }
        if (!is_array($bag)) {
            throw new JxException('output(... with ...) must be bag/payload data', 'flow', true);
        }

        return \Control::output($callback, $anchor, $bag);
    }

    /**
     * One-shot legal Bag write: put(value, into, at).
     *
     * The public call stays short while the underlying operation still signs,
     * commits, and revokes the reference.
     */
    public static function put(mixed $value, Bag $into, string $at = '_default'): Bag
    {
        $ref = $into->sign($at);
        try {
            $into->set($value, $at)->commit($ref);
        } finally {
            if ($into->isLiveRef($ref)) {
                $into->unsign($ref);
            }
        }
        return $into;
    }

    /** Read a Bag cell with the same direction: take(from, at). */
    public static function take(Bag $from, string $at = '_default'): mixed
    {
        $ref = $from->sign($at);
        try {
            return $from->get($ref, $at);
        } finally {
            if ($from->isLiveRef($ref)) {
                $from->unsign($ref);
            }
        }
    }

    /** Deep read: deliver(root, through, or). */
    public static function deliver(mixed $root, array|string $through, mixed $or = null): mixed
    {
        return Delivery::extract($root, $through, $or);
    }

    /** Deep immutable-style update: rebind(root, through, to). */
    public static function rebind(array $root, array|string $through, mixed $to): array
    {
        return Delivery::rebind($root, $through, $to);
    }

    /** Compile source to a named PASL backend: compile(source, to). */
    public static function compile(string $source, string $to = 'c'): array
    {
        if (!class_exists(\pasl\Package::class)) {
            throw new JxException('PASL package compiler is unavailable', 'compile', true);
        }
        return \pasl\Package::compile($source, $to);
    }

    /** Begin a runtime Book paragraph. */
    public static function book(string $named, int $withMemory = 8_388_608): BookFlow
    {
        return BookFlow::open($named, $withMemory);
    }

    private static function role(string $role, mixed $value): array
    {
        return [self::ROLE => $role, self::VALUE => $value];
    }

    private static function point(string $role, int|float $x, int|float $y): array
    {
        return [self::ROLE => $role, 'x' => $x, 'y' => $y];
    }

    private static function pointValue(array $point, string $expected): array
    {
        $role = $point[self::ROLE] ?? null;
        if ($role !== null && !in_array($role, ['from', 'through', 'to'], true)) {
            throw new JxException("Expected {$expected} point, got {$role}", 'flow', true);
        }
        return [
            'x' => (int)($point['x'] ?? 0),
            'y' => (int)($point['y'] ?? 0),
        ];
    }

    private static function isRole(array $part, string $role): bool
    {
        return ($part[self::ROLE] ?? null) === $role;
    }

    private static function unrole(mixed $part, string $role): mixed
    {
        if (!is_array($part)) {
            return $part;
        }
        if (($part[self::ROLE] ?? null) !== $role) {
            return $part;
        }
        return $part[self::VALUE] ?? null;
    }

    private static function needControl(): void
    {
        if (!class_exists('Control')) {
            throw new JxException('JX Control host is unavailable', 'flow', true);
        }
    }
}

/**
 * A Book expressed as a short paragraph instead of one long constructor.
 *
 *   Flow::book('demo')
 *       ->withBag('state', 4096)
 *       ->withPage('home', fn(Task $task) => 1)
 *       ->done();
 */
final class BookFlow
{
    private Book $book;

    private function __construct(string $name, int $memory)
    {
        $this->book = Book::open($name, $memory);
    }

    public static function open(string $named, int $withMemory = 8_388_608): self
    {
        return new self($named, $withMemory);
    }

    public function withBag(string $named, int|Bag $withMemory): self
    {
        $bag = $withMemory instanceof Bag ? $withMemory : Bag::underwrite($withMemory);
        $this->book->registerBag($named, $bag);
        return $this;
    }

    public function withPage(
        string $named,
        Page|callable $doing,
        int $withMemory = 65_536,
    ): self {
        $page = $doing instanceof Page
            ? $doing
            : Page::spawn($doing, null, $withMemory, $named);
        $this->book->registerPage($named, $page);
        return $this;
    }

    public function done(): Book
    {
        return $this->book;
    }
}

}
