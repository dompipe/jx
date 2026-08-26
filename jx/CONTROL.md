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
- `toggle`: switch-style boolean control
- `drawing`: host-renderable drawing operations
- `image`: any image type, identified by MIME/source contract

Control taxonomy is family-based. `Control` owns host controls. `Image` owns
image-family leaves that can be rendered directly or attached to another
control. `Theme` owns shared motion, spin, zoom, and composition contracts.
That keeps a line with an image brush as a line, not a special
`neonLine` primitive:

```php
Control::line(
    'image-trail',
    ['x' => 16, 'y' => 42],
    ['x' => 220, 'y' => 42],
    false,
    Image::blur('neon-line.png', 'image/png', 8, ['role' => 'paint', 'glow' => 0.9]),
) + ['stroke' => '#00f5ff', 'width' => 5];
```

Image repeat modes:

- `Image::IMG_DOTTED`: lay the same image one after another along the path.
- `Image::IMG_BLUR`: repeat the image every `x` pixels for an overt speed or
  blur trail.

```php
Image::dotted('spark.png', 'image/png', 24);
Image::blur('neon-line.png', 'image/png', 8);
```

Theme contracts make control images theme-friendly. A spin can declare how many
clicks move from one degree to the next, and a line or curve can share that
same motion with zoom:

```php
$spinTheme = Theme::spinClicks('spin.rate', 1, 2, 12, ['wrap' => true]);
$zoomTheme = Theme::zoom(1.0, 1.35, 'ease-out');
$mashTheme = Theme::mash([$spinTheme, $zoomTheme]);

Control::spin('spin.rate', 'Spin control', 3, [
    'theme' => $spinTheme,
]);

Control::line(
    'sweep-line',
    ['x' => 26, 'y' => 150],
    ['x' => 330, 'y' => 34],
    true,
) + ['theme' => $mashTheme];
```

The host reads this as: between degree `1` and degree `2`, the spin consumes
`12` clicks; the surrounding movement can mash spin and zoom together. The
host may grow scale, momentum, or visual weight while the control moves.

Replacement control images use the same image family. Dials, buttons, and
switches are not separate host primitives; they are roles in an image
replacement set:

```php
$switchImages = Image::replacementSet(Image::ROLE_SWITCH, [
    'off' => Image::img('controls/switch-off.png', 'image/png'),
    'on' => Image::img('controls/switch-on.png', 'image/png'),
    'cover' => Image::img('controls/switch-cover.png', 'image/png'),
]);

$dialImages = Image::replacementSet(Image::ROLE_DIAL, [
    '0' => Image::img('controls/dial-000.png', 'image/png'),
    '90' => Image::img('controls/dial-090.png', 'image/png'),
]);
```

An event-sourced image trade-off records why one replacement image changed to
another. The host should append or consume these as events, not infer them from
the current markup:

```php
Image::tradeOff(
    'evt-image-view-toggle',
    'control.image.view.changed',
    Image::img('controls/button-up.png', 'image/png'),
    Image::img('controls/button-disabled.png', 'image/png'),
    'View display switched off',
);
```

Image controls intentionally allow any image type. SVG is one supported image
source, not the special case:

```php
Control::image('image.any', 'Any image type', $src, 'image/*');
Control::image('logo.svg', 'SVG logo', $svgDataUri, 'image/svg+xml');
Control::image('photo', 'Photo', '/assets/photo.jpg', 'image/jpeg');
```

Image controls may also carry a pin contract:

```php
Control::image('image.any', 'Any image type', $src, 'image/*', [
    'imageSet' => Image::replacementSet(Image::ROLE_BUTTON, [
        'up' => Image::img('controls/button-up.png', 'image/png'),
        'down' => Image::img('controls/button-down.png', 'image/png'),
        'disabled' => Image::img('controls/button-disabled.png', 'image/png'),
    ]),
    'tradeOffs' => [
        Image::tradeOff(
            'evt-image-view-toggle',
            'control.image.view.changed',
            Image::img('controls/button-up.png', 'image/png'),
            Image::img('controls/button-disabled.png', 'image/png'),
        ),
    ],
    'display' => Control::imageDisplay(
        true,  // visible
        0,     // blur pixels
        false, // covered
    ),
    'pin' => Control::imagePin(
        Control::XY_CENTER, // turning point
        Control::XY_LB,     // stuck-to-path point
        Control::paintPoint(
            Control::XY_RT, // painting point
            Control::line(
                'image-trail',
                ['x' => 16, 'y' => 42],
                ['x' => 220, 'y' => 42],
                false,
                Image::blur('neon-line.png', 'image/png', 8, ['role' => 'paint']),
            ),
        ),
    ),
]);
```

Supported anchor constants:

- `Control::XY_CENTER`
- `Control::XY_LT`
- `Control::XY_RT`
- `Control::XY_LB`
- `Control::XY_RB`

The three image pin points have separate meanings. `turningPoint` is where the
image rotates or turns. `pathPoint` is the point stuck to the movement path.
`paintingPoint` is the point the host should treat as the image paint origin.
`paintPoint()` lets that origin carry a child paint control, such as a normal
line with an attached `Image::img()` brush. `imageDisplay()` carries view state:
the image can stay in the control tree while the host shows it, hides it, blurs
it, or covers it.

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

Drawing controls also support explicit shapes and SVG-style path evokers:

```php
Control::polygon('triangle-shape', [
    ['x' => 258, 'y' => 24],
    ['x' => 334, 'y' => 76],
    ['x' => 280, 'y' => 124],
]);

Control::curvedShape('soft-shape', [
    ['x' => 134, 'y' => 116],
    ['x' => 174, 'y' => 98],
    ['x' => 222, 'y' => 128],
], ['smooth' => 0.72]);

Control::path(
    'svg-evoker',
    'M 40 42 C 76 6 118 74 156 42 S 238 78 316 42',
    ['fill' => 'none', 'stroke' => '#be185d'],
);
```

`path()` is the SVG path evoker. It preserves the path language as a host
contract while letting native renderers map it to their own path APIs.

Drawing controls also support movement paths:

```php
Control::curve(
    'motion-curve',
    [
        'smooth' => 0.82,
        'spin' => $spinTheme,
        'zoom' => $zoomTheme,
        'mash' => $mashTheme,
    ],
    ['x' => 0, 'y' => 80],
    ['x' => 40, 'y' => 10],
    ['x' => 120, 'y' => 130],
    ['x' => 180, 'y' => 80],
);
```

`curve(degree1, degree2, degree3, ...)` stores the degree/control points as a
path for movement. The HTML renderer shows it as an SVG path, but native hosts
should treat it as a motion contract a control can travel along.

Curve properties are passed before the degree points. `smooth` is clamped from
`0` to `1`, where `0` means direct geometric travel and `1` means maximally
smoothed host interpolation. `spin`, `zoom`, and `mash` are theme-family
contracts that let a host coordinate clicks, line travel, curve travel, and
zooming without rewriting the underlying control.
