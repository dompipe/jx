<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/jx.php';
require_once dirname(__DIR__) . '/pasl/xi/src/Bag.php';
require_once dirname(__DIR__) . '/pasl/xi/src/ChannelBus.php';
require_once dirname(__DIR__) . '/pasl/xi/src/Binding.php';

function smoke(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('smoke failed: ' . $message);
    }
}

$root = sys_get_temp_dir() . '/jx-bag-bind-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700, true) && !is_dir($root)) {
    throw new RuntimeException('cannot create smoke directory');
}

try {
    $bus = new ChannelBus($root);
    $state = $bus->channel('state');

    $bindingId = $state->bind(
        'sql.main',
        'active-users',
        'users',
        'auto',
        ['active' => 1],
    );

    smoke(count($state->bindings()) === 1, 'Bag should hold one data-source binding');
    smoke($state->bindings()[0]['source'] === 'sql.main', 'Bag source should be retained');
    smoke($state->bindings()[0]['through'] === 'active-users', 'Bag listener/query name should be retained');
    smoke($state->bindings()[0]['at'] === 'users', 'Bag destination node should be retained');

    $page = new Binding('demo', ['home', 'users'], $bus);
    $useId = $page->useBag('users', 'state');

    smoke($page->activeBags() === [], 'home Page should not activate users Bag');
    smoke($page->open('users') === 'users', 'users Page should open');
    smoke(count($page->activeBags()) === 1, 'users Page should activate one Bag');
    smoke(count($page->activeListeners()) === 1, 'active Bag binding should expand for the host');
    smoke($page->activeListeners()[0]['binding']['id'] === $bindingId, 'expanded listener should reference Bag binding');

    $snapshot = $page->snapshot();
    $bus->save();

    unset($state, $page, $bus);

    $bus = new ChannelBus($root);
    $state = $bus->channel('state');
    smoke(count($state->bindings()) === 1, 'Bag binding should survive ChannelBus restart');
    smoke($state->bindings()[0]['id'] === $bindingId, 'restored Bag binding id should remain stable');

    $page = Binding::restore($snapshot, $bus);
    smoke($page->here() === 'users', 'Binding Page cursor should survive restore');
    smoke(count($page->activeListeners()) === 1, 'restored active Page should still see Bag source');

    smoke($page->releaseBag($useId), 'Page-to-Bag use should be releasable');
    smoke($page->activeBags() === [], 'released Page use should become inactive');

    smoke($state->unbind($bindingId), 'Bag source should unbind');
    smoke($state->bindings() === [], 'Bag should have no bindings after unbind');
    $bus->save('state');

    unset($state, $page, $bus);

    $bus = new ChannelBus($root);
    $state = $bus->channel('state');
    smoke($state->bindings() === [], 'unbind should survive restart');

    echo "jx-bag-bind-smoke: ok\n";
} finally {
    foreach (glob($root . '/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($root);
}
