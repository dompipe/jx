# JX Browser Runtime

JX browser applications should not require application-authored JavaScript.

The browser target is split into two layers:

1. **JX/JXL application code** — owns application state, events, controls, routing, networking, and DOM intent.
2. **`jx-browser-host.js`** — a fixed runtime bridge to browser DOM and History APIs.

The host is infrastructure. It is not application logic.

## No-refresh rule

Normal JX UI updates mutate the live document in place. They MUST NOT reload the page unless the JX program explicitly requests a full navigation.

A typical update is:

```text
JX event -> JX state change -> JX browser host mutation -> browser repaint
```

Only the affected nodes need to change.

## Host ABI

The browser host publishes `globalThis.JxBrowser` for compiler/runtime lowering.

### Node handles

DOM nodes cross the runtime boundary as integer handles. JX code should never depend on raw browser object identity.

- `JxBrowser.dom.get(selector)` -> node handle or `0`
- `JxBrowser.dom.getAll(selector)` -> vector of handles
- `JxBrowser.dom.create(tag[, namespace])` -> node handle
- `JxBrowser.dom.release(handle)` -> release runtime handle

### Mutations

- `text(target, value)`
- `html(target, value)`
- `value(target[, value])`
- `attr(target, name[, value])`
- `prop(target, name[, value])`
- `style(target, name, value)`
- `classAdd(target, name)`
- `classRemove(target, name)`
- `classToggle(target, name[, force])`
- `append(parent, child)`
- `prepend(parent, child)`
- `before(target, child)`
- `after(target, child)`
- `remove(target)`
- `clear(target)`
- `show(target[, display])`
- `hide(target)`
- `focus(target)`

These methods mutate the existing DOM and do not refresh the document.

### Events

`JxBrowser.dom.on(target, type, callback[, options])` installs a browser event listener and returns a listener id. The callback receives a serializable event bag with common keyboard, pointer, input, and target data.

`JxBrowser.dom.off(listenerId)` removes the listener.

The compiler should lower JX event syntax onto this API rather than emitting user-visible JavaScript.

### No-refresh routing

The browser host exposes:

- `JxBrowser.router.route(path, callback)`
- `JxBrowser.router.unroute(path)`
- `JxBrowser.router.go(url[, state[, replace]])`
- `JxBrowser.router.replace(url[, state])`
- `JxBrowser.router.current()`
- `JxBrowser.router.dispatch()`

`go()` uses the History API (`pushState`) and `replace()` uses `replaceState`; neither reloads the page.

Links marked with `data-jx-route` are intercepted for same-origin navigation and routed without refresh. Back/forward navigation is handled through `popstate`.

## JX source surface

The intended source-level surface is deliberately smaller than the browser ABI:

```jx
button = dom.get("#save")
button.text = "Save"

button.click {
    panel = dom.get("#panel")
    panel.show()
}

route("/settings") {
    view.load(Settings)
}
```

The compiler/runtime should lower those operations to `JxBrowser` calls. The source program should not need to know that the browser host is implemented in JavaScript.

## Compiler lowering contract

The browser compiler target should treat DOM references as host handles and lower operations approximately as follows:

| JX operation | Browser ABI |
| --- | --- |
| `dom.get(s)` | `JxBrowser.dom.get(s)` |
| `node.text = v` | `JxBrowser.dom.text(node, v)` |
| `node.html = v` | `JxBrowser.dom.html(node, v)` |
| `node.show()` | `JxBrowser.dom.show(node)` |
| `node.hide()` | `JxBrowser.dom.hide(node)` |
| `node.add(child)` | `JxBrowser.dom.append(node, child)` |
| `node.remove()` | `JxBrowser.dom.remove(node)` |
| event block | `JxBrowser.dom.on(...)` |
| `route(path)` | `JxBrowser.router.route(...)` |
| route navigation | `JxBrowser.router.go(...)` |

The implementation may use PASM/JXL host-call instructions internally. The public JX syntax should remain independent of the bridge mechanism.

## Browser target invariant

A conforming JX browser application should be able to ship application logic as JX/JXL plus the fixed JX browser runtime. Hand-authored application JavaScript is not required.
