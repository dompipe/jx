<?php declare(strict_types=1);

/**
 * JX11 -> canonical JX window Bag bridge.
 *
 * Listens on a Unix datagram socket. The native jx11 process never waits for
 * this service; datagrams are observational/checkpoint traffic only.
 *
 * Usage:
 *   php host/linux/jx11-event-bridge.php /tmp/jx11-events.sock
 */
require_once dirname(__DIR__, 2) . '/jx/bootstrap.php';

use jx\Bag;
use jx\DesktopHostBridge;

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
    if (count($p) !== 12 || $p[0] !== 'JX11E/1') throw new RuntimeException('unsupported packet');

    $event = $p[1];
    if (!in_array($event, ['window-open','window-update','window-focus','window-close','window-unmap'], true)) {
        throw new RuntimeException('unsupported event');
    }
    if (!preg_match('/^x11:[0-9a-f]{1,8}$/i', $p[2])) throw new RuntimeException('invalid host id');

    return [
        'event' => $event,
        'window' => [
            'host_id' => strtolower($p[2]),
            'x' => (int)$p[3],
            'y' => (int)$p[4],
            'width' => max(0, (int)$p[5]),
            'height' => max(0, (int)$p[6]),
            'focused' => $p[7] === '1',
            'mapped' => $p[8] === '1',
            'workspace' => max(0, (int)$p[9]),
            'title' => jx11e_decode_hex($p[10], 1024),
            'class' => jx11e_decode_hex($p[11], 256),
        ],
    ];
}

fwrite(STDERR, "jx11-event-bridge: listening {$path}\n");
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
        // This service is deliberately data-shaped. stdout is a checkpoint
        // stream suitable for tests, supervisors, or a later shared Bag host.
        echo json_encode([
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
