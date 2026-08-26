<?php
/** @var Binding $bind */
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/_nav.php';
?>
<p class="road-kicker">Where it is going</p>
<h1>Toward an OS-like window environment</h1>
<p>The long direction is not merely "a better webserver." The long direction is a window environment where the same Book model can be hosted by different operating systems, and where JX can become the language glue between executable logic, durable state, package install, and windows.</p>
<p>That makes the browser useful without making it sacred. If browsers change, disappear, or become the wrong host for some work, the Book model should survive. A Book should still have a spine, leaves, Bags, drops, protocols, and a window. The host should adapt around that contract.</p>
<div class="road-grid">
  <section class="road-panel">
    <h2>X11 replacement energy</h2>
    <p>The target is an explicit window protocol that can matter outside the browser, with X11/Linux relevance and a Windows path.</p>
  </section>
  <section class="road-panel">
    <h2>More than Node or Docker</h2>
    <p>JX sits somewhere between process host, language runtime, package system, and window server.</p>
  </section>
  <section class="road-panel">
    <h2>Drop-in JSON updates</h2>
    <p>Hosts can feed changes through versioned drops, letting windows and Books update without tying the design to one OS or browser.</p>
  </section>
</div>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="book.turn">
  <input type="hidden" name="dir" value="forward">
  <button type="submit">Continue to Next</button>
</form>
