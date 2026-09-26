<?php

// Sample 03: iframe-style attachment declarations.
// Shows how jx_iframe_title and jx_iframe_html carry attached rich content.

jx_page_title('Sample 03 - Attached HTML');
jx_page_badge('IFRAME');
jx_page_body('The main native page can carry an attached HTML payload. Renderers decide whether to lower it to text, native widgets, or an embedded attachment surface.');

jx_iframe_title('Attached status card');
jx_iframe_html('<section class="card"><h2>Attached HTML</h2><p>This HTML is declared through jx_iframe_html and emitted into the C program as an attachment payload.</p></section>');

jx_page_json(json_encode([
    'sample' => 3,
    'page' => ['title' => 'Sample 03 - Attached HTML'],
    'iframe' => [
        'title' => 'Attached status card',
        'html' => '<section class="card"><h2>Attached HTML</h2><p>Attachment payload.</p></section>',
    ],
], JSON_PRETTY_PRINT));
