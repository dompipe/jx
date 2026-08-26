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

$failed = false;
try {
    BindingCoercion::apply(['not' => 'algebra'], 'algebra');
} catch (JxException) {
    $failed = true;
}
smoke($failed, 'invalid algebra coercion must fail');

echo "jx-binding-coercion-smoke: ok\n";
