<?php declare(strict_types=1);
/**
 * jx smoke test — Bag memory law, Task.push, Delivery, Complex, Book/Page.
 */
require_once dirname(__DIR__) . '/jx.php';

use jx\Jx;
use jx\Delivery;
use jx\Complex;
use jx\JxException;

echo "jx smoke\n";

// Book + Bag + Task
$book = Jx::book('smoke', 1_000_000);
$bag = Jx::bag(256);
$book->registerBag('main', $bag);

$task = Jx::task(512, 'worker');
$task->push('title', 'Smoke Page');
assert($task->prop('title') === 'Smoke Page');
assert($task->id() > 0);

// Sign + handshake write. The write node is explicit and must match the signed node.
$ref = $bag->sign('cell');
$bag->set('hello', 'cell')->commit($ref);
assert($bag->get($ref, 'cell') === 'hello');
assert($bag->quotient() < 256);

// Verbose path
$ref2 = $bag->tell('sign', 'cell2');
$bag->tell('set', 'world', 'cell2')->pass($ref2);
assert($bag->peek('cell2') === 'world');

// Overflow must fail closed
try {
    $small = Jx::bag(8);
    $r = $small->sign('x');
    $small->set(str_repeat('A', 100), 'x')->commit($r);
    fwrite(STDERR, "FAIL: expected overflow\n");
    exit(1);
} catch (JxException $e) {
    assert($e->resistant === true);
    echo "overflow protected: ok\n";
}

// Delivery
$config = ['server' => ['ports' => ['https' => 443, 'http' => 80]]];
assert(Delivery::extract($config, 'server.ports.https') === 443);
assert(Jx::delivery($config, ['server', 'ports', 'http']) === 80);
assert(Delivery::extract($config, 'server.missing', 7) === 7);

// Complex
$c = Complex::parse('3+4i');
assert((string)$c->mul(Complex::parse('1+0i')) === '3+4i');
assert(Jx::complex(0, 1)->mag() === 1.0);

// Page
$page = Jx::page(function ($t) {
    $t->push('ran', true);
    return $t->id();
}, 1024, 'p1');
$book->registerPage('p1', $page);
$id = $page->run();
assert($id === $page->id());

// Smart table extrusion
$ex = Jx::table()->extrude('bag.set');
assert(isset($ex['mode']));

echo "all smoke checks passed\n";
exit(0);
