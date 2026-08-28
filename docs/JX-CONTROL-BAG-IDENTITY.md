# JX Control Bag Identity

Status: canonical control-state contract

## Rule

**A control is a Bag. A window is only its placement. A data source is only its current dependency.**

A control MUST NOT lose canonical information because a host moves it, recreates a window/surface shadow, changes layout, or rebinds the control to another data source.

The stable identity is the control Bag. Host objects are disposable realizations of that Bag.

```text
Control Bag (stable identity)
        |
        +-- identity
        +-- canonical value
        +-- state
        +-- style
        +-- tooltip/groups/event metadata
        +-- layout record
        +-- source-binding record
        +-- bounded transition history
        |
        +--> browser DOM placement
        +--> JX11 window/surface placement
        +--> WSJX64 placement
```

Moving a control therefore means:

```text
same Bag ID
same value
same source binding
same style
same state
same event identity
       |
       +-- mutate control.layout only
```

Rebinding a data source means:

```text
same Bag ID
same layout
same style/state/events
same last canonical value
       |
       +-- replace control.source binding descriptor
       +-- increment binding_revision
       +-- wait for a newer publication from the new source
```

The previous value is retained until the newly bound source publishes. A stale publication from the old source MUST NOT overwrite the control after rebinding. A publication from the current source whose revision is not newer MUST also be ignored.

## Canonical Bag nodes

`jx.control/2` currently records:

- `control.identity` — stable control id and control type.
- `control.value` — last accepted canonical value.
- `control.layout` — container, x, y, width, height, z and layout revision.
- `control.source` — current and previous source identity, source revision, binding revision, Bag binding id, listener/through mode and options.
- `control.state` — enabled, visible, focused, selected and state revision.
- `control.style` — canonical style properties.
- `control.tooltip` — tooltip Bag data.
- `control.groups` — collector/group membership.
- `control.events` — serializable event identity/listener-count metadata.
- `control.history` — bounded canonical transition provenance.

Callable listeners remain runtime objects. Closures are not serialized into a Bag. The Bag remembers the event identities and canonical state while a live host keeps the executable listeners attached to the same control object.

## Placement and JX11

JX11 window and surface handles must be regarded as realization handles, not control identities. Destroying and recreating a JX11 surface must not create a new control Bag.

```text
Bag 37: market.panel
        |
        +-- JX11 window 4 / surface 9
                  |
                  +-- move / resize / recreate

Bag 37 remains Bag 37.
```

This is especially important when JX11 moves controls between windows, workspaces, panels, or foreground/background layouts.

## Reactive sources

The control uses the normal Bag external-source binding contract. It does not invent a second connection model. The binding descriptor is also copied into `control.source` so the control's canonical serialized state contains enough provenance to reconstruct the relationship.

The host may reconnect the actual source later. Reconnection is not control recreation.

## Processor multiplex bus

The processor-owned multiplex bus should pass source-change information to programs as change notifications. A control's Bag remains the durable state holder. The bus wakes a program to inspect the generation; it does not carry the control's entire identity as transient host state.

Thus:

**Bags remember. The multiplex bus wakes. Registers react. Renderers place.**
