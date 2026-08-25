<?php
/** @var Bag $buffer */
/** @var Binding $bind */
$dir = (string)$buffer->get('dir', 'forward');
if ($dir === 'back') {
    $bind->back();
} else {
    $bind->forward();
}
$buffer->set('next', $bind->here());
