<?php
/** @var Binding $bind */
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/_nav.php';
?>
<p class="road-kicker">Language history</p>
<h1>From PASM and jx-lang into one JX</h1>
<p>JX now carries two source lineages in one repo. The PASM/PASL side supplies the executable substrate: bytecode, compiler passes, payload loaders, numeric runtime behavior, and the low-level habit of treating programs as transportable frames. The jx-lang side supplies the language intent: Books, Pages, Bags, Delivery, smart tables, complex values, and a hosting API that is not trapped inside a normal website model.</p>
<p>The convergence work did not erase either history. The original jx-lang documents live under <code>history/jx-lang</code>, while the active runtime sits beside PASM/PASL. That matters because JX is not just a syntax layer. It is the place where language design, package installation, Book state, and window hosting are being made into one conveyance.</p>
<div class="road-grid">
  <section class="road-panel">
    <h2>PASM</h2>
    <p>The machine-near instruction and bytecode base. It gives JX a path toward compiled execution instead of remaining only a PHP page.</p>
  </section>
  <section class="road-panel">
    <h2>PASL</h2>
    <p>The language/compiler layer above PASM. PASL can lower readable code into PASM and can be hosted by a Book leaf.</p>
  </section>
  <section class="road-panel">
    <h2>JX</h2>
    <p>The system language surface: Books, windows, packages, host drops, and eventual native execution.</p>
  </section>
</div>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="book.turn">
  <input type="hidden" name="dir" value="forward">
  <button type="submit">Continue to PASL</button>
</form>
