# JX Porting Notes

This is the porting portion for JX. It tracks the platform split for the compiler front-end, generated C runtime, native window targets, and future C/ASM lowering.

## Include Matrix

This matrix is the working guide for JX C/ASM compilation. Its purpose is to keep generated C portable and to stop POSIX-only headers from leaking into Windows builds.

JX separates includes by layer:

1. **Compiler front-end**: the `jx` program that reads PHP/CSS and emits C.
2. **Generated runtime**: the C file produced by `jx emit-c`.
3. **Native window runners**: Win32, X11, macOS/Cocoa wrappers.
4. **ASM lowering layer**: future C/ASM/native replacements for the PHP bridge.

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

- Is the include for the compiler front-end, generated runtime, window runner, or future ASM runtime?
- Does Windows have this header?
- Does it need `_WIN32` guarding?
- Can the feature be moved to a platform-specific function instead of a global include?
- Is the include temporary until a PHP bridge feature is lowered to C/ASM?

### Immediate priority

1. Keep `src/native/jx.c` portable.
2. Keep generated `.c` guarded with `_WIN32` / POSIX branches.
3. Move PHP bridge features into named runtime functions.
4. Replace bridge functions with C/ASM-native operations one group at a time.
