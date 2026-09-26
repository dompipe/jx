<?php

// Sample 04: page JSON as the authoritative machine-readable attachment.
// Shows how jx_page_json can describe the page object model, renderer hints, API routes, and state.

$page = [
    'type' => 'page',
    'id' => 'sample-04-page',
    'classes' => ['sample', 'json-first'],
    'attrs' => [
        'title' => 'Sample 04 - JSON State',
        'renderer' => 'win32-native',
        'webview' => false,
    ],
    'children' => [
        ['type' => 'text', 'id' => 'hero-title', 'classes' => ['hero'], 'text' => 'JSON is the structured page contract'],
        ['type' => 'text', 'id' => 'body-copy', 'text' => 'The PHP declarations are human-friendly. The JSON gives tools a stable object model.'],
    ],
];

jx_page_title('Sample 04 - JSON State');
jx_page_badge('JSON');
jx_page_body('This sample makes the page JSON explicit. The same information can be used by the compiler, Island protocol, diagnostics, and tests.');
jx_page_json(json_encode(['page' => $page], JSON_PRETTY_PRINT));
