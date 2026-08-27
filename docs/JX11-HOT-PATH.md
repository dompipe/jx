# JX11 Hot-Path Law

JX11 is the Linux/X11 desktop host for JX. `jx` remains the compiler/runtime.

The desktop source is allowed to be rich and readable. The event hot path is not.

## Canonical source may contain names

A JX desktop may describe:

- desktop ids
- icon ids and labels
- image paths
- launch programs
- Bag names
- styles
- window rules
- reactive handlers

Those names are authoring and provenance information. They are not a requirement for runtime lookup.

## Startup prelink

Before the interactive loop, JX11 converts the execution shadow into native state:

1. Desktop icon descriptors occupy fixed numeric slots.
2. X11 window ids are indexed to numeric icon/window slots.
3. PNG wallpaper and icon assets are decoded once and cached as Cairo image surfaces.
4. Taskbar buttons are materialized as numeric window-slot references when the taskbar is painted.
5. Host-only resources remain outside canonical JX state.

The normal click/focus/property event path therefore does not resolve canonical ids or reopen image files.

## Event law

The hot path is:

```text
X11 event type
    -> XID hash index
    -> numeric slot
    -> native mutation
    -> dirty bit
```

Not:

```text
X11 event
    -> string name
    -> object lookup
    -> style lookup
    -> asset decode
    -> full desktop traversal
```

The XID index uses a fixed open-addressed table. Window removals are cold operations and may rebuild the small index so lookups remain tombstone-free.

## Burst coalescing

X11 frequently queues several events for one user-visible change. JX11 processes one blocking event, drains all already queued events, then renders dirty surfaces once.

```text
configure
property
focus
expose
    -> native mutations
    -> DIRTY_TASKBAR
    -> one paint
    -> one XCB flush
```

This rule should be preserved as more Controls and reactive features move into JX11.

## Rendering law

Assets are decoded at startup or explicit asset replacement, not during ordinary expose/click/focus handling.

A redraw should be limited to a dirty surface or region whenever possible. Adding a feature must not imply repainting the whole desktop unless the desktop itself changed.

## Canonical boundary

JX11 may hold XIDs, XCB replies, Cairo surfaces, process ids and native indexes. Canonical Bags receive normalized data such as window title, geometry, focus, mapped state and workspace. They never receive XCB pointers or Cairo objects.

The intended boundary is:

```text
canonical JX
    -> compiled desktop shadow
    -> JX11 native slots
    -> XCB/Cairo hot state
    -> normalized dirty events
    -> JX Bag checkpoint
```

## Rule for future features

Anything knowable at compile/startup time should be prelinked or cached before the interactive loop. Dynamic lookup is reserved for genuinely dynamic state.

Canonical richness must not require runtime richness.
