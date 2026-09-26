<?php

// Sample 01: the smallest useful JX native page declaration.
// Shows how jx_page_title, jx_page_badge, and jx_page_body become page state.

jx_page_title('Sample 01 - Native Header');
jx_page_badge('BASIC');
jx_page_body('This sample declares the main page title, the small badge, and the body copy. JX captures these values and emits them into the generated C page state.');

jx_page_json(json_encode([
    'sample' => 1,
    'purpose' => 'title badge body',
    'page' => [
        'title' => 'Sample 01 - Native Header',
        'badge' => 'BASIC',
        'body' => 'Main body copy is declared in PHP and compiled into C.',
    ],
], JSON_PRETTY_PRINT));
