<?php

// JX native page declarations.
// The first native-page compiler pass reads these literal declarations
// and compiles them into a standalone Win32 executable.

jx_page_title('JX PHP Native Page');
jx_page_badge('BUILT FROM PHP');
jx_page_body('This standalone EXE was generated from examples/native_page.php and examples/style.css. Edit the form or call the local URL API to update this native page live.');

jx_form_field('title', 'Title');
jx_form_field('badge', 'Badge');
jx_form_field('body', 'Body');

jx_local_api('/update', ['title', 'badge', 'body']);
