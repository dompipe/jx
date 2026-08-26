#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd -P)
OUT_DIR="$ROOT/build/wsl"
OUT="$OUT_DIR/jx"
CC=${CC:-cc}

mkdir -p "$OUT_DIR"

"$CC" -std=c11 -O2 -Wall -Wextra -pedantic \
  -DJX_ROOT_COMPILED="\"$ROOT\"" \
  -o "$OUT" \
  "$ROOT/tools/wsl/jx-wsl.c"

chmod +x "$OUT"
printf '%s\n' "$OUT"
