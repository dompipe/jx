# JX API Dispatch

JX API dispatch uses the same awake-state addressing law as controls, windows, media, and other reactive subsystems:

> Canonical definitions describe meaning. `W:slot:shadow` addresses drive awake execution.

## Route model

An API service is assigned a register and each compiled endpoint receives one slot. Shadows are fixed:

```text
W9:[12:0] request
W9:[12:1] success
W9:[12:2] error
W9:[12:3] stream/chunk
W9:[12:4] cancel
```

The endpoint name is therefore not resolved on every call. Canonical source may name `weather.current`; the compiled Book can lower it to `W9:[12:0]`.

## Call envelope

API payloads begin with an 8-byte API header inside the normal JX hot packet:

```text
uint32 call_id
uint16 status
uint8  content_type
uint8  flags
body...
```

The outer hot packet already contains:

```text
version | register | slot | shadow | delivery | flags | payload_length
```

A response preserves register/slot and changes only the shadow, while `call_id` correlates asynchronous work.

## Transports

Transport selection is compile-time metadata, not part of endpoint identity:

- `direct` — same-process compiled service table
- `native` — native library or OS service thunk
- `unix` — local AF_UNIX adapter
- `udp` — explicit datagram boundary
- `http` — clear HTTP adapter; TLS is not implied
- `https` — TLS-required HTTP adapter with peer and hostname verification
- `ssh` — authenticated SSH session/transfer adapter using narrow capabilities
- `device` — device/host adapter

This allows one API model to cover fast local operating-system services and slower external APIs without changing canonical JX semantics.

`https` and `ssh` are explicit rather than aliases. That lets a compiled Book state a transport security requirement instead of relying on a host to infer one.

## Fast local dispatch

For local/native endpoints the intended path is:

```text
canonical API name
    ↓ compile/wake
W-register + slot
    ↓ call
W:[slot:0]
    ↓ direct function/service-table index
native service
    ↓
W:[slot:1] success
```

No string lookup, URL parsing, JSON parsing, or socket hop is required unless the selected adapter needs one.

## External dispatch

Remote HTTPS is an adapter boundary:

```text
W:[slot:0]
   ↓
HTTPS adapter
   ↓
TLS + network
   ↓
response parser
   ↓
W:[slot:1] or W:[slot:2]
```

Streaming adapters publish chunks through shadow 3 and cancellation uses shadow 4.

SSH uses the same prepared numeric route, but the adapter establishes an authenticated SSH transport before performing the compiled operation.

## Capabilities

Endpoints may declare narrow capabilities such as:

```text
clock.read
network.http
network.https.connect
network.https.download
network.ssh.connect
network.ssh.transfer.read
network.ssh.transfer.write
filesystem.read
process.launch
device.camera
```

The generic capability `network.ssh` is rejected for compiled SSH endpoints. SSH authority must describe the allowed operation rather than granting an unrestricted remote shell.

The compiler/package stage should place required capabilities in the `.64B` manifest. Native launchers can then approve or deny capabilities before the Book wakes. The hot path carries only the already-authorized endpoint route.

## Secrets and credentials

Compiled Books may contain credential references, not reusable credentials themselves. TLS client identities, SSH private keys, passwords, refresh tokens, and known-hosts trust belong in the OS key store/agent or host configuration.

For SSH, the preferred deployment model is the operating system OpenSSH implementation or an equivalent mature implementation with:

- agent/key-store backed private keys;
- strict host-key checking;
- known-hosts data outside `.64B`;
- no embedded password or private key;
- operation-scoped capability checks.

## Live patch relationship

HTTPS and SSH can carry a JX live patch, but transport authentication never authorizes the patch by itself.

A live patch must additionally pass the signed generation protocol in `docs/LIVE-PATCH.md`:

```text
transport authentication
→ patch signature
→ base generation/hash
→ timestamp/expiry/nonce
→ capability authorization
→ staged target digest
→ safe-point generation swap
```

This gives JX11 a no-restart update path without treating a network connection as permission to replace active code.

## `.64B` target

A native compiled Book should eventually contain:

```text
HOT/api-table.bin
HOT/api-reactions.bin
META/capabilities.bin
```

The API table maps canonical compile-time endpoint identities to numeric register/slot routes and transport thunks. Checksums already provided by the `.64B` package protect these sections like the rest of the native Book.

## Performance rule

The API subsystem follows the same runtime principle as the rest of native JX:

> Bags remember. Registers react. API endpoints dispatch by prepared numeric route.

The speed advantage applies primarily to routing, local service calls, serialization avoidance, batching, and reaction dispatch. It cannot make remote network latency disappear; HTTPS and SSH endpoints remain bounded by network/server/crypto time. The important gain is that JX does not add a heavyweight dynamic dispatch layer around that unavoidable latency.
