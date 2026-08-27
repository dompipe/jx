#include "jx11-shadow.h"
#include <stdint.h>

/* Reserved-shadow reaction masks. One byte covers the current five ABI shadows. */
uint8_t jx11_shadow_mask_for_event(uint8_t event_kind) {
    switch (event_kind) {
        case 1: /* open/state */
            return (uint8_t)((1u << JX11_SHADOW_STATE) | (1u << JX11_SHADOW_TASKBAR));
        case 2: /* title */
            return (uint8_t)((1u << JX11_SHADOW_TITLE) | (1u << JX11_SHADOW_TASKBAR));
        case 3: /* focus */
            return (uint8_t)((1u << JX11_SHADOW_FOCUS) | (1u << JX11_SHADOW_TASKBAR));
        case 4: /* geometry */
            return (uint8_t)(1u << JX11_SHADOW_GEOMETRY);
        case 5: /* close/state */
            return (uint8_t)((1u << JX11_SHADOW_STATE) | (1u << JX11_SHADOW_TASKBAR));
        default:
            return 0;
    }
}

int jx11_shadow_mask_has(uint8_t mask, uint8_t shadow) {
    if (shadow >= 8u) return 0;
    return (mask & (uint8_t)(1u << shadow)) != 0;
}
