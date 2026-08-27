# JX v0.1 Style and Layout Contract

JX pages need one layout and style language that can be rendered by a browser, Win32, X11, or another native host. HTML and CSS remain valid browser outputs, but they are not the definition of the Page.

The JX rule is:

> **Controls say what exists. Bags hold what changes. Collectors say what belongs together. Anchors say where. Style says how it breathes and looks.**

## 1. CSS-like, host-neutral

JX Style intentionally borrows the vocabulary people already know from CSS:

```text
color
background
background-color
background-image
background-opacity
background-size
background-position
background-repeat
image-opacity
border
border-width
border-radius
font-size
font-weight
width
height
min-width
min-height
max-width
max-height
margin
padding
gap
row-gap
column-gap
opacity
```

A browser host may translate these directly into CSS or generated compositing layers. A Win32 or X11 host translates the same values into native colors, images, fonts, bounds, alpha values, and spacing math.

The property names are the contract. CSS is one renderer.

## 2. Hex colors belong in Controls and Bags

Colors use hex strings so they remain portable and compact:

```text
#RRGGBB
#RRGGBBAA
```

The eight-digit form includes alpha. For example:

```text
#69F0AE80
```

means the same RGB accent with approximately half opacity.

A Control may carry those values directly:

```php
$save = Control::make('save', 'button', [
    'label' => 'Save',
    'style' => [
        'color' => '#08110C',
        'background' => '#69F0AE',
        'border-radius' => 8,
    ],
]);
```

A Bag may carry them when color is state, theme data, user preference, or reusable presentation material:

```php
$theme = Bag::underwrite(512);

Flow::put('#EAF2FF', $theme, 'text');
Flow::put('#101722', $theme, 'surface');
Flow::put('#69F0AE', $theme, 'accent');
```

The distinction is useful:

```text
Control style = presentation attached to this Control
Bag style data = presentation values that can change or be shared
```

A renderer resolves Bag-backed values before drawing.

## 3. Every Control may have a background image

Background imagery is a Style feature, not a special privilege of the Image control.

```php
$cardStyle = [
    'background-color' => '#101722',
    'background-image' => '/assets/card-grid.png',
    'background-opacity' => 0.72,
    'background-size' => 'cover',
    'background-position' => 'center center',
    'background-repeat' => 'no-repeat',
];
```

The same Style record can be attached to text, buttons, spinners, drawings, images, panels, or future Control types.

A browser can lower it to CSS plus a generated background layer when necessary. Native hosts load the image and paint it into the Control rectangle before the foreground Control is rendered.

The order is conceptually:

```text
background color
    -> background image + background-opacity
        -> border
            -> Control content / foreground image
                -> whole-Control opacity
```

A Bag may provide the image source and transparency too:

```php
$skin = Bag::underwrite(1024);
Flow::put('/assets/panel-dark.png', $skin, 'background-image');
Flow::put(0.68, $skin, 'background-opacity');
Flow::put('#101722', $skin, 'background-color');
Flow::put('cover', $skin, 'background-size');
```

This lets themes, state, and user-selected skins change backgrounds without rebuilding the Control.

## 4. Image transparency

JX separates three kinds of transparency because they affect different layers.

```text
background-opacity = only the Control background image
image-opacity      = foreground image content on an Image control
opacity            = the final composed Control
```

Each numeric opacity is clamped to the range `0.0` through `1.0`.

Example:

```php
$page->style('hero-card', [
    'background-image' => '/assets/grid.png',
    'background-opacity' => 0.35,
    'opacity' => 1.0,
]);
```

The text and foreground content remain fully opaque while the grid fades behind them.

For an Image control:

```php
$page->style('portrait', [
    'image-opacity' => 0.78,
]);
```

Intrinsic image alpha is preserved. JX opacity multiplies it rather than replacing it:

```text
final pixel alpha = source image alpha * JX image/background opacity * Control opacity
```

That means transparent PNG, WebP, or other alpha-aware sources keep their own cutouts and soft edges.

A Bag can carry transparency as live style state:

```php
Flow::put(0.42, $theme, 'background-opacity');
Flow::put(0.90, $theme, 'image-opacity');
```

Collector styles may apply the same values to a group of Controls at once.

> **Fade the layer you mean, not everything around it.**

## 5. Gap is first-class

`gap` belongs to Style instead of being another positional argument in every layout call.

```php
$style = [
    'gap' => 12,
];
```

JX gives `gap` two predictable meanings.

### On a collector or container

It is the spacing between members or children:

```text
A   12   B   12   C
```

### On an attached Control

It is the requested separation from the Control or anchor it is attached to along the relationship axis.

If `B.left-center` is attached to `A.right-center` with `gap: 12`, the layout solver resolves:

```text
B.LC.x = A.RC.x + 12
B.LC.y = A.RC.y
```

The relationship establishes direction. Style establishes breathing room.

> **Attach the relationship. Style the distance.**

## 6. Margin, padding, and gap are different

```text
margin  = space outside one Control
padding = space inside one Control
gap     = space between related Controls or collector members
```

Example:

```php
$panelStyle = [
    'padding' => 16,
    'gap' => 12,
    'margin' => 20,
];
```

For directional layouts:

```php
$gridStyle = [
    'row-gap' => 8,
    'column-gap' => 14,
];
```

This vocabulary prevents geometry calls from accumulating spacing parameters.

## 7. Group collectors

A collector is a named, non-owning set of Controls. It works like a selector or class without making browser CSS the runtime model.

Conceptually:

```php
$page->collect('form-fields', [
    'username',
    'password',
    'email',
]);
```

Then layout or Style can target the collector once:

```php
$page->style('form-fields', [
    'background' => '#101722',
    'color' => '#EAF2FF',
    'padding' => 10,
    'gap' => 12,
]);
```

Collectors do not copy Controls and do not become another ownership container. They collect references.

A Control may belong to several collectors:

```text
username -> form-fields
username -> required
username -> account-panel
```

That permits useful selector-like composition without duplicating the Control.

### Collector cascade

A practical specificity order is:

```text
Book
 -> Page
    -> collector
       -> more-specific collector
          -> Control
             -> live Bag override
```

When collectors overlap, later/more-specific collector rules override earlier collector values before the Control's own Style is applied.

### Collector layout

Collectors may also participate in layout:

```php
$page->stack('form-fields', downward());
$page->style('form-fields', [
    'gap' => 12,
]);
```

The collector supplies the members. Layout supplies the relationship. Style supplies the spacing.

A collector may also carry a common background treatment:

```php
$page->style('account-panel', [
    'background-image' => '/assets/panel-grid.png',
    'background-opacity' => 0.24,
    'gap' => 14,
]);
```

> **Collect what belongs together. Style it once.**

## 8. Anchors are composable

An anchor is the combination of a horizontal relationship and a vertical relationship.

```text
left + top
center + top
right + top
left + center
center + center
right + center
left + bottom
center + bottom
right + bottom
```

Shorthand aliases may still exist:

```text
LT CT RT
LC CC RC
LB CB RB
```

but they are aliases for the composed anchor, not nine unrelated concepts.

A conceptual JX surface reads:

```php
anchor(left(), top());
anchor(center(), center());
anchor(right(), bottom());
```

Attachment uses an anchor on each object:

```php
attach(
    'tooltip',
    anchor(left(), center()),
    to('save', anchor(right(), center()))
);
```

The geometry is unambiguous:

```text
tooltip.LC = save.RC
```

and Style may then add:

```php
['gap' => 8]
```

## 9. A tooltip is a Bag

Tooltip content is data, so it belongs in a Bag rather than inside a special tooltip widget.

The smallest tooltip Bag can use `_default`:

```php
$tip = Bag::underwrite(128);
Flow::put('Save the current document.', $tip);
```

The Page attaches that Bag to a Control. The layout contract decides where it appears.

A richer tooltip can remain the same Bag type:

```php
$tip = Bag::underwrite(512);
Flow::put('Compile this Book.', $tip, 'text');
Flow::put('Ctrl+Enter', $tip, 'shortcut');
Flow::put('#EAF2FF', $tip, 'color');
Flow::put('#101722', $tip, 'background');
Flow::put('/assets/tip-grid.png', $tip, 'background-image');
Flow::put(0.30, $tip, 'background-opacity');
```

This keeps presentation data roomy without making Control constructors larger.

The separation is:

```text
Control   = what it is
Bag       = what it contains or remembers
Collector = what belongs together
Layout    = where it is
Style     = how it looks and breathes
Event     = when it acts
PASL      = what happens
```

## 10. Style may come from a Bag

JX should allow a Control or collector to resolve Style from either inline values or a Bag.

Conceptually:

```php
$page->style('save', [
    'background' => '#69F0AE',
    'color' => '#08110C',
    'padding' => 10,
    'gap' => 8,
]);
```

or:

```php
$page->style('save', fromBag($buttonStyle));
$page->style('form-fields', fromBag($formStyle));
```

This gives Books enough room for themes and live styling without inventing a second state system.

## 11. Cascade without browser dependence

JX can use a small cascade:

```text
Book style
  -> Page style
      -> collector style
          -> Control style
              -> live Bag override
```

Later, more-specific values override earlier ones. The resolved result is a flat host-neutral Style record before rendering.

The browser may turn it into CSS. Native hosts do not need a CSS engine; they receive the resolved values.

## 12. Page contract

The coherent visible Page becomes:

```text
Page
|- Controls      what exists
|- Bags          what changes / content
|- Collectors    what belongs together
|- Layout        relationships and anchors
|- Style         color, images, opacity, size, gap, margin, padding
|- Events        when something happens
`- PASL          what the event does
```

HTML is therefore one output:

```text
JX Page Contract
       |
 collect + resolve layout/style
       |
  +----+------+------+
  |           |      |
browser     Win32   X11
HTML/CSS    native  native
```

## 13. Rhetorical rule

Do not make layout pseudo-English. Make the next role predictable.

```text
collect -> name -> members
attach  -> subject -> by anchor -> to target -> by anchor
style   -> subject/collector -> with properties
stack   -> subjects/collector -> direction
align   -> subject/collector -> anchor -> in container
```

Spacing and transparency stay out of those sentences when Style can express them.

> **Name the relationship first. Put the breathing room and paint in Style.**
