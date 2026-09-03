#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

PHP_BIN="${PHP_BIN:-php}"

"$PHP_BIN" -d zend.assertions=1 -d assert.exception=1 test-jx-jxl-containers.php

bash native/x86_64/build-jxl-containers.sh >/dev/null

OBJ="${JX_NATIVE_OUT:-build/native/x86_64}/jxl_container_runtime.o"
test -s "$OBJ"

if command -v nm >/dev/null 2>&1; then
    SYMBOLS="$(nm -g --defined-only "$OBJ")"
    for symbol in \
        jx_jxl_container_execute \
        jx_jxl_container_native_table \
        jx_vector_push_u64 \
        jx_queue_push_u64 \
        jx_deque_push_front_u64 \
        jx_map_emplace_u64 \
        jx_set_add_u64 \
        jx_bag_sync
    do
        grep -q " ${symbol}$" <<<"$SYMBOLS"
    done
fi

printf 'JXL native container build: ok (%s)\n' "$OBJ"
