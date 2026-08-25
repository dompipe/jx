<?php
/** @var Binding $bind */
/** @var Book $book */
$user = $bind->channel('user');
$drop = $bind->channel('drop');
$leaf = $bind->channel('leaf.home');
$name = htmlspecialchars((string)$user->get('name', ''), ENT_QUOTES, 'UTF-8');
$dropMsg = htmlspecialchars((string)$drop->get('message', ''), ENT_QUOTES, 'UTF-8');
$rows = $leaf->get('rows', []);
if (!is_array($rows)) {
    $rows = [];
}
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
?>
<h1>Home (state-ready)</h1>
<p>Hello, <strong id="id1"><?= $name !== '' ? $name : 'guest' ?></strong></p>
<?php if ($dropMsg !== ''): ?>
  <p class="meta">drop channel: <?= $dropMsg ?></p>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="home.save">
  <label>Name <input name="name" value="<?= $name ?>"></label>
  <button type="submit">Save</button>
</form>

<h2>Table grid1 (Y-axis / iframe-like channel)</h2>
<table>
  <thead><tr><th>Y</th><th>A</th><th>B</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $y => $row): ?>
    <?php if (!is_array($row)) continue; ?>
    <tr id="row-<?= (int)$y ?>">
      <td><?= (int)$y ?></td>
      <td><?= htmlspecialchars((string)($row['a'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
      <td><?= htmlspecialchars((string)($row['b'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if ($rows === []): ?>
    <tr><td colspan="3"><em>no rows yet</em></td></tr>
  <?php endif; ?>
  </tbody>
</table>
<p class="meta">Drop JSON into <code>data/books/cover/inbox/*.json</code> — picked up on next interaction (no refresh loop).</p>
