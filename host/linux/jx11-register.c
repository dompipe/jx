#include "jx11-register.h"
#include <stddef.h>
#include <string.h>

#define JX11_REGISTER_NAME_MAX 128u

typedef struct {
    char name[JX11_REGISTER_NAME_MAX + 1u];
    uint8_t used;
} jx11_register_entry;

static jx11_register_entry jx11_window_registers[JX11_MAX_REGISTERS];
static uint16_t jx11_window_register_count = 0;

void jx11_register_reset(void) {
    memset(jx11_window_registers, 0, sizeof jx11_window_registers);
    jx11_window_register_count = 0;
}

int jx11_register_intern(const char *canonical_name, jx11_register_t *out) {
    if (!canonical_name || !*canonical_name || !out) return -1;
    size_t n = strlen(canonical_name);
    if (n > JX11_REGISTER_NAME_MAX) return -2;

    for (uint16_t i = 0; i < jx11_window_register_count; ++i) {
        if (jx11_window_registers[i].used && strcmp(jx11_window_registers[i].name, canonical_name) == 0) {
            *out = (jx11_register_t)i;
            return 0;
        }
    }

    if (jx11_window_register_count >= JX11_MAX_REGISTERS) return -3;
    uint16_t index = jx11_window_register_count++;
    jx11_register_entry *entry = &jx11_window_registers[index];
    memcpy(entry->name, canonical_name, n + 1u);
    entry->used = 1u;
    *out = (jx11_register_t)index;
    return 0;
}

const char *jx11_register_name(jx11_register_t reg) {
    uint16_t index = (uint16_t)reg;
    if (index >= jx11_window_register_count || !jx11_window_registers[index].used) return NULL;
    return jx11_window_registers[index].name;
}

uint16_t jx11_register_count(void) {
    return jx11_window_register_count;
}
