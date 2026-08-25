<?php
/** @var Binding $bind */
$user = $bind->channel('user');
$name = htmlspecialchars((string)$user->get('name', 'reader'), ENT_QUOTES, 'UTF-8');
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
?>
<h1>End (normalized)</h1>
<p>Thank you, <?= $name ?>.</p>
<p class="meta">Normalized leaf: rest surface. Durable bags still exist for state-ready leaves when you go back.</p>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="book.turn">
  <input type="hidden" name="dir" value="back">
  <button type="submit">Back</button>
</form>
