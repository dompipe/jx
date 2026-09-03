<?php declare(strict_types=1);

/**
 * Canonical JX v0.1 bootstrap.
 *
 * Server adapters and applications include this boundary once rather than
 * repeatedly including individual runtime files from Pages.
 */

require_once dirname(__DIR__) . '/jx.php';
require_once __DIR__ . '/Flow.php';
require_once __DIR__ . '/SQL.php';
require_once __DIR__ . '/SQLiteResolver.php';
require_once __DIR__ . '/BindingCoercion.php';
require_once __DIR__ . '/RefId.php';
require_once __DIR__ . '/Plugin.php';
require_once __DIR__ . '/ControlBinding.php';
require_once __DIR__ . '/HotRegister.php';
require_once __DIR__ . '/HotEvent.php';
require_once __DIR__ . '/ApiDispatch.php';
require_once __DIR__ . '/HotShadow.php';
require_once __DIR__ . '/AppliedBytecode.php';
require_once __DIR__ . '/NativeBook64.php';
require_once __DIR__ . '/Desktop.php';
require_once __DIR__ . '/DesktopWindowRegister.php';
require_once __DIR__ . '/DesktopHostBridge.php';

// Bundled host-neutral plugins. Hosts still choose their rendering/backend work.
require_once __DIR__ . '/plugins/Charts.php';
require_once __DIR__ . '/plugins/Media.php';
require_once __DIR__ . '/plugins/AudioFx.php';
require_once __DIR__ . '/plugins/AudioAnalysis.php';
require_once __DIR__ . '/plugins/AudioSignals.php';
require_once __DIR__ . '/plugins/VideoAnalysis.php';

$control = dirname(__DIR__) . '/pasl/xi/src/Control.php';
if (is_file($control)) require_once $control;
