#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

NASM_BIN="${NASM_BIN:-nasm}"
LD_BIN="${LD_BIN:-ld}"
OUT_DIR="${JX_NATIVE_OUT:-build/native/x86_64}"
mkdir -p "$OUT_DIR"

CORE_OBJ="$OUT_DIR/jxl_containers.o"
EXEC_OBJ="$OUT_DIR/jxl_container_executor.o"
RUNTIME_OBJ="$OUT_DIR/jxl_container_runtime.o"

"$NASM_BIN" -Wall -f elf64 native/x86_64/jxl_containers.asm -o "$CORE_OBJ"
"$NASM_BIN" -Wall -f elf64 native/x86_64/jxl_container_executor.asm -o "$EXEC_OBJ"

# Produce one relocatable native runtime object suitable for the JX native host,
# WSJX64/ELF link step, or later .64B native-section packaging.
"$LD_BIN" -r -o "$RUNTIME_OBJ" "$CORE_OBJ" "$EXEC_OBJ"

if command -v nm >/dev/null 2>&1; then
    nm -g --defined-only "$RUNTIME_OBJ" | sort > "$OUT_DIR/jxl_container_runtime.symbols"
fi

printf '%s\n' "$RUNTIME_OBJ"
