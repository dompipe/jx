<?php
/** @var Bag $buffer */
/** @var Binding $bind */
$user = $bind->channel('user');
$name = (string)$buffer->get('name', '');
if (strlen($name) > 200) {
    $name = substr($name, 0, 200);
}
$user->set('name', $name);
$leaf = $bind->channel('leaf.home');
$rows = $leaf->get('rows', []);
if (!is_array($rows)) {
    $rows = [];
}
$rows[] = ['a' => $name !== '' ? $name : '(empty)', 'b' => date('H:i:s')];
if (count($rows) > 20) {
    $rows = array_slice($rows, -20);
}
$leaf->set('rows', $rows);
$buffer->set('next', 'home');
