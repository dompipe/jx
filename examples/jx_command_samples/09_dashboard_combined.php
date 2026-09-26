<?php

// Sample 09: combined page, modal, iframe, JSON, and multiple API routes.
// Shows how the individual jx_* declarations cooperate as one native page state.

$state = [
    'sample' => 9,
    'page' => [
        'title' => 'Sample 09 - Operations Dashboard',
        'badge' => 'DASHBOARD',
        'body' => 'All declaration families are active in this sample.',
    ],
    'modal' => [
        'title' => 'Dispatch note',
        'body' => 'The modal can be replaced through /modal.',
    ],
    'iframe' => [
        'title' => 'Attached metrics',
        'html' => '<div><strong>Open:</strong> 4<br><strong>Closed:</strong> 9</div>',
    ],
    'api' => [
        ['path' => '/json', 'params' => []],
        ['path' => '/update', 'params' => ['title', 'badge', 'body']],
        ['path' => '/modal', 'params' => ['title', 'body']],
        ['path' => '/iframe', 'params' => ['title', 'html']],
    ],
];

jx_page_title($state['page']['title']);
jx_page_badge($state['page']['badge']);
jx_page_body($state['page']['body']);
jx_modal_title($state['modal']['title']);
jx_modal_body($state['modal']['body']);
jx_iframe_title($state['iframe']['title']);
jx_iframe_html($state['iframe']['html']);
jx_page_json(json_encode($state, JSON_PRETTY_PRINT));

jx_local_api('/json', []);
jx_local_api('/update', ['title', 'badge', 'body']);
jx_local_api('/modal', ['title', 'body']);
jx_local_api('/iframe', ['title', 'html']);
