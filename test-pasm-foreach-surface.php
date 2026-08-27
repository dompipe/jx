<?php declare(strict_types=1);

require_once __DIR__ . '/pasm-lang.php';

use pasm\lang\PASMForeachSurface;

assert(PASMForeachSurface::keywords() === ['foreach', 'reveach']);
assert(PASMForeachSurface::iteratorOpcode('foreach') === 'ITERF');
assert(PASMForeachSurface::iteratorOpcode('reveach') === 'ITERR');
assert(PASMForeachSurface::reverse('foreach') === false);
assert(PASMForeachSurface::reverse('reveach') === true);

fwrite(STDOUT, "PASS PASL collection loop vocabulary foreach/reveach\n");
