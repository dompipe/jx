<?php

jx_page_title('JX Native API Window');
jx_page_badge('NO WEBVIEW');
jx_page_body('This window was declared in PHP, emitted as C with jx -o out.c in.php, and compiled into a Win32 executable. It draws with the native renderer and listens on localhost for API updates.');

jx_modal_title('Compiled Modal');
jx_modal_body('The modal text is compiled from PHP declarations into the generated C file.');

jx_iframe_title('Attached Page JSON');
jx_iframe_html('<h2>JX native attachment</h2><p>The executable exposes /json, /update, /modal, and /iframe on localhost.</p>');

jx_page_json('{"page":{"title":"JX Native API Window","renderer":"win32-native","webview":false},"api":[{"path":"/json","method":"GET","returns":"application/json"},{"path":"/update","params":["title","badge","body"]},{"path":"/modal","params":["title","body"]},{"path":"/iframe","params":["title","html"]}]}');

jx_local_api('/json', []);
jx_local_api('/update', ['title', 'badge', 'body']);
jx_local_api('/modal', ['title', 'body']);
jx_local_api('/iframe', ['title', 'html']);
