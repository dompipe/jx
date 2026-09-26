# JX

JX is now the separate PHP-to-executable-code path in `dompipe/jx`.

The executable is named:

```bash
./jx
```

## Current compiler layer

This first layer takes PHP source and emits an executable artifact:

```bash
./jx compile examples/hello.php -o build/hello
./build/hello world
```

It also supports direct compile-and-run:

```bash
./jx run examples/hello.php -- world
```

The generated executable currently embeds the PHP source payload and delegates runtime execution to the installed PHP runtime. That gives JX a real executable artifact, argument forwarding, stdout/stderr passthrough, and exit-code preservation now, while the next compiler layers lower PHP into JX-native executable code instead of delegating to PHP.

## Commands

```bash
chmod +x jx
./jx compile examples/hello.php -o build/hello
./build/hello world
./jx run examples/hello.php -- world
./tests/run.sh
```

Expected example output:

```text
Hello from JX, world
```

## Environment

Set `JX_PHP` to choose the PHP runtime used by generated artifacts:

```bash
JX_PHP=/usr/bin/php ./build/hello world
```

## Direction

JX should become the compiler that accepts PHP code and turns it into executable code. This repo reset keeps the surface small and makes the next work clear:

1. Parse PHP into a JX intermediate representation.
2. Lower expressions, assignments, branches, loops, functions, and arrays.
3. Emit executable JX bytecode or native code.
4. Differential-test JX output against PHP behavior.
