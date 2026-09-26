<?php

// Sample 06: /update route.
// Shows how the Island/API layer can mutate title, badge, and body at runtime.

jx_page_title('Sample 06 - Mutable Page');
jx_page_badge('LIVE');
jx_page_body('Start state. The emitted C program can accept /update?title=...&badge=...&body=... to replace these fields.');

jx_page_json(json_encode([
    'sample' => 6,
    'page' => [
        'title' => 'Sample 06 - Mutable Page',
        'badge' => 'LIVE',
        'body' => 'Start state.',
    ],
    'api' => [
        [
            'path' => '/update',
            'method' => 'GET',
            'params' => ['title', 'badge', 'body'],
            'effect' => 'replace page title, badge, and body fields',
        ],
    ],
], JSON_PRETTY_PRINT));

jx_local_api('/json', []);
jx_local_api('/update', ['title', 'badge', 'body']);
