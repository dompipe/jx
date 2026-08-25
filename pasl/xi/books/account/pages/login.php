<?php
/** @var Binding $bind */
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
?>
<h1>Login</h1>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="account.login">
  <label>Name <input name="name"></label>
  <button type="submit">Continue</button>
</form>
<p class="meta"><a href="/?book=cover">Switch to cover book</a></p>
