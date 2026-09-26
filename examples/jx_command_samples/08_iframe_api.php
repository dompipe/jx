<?php

// Sample 08: /iframe route.
// Shows how attached HTML can be changed through the local Island/API surface.

jx_page_title('Sample 08 - API Attachment');
jx_page_badge('IFRAME API');
jx_page_body('This page has an attached HTML region. The route /iframe?title=...&html=... replaces its payload.');

jx_iframe_title('Original attachment');
jx_iframe_html('<article><h2>Original</h2><p>This HTML is the starting attachment.</p></article>');

jx_page_json(json_encode([
    'sample' => 8,
    'iframe' => [
        'title' => 'Original attachment',
        'html' => '<article><h2>Original</h2><p>This HTML is the starting attachment.</p></article>',
    ],
    'api' => [
        ['path' => '/iframe', 'method' => 'GET', 'params' => ['title', 'html'], 'effect' => 'replace attachment title and HTML'],
    ],
], JSON_PRETTY_PRINT));

jx_local_api('/json', []);
jx_local_api('/iframe', ['title', 'html']);
