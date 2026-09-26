# JX PHP Function Include Manifest

JX must not treat a PHP function as native unless it has an include/link note in `data/php_function_manifest.csv`.

## Required rule

Every PHP function that JX can lower, mirror, or bridge must have one manifest row.

That row tells the compiler exactly what is needed in C context:

| Field | Meaning |
| --- | --- |
| `function` | PHP function or construct name. |
| `status` | `native_c`, `native_asm`, `language_construct`, `runtime_wrapper`, `bridge_php`, or `unsupported`. |
| `c_symbol` | C function/helper symbol JX emits or calls. |
| `c_headers` | Standard/platform C headers required by that function. |
| `jx_headers` | JX runtime headers required by that function. |
| `jx_sources` | JX runtime `.c` files required at compile/link time. |
| `link_libraries` | Native libraries needed by the final link command. |
| `feature_flags` | Compiler/runtime feature bucket used for grouping. |
| `notes` | Exact behavior, limits, or missing runtime work. |

## Native rule

A row with `status=native_c` or `status=native_asm` must provide:

```text
function name
C symbol
required headers
required JX runtime headers/sources
required native link libraries, when any
```

## Bridge rule

A row with `status=bridge_php` is allowed only when native mode explicitly permits PHP bridge fallback.

A native-only build must fail on `bridge_php` rows unless the user asks for bridge mode.

## Unsupported rule

A row with `status=unsupported` must stop compilation in native-only mode with a clear diagnostic.

Unsupported functions cannot silently fall back to PHP.

## Include/link generation target

JX should eventually generate this from the manifest:

```text
used PHP functions
        ↓
data/php_function_manifest.csv
        ↓
required #include lines
required JX runtime source files
required linker libraries
        ↓
generated C + compile command
```

## Current seed rows

The manifest is seeded with the first runtime functions already scaffolded in `src/runtime/jx_php_runtime.c`:

```text
strlen
count
sizeof
intval
strval
print
echo
```

It also includes starter rows for known bridge/unsupported areas such as filesystem, JSON, and database functions.

The full PHP function set still needs expansion into this table before JX can claim complete PHP-native coverage.
