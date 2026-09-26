# JX Native Page Object Model

JX native pages should be programmed as a tree of objects. Each object has a required `type`, required `id`, optional `classes`, optional `attrs`, and either `text`, `html`, `children`, `fields`, or another object-specific payload.

This keeps the PHP source structured while letting the compiler lower the same page into native Win32, X11, Cocoa, or another renderer later.

## Core shape

```php
$page = [
    'type' => 'page',
    'id' => 'demo-page',
    'classes' => ['page', 'native-page'],
    'attrs' => [
        'title' => 'JX PHP Native Page',
        'renderer' => 'win32-native',
    ],
    'children' => [
        [
            'type' => 'text',
            'id' => 'hero-title',
            'classes' => ['hero', 'title'],
            'attrs' => ['role' => 'title'],
            'text' => 'JX PHP Native Page',
        ],
        [
            'type' => 'html',
            'id' => 'html-block',
            'classes' => ['content-html'],
            'attrs' => ['role' => 'content'],
            'html' => '<h2>HTML allowed</h2><p>Native lowering decides how to render it.</p>',
        ],
    ],
];
```

## Required object fields

| Field | Required | Meaning |
|---|---:|---|
| `type` | Yes | Object kind. Required on every object. |
| `id` | Yes | Stable object identifier. Required on every object so updates, APIs, events, styles, and diagnostics can address it. |
| `classes` | No | Optional list of CSS-like class names. Used for styling, grouping, and renderer-specific matching. |
| `attrs` | No | Optional associative array for metadata, behavior, API route details, layout hints, names, labels, roles, state, or renderer settings. |
| `text` | Object-specific | Plain text payload. |
| `html` | Object-specific | HTML payload. Allowed, but each renderer decides how complete the HTML support is. |
| `children` | Object-specific | Nested child objects. |
| `fields` | Object-specific | Form field objects. |
| `api` | Object-specific | Local API route declarations for update/testing hooks. |

## Identity rule

Every object must have an `id` that is unique inside the page tree.

Good:

```php
['type' => 'text', 'id' => 'hero-title', 'text' => 'Hello']
```

Bad:

```php
['type' => 'text', 'text' => 'Hello']
```

The compiler should eventually fail builds with missing or duplicate IDs.

## Classes rule

`classes` is optional, but when present it must be an array of strings.

```php
'classes' => ['card', 'primary-card']
```

Classes are not IDs. Many objects may share the same class.

## Attrs rule

`attrs` is where non-payload metadata goes. Do not overload `text` or `html` with behavior. Put behavior in `attrs`.

Examples:

```php
'attrs' => ['role' => 'title']
'attrs' => ['name' => 'body', 'label' => 'Body']
'attrs' => ['path' => '/update', 'params' => ['title', 'badge', 'body']]
'attrs' => ['api' => '/iframe', 'html' => 'allowed']
```

## Supported object types

### `page`

Top-level container.

```php
[
    'type' => 'page',
    'id' => 'main',
    'classes' => ['page'],
    'attrs' => ['title' => 'Main Page'],
    'children' => [],
]
```

### `text`

Plain text object.

```php
[
    'type' => 'text',
    'id' => 'body-copy',
    'classes' => ['copy'],
    'attrs' => ['role' => 'body'],
    'text' => 'Plain text goes here.',
]
```

### `html`

HTML object. HTML is allowed in the page tree. The current Win32-native pass strips tags into native text for iframe display. Later passes may lower allowed tags into native text runs, links, boxes, and images.

```php
[
    'type' => 'html',
    'id' => 'iframe-html',
    'classes' => ['iframe-html'],
    'attrs' => ['role' => 'content'],
    'html' => '<h2>Iframe HTML is allowed</h2><p>Rendered natively.</p>',
]
```

### `form`

Dynamic form object.

```php
[
    'type' => 'form',
    'id' => 'dynamic-form',
    'classes' => ['panel', 'dynamic-form'],
    'attrs' => ['method' => 'local', 'api' => '/update'],
    'fields' => [
        [
            'type' => 'field',
            'id' => 'field-title',
            'classes' => ['field', 'text-field'],
            'attrs' => ['name' => 'title', 'label' => 'Title'],
        ],
        [
            'type' => 'field',
            'id' => 'field-body',
            'classes' => ['field', 'textarea-field'],
            'attrs' => ['name' => 'body', 'label' => 'Body'],
        ],
    ],
]
```

### `modal`

Native modal object.

```php
[
    'type' => 'modal',
    'id' => 'help-modal',
    'classes' => ['modal', 'help-modal'],
    'attrs' => ['title' => 'Help', 'api' => '/modal'],
    'children' => [
        [
            'type' => 'text',
            'id' => 'help-modal-body',
            'classes' => ['modal-body'],
            'text' => 'Modal body text.',
        ],
    ],
]
```

### `iframe`

Native iframe-like framed object. HTML is allowed inside it.

```php
[
    'type' => 'iframe',
    'id' => 'native-iframe',
    'classes' => ['iframe', 'native-frame'],
    'attrs' => ['title' => 'Native Iframe', 'api' => '/iframe', 'html' => 'allowed'],
    'children' => [
        [
            'type' => 'html',
            'id' => 'native-iframe-html',
            'classes' => ['iframe-html'],
            'html' => '<h2>Hello</h2><p>Inside the frame.</p>',
        ],
    ],
]
```

### `api`

Local API route declaration.

```php
[
    'type' => 'api',
    'id' => 'api-update-page',
    'classes' => ['local-api'],
    'attrs' => [
        'path' => '/update',
        'params' => ['title', 'badge', 'body'],
    ],
]
```

The current Win32-native test app binds these routes to `127.0.0.1:8765` only.

## Compatibility declarations

The first compiler pass still extracts helper calls such as:

```php
jx_page_title($page['children'][0]['text']);
jx_modal_body($page['children'][4]['children'][0]['text']);
jx_iframe_html($page['children'][5]['children'][0]['html']);
```

`examples/native_page.php` keeps a `$page` object tree first, then emits compatibility declarations from that object tree. That keeps today's build working while documenting the intended structure.

## Lowering rule

Every object must lower into one of these outcomes:

1. Native renderer object.
2. Runtime helper object.
3. Explicit bridge/fallback object.
4. Unsupported object with a test failure.

Silent unsupported behavior is not allowed.
