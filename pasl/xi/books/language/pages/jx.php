<?php
/** @var Binding $bind */
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
include __DIR__ . '/_nav.php';
?>
<p class="road-kicker">System language</p>
<h1>JX is becoming a runtime, package format, and host contract</h1>
<p>The JX executable is still PHP at the surface, but it now has a clearer job: install the runtime, load context-free packages, run <code>.jx</code> programs, and bridge into PASM/PASL where the lower layer is ready. The installer work moved plugins away from sibling-package assumptions and toward shared packages that can be linked into a Book or Library.</p>
<p>This means a plugin should be a complete package, not a pile of files that only works because another package is nearby. A Book or Library can link the package, but the package itself must carry its runtime-root lookup and declare every required target: Windows, macOS, Linux, and web.</p>
<div class="road-grid">
  <section class="road-panel">
    <h2>Context-free package</h2>
    <p>No sibling dependency assumptions. The installer hard-rejects manifest <code>depends</code> entries.</p>
  </section>
  <section class="road-panel">
    <h2>Shared plugin folder</h2>
    <p>Packages live once and are linked into Books or Libraries as needed.</p>
  </section>
  <section class="road-panel">
    <h2>Uninstall</h2>
    <p>Both whole-system uninstall and per-plugin uninstall are part of the runtime contract.</p>
  </section>
</div>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="book.turn">
  <input type="hidden" name="dir" value="forward">
  <button type="submit">Continue to Books</button>
</form>
