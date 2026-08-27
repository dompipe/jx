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
- `http` — HTTP/HTTPS adapter
- `device` — device/host adapter

This allows one API model to cover fast local operating-system services and slower external APIs without changing canonical JX semantics.

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

Remote HTTP is an adapter boundary:

```text
W:[slot:0]
   ↓
HTTP adapter
   ↓
network
   ↓
response parser
   ↓
W:[slot:1] or W:[slot:2]
```

Streaming adapters publish chunks through shadow 3 and cancellation uses shadow 4.

## Capabilities

Endpoints may declare a capability such as:

```text
clock.read
network.http
filesystem.read
process.launch
device.camera
```

The compiler/package stage should place required capabilities in the `.64B` manifest. Native launchers can then approve or deny capabilities before the Book wakes. The hot path carries only the already-authorized endpoint route.

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

The speed advantage applies primarily to routing, local service calls, serialization avoidance, batching, and reaction dispatch. It cannot make remote network latency disappear; HTTP endpoints remain bounded by network/server time. The important gain is that JX does not add a heavyweight dynamic dispatch layer around that unavoidable latency.
