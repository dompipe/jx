<?php
/** @var Binding $bind */
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/_nav.php';
?>
<p class="road-kicker">Window host</p>
<h1>The browser is one window host, not the final boundary</h1>
<p>The next layer is a window system contract. A Book declares portable window metadata: id, title, bounds, current leaf, and host protocol version. The host protocol currently recognizes <code>browser</code>, <code>win32</code>, and <code>x11</code>. That is the beginning of treating a browser window, a native Windows window, and an X11-style window as equivalent hosts for the same Book.</p>
<p>The browser-hosted webserver remains important because it gives inspection, fallback, remote access, and compatibility. But the goal is larger: JX should be able to open and control windows as a native surface, with Books moving smoothly through leaves and across sections. Web delivery becomes one host among several.</p>
<p class="road-note">Current native files are scaffolds: <code>pasl/host/jx_host.h</code>, <code>jx_host_win32.c</code>, and <code>jx_host_x11.c</code>. They need real target OS compilation work next.</p>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="book.turn">
  <input type="hidden" name="dir" value="forward">
  <button type="submit">Continue to OS Road</button>
</form>
