<?php
/** @var Binding $bind */
$user = $bind->channel('user');
$name = htmlspecialchars((string)$user->get('name', ''), ENT_QUOTES, 'UTF-8');
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
?>
<h1>About (state-ready)</h1>
<p>This leaf still sees the <strong>user</strong> channel: <?= $name !== '' ? $name : '(no name yet)' ?>.</p>
<p>Back/forward uses the binding — state is not lost.</p>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="book.turn">
  <input type="hidden" name="dir" value="forward">
  <button type="submit">Go to end</button>
</form>
