#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT_DIR"

mkdir -p build

if command -v pkg-config >/dev/null 2>&1 && pkg-config --exists x11; then
    cc -O2 -std=c11 -Wall -Wextra -pedantic \
        -o build/jx-window-x11 \
        src/window/jx-window-x11.c \
        $(pkg-config --cflags --libs x11)
    printf 'Built X11 window runner: build/jx-window-x11\n'
else
    printf 'Skipped X11 window runner: pkg-config x11 not found\n' >&2
fi

if command -v x86_64-w64-mingw32-gcc >/dev/null 2>&1; then
    x86_64-w64-mingw32-gcc -O2 -Wall -Wextra \
        -o build/jx-window-win32.exe \
        src/window/jx-window-win32.c \
        -lgdi32 -luser32
    printf 'Built Win32 window runner: build/jx-window-win32.exe\n'
else
    printf 'Skipped Win32 window runner: x86_64-w64-mingw32-gcc not found\n' >&2
fi
