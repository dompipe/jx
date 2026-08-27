# JX Live Patch — secure generation replacement without restart

JX native hosts are designed to accept prepared code/table changes without rebooting the machine or restarting the host process. A live patch is **not** an in-place overwrite of instructions currently executing. It is a transaction that prepares a new generation beside the active one and changes the active generation only at a quiescent boundary.

## Core law

> Transport security answers who carried the patch. Patch verification answers whether this exact patch may become active.

HTTPS or SSH is therefore never sufficient by itself.

A patch must pass both layers:

```text
HTTPS / SSH / local signed channel
            ↓
transport authentication
            ↓
signed JX patch manifest
            ↓
base generation + SHA-256 match
            ↓
timestamp + expiry + nonce/replay check
            ↓
capability authorization
            ↓
stage complete target generation
            ↓
target digest validation
            ↓
JX11 event-batch safe point
            ↓
active generation pointer/table swap
            ↓
previous generation retained for rollback
```

## XI lineage

The design deliberately carries forward useful properties from `dompipe/xi` without copying XI's web implementation:

- canonical signed requests;
- timestamped requests;
- idempotency/replay protection;
- base-hash-guarded patching;
- whole-state digest comparison before accepting drift/rebase;
- OpenSSH through the operating system's agent and `known_hosts`;
- secrets kept outside distributable state;
- narrow allowlists/capabilities, with deny taking precedence;
- no unrestricted remote shell as the normal update interface.

XI applies these ideas to its control plane and hot-swappable registry/files. JX applies them to native compiled generations.

## Patch manifest ABI

The host-neutral ABI is `host/common/jx-live-patch.h`.

A manifest includes:

```text
version
protocol                 HTTPS | SSH | LOCAL_SIGNED
generation               generation to activate
base_generation          generation the patch was built against
issued_at
expires_at
nonce                     monotonically increasing replay guard
capability_mask
patch_length
base_digest[32]           SHA-256 identity of active base
target_digest[32]         SHA-256 identity of staged target
```

The core validator is fail-closed. It rejects a patch when:

- the signature verifier is absent;
- the signature is empty or invalid;
- the protocol is unsupported;
- the base generation differs from the active generation;
- the base digest differs from the active digest;
- the new generation is not newer;
- the nonce has already been seen;
- the patch is expired, too far in the future, or has an excessive validity window;
- requested capabilities exceed the host's allowlist;
- the payload size differs from the signed manifest or exceeds the bound;
- the target digest is empty or identical to the active target.

`jx_patch_commit()` changes security generation state only after the caller has staged and independently validated the target.

## Capability tiers

Initial native patch capabilities are deliberately granular:

```text
HOT_TABLES
REACTIONS
ASSETS
CONFIG
NATIVE_CODE
```

`NATIVE_CODE` is intentionally separate and should be disabled by default. Most JX updates should be expressible as replacement compiled tables, reactions, assets, configuration, or separately mapped compiled modules.

This avoids granting every routine update permission to alter executable mappings.

## JX11 generation manager

`host/linux/jx11-live-patch.h/.c` maintains three prepared generations:

```text
active
pending
previous
```

Staging does not modify the active generation.

At an event-batch/quiescent boundary JX11 may call:

```text
jx11_live_patch_commit_pending(...)
```

which performs:

```text
previous = active
active   = pending
pending  = empty
```

The previous generation remains available to `jx11_live_patch_rollback()`.

The generation object holds indirection roots for prepared structures such as:

```text
API dispatch table
reaction table
configuration table
asset table
```

Changing those roots is materially safer than rewriting instructions that may currently be on a CPU stack.

## HTTPS

HTTPS patch delivery requires normal TLS security. An adapter must not disable certificate or hostname verification for speed.

Recommended capability split:

```text
network.https.connect
network.https.download
network.https.upload
network.https.client-cert
```

The remote transport may deliver a patch, but the JX signature/base-generation validator still decides whether it can be staged.

## SSH

SSH is an authenticated session transport and is more privileged than an ordinary API request. JX therefore rejects the generic capability `network.ssh` for compiled SSH endpoints. Use narrow capabilities such as:

```text
network.ssh.connect
network.ssh.transfer.read
network.ssh.transfer.write
network.ssh.tunnel
network.ssh.exec            # privileged, not the default patch path
```

For deployment/update use, the preferred model follows XI's security posture:

- use the operating system OpenSSH client/library or equivalent mature implementation;
- private keys remain in the OS agent/key store;
- strict host-key verification is mandatory;
- known hosts remain outside `.64B`;
- no private key or password is embedded in `.64B`;
- patch transfer is a narrow operation, not an unrestricted terminal;
- the received patch must still pass JX signature and generation validation.

## Secrets

A native Book contains credential references, never reusable secrets:

```text
credential = system-keyring:jx.production.patch
```

not:

```text
private-key = ...
password = ...
refresh-token = ...
```

Checksumming a Book proves integrity; it does not make embedded secrets secret.

## Safe native-code replacement

For compiled machine code, the intended later implementation is generation-based mapping:

1. validate signed target;
2. map new code/data into a separate region using platform W^X rules;
3. resolve its prepared dispatch table;
4. wait for a quiescent/event-batch boundary;
5. swap the dispatch root;
6. retain the old mapping until no execution can reference it;
7. reclaim it only after the grace period.

JX should never make writable-and-executable memory the normal live-patch mechanism.

## Relationship to `.64B`

A full update may be a new `.64B`; a later compact patch package can contain only replacement sections. The extension remains descriptive. The internal header, signed manifest, base digest, target digest, and generation determine identity.

Candidate compiled sections:

```text
HOT/api-table.bin
HOT/reactions.bin
HOT/registers.bin
CODE/module-*.bin
META/capabilities.bin
META/generation.bin
META/patch-policy.bin
```

## Runtime laws

The live-update design adds three security/runtime laws to JX:

> Hot addresses answer **where**. Capabilities answer **whether**.

> Transport authentication does not equal patch authorization.

> Patch beside, validate completely, then swap at a safe boundary — never rewrite the active generation in place.
