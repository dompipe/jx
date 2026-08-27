<?php declare(strict_types=1);

require_once __DIR__ . '/jx/bootstrap.php';

use jx\ApiDispatchTable;
use jx\ApiPacket;
use jx\ApiShadow;
use jx\ApiTransport;
use jx\HotAddress;
use jx\HotDelivery;
use jx\JxException;

$table = new ApiDispatchTable(9);
$get = $table->add('system.clock.read', ApiTransport::DIRECT, HotDelivery::QUEUE, true, 1000, 'clock.read');
$net = $table->add('weather.current', ApiTransport::HTTPS, HotDelivery::QUEUE, true, 5000, 'network.https.connect');
$ssh = $table->add('server.logs.read', ApiTransport::SSH, HotDelivery::QUEUE, true, 10000, 'network.ssh.transfer.read');

if ($get->slot !== 0 || $net->slot !== 1 || $ssh->slot !== 2 || $table->count() !== 3) {
    throw new RuntimeException('API slot allocation mismatch');
}
if ($table->add('system.clock.read') !== $get) {
    throw new RuntimeException('API endpoint interning mismatch');
}
if ($get->address(ApiShadow::REQUEST) !== 0x090000 ||
    $get->address(ApiShadow::SUCCESS) !== 0x090001 ||
    $net->address(ApiShadow::ERROR) !== 0x090102 ||
    $ssh->address(ApiShadow::REQUEST) !== 0x090200) {
    throw new RuntimeException('API hot address layout mismatch');
}
if (HotAddress::canonical($net->address(ApiShadow::STREAM)) !== 'W9:[1:3]') {
    throw new RuntimeException('API canonical route mismatch');
}
if (!ApiTransport::isSecureRemote(ApiTransport::HTTPS) || !ApiTransport::isSecureRemote(ApiTransport::SSH) ||
    ApiTransport::isSecureRemote(ApiTransport::HTTP)) {
    throw new RuntimeException('secure remote transport classification mismatch');
}

$rejectedGenericSsh = false;
try {
    $table->add('server.shell', ApiTransport::SSH, HotDelivery::QUEUE, false, 10000, 'network.ssh');
} catch (JxException) {
    $rejectedGenericSsh = true;
}
if (!$rejectedGenericSsh) {
    throw new RuntimeException('generic SSH authority must be rejected');
}

$packet = ApiPacket::encode($net, 0x10203040, '{"city":"Detroit"}', ApiShadow::REQUEST, 0,
    ApiPacket::CONTENT_JSON, 0x80);
$decoded = ApiPacket::decode($packet);
if ($decoded['canonical'] !== 'W9:[1:0]' ||
    $decoded['call_id'] !== 0x10203040 ||
    $decoded['status'] !== 0 ||
    $decoded['content_type'] !== ApiPacket::CONTENT_JSON ||
    $decoded['api_flags'] !== 0x80 ||
    $decoded['body'] !== '{"city":"Detroit"}') {
    throw new RuntimeException('API packet round-trip mismatch');
}

$d = $net->descriptor();
if ($d['transport'] !== 'https' || $d['request'] !== 'W9:[1:0]' ||
    $d['success'] !== 'W9:[1:1]' || $d['error'] !== 'W9:[1:2]' ||
    $d['stream'] !== 'W9:[1:3]' || $d['cancel'] !== 'W9:[1:4]' ||
    $d['capability'] !== 'network.https.connect') {
    throw new RuntimeException('HTTPS endpoint descriptor mismatch');
}

$s = $ssh->descriptor();
if ($s['transport'] !== 'ssh' || $s['request'] !== 'W9:[2:0]' ||
    $s['capability'] !== 'network.ssh.transfer.read') {
    throw new RuntimeException('SSH endpoint descriptor mismatch');
}

echo "jx-api-dispatch: ok https+ssh\n";
