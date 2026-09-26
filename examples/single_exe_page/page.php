<?php
require_once 'parts/content.php';

$cssFile = getenv('JX_CSS_FILE') ?: '';
$css = is_file($cssFile) ? file_get_contents($cssFile) : '';
$logoPath = 'images/logo.svg';
$logoSize = is_file($logoPath) ? filesize($logoPath) : 0;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(page_title(), ENT_QUOTES) ?></title>
  <style><?= $css ?></style>
</head>
<body>
  <main class="shell">
    <img class="logo" src="<?= htmlspecialchars($logoPath, ENT_QUOTES) ?>" alt="JX logo">
    <p class="eyebrow">single executable input</p>
    <h1><?= htmlspecialchars(page_title(), ENT_QUOTES) ?></h1>
    <p><?= htmlspecialchars(page_body(), ENT_QUOTES) ?></p>
    <p class="proof">Bundled image bytes: <?= (int)$logoSize ?></p>
  </main>
</body>
</html>
