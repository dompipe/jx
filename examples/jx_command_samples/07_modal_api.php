<?php

// Sample 07: /modal route.
// Shows how modal declaration state and local API parameters work together.

jx_page_title('Sample 07 - API Modal');
jx_page_badge('MODAL API');
jx_page_body('The base page is static, but the modal can be replaced through the Island/API route.');

jx_modal_title('Original modal title');
jx_modal_body('Original modal body. Call /modal?title=New%20Title&body=New%20Body to update it.');

jx_page_json(json_encode([
    'sample' => 7,
    'modal' => [
        'title' => 'Original modal title',
        'body' => 'Original modal body',
    ],
    'api' => [
        ['path' => '/modal', 'method' => 'GET', 'params' => ['title', 'body'], 'effect' => 'replace modal title and body'],
    ],
], JSON_PRETTY_PRINT));

jx_local_api('/json', []);
jx_local_api('/modal', ['title', 'body']);
