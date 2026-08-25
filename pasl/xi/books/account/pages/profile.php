<?php
/** @var Binding $bind */
$name = htmlspecialchars((string)$bind->channel('user')->get('name', ''), ENT_QUOTES, 'UTF-8');
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
?>
<h1>Profile</h1>
<p>Signed in as <strong><?= $name !== '' ? $name : '—' ?></strong></p>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="book.turn">
  <input type="hidden" name="dir" value="back">
  <button type="submit">Back to login</button>
</form>
