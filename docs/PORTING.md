# JX Porting Notes

This is the porting portion for JX. It tracks the platform split for the compiler front-end, generated C runtime, native window targets, future C/ASM lowering, and the PHP feature support that must be included in the generated C program before JX can stop depending on the PHP bridge.

## Include Matrix

This matrix is the working guide for JX C/ASM compilation. Its purpose is to keep generated C portable and to stop POSIX-only headers from leaking into Windows builds.

JX separates includes by layer:

1. **Compiler front-end**: the `jx` program that reads PHP/CSS and emits C.
2. **Generated runtime**: the C file produced by `jx emit-c`.
3. **Native window runners**: Win32, X11, macOS/Cocoa wrappers.
4. **ASM lowering layer**: future C/ASM/native replacements for the PHP bridge.
5. **PHP completion runtime**: the C runtime pieces required to replace PHP behavior inside the generated program.

### Rule

Do not put platform headers in global scope unless they are behind the right platform guard.

```c
#ifdef _WIN32
#define WIN32_LEAN_AND_MEAN
#include <windows.h>
#else
#include <sys/wait.h>
#include <unistd.h>
#endif

#include <errno.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
```

### Core compiler front-end matrix

These are for `src/native/jx.c`, the JX compiler/emitter itself.

| Header | Windows | Linux/X11 | macOS | Needed for | Notes |
|---|---:|---:|---:|---|---|
| `<errno.h>` | yes | yes | yes | error reporting | portable |
| `<stdint.h>` | yes | yes | yes | fixed-width types | portable |
| `<stdio.h>` | yes | yes | yes | `FILE`, `fopen`, `printf`, `snprintf` | portable |
| `<stdlib.h>` | yes | yes | yes | `malloc`, `free`, `exit`, `getenv` | portable |
| `<string.h>` | yes | yes | yes | `strlen`, `memcpy`, `strcmp`, `strrchr` | portable |
| `<sys/stat.h>` | avoid unless needed | optional | optional | file metadata | only add when required |
| `<sys/types.h>` | avoid unless needed | optional | optional | POSIX types | do not require for Windows front-end |
| `<windows.h>` | guarded only | no | no | Windows API | only inside `_WIN32` blocks |
| `<sys/wait.h>` | no | guarded only | guarded only | `waitpid`, child status macros | never global |
| `<unistd.h>` | no | guarded only | guarded only | `fork`, `execvp`, `mkstemp`, `write`, `close`, `unlink` | never global |

### Generated runtime matrix

These are for the `.c` file emitted by `./jx emit-c`.

| Runtime feature | Windows include/library | POSIX include/library | Purpose | Compile requirement |
|---|---|---|---|---|
| temp payload file | `<windows.h>` | `<unistd.h>` | materialize embedded PHP payload | Win32 uses `GetTempPathA`, `GetTempFileNameA`, `CreateFileA`; POSIX uses `mkstemp` |
| write temp file | `<windows.h>` | `<unistd.h>` | write embedded byte arrays | Win32 `WriteFile`; POSIX `write` |
| delete temp file | `<windows.h>` | `<unistd.h>` | cleanup payload/CSS | Win32 `DeleteFileA`; POSIX `unlink` |
| run PHP runtime | `<windows.h>` | `<sys/wait.h>`, `<unistd.h>` | execute local PHP runtime | Win32 `CreateProcessA`; POSIX `fork` + `execvp` |
| environment variables | `<stdlib.h>` or `<windows.h>` | `<stdlib.h>` | export CSS temp path/name | Win32 can use `_putenv_s`; POSIX uses `setenv` |
| exit code | `<windows.h>` | `<sys/wait.h>` | preserve PHP exit code | Win32 `GetExitCodeProcess`; POSIX `WEXITSTATUS` |
| stdout capture | optional `<windows.h>` | optional pipes | future window/WebView capture | needed for GUI rendering targets |
| stderr passthrough | `<windows.h>` | `<unistd.h>` | preserve diagnostics | pipe or inherited handle |

## PHP Completion Matrix

This is the table JX uses to know what PHP needs to become complete inside the emitted C program. Each PHP capability must have a C runtime piece, a known include/library need, and a lowering target. Until a row is implemented natively, the generated program must keep the PHP bridge for that capability.

### PHP core runtime values

| PHP capability | What the C program must include | C runtime module | Minimum headers/libraries | Lowering target | Status |
|---|---|---|---|---|---|
| PHP value container | tagged value type for null, bool, int, float, string, array, object/resource placeholder | `jx_value.h/.c` | `<stdint.h>`, `<stddef.h>` | `JxValue` ABI struct | planned |
| null | singleton null value | `jx_value` | `<stdint.h>` | tagged immediate | planned |
| booleans | true/false values and conversions | `jx_value` | `<stdbool.h>` or int tags | tagged immediate | planned |
| integers | signed integer storage and overflow policy | `jx_number` | `<stdint.h>`, `<limits.h>` | native integer ops | planned |
| floats | double storage and numeric conversions | `jx_number` | `<math.h>` if math funcs needed | native double ops/SSE later | planned |
| strings | length-aware byte string, NUL-safe | `jx_string.h/.c` | `<stdlib.h>`, `<string.h>`, `<stdint.h>` | heap buffer/string runtime | planned |
| arrays | ordered hash map with integer/string keys | `jx_array.h/.c` | `<stdlib.h>`, `<string.h>`, `<stdint.h>` | JX hash table/vector | planned |
| objects | class/object storage | `jx_object.h/.c` | JX runtime headers | object table/vtable | future |
| resources | file/socket/process placeholders | `jx_resource.h/.c` | platform file/process headers | resource table | future |
| references | PHP reference cells | `jx_ref.h/.c` | JX value/runtime headers | heap ref cell | future |

### PHP syntax and control flow

| PHP capability | What the C program must include | C runtime module | Minimum headers/libraries | Lowering target | Status |
|---|---|---|---|---|---|
| `echo` | stdout emitter for strings/values | `jx_output.h/.c` | `<stdio.h>` or platform write API | direct output writes | planned |
| `print` | stdout emitter returning `1` | `jx_output` | `<stdio.h>` | direct output writes | planned |
| variables | local variable slots/scope frame | `jx_frame.h/.c` | JX value runtime | stack frame struct | planned |
| assignment | value copy/ref rules | `jx_value`, `jx_ref` | JX value runtime | C assignments/runtime calls | planned |
| arithmetic operators | numeric conversion and math ops | `jx_number` | `<stdint.h>`, `<math.h>` | native arithmetic | planned |
| string concat `.` | append/string builder | `jx_string` | `<stdlib.h>`, `<string.h>` | string builder | planned |
| comparison | PHP loose/strict compare rules | `jx_compare.h/.c` | JX value/string/number | compare helpers | planned |
| `if/else` | truthiness conversion | `jx_truth.h/.c` | JX value runtime | C branch | planned |
| `while` / `for` | condition lowering and loop labels | compiler lowering | none beyond truthiness | C loop / ASM labels | planned |
| `foreach` | PHP array iterator rules | `jx_iterator.h/.c` | JX array runtime | iterator struct | planned |
| `break` / `continue` | loop label targets | compiler lowering | none | C labels / ASM jumps | planned |
| functions | call frame, args, return values | `jx_call.h/.c` | JX frame/value runtime | C functions + call ABI | planned |
| includes | include resolver/loader or static bundle | `jx_include.h/.c` | `<stdio.h>`, `<stdlib.h>` or platform files | static include graph | planned |
| errors | PHP-style warnings/fatals | `jx_error.h/.c` | `<stdio.h>`, `<stdlib.h>` | error runtime | planned |

### PHP built-in function support

| Function group | What the C program must include | C runtime module | Headers/libraries likely needed | Notes |
|---|---|---|---|---|
| string functions | strlen, substr, trim, strpos, str_replace, strtolower, strtoupper, etc. | `jx_builtin_string.h/.c` | `<string.h>`, `<ctype.h>`, `<stdlib.h>` | must be byte-string correct first; Unicode later |
| array functions | count, array_push, array_pop, array_merge, in_array, array_keys, sort, etc. | `jx_builtin_array.h/.c` | JX array/value runtime | depends on ordered hash table |
| math functions | abs, min, max, round, floor, ceil, pow, sqrt, rand | `jx_builtin_math.h/.c` | `<math.h>`, `<stdlib.h>` | link `-lm` on POSIX if needed |
| type functions | is_string, is_int, is_array, empty, isset | `jx_builtin_type.h/.c` | JX value runtime | mostly tag checks |
| output buffering | ob_start, ob_get_clean, headers-ish text mode | `jx_output_buffer.h/.c` | `<stdlib.h>`, `<string.h>` | needed for web-style PHP |
| date/time | time, date, strtotime subset | `jx_builtin_datetime.h/.c` | `<time.h>` | timezone support later |
| filesystem | file_get_contents, file_put_contents, fopen, fread | `jx_builtin_file.h/.c` | `<stdio.h>`, platform file APIs | paths and binary mode must be handled |
| environment | getenv, putenv | `jx_env.h/.c` | `<stdlib.h>` / Win32 env APIs | used by CSS bridge now |
| JSON | json_encode, json_decode | `jx_builtin_json.h/.c` | JX string/array/value runtime | own parser/emitter required |
| regex | preg_match, preg_replace | `jx_builtin_regex.h/.c` | optional PCRE2 library | external dependency unless implemented |
| hash/crypto | hash, md5, sha1 | `jx_builtin_hash.h/.c` | own hash modules or crypto lib | avoid platform drift |
| sessions/cookies | session_start, $_COOKIE | `jx_web.h/.c` | web/server runtime | later web target |

### PHP superglobals and runtime input

| PHP item | What the C program must include | C runtime module | Headers/libraries | Status |
|---|---|---|---|---|
| `$argv` | CLI argument importer | `jx_cli.h/.c` | none beyond `argc/argv` | planned |
| `$argc` | CLI argument count | `jx_cli` | none | planned |
| `$_ENV` | environment table | `jx_env` | `<stdlib.h>` / Win32 env APIs | planned |
| `$_GET` | query-string parser | `jx_web_request.h/.c` | JX string/array | web target |
| `$_POST` | body parser | `jx_web_request` | JX string/array | web target |
| `$_SERVER` | server metadata table | `jx_web_request` | platform/env APIs | web target |
| `$_FILES` | multipart upload parser | `jx_web_upload.h/.c` | file/runtime APIs | web target |
| `$_COOKIE` | cookie parser | `jx_web_request` | string/array runtime | web target |
| `$_SESSION` | session store | `jx_session.h/.c` | file/db backend later | web target |

### C/ASM inclusion map

| Runtime piece | Include in emitted C? | Separate source later? | ASM replacement path | Dependency note |
|---|---:|---:|---|---|
| `jx_value` | yes | yes | tagged value ABI | required before any PHP bridge removal |
| `jx_string` | yes | yes | `.rodata` + heap string ops | required for echo/concat/functions |
| `jx_array` | yes | yes | native hash/vector routines | required for arrays and superglobals |
| `jx_output` | yes | yes | syscall/write wrapper | required for CLI/web output |
| `jx_number` | yes | yes | integer/FPU/SSE ops | required for arithmetic/math |
| `jx_compare` | yes | yes | cmp/jump helpers | required for conditionals |
| `jx_frame` | yes | yes | stack-frame layout | required for variables/functions |
| `jx_call` | yes | yes | call ABI | required for PHP functions |
| `jx_error` | yes | yes | trap/diagnostic path | required for warnings/fatals |
| `jx_builtin_*` | selected as used | yes | per-function lowering | include only functions referenced by compiled PHP |
| platform file/process API | only if used | yes | syscall wrappers | avoid global platform includes |
| WebView/window API | only for GUI target | yes | platform GUI ABI | not part of core PHP runtime |

### Completion rule

A generated C program is not complete for a PHP file until every PHP feature in that file resolves to one of these:

1. a native C lowering,
2. a JX runtime function included in the generated program,
3. a linked JX runtime module,
4. or the temporary PHP bridge.

When the bridge is removed, every used PHP construct and built-in must appear in the PHP Completion Matrix with an implementation target.

### Window target matrix

| Target | Source file | Required includes | Link libraries | Output | Notes |
|---|---|---|---|---|---|
| Win32 text window | `src/window/jx-window-win32.c` | `<windows.h>`, `<stdio.h>`, `<stdlib.h>`, `<string.h>` | `-lgdi32 -luser32` | `.exe` | captures child output and draws text with GDI |
| X11 text window | `src/window/jx-window-x11.c` | `<X11/Xlib.h>`, POSIX process headers | `$(pkg-config --cflags --libs x11)` | ELF | captures child output and draws text with Xlib |
| macOS browser/open fallback | planned | `<stdlib.h>` or Cocoa | system `open` or Cocoa frameworks | `.app` | first fallback opens generated HTML |
| macOS native WebView | planned | Cocoa/WebKit headers | `-framework Cocoa -framework WebKit` | `.app` / `.dmg` | real CSS rendering path |
| Windows WebView2 | planned | `windows.h`, WebView2 SDK headers | WebView2 loader lib | `.exe` | real CSS rendering path |
| Linux WebKitGTK | planned | GTK/WebKitGTK headers | `pkg-config --libs gtk+-3.0 webkit2gtk-4.1` | ELF | real CSS rendering path |

### ASM lowering matrix

This is the table for deciding what C includes disappear as PHP features are lowered to C/ASM.

| PHP/JX feature | Current bridge need | Native C need | ASM/native target | Headers after lowering |
|---|---|---|---|---|
| `echo` / raw output | PHP runtime process | `fwrite` / buffered stdout | syscall/write wrapper | `<stdio.h>` or platform write only |
| `$argv` access | PHP runtime argv forwarding | `argc`, `argv` | direct ABI args | none beyond C entrypoint |
| string literals | embedded PHP payload | embedded C string/byte arrays | `.rodata` | none |
| string concat | PHP runtime | heap/string builder | custom allocator/string ops | `<stdlib.h>`, `<string.h>` until custom runtime |
| integer arithmetic | PHP runtime | C integer ops | native arithmetic instructions | none |
| conditionals | PHP runtime | C branches | `cmp`/jump | none |
| loops | PHP runtime | C loops | labels/jumps | none |
| arrays | PHP runtime | JX hash table/vector | native data structure | JX runtime header only |
| file reads | PHP runtime | C file API | syscall wrapper | `<stdio.h>` or platform file API |
| env reads | PHP runtime | `getenv` | platform env API | `<stdlib.h>` or Win32 |
| CSS asset access | PHP reads `JX_CSS_FILE` | direct embedded CSS bytes | `.rodata` pointer | none after direct embedding |
| HTML output | PHP runtime | C template emitter | direct writes | minimal output runtime |
| window output | external window runner | native window API | platform GUI calls | Win32/X11/Cocoa/WebView includes |

### Compile command matrix

| Target | Command shape |
|---|---|
| JX compiler on Windows | `x86_64-w64-mingw32-gcc -O2 -Wall -Wextra -o build/jx-native.exe src/native/jx.c` |
| JX compiler on Linux/macOS | `cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/jx-native src/native/jx.c` |
| Generated Windows app | `x86_64-w64-mingw32-gcc -O2 -Wall -Wextra -o build/page.exe build/page.c` |
| Generated POSIX app | `cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/page build/page.c` |
| Win32 window runner | `x86_64-w64-mingw32-gcc -O2 -Wall -Wextra -o build/jx-window-win32.exe src/window/jx-window-win32.c -lgdi32 -luser32` |
| X11 window runner | `cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/jx-window-x11 src/window/jx-window-x11.c $(pkg-config --cflags --libs x11)` |
| macOS app bundle | planned: `clang ... -framework Cocoa -framework WebKit` |

### Checklist before adding an include

- Is the include for the compiler front-end, generated runtime, window runner, future ASM runtime, or PHP completion runtime?
- Does Windows have this header?
- Does it need `_WIN32` guarding?
- Which PHP capability requires it?
- Is the header needed globally or only in one runtime module?
- Can the feature be moved to a platform-specific function instead of a global include?
- Is the include temporary until a PHP bridge feature is lowered to C/ASM?

### Immediate priority

1. Keep `src/native/jx.c` portable.
2. Keep generated `.c` guarded with `_WIN32` / POSIX branches.
3. Add the `jx_value`, `jx_string`, `jx_array`, `jx_output`, and `jx_frame` runtime units.
4. Mark every PHP construct used by an input file against the PHP Completion Matrix.
5. Include only the JX runtime modules needed by that PHP file.
6. Replace bridge functions with C/ASM-native operations one group at a time.
