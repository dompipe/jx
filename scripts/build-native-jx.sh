#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
CC_BIN=${CC:-cc}
CFLAGS_VALUE=${CFLAGS:-"-O2 -std=c11 -Wall -Wextra -pedantic"}

mkdir -p "$ROOT_DIR/build"

# Build the real native executable in the repo root, like the old ./jinx path.
# This intentionally replaces the bootstrap PHP shebang file with a GCC-built binary.
$CC_BIN $CFLAGS_VALUE -o "$ROOT_DIR/jx" "$ROOT_DIR/src/native/jx.c"

printf 'Built native executable: %s\n' "$ROOT_DIR/jx"
