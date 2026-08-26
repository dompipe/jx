<?php
/** @var Bag $buffer */
/** @var Binding $bind */
$dir = (string)$buffer->get('dir', 'forward');
if ($dir === 'back') {
    $bind->back();
} elseif ($dir === 'open') {
    $bind->open((string)$buffer->get('page', $bind->here()));
} else {
    $bind->forward();
}
$buffer->set('next', $bind->here());
