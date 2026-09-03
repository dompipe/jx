#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

OUT_DIR="${JX_NATIVE_OUT:-build/native/x86_64}"
PREFIX="$OUT_DIR/prepared-loop"
mkdir -p "$OUT_DIR"

php -d zend.assertions=1 -d assert.exception=1 \
  jx-jxl-prepared-compile.php \
  tests/fixtures/jxl-prepared-loop.jx \
  "$PREFIX" >/dev/null

bash native/x86_64/build-jxl-containers.sh >/dev/null

NASM_BIN="${NASM_BIN:-nasm}"
CC_BIN="${CC_BIN:-cc}"
PREPARED_OBJ="$OUT_DIR/jxl_prepared_program.o"
HARNESS="$OUT_DIR/test_jxl_prepared_program"
MAP_HARNESS="$OUT_DIR/test_jxl_map_vector"

"$NASM_BIN" -Wall -f elf64 native/x86_64/jxl_prepared_program.asm -o "$PREPARED_OBJ"

"$CC_BIN" \
  -std=c11 -O2 -Wall -Wextra -Werror -no-pie \
  -Inative/x86_64 \
  native/x86_64/test_jxl_prepared_program.c \
  native/x86_64/jxl_container_admission.c \
  "$OUT_DIR/jxl_container_runtime.o" \
  "$PREPARED_OBJ" \
  -o "$HARNESS"

"$HARNESS" \
  "$PREFIX.jxl" \
  "$PREFIX.jxcb" \
  "$PREFIX.jxrw"

# Correctness-only keyed-vector Map check. Performance comparison against the
# retained split-array backend is intentionally a separate benchmark step.
"$CC_BIN" \
  -std=c11 -O2 -Wall -Wextra -Werror -no-pie \
  -Inative/x86_64 \
  native/x86_64/test_jxl_map_vector.c \
  "$OUT_DIR/jxl_container_runtime.o" \
  -o "$MAP_HARNESS"

"$MAP_HARNESS"
