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

./jx emit-c examples/page.php --asset examples/style.css -o build/page.c
cc -O2 -std=c11 -Wall -Wextra -pedantic -o build/page build/page.c
./build/page world > build/page.html

if ! grep -q 'font-family: Arial' build/page.html; then
    printf 'FAIL: CSS asset was not embedded into page output\n' >&2
    exit 1
fi

if ! grep -q 'Loaded CSS asset: style.css' build/page.html; then
    printf 'FAIL: CSS asset name was not exported to PHP\n' >&2
    exit 1
fi

printf 'PASS: native JX emits GCC-compilable C from PHP, embeds CSS assets, and the compiled artifacts run\n'
