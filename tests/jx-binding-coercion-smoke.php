<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/jx.php';
require_once dirname(__DIR__) . '/jx/BindingCoercion.php';

use jx\BindingCoercion;
use jx\Complex;
use jx\JxException;

function smoke(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException('smoke failed: ' . $message);
}

smoke(BindingCoercion::apply('42', 'algebra') === 42, 'integer algebra coercion');
smoke(BindingCoercion::apply('42.5', 'algebra') === 42.5, 'float algebra coercion');
smoke(BindingCoercion::apply(true, 'algebra') === 1, 'boolean algebra coercion');

$complex = BindingCoercion::apply('3+2i', 'algebra');
smoke($complex instanceof Complex, 'complex algebra type');
smoke($complex->re === 3.0 && $complex->im === 2.0, 'complex algebra value');
smoke(BindingCoercion::apply($complex, 'string') === '3+2i', 'complex string coercion');

smoke(BindingCoercion::apply(42.5, 'string') === '42.5', 'numeric string coercion');
smoke(BindingCoercion::apply(['a' => 1], 'string') === '{"a":1}', 'array string coercion uses JSON');
smoke(BindingCoercion::apply('yes', 'boolean') === true, 'boolean true coercion');
smoke(BindingCoercion::apply('0', 'boolean') === false, 'boolean false coercion');

$binding = ['source' => 'sql.main', 'through' => 'price', 'with' => ['as' => 'algebra']];
smoke(BindingCoercion::forBinding($binding, '19.75') === 19.75, 'binding metadata coercion');

$mathBinding = [
    'source' => 'sql.main',
    'through' => 'cart-row',
    'with' => [
        'as' => 'algebra',
        'expression' => 'price * quantity + shipping',
    ],
];
$math = BindingCoercion::forBinding($mathBinding, [
    'price' => '2.5',
    'quantity' => 4,
    'shipping' => 3,
]);
smoke(abs((float)$math - 13.0) < 0.000001, 'field algebra expression');

$templateBinding = [
    'source' => 'sql.main',
    'through' => 'player',
    'with' => [
        'as' => 'string',
        'template' => '{player.name}: {player.score}',
    ],
];
$label = BindingCoercion::forBinding($templateBinding, [
    'player' => ['name' => 'Ada', 'score' => 99],
]);
smoke($label === 'Ada: 99', 'dotted string template');

$pipelineBinding = [
    'source' => 'sql.main',
    'through' => 'cart-row',
    'with' => [
        'coerce' => [
            ['as' => 'algebra', 'expression' => 'price * quantity'],
            ['as' => 'string', 'template' => 'Total: {value}'],
        ],
    ],
];
$label = BindingCoercion::forBinding($pipelineBinding, [
    'price' => 7,
    'quantity' => 3,
]);
smoke($label === 'Total: 21', 'algebra feeds string template');

$failed = false;
try {
    BindingCoercion::apply(['not' => 'algebra'], 'algebra');
} catch (JxException) {
    $failed = true;
}
smoke($failed, 'invalid algebra coercion must fail');

$failed = false;
try {
    BindingCoercion::forBinding([
        'with' => [
            'as' => 'algebra',
            'expression' => 'phpinfo()',
        ],
    ], ['value' => 1]);
} catch (JxException) {
    $failed = true;
}
smoke($failed, 'code-like algebra expression must fail');

echo "jx-binding-coercion-smoke: ok\n";
