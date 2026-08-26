# JX v0.1 — Page Style, Collectors, Anchors, and Bags

This chapter belongs **immediately after Controls and before PASL** in the Programming Guide, and immediately after the Control/host-contract chapter in the Engine Manual.

A visible JX Page is not HTML. HTML/CSS is one renderer of a host-neutral Page contract.

The Page is easier to reason about when each part has one job:

```text
Control   = what exists
Bag       = what it contains or remembers
Collector = what belongs together
Layout    = where it is
Style     = how it looks and breathes
Event     = when it acts
PASL      = what happens
```

> **Controls say what exists. Bags hold what changes. Collectors gather. Anchors place. Style paints and spaces.**

## CSS-like Style without requiring CSS

JX Style uses familiar names:

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
opacity
border
border-width
border-radius
font-size
font-weight
width
height
margin
padding
gap
row-gap
column-gap
```

A browser may lower them to CSS. Win32 and X11 resolve the same properties into native drawing and geometry.

The browser does not own the Style language.

## Hex colors

Hex is the portable color form:

```text
#RRGGBB
#RRGGBBAA
```

For example:

```php
$page->style('save', [
    'color' => '#08110C',
    'background-color' => '#69F0AE',
]);
```

Eight-digit hex includes alpha:

```text
#69F0AE80
```

The same values may live in Bags when they are shared, themed, or change while the Book is running:

```php
$theme = Bag::underwrite(1024);

Flow::put('#EAF2FF', $theme, 'text');
Flow::put('#101722', $theme, 'surface');
Flow::put('#69F0AE', $theme, 'accent');
```

## Background images belong to every Control

A background image is Style, not a separate kind of Control:

```php
$page->style('hero-card', [
    'background-color' => '#101722',
    'background-image' => '/assets/card-grid.png',
    'background-size' => 'cover',
    'background-position' => 'center center',
    'background-repeat' => 'no-repeat',
]);
```

That can style a button, text field, drawing, panel, image, spinner, or future Control type.

A Bag can supply the skin:

```php
$skin = Bag::underwrite(1024);

Flow::put('/assets/panel-dark.png', $skin, 'background-image');
Flow::put('#101722', $skin, 'background-color');
Flow::put('cover', $skin, 'background-size');
```

## Image transparency

Transparency is layer-specific:

```text
background-opacity = background image only
image-opacity      = foreground image of an Image control
opacity            = the entire composed Control
```

All numeric opacity values run from `0.0` to `1.0`.

```php
$page->style('hero-card', [
    'background-image' => '/assets/grid.png',
    'background-opacity' => 0.35,
    'opacity' => 1.0,
]);
```

The grid fades while the text stays crisp.

Transparent image files keep their own alpha. JX multiplies that alpha by the Style opacity:

```text
final alpha = source alpha * layer opacity * Control opacity
```

> **Fade the layer you mean, not everything around it.**

## Gap is Style

Spacing should not interrupt a layout sentence.

```php
$page->style('form-fields', [
    'gap' => 12,
]);
```

`gap` has two predictable uses:

- on a collector/container, it spaces members or children;
- on an attached Control, it guarantees separation along the attachment relationship.

```text
A   12   B   12   C
```

For an anchor attachment:

```text
B.left-center = A.right-center + 12px
```

The relationship says where. Style says how much air stays between them.

> **Attach the relationship. Style the distance.**

`margin`, `padding`, and `gap` remain distinct:

```text
margin  = outside one Control
padding = inside one Control
gap     = between Controls
```

Directional collectors may use:

```php
[
    'row-gap' => 8,
    'column-gap' => 14,
]
```

## Group collectors

A collector is a named, non-owning set of Controls.

```php
$page->collect('form-fields', [
    'username',
    'password',
    'email',
]);
```

A Control may be collected more than once:

```text
username -> form-fields
username -> required
username -> account-panel
```

Then Style and Layout can address the collection once:

```php
$page->style('form-fields', [
    'color' => '#EAF2FF',
    'background-color' => '#101722',
    'padding' => 10,
    'gap' => 12,
]);

$page->stack('form-fields', downward());
```

A collector can also carry a shared image treatment:

```php
$page->style('account-panel', [
    'background-image' => '/assets/panel-grid.png',
    'background-opacity' => 0.24,
    'gap' => 14,
]);
```

> **Collect what belongs together. Style it once.**

## Composable anchors

Anchors are composed from horizontal and vertical relationships:

```text
left + top       center + top       right + top
left + center    center + center    right + center
left + bottom    center + bottom    right + bottom
```

Short aliases such as `LT`, `CC`, and `RB` may remain, but they are shorthand for the composed form.

Conceptually:

```php
anchor(left(), top());
anchor(center(), center());
anchor(right(), bottom());
```

Two Controls can connect anchor-to-anchor:

```php
attach(
    'tip',
    anchor(left(), center()),
    to('save', anchor(right(), center()))
);
```

The engine sees the simple equation:

```text
tip.LC = save.RC
```

If the tooltip Style has:

```php
['gap' => 8]
```

then the layout solver guarantees eight units of separation.

## Tooltip = Bag

Tooltip content is data. It should therefore be a Bag rather than a text property hidden inside a tooltip widget.

Small form:

```php
$tip = Bag::underwrite(128);
Flow::put('Save the current document.', $tip);
```

Richer form:

```php
$tip = Bag::underwrite(512);

Flow::put('Compile this Book.', $tip, 'text');
Flow::put('Ctrl+Enter', $tip, 'shortcut');
Flow::put('#EAF2FF', $tip, 'color');
Flow::put('#101722', $tip, 'background-color');
Flow::put('/assets/tip-grid.png', $tip, 'background-image');
Flow::put(0.30, $tip, 'background-opacity');
```

The Page attaches that Bag to a Control. The Style resolver paints it. The Bag remains the content source.

## Cascade

JX can resolve Style in a small host-neutral cascade:

```text
Book style
  -> Page style
      -> collector style
          -> Control style
              -> live Bag override
```

The final renderer receives resolved Style. A native host does not need to implement a browser CSS engine to honor it.

## The visible Page contract

```text
Page
|- Controls
|- Bags
|- Collectors
|- Layout / Anchors
|- Style
|- Events
`- PASL
```

Then:

```text
JX Page
   |
resolve Bags + collectors + layout + Style
   |
+--+----------+----------+
|             |          |
browser      Win32      X11
HTML/CSS     native     native
```

The Page stays the same application in every host.

> **Name the relationship first. Put the breathing room and paint in Style.**
