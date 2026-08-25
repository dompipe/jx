<?php
/** @var Bag $buffer */
/** @var Binding $bind */
$user = $bind->channel('user');
$user->set('name', (string)$buffer->get('name', ''));
$buffer->set('next', 'profile');
