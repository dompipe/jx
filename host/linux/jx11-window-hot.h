#ifndef JX11_WINDOW_HOT_H
#define JX11_WINDOW_HOT_H

#include "jx11-register.h"
#include "jx11-shadow.h"
#include <stdint.h>

typedef struct {
    jx11_window_ref first;
    jx11_window_ref second;
    uint8_t mask;
    uint8_t count;
} jx11_window_reaction;

typedef struct {
    jx11_window_ref state;
    jx11_window_ref taskbar;
    jx11_window_ref title;
    jx11_window_ref focus;
    jx11_window_ref geometry;
    /* Indexed directly by JX11_EVENT_*; element 0 is unused. */
    jx11_window_reaction reaction[6];
} jx11_window_hot;

jx11_window_hot jx11_window_hot_make(jx11_register_t reg, uint8_t slot);
jx11_window_ref jx11_window_hot_ref(const jx11_window_hot *hot, uint8_t shadow);

static inline const jx11_window_reaction *jx11_window_hot_reaction(
    const jx11_window_hot *hot, uint8_t event_kind
) {
    return hot && event_kind < 6u ? &hot->reaction[event_kind] : (const jx11_window_reaction *)0;
}

#endif
