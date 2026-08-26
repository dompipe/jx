# Delivery (Derivative Apprehensives)

Delivery is the deep-path operator that makes nested structure addressable without manual traversal boilerplate.

## Syntax

### Extract
```jx
val = config.server.ports.https.delivery()
val = config.server.ports.https.delivery(default = 443)
```

### Rebind / assign into
```jx
newVar.delivery(config.server.ports.https)
existing.delivery(config.server.ports.https)
```

### Path form (explicit)
```jx
val = delivery(config, ["server", "ports", "https"])
```

## Semantics

- The path is checked statically when fully constant; otherwise runtime checks are inserted.
- Missing intermediate nodes produce a controlled error (or the provided default).
- Delivery never performs a free memory write. If the target is inside a Bag, a proper sign + handshake is still required for mutation.
- Delivery into a `const` target is rejected.

## Lowering

- Constant paths → direct offset / field loads (native_template).
- Dynamic paths → bounds- and existence-checked loop or recursive helper (often Resistant).
- The smart table entry `delivery.extract` / `delivery.rebind` decides which template to extrude.

## Verbose form
```jx
val = tell(delivery, config.server.ports.https)
```
Lowers to the tight form above.
