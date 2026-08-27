<?php declare(strict_types=1);

/**
 * JX11 -> canonical JX window Bag bridge.
 *
 * JX11E/1 compact packet:
 * version|event|host_id|wreg|wref|x|y|w|h|focus|mapped|workspace|title_hex|class_hex
 *
 * wreg is the startup-resolved WindowBag register (0..255).
 * wref is the packed uint16 [slot:shadow] identity.
 */
require_once dirname(__DIR__, 2) . '/jx/bootstrap.php';

use jx\Bag;
use jx\DesktopHostBridge;
use jx\DesktopWindowRegister;

$path = $argv[1] ?? '/tmp/jx11-events.sock';
if ($path === '' || strlen($path) > 100 || str_contains($path, "\0")) {
    fwrite(STDERR, "jx11-event-bridge: invalid socket path\n");
    exit(2);
}

@unlink($path);
$socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
if ($socket === false || !socket_bind($socket, $path)) {
    fwrite(STDERR, "jx11-event-bridge: cannot bind {$path}\n");
    exit(2);
}
@chmod($path, 0600);

$bag = Bag::empty(1_048_576);
$bridge = new DesktopHostBridge($bag, 'windows');
$registers = new DesktopWindowRegister();
$registers->intern('windows'); // W0 for the first JX11 desktop bridge.

function jx11e_decode_hex(string $hex, int $maxBytes): string
{
    if ($hex === '') return '';
    if (strlen($hex) > $maxBytes * 2 || (strlen($hex) & 1) || preg_match('/[^0-9a-f]/i', $hex)) {
        throw new RuntimeException('invalid hex field');
    }
    $value = hex2bin($hex);
    if ($value === false || str_contains($value, "\0")) throw new RuntimeException('invalid text field');
    return $value;
}

/** @return array<string,mixed> */
function jx11e_parse(string $packet): array
{
    if (strlen($packet) > 8192 || str_contains($packet, "\0")) throw new RuntimeException('packet too large');
    $p = explode('|', trim($packet));
    if (count($p) !== 14 || $p[0] !== 'JX11E/1') throw new RuntimeException('unsupported packet');

    $event = $p[1];
    if (!in_array($event, ['window-open','window-update','window-focus','window-close','window-unmap'], true)) {
        throw new RuntimeException('unsupported event');
    }
    if (!preg_match('/^x11:[0-9a-f]{1,8}$/i', $p[2])) throw new RuntimeException('invalid host id');

    $wreg = (int)$p[3];
    $wref = (int)$p[4];
    if ($wreg < 0 || $wreg > 255) throw new RuntimeException('invalid WindowBag register');
    DesktopWindowRegister::unpack($wref); // validates uint16 range.

    return [
        'event' => $event,
        'window_register' => $wreg,
        'window' => [
            'host_id' => strtolower($p[2]),
            'window_ref' => $wref,
            'x' => (int)$p[5],
            'y' => (int)$p[6],
            'width' => max(0, (int)$p[7]),
            'height' => max(0, (int)$p[8]),
            'focused' => $p[9] === '1',
            'mapped' => $p[10] === '1',
            'workspace' => max(0, (int)$p[11]),
            'title' => jx11e_decode_hex($p[12], 1024),
            'class' => jx11e_decode_hex($p[13], 256),
        ],
    ];
}

fwrite(STDERR, "jx11-event-bridge: listening {$path} W0=windows\n");
register_shutdown_function(static function () use ($socket, $path): void {
    if ($socket !== false) @socket_close($socket);
    @unlink($path);
});

for (;;) {
    $packet = '';
    $from = '';
    $n = @socket_recvfrom($socket, $packet, 8192, 0, $from);
    if ($n === false) continue;
    try {
        $event = jx11e_parse($packet);
        $bridge->apply($event);
        echo json_encode([
            'window_register' => $event['window_register'],
            'bag' => 'windows',
            'event' => $event['event'],
            'rows' => $bridge->rows(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
        if (function_exists('ob_flush')) @ob_flush();
        flush();
    } catch (Throwable $e) {
        fwrite(STDERR, "jx11-event-bridge: rejected packet: {$e->getMessage()}\n");
    }
}
