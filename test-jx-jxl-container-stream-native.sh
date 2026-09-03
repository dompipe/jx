#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

OUT_DIR="${JX_NATIVE_OUT:-build/native/x86_64}"
PREFIX="$OUT_DIR/canonical-bag-stream"
mkdir -p "$OUT_DIR"

php -d zend.assertions=1 -d assert.exception=1 \
  jx-jxl-container-compile.php \
  tests/fixtures/jxl-container-native.jx \
  "$PREFIX" >/dev/null

bash native/x86_64/build-jxl-containers.sh >/dev/null

CC_BIN="${CC_BIN:-cc}"
HARNESS="$OUT_DIR/test_jxl_container_stream"
"$CC_BIN" \
  -std=c11 -O2 -Wall -Wextra -Werror -no-pie \
  -Inative/x86_64 \
  native/x86_64/jxl_container_admission.c \
  native/x86_64/test_jxl_container_stream.c \
  "$OUT_DIR/jxl_container_runtime.o" \
  -o "$HARNESS"

"$HARNESS" \
  "$PREFIX.jxl" \
  "$PREFIX.jxcb" \
  "$PREFIX.jxrw"
