# JX Native Window C Emission

## 1. Overview

JX can emit a standalone C file from a PHP-style input file.

The workflow is:

```bash
jx -o out.c in.php
```

The generated `out.c` file is intended to be compiled with GCC or MinGW-w64 into a native executable.

For the native window target, JX emits C code that can build into a real Win32 desktop window executable. This output does **not** require a WebView, embedded browser, Chromium runtime, Electron, or any browser dependency.

In this mode:

1. The developer writes a PHP input file using `jx_*` declarations.
2. JX reads the PHP-style input.
3. JX emits a GCC-compilable C source file.
4. GCC or MinGW-w64 compiles that C file into a native Windows executable.
5. The result is a real Win32 window program.

The PHP file is the authoring surface.  
The emitted C file is the native build artifact.  
The final `.exe` is the native desktop application.

---

## 2. Requirements

To use native window C emission, the following tools are required.

### JX

JX must be built from:

```text
src/native/jx.c
```

The resulting executable should be available as:

```bash
jx
```

or invoked directly by path.

### C Compiler

A GCC-compatible compiler is required.

Supported options include:

```text
GCC
MinGW-w64
```

On Windows with MSYS2, the expected compiler path is usually:

```text
C:\msys64\ucrt64\bin\gcc.exe
```

### Windows Libraries

The native Win32 window build links against the following Windows libraries:

```text
ws2_32
gdi32
user32
```

These are needed for Windows socket/runtime support, graphics/device context support, and native window creation.

---

## 3. Basic Workflow

Given an input file:

```bash
in.php
```

Run JX with the `-o` option:

```bash
jx -o out.c in.php
```

This tells JX to read `in.php` and emit C source into:

```bash
out.c
```

After emission, JX prints the follow-up GCC command that can be used to compile the generated C file.

The expected flow is:

```bash
jx -o out.c in.php
gcc -O2 -Wall -Wextra -mwindows -I . -o out.exe out.c -lws2_32 -lgdi32 -luser32
```

Then run:

```bash
./out.exe
```

On Windows Command Prompt or PowerShell:

```powershell
.\out.exe
```

---

## 4. Compile Command

The correct GCC command for a native Win32 window executable is:

```bash
gcc -O2 -Wall -Wextra -mwindows -I . -o out.exe out.c -lws2_32 -lgdi32 -luser32
```

### Command Breakdown

```bash
gcc
```

Runs the C compiler.

```bash
-O2
```

Enables optimization.

```bash
-Wall -Wextra
```

Enables useful compiler warnings.

```bash
-mwindows
```

Builds a Windows GUI application instead of a console application.

```bash
-I .
```

Adds the current directory to the include path.

```bash
-o out.exe
```

Sets the output executable name.

```bash
out.c
```

The C file emitted by JX.

```bash
-lws2_32 -lgdi32 -luser32
```

Links the required Windows libraries.

---

## 5. Example Input

Example `in.php`:

```php
<?php

jx_window("JX Native Window", 800, 600);

jx_label("Hello from JX native C emission.");

jx_button("Close");
```

Then emit C:

```bash
jx -o out.c in.php
```

Then compile:

```bash
gcc -O2 -Wall -Wextra -mwindows -I . -o out.exe out.c -lws2_32 -lgdi32 -luser32
```

Then run:

```bash
./out.exe
```

Expected result:

```text
A native Windows window opens with the declared title and UI elements.
```

No browser is launched.  
No WebView is embedded.  
No server is required.  
The result is a compiled native executable.

---

## 6. What JX Emits

When this feature is used, JX emits a C file containing the native window program.

The generated C may include:

- Win32 entry point logic
- window class registration
- native window creation
- message loop handling
- UI declaration mapping
- generated handlers for declared controls
- required includes and support definitions

The emitted file should be directly inspectable and compilable.

Example output target:

```bash
out.c
```

The output file is plain C, not bytecode and not an interpreted runtime bundle.

---

## 7. Native Window Behavior

The generated executable uses the Win32 API directly.

The native window executable should:

- open as a desktop window
- use the requested title
- use the requested dimensions
- render declared native UI controls where supported
- respond through the normal Windows message loop
- close cleanly when the window is closed

This mode is intended for real native desktop output, not browser-based rendering.

---

## 8. No WebView or Browser Dependency

The native window C emission target is specifically designed to avoid browser dependency.

It does not require:

```text
Electron
Chromium
Edge WebView2
CEF
a local web server
HTML rendering
a browser tab
```

The output is a compiled native program.

This is useful when the goal is:

- a small native executable
- direct Win32 behavior
- no embedded browser runtime
- no JavaScript frontend requirement
- a PHP-like authoring syntax that emits C

---

## 9. Windows / MSYS2 Example

On Windows with MSYS2 UCRT64 installed, the compiler may be located at:

```text
C:\msys64\ucrt64\bin\gcc.exe
```

Example command:

```powershell
C:\msys64\ucrt64\bin\gcc.exe -O2 -Wall -Wextra -mwindows -I . -o out.exe out.c -lws2_32 -lgdi32 -luser32
```

Then run:

```powershell
.\out.exe
```

---

## 10. Full Command Sequence

From a clean project folder:

```bash
jx -o out.c in.php
gcc -O2 -Wall -Wextra -mwindows -I . -o out.exe out.c -lws2_32 -lgdi32 -luser32
./out.exe
```

On Windows PowerShell with MSYS2 GCC:

```powershell
jx -o out.c in.php
C:\msys64\ucrt64\bin\gcc.exe -O2 -Wall -Wextra -mwindows -I . -o out.exe out.c -lws2_32 -lgdi32 -luser32
.\out.exe
```

---

## 11. Troubleshooting

### `jx` is not found

Make sure JX has been built from:

```text
src/native/jx.c
```

Then either place the compiled `jx` executable on your `PATH`, or run it by direct path.

Example:

```bash
./jx -o out.c in.php
```

### `gcc` is not found

Install GCC or MinGW-w64.

On Windows, MSYS2 UCRT64 is recommended.

Expected compiler path:

```text
C:\msys64\ucrt64\bin\gcc.exe
```

### Missing Win32 symbols

If the compile fails with missing Win32 symbols, confirm that these libraries are included:

```bash
-lws2_32 -lgdi32 -luser32
```

### Console window appears

Use:

```bash
-mwindows
```

This builds the output as a Windows GUI executable.

### Output C file was not generated

Confirm that the command includes `-o`:

```bash
jx -o out.c in.php
```

Also confirm that the input file exists:

```bash
in.php
```

---

## 12. Purpose

The native window C emission workflow gives JX a direct path from PHP-like declarations to a compiled native desktop executable.

The important promise is:

```text
PHP-style JX input -> generated C -> native Win32 executable
```

This makes JX useful as a lightweight native application generator where PHP-like syntax is used to describe the app, but the final output is compiled C and native Windows UI.
