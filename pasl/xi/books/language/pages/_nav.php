<?php
/** @var Binding $bind */
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
$leaves = [
    'origin' => 'Origin',
    'pasl' => 'PASL',
    'jx' => 'JX',
    'books' => 'Books',
    'windows' => 'Windows',
    'os-road' => 'OS Road',
    'next' => 'Next'
];
?>
<style>
.road-nav{display:flex;flex-wrap:wrap;gap:.4rem;margin:1rem 0}
.road-nav form{display:inline}
.road-nav button{padding:.35rem .55rem}
.road-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:1rem;margin:1rem 0}
.road-panel{border:1px solid #ddd;border-radius:8px;padding:1rem}
.road-kicker{color:#666;font-size:.9rem;text-transform:uppercase;letter-spacing:.08em}
.road-note{background:#f7f7f7;border-left:4px solid #777;padding:.75rem 1rem}
</style>
<div class="road-nav" aria-label="Book leaves">
<?php foreach ($leaves as $leaf => $label): ?>
  <form method="post">
    <input type="hidden" name="book" value="<?= $bookId ?>">
    <input type="hidden" name="protocol" value="book.turn">
    <input type="hidden" name="dir" value="open">
    <input type="hidden" name="page" value="<?= htmlspecialchars($leaf, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></button>
  </form>
<?php endforeach; ?>
</div>
