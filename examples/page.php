<?php
$name = $argv[1] ?? 'world';
$cssFile = getenv('JX_CSS_FILE') ?: '';
$cssName = getenv('JX_CSS_NAME') ?: 'style.css';
$css = '';

if ($cssFile !== '' && is_file($cssFile)) {
    $css = file_get_contents($cssFile);
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>JX CSS Asset Demo</title>
  <style><?= $css ?></style>
</head>
<body>
  <main class="card">
    <span class="badge">JX CSS</span>
    <h1>Hello from JX, <?= htmlspecialchars($name, ENT_QUOTES) ?></h1>
    <p>Loaded CSS asset: <?= htmlspecialchars($cssName, ENT_QUOTES) ?></p>
  </main>
</body>
</html>
