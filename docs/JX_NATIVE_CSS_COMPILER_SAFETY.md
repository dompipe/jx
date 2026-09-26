# JX Native CSS Compiler Safety

JX must compile PHP to native code on its own. CSS cannot be treated as loose browser decoration. It becomes compiler input, so unsupported or malformed CSS must be caught early, explained clearly, and lowered safely.

This document defines the native CSS safety rules for the PHP-native page path.

## Compiler rule

Native CSS has three possible outcomes per declaration:

1. **Lowered**: JX knows the selector, property, unit, and value, and emits native renderer data.
2. **Ignored with warning**: JX knows the declaration is harmless but unsupported in the current renderer.
3. **Build failure**: JX cannot safely interpret the declaration and it may change layout, hit testing, sizing, or security behavior.

Silent failure is not allowed for native-only builds.

## Common CSS problems JX must catch

| Problem | Example | Native behavior |
|---|---|---|
| Unknown selector | `#wrong-id { ... }` | Warning or fail in strict mode. |
| Unknown property | `display: grid` | Warning now; fail when used on layout-critical objects. |
| Bad color | `background: blueish` | Fail. Native renderer needs a concrete color. |
| Bad unit | `width: 20vw` | Fail until the unit is supported. |
| Negative size | `padding: -4px` | Fail. |
| Duplicate object id | two objects with `id => 'same'` | Fail during PHP object validation. |
| Missing object id | object without `id` | Fail during PHP object validation. |
| Ambiguous object style | styles target a class shared by unrelated object types | Warning unless intentional. |
| Unsupported inherited property | `font-family`, `line-height`, etc. | Warning until native text engine supports it. |
| Unsupported HTML/CSS interaction | iframe HTML tries browser-only styling | Warning or fail depending on scope. |

## Supported native selectors in the current pass

The current Win32 native demo supports these selectors:

```css
window
body
#demo-page
#main-card
#badge
#dynamic-form
#native-iframe
#native-iframe-chrome
#help-modal
#help-modal-overlay
#help-modal-close
.card
.badge
.panel
.iframe
.iframe-chrome
.modal
.modal-overlay
.modal-close
```

The object model rule is:

```text
object id       -> #object-id
object classes  -> .class-name
object type     -> type selector, later
object attrs    -> attribute/state selectors, later
```

## Supported native properties in the current pass

```css
width
height
panel-width
gap
min-content-width
background
color
border
border-radius
padding
margin
font-family
display
font-weight
letter-spacing
```

Only some properties are lowered today. The validator allows documented no-op properties like `font-family`, `margin`, `display`, `font-weight`, and `letter-spacing` so existing CSS can remain source-compatible. The native renderer should warn or ignore them until they are implemented.

## Supported values

### Colors

Only concrete hex colors are safe in the current native pass:

```css
#101318
#f4f7fb
#3b4454
```

`rgb(...)`, named colors, variables, gradients, and alpha colors are not native-safe yet.

### Sizes

Supported size units:

```css
px
rem
0
```

Current `rem` lowering assumes:

```text
1rem = 16px
```

Unsupported units such as `%`, `vw`, `vh`, `em`, `calc(...)`, and `auto` must not silently compile into a wrong layout.

## Native CSS validation step

Before the standalone EXE builds, the build script should run:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\validate-native-page-css.ps1
```

The validator checks:

```text
selector allowlist
property allowlist
hex color syntax
supported units
negative sizes
missing braces / unparsed blocks
```

## Full standalone PHP compiler target

The current PHP-native page compiler still extracts a compatibility declaration layer. That is a first-pass bridge. The target is:

```text
PHP object tree
  -> JX PHP parser / AST
  -> object validation
  -> CSS validation
  -> CSS/object matching
  -> native layout tree
  -> generated C / native runtime
  -> standalone executable
```

The final compiler must not require the PHP runtime to execute the generated program.

## Failure wording

CSS errors should point to the exact selector and property:

```text
CSS validation failed:
  - #native-iframe width uses unsupported unit: 60vw
  - #help-modal background is not a supported hex color: blueish
```

JX should stop before C compilation when a CSS error would create a broken native window.
