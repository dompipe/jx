<?php

// Sample 10: Island protocol map.
// Shows the page JSON as a contract between the emitted C program and localhost API clients.

$protocol = [
    'sample' => 10,
    'island' => [
        'name' => 'jx-native-island',
        'transport' => 'localhost-http',
        'model' => 'page-state-plus-command-routes',
        'routes' => [
            ['method' => 'GET', 'path' => '/json', 'returns' => 'current page JSON'],
            ['method' => 'GET', 'path' => '/update', 'params' => ['title', 'badge', 'body'], 'effect' => 'mutate page core fields'],
            ['method' => 'GET', 'path' => '/modal', 'params' => ['title', 'body'], 'effect' => 'mutate modal fields'],
            ['method' => 'GET', 'path' => '/iframe', 'params' => ['title', 'html'], 'effect' => 'mutate attachment fields'],
        ],
    ],
];

jx_page_title('Sample 10 - Island Protocol');
jx_page_badge('ISLAND');
jx_page_body('The Island protocol treats the native executable as a small local island: compiled page state plus localhost routes that read or mutate that state.');

jx_modal_title('Island command routes');
jx_modal_body('/json reads state. /update, /modal, and /iframe mutate parts of state. The renderer then redraws the native window.');

jx_iframe_title('Protocol JSON');
jx_iframe_html('<pre>' . htmlspecialchars(json_encode($protocol['island'], JSON_PRETTY_PRINT), ENT_QUOTES) . '</pre>');

jx_page_json(json_encode($protocol, JSON_PRETTY_PRINT));

jx_local_api('/json', []);
jx_local_api('/update', ['title', 'badge', 'body']);
jx_local_api('/modal', ['title', 'body']);
jx_local_api('/iframe', ['title', 'html']);
