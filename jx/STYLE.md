# JX v0.1 Style and Layout Contract

JX pages need one layout and style language that can be rendered by a browser, Win32, X11, or another native host. HTML and CSS remain valid browser outputs, but they are not the definition of the Page.

The JX rule is:

> **Controls say what exists. Bags hold what changes. Anchors say where. Style says how it breathes and looks.**

## 1. CSS-like, host-neutral

JX Style intentionally borrows the vocabulary people already know from CSS:

```text
color
background
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

A browser host may translate these directly into CSS. A Win32 or X11 host translates the same values into native colors, fonts, bounds, and spacing math.

The property names are the contract. CSS is one renderer.

## 2. Hex colors belong in Controls and Bags

Colors use hex strings so they remain portable and compact:

```text
#RRGGBB
#RRGGBBAA
```

Examples:

```php
$style = [
    'color' => '#EAF2FF',
    'background' => '#101722',
    'border' => '#69F0AE',
];
```

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

A renderer can resolve Bag-backed values before drawing.

## 3. Gap is first-class

`gap` belongs to Style instead of being another positional argument in every layout call.

```php
$style = [
    'gap' => 12,
];
```

JX gives `gap` two predictable meanings.

### On a group or container

It is the spacing between children, just as a CSS-aware programmer would expect:

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

## 4. Margin, padding, and gap are different

```text
margin  = space outside one Control
padding = space inside one Control
gap     = space between related Controls or children
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

## 5. Anchors are composable

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

## 6. A tooltip is a Bag

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
```

This keeps presentation data roomy without making Control constructors larger.

The separation is:

```text
Control = what it is
Bag     = what it contains or remembers
Layout  = where it is
Style   = how it looks and breathes
Event   = when it acts
PASL    = what happens
```

## 7. Style may come from a Bag

JX should allow a Control to resolve style from either inline values or a Bag.

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
```

This gives Books enough room for themes and live styling without inventing a second state system.

## 8. Cascade without browser dependence

JX can use a small cascade:

```text
Book style
  -> Page style
      -> group/container style
          -> Control style
              -> live Bag override
```

Later, more-specific values override earlier ones. The resolved result is a flat host-neutral style record before rendering.

The browser may turn it into CSS. Native hosts do not need a CSS engine; they receive the resolved values.

## 9. Page contract

The coherent visible Page becomes:

```text
Page
|- Controls      what exists
|- Bags          what changes / content
|- Layout        relationships and anchors
|- Style         color, size, gap, margin, padding
|- Events        when something happens
`- PASL          what the event does
```

HTML is therefore one output:

```text
JX Page Contract
       |
   resolve layout/style
       |
  +----+------+------+
  |           |      |
browser     Win32   X11
HTML/CSS    native  native
```

## 10. Rhetorical rule

Do not make layout pseudo-English. Make the next role predictable.

```text
attach -> subject -> by anchor -> to target -> by anchor
style  -> subject -> with properties
stack  -> subjects -> direction
align  -> subject -> anchor -> in container
```

Spacing stays out of those sentences when Style can express it.

> **Name the relationship first. Put the breathing room in Style.**
