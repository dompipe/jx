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

static inline int jx11_shadow_is_reserved(uint8_t shadow) {
    return shadow < JX11_SHADOW_FIRST_DYNAMIC;
}

#endif
