#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT_DIR"

sh scripts/build-native-jx.sh

mkdir -p build
./jx emit-c examples/hello.php -o build/hello.c
cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/hello build/hello.c

OUTPUT=$(./build/hello world)
EXPECTED='Hello from JX, world'

if [ "$OUTPUT" != "$EXPECTED" ]; then
    printf 'FAIL: expected %s, got %s\n' "$EXPECTED" "$OUTPUT" >&2
    exit 1
fi

printf 'PASS: native JX emits GCC-compilable C from PHP and the compiled artifact runs\n'
