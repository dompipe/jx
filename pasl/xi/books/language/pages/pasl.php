<?php
/** @var Binding $bind */
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/_nav.php';
?>
<p class="road-kicker">Compiler layer</p>
<h1>PASL became the bridge from source to execution</h1>
<p>PASL started as the readable language path into PASM. It now carries arrays, strings, complex numbers, loops, control flow, bytecode output, and packaged compiler payloads. The important direction is that PASL is no longer only a demonstration language; it is becoming the stable intermediate language that JX can rely on when a Book leaf needs executable behavior.</p>
<p>In the current Book host, a leaf can name a PASL file in <code>book.json</code>. The engine compiles that leaf program to PASM and hands it to a host. The browser adapter can execute the PASM result, but the Book itself still renders and navigates without JavaScript. That keeps the foundation honest: the webserver is a host, not the whole system.</p>
<p class="road-note">Near-term rule: prove navigation and Book state through PHP and HTTP form posts. Browser execution is an adapter, not the required base.</p>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="book.turn">
  <input type="hidden" name="dir" value="forward">
  <button type="submit">Continue to JX</button>
</form>
