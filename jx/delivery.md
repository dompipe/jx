# Delivery

```jx
val = config.server.ports.https.delivery()
val = config.server.ports.https.delivery(default = 443)
newVar.delivery(config.server.ports.https)
val = delivery(config, ["server", "ports", "https"])
```

- Static check when path is constant; else runtime checks (often Resistant).
- No free write: mutation into a Bag still needs sign + handshake.
- Delivery into `const` target is rejected.
