<?php declare(strict_types=1);

require_once __DIR__ . '/jx-environment.php';

use jx\EnvironmentProfile;
use jx\ExecutionTarget;
use jx\JxTargetPolicy;

$policy = new JxTargetPolicy();

$server = $policy->choose('BOOK.CHECKPOINT', EnvironmentProfile::server());
if ($server->target !== ExecutionTarget::NATIVE_MACHINE) {
    throw new RuntimeException('server shadowable work must prefer native machine');
}

$cli = $policy->choose('BOOK.CHECKPOINT', EnvironmentProfile::cli());
if ($cli->target !== ExecutionTarget::NATIVE_MACHINE) {
    throw new RuntimeException('CLI shadowable work must prefer native machine');
}

$native = $policy->choose('BOOK.CHECKPOINT', EnvironmentProfile::native());
if ($native->target !== ExecutionTarget::NATIVE_MACHINE) {
    throw new RuntimeException('native host must prefer native machine');
}

$browser = $policy->choose('BOOK.CHECKPOINT', EnvironmentProfile::browser());
if ($browser->target !== ExecutionTarget::BROWSER_LOCAL) {
    throw new RuntimeException('browser shadowable work must stay browser-local');
}

$sql = $policy->choose('SQL.QUERY', EnvironmentProfile::server(), false);
if ($sql->target !== ExecutionTarget::HOST_FALLBACK) {
    throw new RuntimeException('unshadowable host effect must remain host-side');
}

echo "PASS JX native-first target policy server+cli+native browser-local host-effect fallback\n";
