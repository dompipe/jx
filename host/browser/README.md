# JX Browser Host — Media

`jx-media-host.js` is the first concrete browser renderer for a JX plugin Control.
It consumes the host-neutral `jx.media/1` descriptor and mounts an HTML
`<audio>` or `<video>` element.

## Media owns two different binding directions

```text
external data source
    -> Bag.bind(...)
       -> Media source/state

Page Control event/value
    -> ControlBinding
       -> Media action/property
```

Do not merge these two relationships. A Bag owns its SQL/NoSQL/channel binding;
a Media descriptor owns the Controls that drive that player.

## Control bindings

A Media Control can listen to another JX Control:

```php
$player = MediaPlugin::mp3('music', '/music/song.mp3')
    ->listen('play-button', 'click', 'play')
    ->listen('pause-button', 'click', 'pause')
    ->listen('volume-control', 'change', 'volume', 'value')
    ->listen('scrubber', 'change', 'seek', 'value')
    ->listen('mute-toggle', 'change', 'muted', 'checked');
```

The serialized descriptor carries these relationships in `controlBindings[]`.
The browser host attaches the listeners when the Media Control is mounted and
removes them when it is unmounted.

Supported Media targets in `jx.media/1` are:

```text
play
pause
toggle
stop
seek
volume
muted
loop
rate
source
```

`play`, `pause`, `toggle`, and `stop` do not require a source value. Value-based
actions carry a source value path such as `value` or `checked` and a safe
coercion descriptor.

## Browser mounting

```html
<script src="/host/browser/jx-media-host.js"></script>
```

```js
const mounted = JXMediaHost.mount(mediaDescriptor, pageRoot, {
  resolveBag(source) {
    // Return the current Bag value for source.bag/source.at.
  },
  subscribeBag(source, changed) {
    // Optional reactive Bag subscription. Return an unsubscribe function.
  },
  coerce(value, as) {
    // Optional JX runtime callback for coercions such as `algebra`.
  }
});
```

A Page renderer should call `mount()` after its Controls are created. The Media
host also uses a `MutationObserver` for source Controls that appear slightly
later in the same Page mount. `mounted.unmount()` removes all Control listeners,
Bag subscriptions, media-event listeners, and the media element.

## Media events back out

The mounted element emits bubbling JX events:

```text
jx:media:play
jx:media:pause
jx:media:ended
jx:media:time
jx:media:seek
jx:media:volume
jx:media:error
```

These are the reverse edge for future Page/Bag bindings: Media can listen to
Controls, and its own state changes can be observed by the Page without placing
DOM-specific behavior in `MediaControl`.

## Testing

`tests/jx-media-host-smoke.js` provides a dependency-free fake DOM smoke harness
for click -> play, change -> volume, change -> seek, change -> muted, URI safety,
and listener cleanup on unmount.
