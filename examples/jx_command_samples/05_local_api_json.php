<?php

// Sample 05: expose the read-only /json route.
// Shows how jx_local_api('/json', []) advertises a local Island/API route.

jx_page_title('Sample 05 - Local JSON API');
jx_page_badge('API');
jx_page_body('The local API can expose the current page state. /json is the simplest Island protocol read route.');

jx_page_json(json_encode([
    'sample' => 5,
    'page' => [
        'title' => 'Sample 05 - Local JSON API',
        'badge' => 'API',
        'body' => 'Read this state from /json.',
    ],
    'api' => [
        ['path' => '/json', 'method' => 'GET', 'params' => [], 'returns' => 'application/json'],
    ],
], JSON_PRETTY_PRINT));

jx_local_api('/json', []);
