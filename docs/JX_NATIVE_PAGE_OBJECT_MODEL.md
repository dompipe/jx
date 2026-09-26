# JX Native Page Object Model

JX native pages should be programmed as a tree of objects. Each object has a `type`, optional identity fields, and either `text`, `html`, or nested `children`.

This keeps the PHP source structured while letting the compiler lower the same page into native Win32, X11, Cocoa, or another renderer later.

## Core shape

```php
$page = [
    'type' => 'page',
    'id' => 'demo-page',
    'title' => 'JX PHP Native Page',
    'children' => [
        [
            'type' => 'text',
            'id' => 'hero-title',
            'role' => 'title',
            'text' => 'JX PHP Native Page',
        ],
        [
            'type' => 'html',
            'id' => 'html-block',
            'html' => '<h2>HTML allowed</h2><p>Native lowering decides how to render it.</p>',
        ],
    ],
];
```

## Required object fields

| Field | Meaning |
|---|---|
| `type` | Object kind. Required on every object. |
| `id` | Stable object identifier. Recommended for anything that can update. |
| `role` | Optional semantic role such as `title`, `badge`, `body`, or `panel`. |
| `text` | Plain text payload. |
| `html` | HTML payload. Allowed, but each renderer decides how complete the HTML support is. |
| `children` | Nested child objects. |
| `fields` | Form field objects or short field descriptors. |
| `api` | Local API route declarations for update/testing hooks. |

## Supported object types

### `page`

Top-level container.

```php
[
    'type' => 'page',
    'id' => 'main',
    'title' => 'Main Page',
    'children' => [],
]
```

### `text`

Plain text object.

```php
[
    'type' => 'text',
    'id' => 'body-copy',
    'role' => 'body',
    'text' => 'Plain text goes here.',
]
```

### `html`

HTML object. HTML is allowed in the page tree. The current Win32-native pass strips tags into native text for iframe display. Later passes may lower allowed tags into native text runs, links, boxes, and images.

```php
[
    'type' => 'html',
    'id' => 'iframe-html',
    'html' => '<h2>Iframe HTML is allowed</h2><p>Rendered natively.</p>',
]
```

### `form`

Dynamic form object.

```php
[
    'type' => 'form',
    'id' => 'dynamic-form',
    'fields' => [
        ['type' => 'field', 'name' => 'title', 'label' => 'Title'],
        ['type' => 'field', 'name' => 'body', 'label' => 'Body'],
    ],
]
```

### `modal`

Native modal object.

```php
[
    'type' => 'modal',
    'id' => 'help-modal',
    'title' => 'Help',
    'children' => [
        ['type' => 'text', 'text' => 'Modal body text.'],
    ],
]
```

### `iframe`

Native iframe-like framed object. HTML is allowed inside it.

```php
[
    'type' => 'iframe',
    'id' => 'native-iframe',
    'title' => 'Native Iframe',
    'children' => [
        ['type' => 'html', 'html' => '<h2>Hello</h2><p>Inside the frame.</p>'],
    ],
]
```

## Local API object declarations

```php
'api' => [
    ['path' => '/update', 'params' => ['title', 'badge', 'body']],
    ['path' => '/modal', 'params' => ['title', 'body']],
    ['path' => '/iframe', 'params' => ['title', 'html']],
]
```

The current Win32-native test app binds these routes to `127.0.0.1:8765` only.

## Compatibility declarations

The first compiler pass still extracts literal helper calls such as:

```php
jx_page_title('Title');
jx_modal_body('Body');
jx_iframe_html('<h2>HTML</h2>');
```

`examples/native_page.php` now keeps a `$page` object tree first, then emits compatibility declarations from that object tree. That keeps today's build working while documenting the intended structure.

## Lowering rule

Every object must lower into one of these outcomes:

1. Native renderer object.
2. Runtime helper object.
3. Explicit bridge/fallback object.
4. Unsupported object with a test failure.

Silent unsupported behavior is not allowed.
