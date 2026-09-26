<?php

// JX native page object model.
//
// The native-page compiler pass should treat every object as an addressable
// node. Every object gets an id. Classes are optional. Attrs carry renderer or
// state data that should not be overloaded into text/html payloads.
//
// The current compiler pass still extracts compatibility declarations below,
// but the page source is now object-first.

$page = [
    'type' => 'page',
    'id' => 'demo-page',
    'classes' => ['page', 'native-page'],
    'attrs' => [
        'title' => 'JX PHP Native Page',
        'renderer' => 'win32-native',
    ],
    'children' => [
        [
            'type' => 'text',
            'id' => 'hero-title',
            'classes' => ['hero', 'title'],
            'attrs' => ['role' => 'title'],
            'text' => 'JX PHP Native Page',
        ],
        [
            'type' => 'text',
            'id' => 'status-badge',
            'classes' => ['badge'],
            'attrs' => ['role' => 'badge'],
            'text' => 'BUILT FROM PHP',
        ],
        [
            'type' => 'text',
            'id' => 'body-copy',
            'classes' => ['copy', 'intro-copy'],
            'attrs' => ['role' => 'body'],
            'text' => 'This standalone EXE was generated from examples/native_page.php and examples/style.css. Edit the form or call the local URL API to update this native page live.',
        ],
        [
            'type' => 'form',
            'id' => 'dynamic-form',
            'classes' => ['panel', 'dynamic-form'],
            'attrs' => [
                'method' => 'local',
                'api' => '/update',
            ],
            'fields' => [
                [
                    'type' => 'field',
                    'id' => 'field-title',
                    'classes' => ['field', 'text-field'],
                    'attrs' => ['name' => 'title', 'label' => 'Title'],
                ],
                [
                    'type' => 'field',
                    'id' => 'field-badge',
                    'classes' => ['field', 'text-field'],
                    'attrs' => ['name' => 'badge', 'label' => 'Badge'],
                ],
                [
                    'type' => 'field',
                    'id' => 'field-body',
                    'classes' => ['field', 'textarea-field'],
                    'attrs' => ['name' => 'body', 'label' => 'Body'],
                ],
            ],
        ],
        [
            'type' => 'modal',
            'id' => 'help-modal',
            'classes' => ['modal', 'help-modal'],
            'attrs' => [
                'title' => 'JX Native Modal',
                'api' => '/modal',
            ],
            'children' => [
                [
                    'type' => 'text',
                    'id' => 'help-modal-body',
                    'classes' => ['modal-body'],
                    'attrs' => ['role' => 'body'],
                    'text' => 'This modal was declared in PHP, compiled into C, and drawn by the Win32 native renderer without WebView.',
                ],
            ],
        ],
        [
            'type' => 'iframe',
            'id' => 'native-iframe',
            'classes' => ['iframe', 'native-frame'],
            'attrs' => [
                'title' => 'Native Iframe',
                'api' => '/iframe',
                'html' => 'allowed',
            ],
            'children' => [
                [
                    'type' => 'html',
                    'id' => 'native-iframe-html',
                    'classes' => ['iframe-html'],
                    'attrs' => ['role' => 'content'],
                    'html' => '<h2>Iframe HTML is allowed</h2><p>This content was declared in PHP as iframe HTML and rendered inside a native framed region.</p><p>No WebView is used in this pass.</p>',
                ],
            ],
        ],
    ],
    'api' => [
        [
            'type' => 'api',
            'id' => 'api-update-page',
            'classes' => ['local-api'],
            'attrs' => ['path' => '/update', 'params' => ['title', 'badge', 'body']],
        ],
        [
            'type' => 'api',
            'id' => 'api-open-modal',
            'classes' => ['local-api'],
            'attrs' => ['path' => '/modal', 'params' => ['title', 'body']],
        ],
        [
            'type' => 'api',
            'id' => 'api-update-iframe',
            'classes' => ['local-api'],
            'attrs' => ['path' => '/iframe', 'params' => ['title', 'html']],
        ],
    ],
];

// Compatibility declarations for the current first-pass compiler extractor.
// These are generated from, and must match, the object tree above until the
// extractor reads nested objects directly.
jx_page_title($page['children'][0]['text']);
jx_page_badge($page['children'][1]['text']);
jx_page_body($page['children'][2]['text']);

jx_form_field($page['children'][3]['fields'][0]['attrs']['name'], $page['children'][3]['fields'][0]['attrs']['label']);
jx_form_field($page['children'][3]['fields'][1]['attrs']['name'], $page['children'][3]['fields'][1]['attrs']['label']);
jx_form_field($page['children'][3]['fields'][2]['attrs']['name'], $page['children'][3]['fields'][2]['attrs']['label']);

jx_modal_title($page['children'][4]['attrs']['title']);
jx_modal_body($page['children'][4]['children'][0]['text']);

jx_iframe_title($page['children'][5]['attrs']['title']);
jx_iframe_html($page['children'][5]['children'][0]['html']);

jx_local_api($page['api'][0]['attrs']['path'], $page['api'][0]['attrs']['params']);
jx_local_api($page['api'][1]['attrs']['path'], $page['api'][1]['attrs']['params']);
jx_local_api($page['api'][2]['attrs']['path'], $page['api'][2]['attrs']['params']);
