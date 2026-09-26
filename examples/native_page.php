<?php

// JX native page object model.
//
// The current native-page compiler pass still extracts the literal helper
// declarations below, but the intended programming model is object-first:
// pages contain nested objects, and objects may carry text or HTML payloads.
// This lets the compiler lower the same page tree into Win32, X11, Cocoa, or
// another native target later.

$page = [
    'type' => 'page',
    'id' => 'demo-page',
    'title' => 'JX PHP Native Page',
    'children' => [
        [
            'type' => 'text',
            'id' => 'hero-title',
            'role' => 'title',
            'text' => 'JX PHP Native Page',
        ],
        [
            'type' => 'text',
            'id' => 'badge',
            'role' => 'badge',
            'text' => 'BUILT FROM PHP',
        ],
        [
            'type' => 'text',
            'id' => 'body-copy',
            'role' => 'body',
            'text' => 'This standalone EXE was generated from examples/native_page.php and examples/style.css. Edit the form or call the local URL API to update this native page live.',
        ],
        [
            'type' => 'form',
            'id' => 'dynamic-form',
            'fields' => [
                ['type' => 'field', 'name' => 'title', 'label' => 'Title'],
                ['type' => 'field', 'name' => 'badge', 'label' => 'Badge'],
                ['type' => 'field', 'name' => 'body', 'label' => 'Body'],
            ],
        ],
        [
            'type' => 'modal',
            'id' => 'help-modal',
            'title' => 'JX Native Modal',
            'children' => [
                [
                    'type' => 'text',
                    'text' => 'This modal was declared in PHP, compiled into C, and drawn by the Win32 native renderer without WebView.',
                ],
            ],
        ],
        [
            'type' => 'iframe',
            'id' => 'native-iframe',
            'title' => 'Native Iframe',
            'children' => [
                [
                    'type' => 'html',
                    'html' => '<h2>Iframe HTML is allowed</h2><p>This content was declared in PHP as iframe HTML and rendered inside a native framed region.</p><p>No WebView is used in this pass.</p>',
                ],
            ],
        ],
    ],
    'api' => [
        ['path' => '/update', 'params' => ['title', 'badge', 'body']],
        ['path' => '/modal', 'params' => ['title', 'body']],
        ['path' => '/iframe', 'params' => ['title', 'html']],
    ],
];

// Compatibility declarations for the current first-pass compiler extractor.
// These are generated from, and must match, the object tree above until the
// extractor reads nested objects directly.
jx_page_title($page['children'][0]['text']);
jx_page_badge($page['children'][1]['text']);
jx_page_body($page['children'][2]['text']);

jx_form_field('title', 'Title');
jx_form_field('badge', 'Badge');
jx_form_field('body', 'Body');

jx_modal_title($page['children'][4]['title']);
jx_modal_body($page['children'][4]['children'][0]['text']);

jx_iframe_title($page['children'][5]['title']);
jx_iframe_html($page['children'][5]['children'][0]['html']);

jx_local_api('/update', ['title', 'badge', 'body']);
jx_local_api('/modal', ['title', 'body']);
jx_local_api('/iframe', ['title', 'html']);
