<?php
/** @var Binding $bind */
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/_nav.php';
?>
<p class="road-kicker">Next build</p>
<h1>The next step is making JX open the Book host</h1>
<p>The immediate next work is to make the JX executable own the window launch path. Today, the working command is still direct XI:</p>
<pre><code>cd pasl/xi
php xi.php localhost:8766 start config.json --foreground</code></pre>
<p>The next version should let JX say: open this Library, open this Book, choose this host, and keep the Book state alive. That path should still support the webserver, but the webserver should be the browser/window host, not the whole identity of the system.</p>
<p>Concrete next tasks:</p>
<ol>
  <li>Add a <code>jx book open</code> or equivalent command that launches XI for a Library and opens a Book.</li>
  <li>Promote <code>jx window-server</code> into the parent desktop process, so Windows and Linux treat JX like a user-space Explorer/X11-style shell.</li>
  <li>Make the host protocol bidirectional enough for window close, focus, resize, leaf change, and drop acknowledgements.</li>
  <li>Compile and test the Win32 and X11 adapters on their native targets.</li>
  <li>Keep Book navigation and state provable without JavaScript.</li>
  <li>Decide where PASL execution belongs for native hosts: embedded VM, compiled target function, or host-provided execution service.</li>
</ol>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="book.turn">
  <input type="hidden" name="dir" value="back">
  <button type="submit">Back</button>
</form>
