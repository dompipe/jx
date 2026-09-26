<?php

// JX native page declarations.
// The native-page compiler pass reads these literal declarations
// and compiles them into a standalone Win32 executable.

jx_page_title('JX PHP Native Page');
jx_page_badge('BUILT FROM PHP');
jx_page_body('This standalone EXE was generated from examples/native_page.php and examples/style.css. Edit the form or call the local URL API to update this native page live.');

jx_form_field('title', 'Title');
jx_form_field('badge', 'Badge');
jx_form_field('body', 'Body');

jx_modal_title('JX Native Modal');
jx_modal_body('This modal was declared in PHP, compiled into C, and drawn by the Win32 native renderer without WebView.');

jx_local_api('/update', ['title', 'badge', 'body']);
jx_local_api('/modal', ['title', 'body']);
