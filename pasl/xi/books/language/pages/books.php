<?php
/** @var Binding $bind */
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/_nav.php';
?>
<p class="road-kicker">Library model</p>
<h1>A Library is made of Books; a Book is made of leaves</h1>
<p>The current XI folder is already a Library shape: <code>pasl/xi/books</code> holds multiple Books. Each Book has a <code>book.json</code>, a spine, pages, optional protocols, channels, drops, and window metadata. The Book is the unit of user movement. A section change is a Book-to-Book move; a page change is a leaf move inside the Book.</p>
<p>This is the refactor direction: a Book is not just a webpage directory. It is a portable section of application state that can be hosted by a browser, an X11-like native host, or a Windows host. The webserver can host it, but the webserver should not define what it is.</p>
<div class="road-grid">
  <section class="road-panel">
    <h2>Binding</h2>
    <p>The current leaf, spine, history, and channel bus. Back and Forward belong here.</p>
  </section>
  <section class="road-panel">
    <h2>Bag</h2>
    <p>A durable state surface. Protocols and leaves exchange values through named Bags/channels.</p>
  </section>
  <section class="road-panel">
    <h2>Drop</h2>
    <p>A JSON message into the Book from a host or external process. Drops are versioned and bounded.</p>
  </section>
</div>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="book.turn">
  <input type="hidden" name="dir" value="forward">
  <button type="submit">Continue to Windows</button>
</form>
