<?php
/** @var Binding $bind */
$bookId = htmlspecialchars($bind->bookId(), ENT_QUOTES, 'UTF-8');
$store = $bind->channel('controls');
include __DIR__ . '/_nav.php';

$name = (string)$store->get('title', 'Window contract');
$spin = (int)$store->get('spin.rate', 3);
$image = (string)$store->get('image.any', 'data:image/svg+xml;utf8,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%20240%20120%22%3E%3Crect%20width%3D%22240%22%20height%3D%22120%22%20fill%3D%22%23f8f8f8%22/%3E%3Ccircle%20cx%3D%2260%22%20cy%3D%2260%22%20r%3D%2234%22%20fill%3D%22%230069a6%22/%3E%3Cpath%20d%3D%22M110%2085%20L150%2035%20L190%2085Z%22%20fill%3D%22%23b0413e%22/%3E%3Ctext%20x%3D%2212%22%20y%3D%22112%22%20font-size%3D%2214%22%3Eany%20image%20control%3C/text%3E%3C/svg%3E');
$mime = str_starts_with($image, 'data:') && preg_match('#^data:([^;,]+)#', $image, $m) ? $m[1] : 'image/*';

$controls = [
    Control::text('title', 'Text input', $name),
    Control::spin('spin.rate', 'Spin control', $spin, ['min' => -12, 'max' => 12, 'step' => 1, 'pin' => true]),
    Control::drawing('drawing.surface', 'Drawing surface', 360, 180, [
        ['op' => 'rect', 'x' => 16, 'y' => 18, 'width' => 112, 'height' => 72, 'fill' => '#dbeafe'],
        ['op' => 'circle', 'cx' => 196, 'cy' => 78, 'r' => 38, 'fill' => '#f59e0b'],
        Control::line('sweep-line', ['x' => 26, 'y' => 150], ['x' => 330, 'y' => 34], true) + ['stroke' => '#111827', 'width' => 3],
        Control::curve('motion-curve', ['x' => 24, 'y' => 108], ['x' => 92, 'y' => 20], ['x' => 228, 'y' => 166], ['x' => 336, 'y' => 88]) + ['stroke' => '#7c3aed', 'width' => 4],
    ]),
    Control::image('image.any', 'Any image type', $image, $mime, ['alt' => 'Image contract accepts any MIME-backed image source']),
];
?>
<p class="road-kicker">Control contracts</p>
<h1>Controls belong to the window contract</h1>
<p>A control is not just markup. It is a contract a host can render as HTML, Win32, X11, or another native surface. This leaf shows form input, a spin control, drawing operations, and an image control that accepts any image type by MIME/source contract.</p>
<form method="post">
  <input type="hidden" name="book" value="<?= $bookId ?>">
  <input type="hidden" name="protocol" value="controls.save">
  <div class="road-grid">
    <?php foreach ($controls as $control): ?>
      <?= $control->render() ?>
    <?php endforeach; ?>
  </div>
  <button type="submit">Save Control Values</button>
</form>
<p class="road-note">Native hosts should read <code>data-control</code> contracts or the same PHP <code>Control::contract()</code> arrays, then render real OS controls. Browser rendering is only one renderer.</p>
