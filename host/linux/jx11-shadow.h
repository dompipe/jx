#ifndef JX11_SHADOW_H
#define JX11_SHADOW_H

#include <stdint.h>

/* Must match jx\HotShadow. 0..15 are ABI-reserved; 16..255 are dynamic. */
enum {
    JX11_SHADOW_STATE = 0,
    JX11_SHADOW_TASKBAR = 1,
    JX11_SHADOW_TITLE = 2,
    JX11_SHADOW_FOCUS = 3,
    JX11_SHADOW_GEOMETRY = 4,
    JX11_SHADOW_FIRST_DYNAMIC = 16
};

enum {
    JX11_EVENT_STATE_OPEN = 1,
    JX11_EVENT_TITLE = 2,
    JX11_EVENT_FOCUS = 3,
    JX11_EVENT_GEOMETRY = 4,
    JX11_EVENT_STATE_CLOSE = 5
};

static inline int jx11_shadow_is_reserved(uint8_t shadow) {
    return shadow < JX11_SHADOW_FIRST_DYNAMIC;
}

/* Hot event dispatch is table indexed rather than semantic branching. */
static inline uint8_t jx11_shadow_mask_for_event(uint8_t event_kind) {
    static const uint8_t masks[6] = {
        0u,
        (uint8_t)((1u << JX11_SHADOW_STATE) | (1u << JX11_SHADOW_TASKBAR)),
        (uint8_t)((1u << JX11_SHADOW_TITLE) | (1u << JX11_SHADOW_TASKBAR)),
        (uint8_t)((1u << JX11_SHADOW_FOCUS) | (1u << JX11_SHADOW_TASKBAR)),
        (uint8_t)(1u << JX11_SHADOW_GEOMETRY),
        (uint8_t)((1u << JX11_SHADOW_STATE) | (1u << JX11_SHADOW_TASKBAR))
    };
    return event_kind < 6u ? masks[event_kind] : 0u;
}

static inline int jx11_shadow_mask_has(uint8_t mask, uint8_t shadow) {
    return shadow < 8u && (mask & (uint8_t)(1u << shadow)) != 0;
}

#endif
