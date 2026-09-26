# JX Function Mirroring Contract

Every PHP function that Jinx can compile or execute must be mirrorable in JX's generated C context.

This is not only documentation. It is the contract for how JX decides whether a generated C program is complete.

## Core rule

For every PHP function name observed by JX, the compiler must resolve it to exactly one runtime outcome:

| Outcome | Meaning | C program complete? |
|---|---|---:|
| `native_c` | Implemented as a JX C runtime function | yes |
| `native_asm` | Lowered to target assembly or compiler intrinsic | yes |
| `runtime_wrapper` | Implemented through a JX runtime wrapper around C/platform APIs | yes |
| `bridge_php` | Temporarily delegated to the PHP bridge runtime | temporary only |
| `unsupported` | Known but not implemented or bridged | no |

A function cannot silently disappear into the PHP payload if JX is claiming native C completion for that file.

## Required C-context shape

A mirrored PHP function must have a C-context record:

```c
typedef enum {
    JX_FUNC_NATIVE_C,
    JX_FUNC_NATIVE_ASM,
    JX_FUNC_RUNTIME_WRAPPER,
    JX_FUNC_BRIDGE_PHP,
    JX_FUNC_UNSUPPORTED
} JxFunctionMirrorKind;

typedef struct {
    const char *php_name;
    const char *c_symbol;
    const char *runtime_header;
    const char *runtime_source;
    const char *required_library;
    JxFunctionMirrorKind kind;
    int min_args;
    int max_args;
} JxFunctionMirror;
```

## Example mirrors

| PHP function | C symbol | Runtime module | Headers/libraries | Kind |
|---|---|---|---|---|
| `strlen` | `jx_php_strlen` | `jx_php_runtime` | `<string.h>` | `native_c` |
| `count` | `jx_php_count` | `jx_php_runtime` | JX array/value runtime | `native_c` |
| `sizeof` | `jx_php_count` | `jx_php_runtime` | JX array/value runtime | `native_c` |
| `intval` | `jx_php_intval` | `jx_php_runtime` | JX value runtime | `native_c` |
| `strval` | `jx_php_strval` | `jx_php_runtime` | JX value/string runtime | `native_c` |
| `print` | `jx_php_print` | `jx_php_runtime` | JX output runtime | `native_c` |
| `file_get_contents` | `jx_php_file_get_contents` | `jx_file` | `<stdio.h>` or Win32 file API | `runtime_wrapper` |
| `preg_match` | `jx_php_preg_match` | `jx_regex` | regex engine dependency | `runtime_wrapper` |
| `json_encode` | `jx_php_json_encode` | `jx_json` | JX JSON runtime | `native_c` |
| `hash` | `jx_php_hash` | `jx_hash` | crypto/hash module | `runtime_wrapper` |
| unknown function | none | none | none | `unsupported` unless bridged |

## Compiler requirements

When JX compiles PHP to C, it must do these steps:

1. Parse every function call.
2. Normalize the PHP function name.
3. Look up the name in the JX mirror registry.
4. Include the required runtime header/source into the generated C build.
5. Emit a direct call to the C symbol when native.
6. Emit bridge fallback only when the function is explicitly marked `bridge_php`.
7. Fail the build when a function is `unsupported` and no bridge fallback was requested.

## Generated C requirement

A generated C program must carry every runtime piece required by the functions it uses.

That can happen in one of two ways:

| Mode | Meaning |
|---|---|
| single-file emit | JX embeds the needed runtime code directly into the generated `.c` file |
| multi-file emit | JX emits `page.c` plus required `src/runtime/*.c` modules and compile command |

For the current JX direction, single-file emit is the easiest correctness path:

```text
PHP source
→ function scan
→ required mirror set
→ generated .c contains only the runtime functions needed
→ GCC builds a complete executable
```

## No hidden PHP-only completion

This is forbidden for native-complete mode:

```text
PHP function appears in source
→ JX does not know the function
→ generated C still runs only because PHP bridge handles it
```

That is allowed only in bridge mode, and bridge mode must be visible in the manifest and output.

## Manifest target

JX should build toward a generated manifest like this:

```text
build/page.jxfuncs
```

Example:

```text
strlen,native_c,jx_php_strlen,src/runtime/jx_php_runtime.c
count,native_c,jx_php_count,src/runtime/jx_php_runtime.c
file_get_contents,runtime_wrapper,jx_php_file_get_contents,src/runtime/jx_file.c
preg_match,bridge_php,,
```

The generated C file should be traceable back to this function manifest.

## Completion test

A PHP function mirror is complete only when all of these are true:

- the PHP name is present in the registry;
- the C symbol exists;
- the C symbol is compiled or embedded into the generated program;
- the argument count rules are known;
- the return value type is represented by `JxValue`;
- a smoke test calls it through the JX-generated executable;
- the same PHP call and the JX C-context call produce matching output for normal cases.

## Relationship to Jinx

Jinx is the source of PHP function coverage expectations. JX must mirror that coverage for C generation.

For every function Jinx supports, JX needs a corresponding row:

```text
Jinx PHP function support
→ JX mirror registry row
→ C runtime implementation or bridge declaration
→ generated C inclusion
→ test
```

Until that exists, the function is not considered C-complete in JX.
