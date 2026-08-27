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
 *
 * No PHP eval is used. Algebra is parsed by a small arithmetic grammar and
 * string templates perform named placeholder substitution only. Complex
 * arithmetic reuses the canonical JX Complex type.
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
            'string' => self::stringValue($value),
            'algebra' => self::algebraValue($value),
            'number' => self::number($value),
            'integer' => self::integer($value),
            'float' => (float)self::number($value),
            'boolean' => self::boolean($value),
            'json' => self::json($value),
        };
    }

    /**
     * Apply coercion declared by one Bag binding descriptor.
     *
     * Compatible simple form:
     *   with: ['as' => 'algebra']
     *
     * Expression/template forms:
     *   with: ['as' => 'algebra', 'expression' => 'price * quantity']
     *   with: ['as' => 'algebra', 'expression' => 'conj(z) * z']
     *   with: ['as' => 'string', 'template' => 'Total: {value}']
     *
     * Pipeline form:
     *   with: ['coerce' => [
     *       ['as'=>'algebra', 'expression'=>'price * quantity'],
     *       ['as'=>'string', 'template'=>'Total: {value}'],
     *   ]]
     */
    public static function forBinding(array $binding, mixed $value): mixed
    {
        $with = is_array($binding['with'] ?? null) ? $binding['with'] : [];
        $scope = is_array($value) ? Boundary::import($value) : [];

        if (isset($with['coerce'])) {
            return self::pipeline($value, $with['coerce'], $scope);
        }

        $mode = self::mode($binding);
        return self::step($value, [
            'as' => $mode,
            'expression' => $with['expression'] ?? $with['algebra'] ?? null,
            'template' => $with['template'] ?? null,
        ], $scope);
    }

    private static function pipeline(mixed $value, mixed $steps, array $scope): mixed
    {
        if (!is_array($steps)) {
            throw new JxException('Binding coercion pipeline must be an array', 'bag.bind.coerce', true);
        }

        if (isset($steps['as'])) $steps = [$steps];

        $current = Boundary::import($value);
        foreach ($steps as $step) {
            if (is_string($step)) $step = ['as' => $step];
            if (!is_array($step)) {
                throw new JxException('Binding coercion step must be a string or object', 'bag.bind.coerce', true);
            }
            $current = self::step($current, $step, $scope);
        }
        return Boundary::import($current);
    }

    private static function step(mixed $value, array $step, array $scope): mixed
    {
        $as = strtolower(trim((string)($step['as'] ?? 'raw')));
        if (!in_array($as, self::MODES, true)) {
            throw new JxException('Unsupported binding coercion', 'bag.bind.coerce', true, ['as' => $as]);
        }

        if ($as === 'algebra' && isset($step['expression']) && $step['expression'] !== null && $step['expression'] !== '') {
            return self::evaluateExpression((string)$step['expression'], $value, $scope);
        }

        if ($as === 'string' && array_key_exists('template', $step) && $step['template'] !== null) {
            return self::renderTemplate((string)$step['template'], $value, $scope);
        }

        return self::apply($value, $as);
    }

    /**
     * Algebra accepts native numeric values, booleans, and JX Complex values
     * or literals such as 3+4i. Arrays/objects do not gain invented arithmetic.
     */
    private static function algebraValue(mixed $value): int|float|Complex
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
            if (str_contains(strtolower($text), 'i')) return self::complexLiteral($text);
        }

        throw new JxException('Binding value cannot become algebra', 'bag.bind.coerce', true, ['type' => get_debug_type($value)]);
    }

    private static function number(mixed $value): int|float
    {
        $value = self::algebraValue($value);
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
        if ($value instanceof Complex) return $value->re != 0.0 || $value->im != 0.0;
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

    private static function stringValue(mixed $value): string
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

    /**
     * Restricted algebra grammar:
     *   expression := term ((+|-) term)*
     *   term       := factor ((*|/|%) factor)*
     *   factor     := (+|-) factor
     *               | number | imaginary | name
     *               | safe-fn '(' expression ')'
     *               | '(' expression ')'
     *
     * Safe functions: mag, conj, real, imag.
     * Names resolve from the original bound array. "value" resolves to the
     * current pipeline value. Dotted paths such as player.score are allowed.
     */
    private static function evaluateExpression(string $expression, mixed $current, array $scope): int|float|Complex
    {
        $expression = trim($expression);
        if ($expression === '' || strlen($expression) > 512 || str_contains($expression, "\0")) {
            throw new JxException('Invalid binding algebra expression', 'bag.bind.coerce', true);
        }
        if (preg_match('/[^A-Za-z0-9_.+\-*\/%()\s]/', $expression)) {
            throw new JxException('Unsupported character in binding algebra', 'bag.bind.coerce', true);
        }

        $tokens = self::tokenize($expression);
        $i = 0;
        $result = self::parseExpression($tokens, $i, $current, $scope);
        if ($i !== count($tokens)) {
            throw new JxException('Unexpected token in binding algebra', 'bag.bind.coerce', true);
        }
        return $result;
    }

    /** @return list<array{type:string,value:string}> */
    private static function tokenize(string $source): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($source);

        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $source, $m, 0, $offset)) {
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G(?:\d+(?:\.\d*)?|\.\d+)i/Ai', $source, $m, 0, $offset)) {
                $tokens[] = ['type' => 'imaginary', 'value' => strtolower($m[0])];
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G(?:\d+(?:\.\d*)?|\.\d+)/A', $source, $m, 0, $offset)) {
                $tokens[] = ['type' => 'number', 'value' => $m[0]];
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G[A-Za-z_][A-Za-z0-9_.]*/A', $source, $m, 0, $offset)) {
                $tokens[] = ['type' => 'name', 'value' => $m[0]];
                $offset += strlen($m[0]);
                continue;
            }

            $char = $source[$offset];
            if (str_contains('+-*/%()', $char)) {
                $tokens[] = ['type' => $char, 'value' => $char];
                $offset++;
                continue;
            }
            throw new JxException('Invalid token in binding algebra', 'bag.bind.coerce', true, ['offset' => $offset]);
        }
        return $tokens;
    }

    /** @param list<array{type:string,value:string}> $tokens */
    private static function parseExpression(array $tokens, int &$i, mixed $current, array $scope): int|float|Complex
    {
        $left = self::parseTerm($tokens, $i, $current, $scope);
        while ($i < count($tokens) && in_array($tokens[$i]['type'], ['+', '-'], true)) {
            $op = $tokens[$i++]['type'];
            $right = self::parseTerm($tokens, $i, $current, $scope);
            $left = $op === '+' ? self::addValues($left, $right) : self::subValues($left, $right);
        }
        return $left;
    }

    /** @param list<array{type:string,value:string}> $tokens */
    private static function parseTerm(array $tokens, int &$i, mixed $current, array $scope): int|float|Complex
    {
        $left = self::parseFactor($tokens, $i, $current, $scope);
        while ($i < count($tokens) && in_array($tokens[$i]['type'], ['*', '/', '%'], true)) {
            $op = $tokens[$i++]['type'];
            $right = self::parseFactor($tokens, $i, $current, $scope);
            $left = match ($op) {
                '*' => self::mulValues($left, $right),
                '/' => self::divValues($left, $right),
                '%' => self::modValues($left, $right),
            };
        }
        return $left;
    }

    /** @param list<array{type:string,value:string}> $tokens */
    private static function parseFactor(array $tokens, int &$i, mixed $current, array $scope): int|float|Complex
    {
        if ($i >= count($tokens)) {
            throw new JxException('Incomplete binding algebra', 'bag.bind.coerce', true);
        }

        $token = $tokens[$i++];

        if ($token['type'] === '+') {
            return self::parseFactor($tokens, $i, $current, $scope);
        }
        if ($token['type'] === '-') {
            return self::negateValue(self::parseFactor($tokens, $i, $current, $scope));
        }
        if ($token['type'] === 'number') {
            return str_contains($token['value'], '.') ? (float)$token['value'] : (int)$token['value'];
        }
        if ($token['type'] === 'imaginary') {
            return self::complexLiteral($token['value']);
        }
        if ($token['type'] === 'name') {
            $name = $token['value'];

            if ($name === 'i') {
                return Complex::of(0.0, 1.0);
            }

            if ($i < count($tokens) && $tokens[$i]['type'] === '(') {
                if (!in_array(strtolower($name), ['mag', 'conj', 'real', 'imag'], true)) {
                    throw new JxException('Unsupported function in binding algebra', 'bag.bind.coerce', true, ['function' => $name]);
                }
                $i++;
                $arg = self::parseExpression($tokens, $i, $current, $scope);
                if ($i >= count($tokens) || $tokens[$i]['type'] !== ')') {
                    throw new JxException('Unclosed function call in binding algebra', 'bag.bind.coerce', true);
                }
                $i++;
                return self::callSafeFunction(strtolower($name), $arg);
            }

            $raw = $name === 'value'
                ? $current
                : self::resolve($scope, $name);
            if ($raw === null) {
                throw new JxException('Unknown value in binding algebra', 'bag.bind.coerce', true, ['name' => $name]);
            }
            return self::algebraValue($raw);
        }
        if ($token['type'] === '(') {
            $result = self::parseExpression($tokens, $i, $current, $scope);
            if ($i >= count($tokens) || $tokens[$i]['type'] !== ')') {
                throw new JxException('Unclosed parenthesis in binding algebra', 'bag.bind.coerce', true);
            }
            $i++;
            return $result;
        }

        throw new JxException('Unexpected token in binding algebra', 'bag.bind.coerce', true);
    }

    private static function complexLiteral(string $text): Complex
    {
        $text = strtolower(trim($text));
        if (preg_match('/^([+-]?(?:\d+(?:\.\d*)?|\.\d+))i$/', $text, $m)) {
            return Complex::of(0.0, (float)$m[1]);
        }
        return Complex::parse($text);
    }

    private static function addValues(int|float|Complex $left, int|float|Complex $right): int|float|Complex
    {
        if ($left instanceof Complex || $right instanceof Complex) {
            return self::toComplex($left)->add(self::toComplex($right));
        }
        return $left + $right;
    }

    private static function subValues(int|float|Complex $left, int|float|Complex $right): int|float|Complex
    {
        if ($left instanceof Complex || $right instanceof Complex) {
            return self::toComplex($left)->sub(self::toComplex($right));
        }
        return $left - $right;
    }

    private static function mulValues(int|float|Complex $left, int|float|Complex $right): int|float|Complex
    {
        if ($left instanceof Complex || $right instanceof Complex) {
            return self::toComplex($left)->mul(self::toComplex($right));
        }
        return $left * $right;
    }

    private static function divValues(int|float|Complex $left, int|float|Complex $right): int|float|Complex
    {
        if ($left instanceof Complex || $right instanceof Complex) {
            $a = self::toComplex($left);
            $b = self::toComplex($right);
            $denominator = $b->re * $b->re + $b->im * $b->im;
            if ($denominator == 0.0) {
                throw new JxException('Division by zero in binding algebra', 'bag.bind.coerce', true);
            }

            $numerator = $a->mul($b->conj());
            return Complex::of(
                $numerator->re / $denominator,
                $numerator->im / $denominator,
            );
        }

        if ($right == 0) {
            throw new JxException('Division by zero in binding algebra', 'bag.bind.coerce', true);
        }
        return $left / $right;
    }

    private static function modValues(int|float|Complex $left, int|float|Complex $right): int|float
    {
        if ($left instanceof Complex || $right instanceof Complex) {
            throw new JxException('Modulo is scalar-only in binding algebra', 'bag.bind.coerce', true);
        }
        if ($right == 0) {
            throw new JxException('Division by zero in binding algebra', 'bag.bind.coerce', true);
        }
        return fmod((float)$left, (float)$right);
    }

    private static function negateValue(int|float|Complex $value): int|float|Complex
    {
        if ($value instanceof Complex) {
            return Complex::of(-$value->re, -$value->im);
        }
        return -$value;
    }

    private static function toComplex(int|float|Complex $value): Complex
    {
        return $value instanceof Complex ? $value : Complex::of((float)$value, 0.0);
    }

    private static function callSafeFunction(string $name, int|float|Complex $value): int|float|Complex
    {
        return match ($name) {
            'mag' => $value instanceof Complex ? $value->mag() : abs($value),
            'conj' => $value instanceof Complex ? $value->conj() : $value,
            'real' => $value instanceof Complex ? $value->re : $value,
            'imag' => $value instanceof Complex ? $value->im : 0.0,
            default => throw new JxException('Unsupported function in binding algebra', 'bag.bind.coerce', true, ['function' => $name]),
        };
    }

    private static function renderTemplate(string $template, mixed $current, array $scope): string
    {
        if (strlen($template) > 4096 || str_contains($template, "\0")) {
            throw new JxException('Invalid binding string template', 'bag.bind.coerce', true);
        }

        $rendered = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_.]*|value)\}/',
            function (array $match) use ($current, $scope): string {
                $name = $match[1];
                $resolved = $name === 'value' ? $current : self::resolve($scope, $name);
                if ($resolved === null) return '';
                return self::stringValue($resolved);
            },
            $template,
        );

        return $rendered ?? $template;
    }

    private static function resolve(array $scope, string $path): mixed
    {
        $current = $scope;
        foreach (explode('.', $path) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) return null;
            $current = $current[$part];
        }
        return $current;
    }
}

}
