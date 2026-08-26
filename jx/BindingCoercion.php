<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/jx.php';
}

namespace jx {

/**
 * Coercion boundary for values arriving through Bag data-source bindings.
 *
 * A binding stays a source descriptor. This class decides how a value should
 * enter JX when the binding asks for a presentation/algebraic view.
 */
final class BindingCoercion
{
    public const MODES = [
        'raw',
        'string',
        'algebra',
        'number',
        'integer',
        'float',
        'boolean',
        'json',
    ];

    public static function mode(array $binding): string
    {
        $with = is_array($binding['with'] ?? null) ? $binding['with'] : [];
        $mode = strtolower(trim((string)($binding['as'] ?? $with['as'] ?? 'raw')));
        if (!in_array($mode, self::MODES, true)) {
            throw new JxException('Unsupported binding coercion', 'bag.bind.coerce', true, ['as' => $mode]);
        }
        return $mode;
    }

    public static function apply(mixed $value, string $as = 'raw'): mixed
    {
        $as = strtolower(trim($as));
        if (!in_array($as, self::MODES, true)) {
            throw new JxException('Unsupported binding coercion', 'bag.bind.coerce', true, ['as' => $as]);
        }

        $value = Boundary::import($value);

        return match ($as) {
            'raw' => $value,
            'string' => self::string($value),
            'algebra' => self::algebra($value),
            'number' => self::number($value),
            'integer' => self::integer($value),
            'float' => (float)self::number($value),
            'boolean' => self::boolean($value),
            'json' => self::json($value),
        };
    }

    /** Apply the coercion declared by one Bag binding descriptor. */
    public static function forBinding(array $binding, mixed $value): mixed
    {
        return self::apply($value, self::mode($binding));
    }

    /**
     * Algebra keeps native numeric values and understands JX Complex literals.
     * It intentionally rejects arrays/objects rather than inventing arithmetic.
     */
    private static function algebra(mixed $value): int|float|Complex
    {
        if ($value instanceof Complex) return $value;
        if (is_int($value) || is_float($value)) return $value;
        if (is_bool($value)) return $value ? 1 : 0;

        if (is_string($value)) {
            $text = trim($value);
            if ($text === '') {
                throw new JxException('Empty string cannot become algebra', 'bag.bind.coerce', true);
            }
            if (preg_match('/^[+-]?\d+$/', $text)) return (int)$text;
            if (is_numeric($text)) return (float)$text;
            if (str_contains(strtolower($text), 'i')) return Complex::parse($text);
        }

        throw new JxException('Binding value cannot become algebra', 'bag.bind.coerce', true, ['type' => get_debug_type($value)]);
    }

    private static function number(mixed $value): int|float
    {
        $value = self::algebra($value);
        if ($value instanceof Complex) {
            if ($value->im != 0.0) {
                throw new JxException('Complex value with an imaginary component is not a scalar number', 'bag.bind.coerce', true);
            }
            return $value->re;
        }
        return $value;
    }

    private static function integer(mixed $value): int
    {
        $number = self::number($value);
        if (is_float($number) && !is_finite($number)) {
            throw new JxException('Non-finite value cannot become integer', 'bag.bind.coerce', true);
        }
        return (int)$number;
    }

    private static function boolean(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_int($value) || is_float($value)) return $value != 0;
        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off', '' => false,
                default => throw new JxException('String cannot become boolean', 'bag.bind.coerce', true, ['value' => $value]),
            };
        }
        if ($value === null) return false;
        throw new JxException('Binding value cannot become boolean', 'bag.bind.coerce', true, ['type' => get_debug_type($value)]);
    }

    private static function string(mixed $value): string
    {
        if ($value === null) return '';
        if ($value instanceof Complex) return (string)$value;
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_int($value) || is_float($value) || is_string($value)) return (string)$value;
        if (is_array($value)) return self::json($value);
        throw new JxException('Binding value cannot become string', 'bag.bind.coerce', true, ['type' => get_debug_type($value)]);
    }

    private static function json(mixed $value): string
    {
        $encoded = json_encode(Boundary::export($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new JxException('Binding value cannot become JSON', 'bag.bind.coerce', true);
        }
        return $encoded;
    }
}

}
