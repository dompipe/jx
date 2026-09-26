# JX

JX is the new executable path for PHP-to-executable-code work.

The command name is:

```bash
./jx
```

## Current direction

JX now has two layers:

1. A bootstrap PHP shebang command checked into the repo as `jx`.
2. A native GCC-compilable implementation at `src/native/jx.c`.

Run the native build to replace the bootstrap `jx` with a real compiled executable in your working tree:

```bash
sh scripts/build-native-jx.sh
```

After that, `./jx` is a GCC-built native program.

## PHP to C

The native `jx` collects the current PHP execution oracle into one final C file. It reads a PHP source file and emits a standalone `.c` file.

```bash
./jx emit-c examples/hello.php -o build/hello.c
```

The emitted C file contains:

- the original PHP payload as embedded bytes
- JX Oracle metadata
- a runtime bridge that writes the payload to a temporary PHP file
- a process executor that calls the local PHP runtime
- argument and exit-code preservation

Compile the emitted C file with GCC/CC:

```bash
cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/hello build/hello.c
./build/hello world
```

Expected output:

```text
Hello from JX, world
```

## Commands

```bash
git pull origin main
sh scripts/build-native-jx.sh
./jx --version
mkdir -p build
./jx emit-c examples/hello.php -o build/hello.c
cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/hello build/hello.c
./build/hello world
./tests/native-run.sh
```

## Important status

This is now a native executable compiler front-end that turns PHP files into GCC-compilable `.c` files.

The generated executable still delegates PHP execution to the installed PHP runtime. That keeps behavior aligned while the native PHP compiler is built underneath it.

The next step is to replace the bridge pieces with native lowered PHP operations one group at a time.
