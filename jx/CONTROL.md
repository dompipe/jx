# JX Control Contract

`Control` is the first-class window-control descriptor for JX.

Controls are not defined as browser widgets. A control is a host-neutral
contract:

- `id`
- `type`
- `props`
- `events`

The HTML renderer is only the current transport. Native hosts should render the
same contract as Win32 controls, X11 controls, or another host-specific surface.

Current control types:

- `text`: normal form input
- `spin`: numeric spinner/stepper, with optional `pin`
- `drawing`: host-renderable drawing operations
- `image`: any image type, identified by MIME/source contract

Image controls intentionally allow any image type. SVG is one supported image
source, not the special case:

```php
Control::image('image.any', 'Any image type', $src, 'image/*');
Control::image('logo.svg', 'SVG logo', $svgDataUri, 'image/svg+xml');
Control::image('photo', 'Photo', '/assets/photo.jpg', 'image/jpeg');
```

Form posts carry control values in `control[<id>]`. Protocols decide which
values to persist into Bags.

Spin controls may be pinned:

```php
Control::spin('spin.rate', 'Spin control', 3, ['pin' => true]);
```

Drawing controls support oscillating lines:

```php
Control::line('sweep-line', ['x' => 0, 'y' => 40], ['x' => 160, 'y' => 40], true);
```

The fourth argument is `pong`. When `pong` is true, the host contract means the
line travels start-to-finish and finish-to-start instead of one-way motion.

Drawing controls also support movement paths:

```php
Control::curve(
    'motion-curve',
    ['x' => 0, 'y' => 80],
    ['x' => 40, 'y' => 10],
    ['x' => 120, 'y' => 130],
    ['x' => 180, 'y' => 80],
);
```

`curve(degree1, degree2, degree3, ...)` stores the degree/control points as a
path for movement. The HTML renderer shows it as an SVG path, but native hosts
should treat it as a motion contract a control can travel along.
