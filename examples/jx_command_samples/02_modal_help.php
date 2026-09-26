<?php

// Sample 02: modal declarations.
// Shows how jx_modal_title and jx_modal_body layer modal state on top of page state.

jx_page_title('Sample 02 - Modal Help');
jx_page_badge('MODAL');
jx_page_body('The base page stays simple. The modal declaration adds an overlay payload that the emitted C program can render as the top layer.');

jx_modal_title('How this native modal works');
jx_modal_body('The modal title and body are declared in PHP. JX stores them beside the page title, badge, and body. The native renderer can draw the modal without a WebView.');

jx_page_json(json_encode([
    'sample' => 2,
    'page' => ['title' => 'Sample 02 - Modal Help', 'badge' => 'MODAL'],
    'modal' => [
        'title' => 'How this native modal works',
        'body' => 'Modal content is its own page-state object.',
        'stack' => 'above-page',
    ],
], JSON_PRETTY_PRINT));
