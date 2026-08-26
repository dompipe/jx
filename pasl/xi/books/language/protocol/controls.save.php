<?php
/** @var Bag $buffer */
/** @var Binding $bind */
$controls = $bind->channel('controls');
$incoming = $buffer->get('control', []);
if (is_array($incoming)) {
    foreach ($incoming as $id => $value) {
        $id = preg_replace('/[^a-z0-9._-]/i', '', (string)$id) ?? '';
        if ($id === '') {
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $controls->set($id, (string)$value);
        }
    }
}
$buffer->set('next', 'controls');
