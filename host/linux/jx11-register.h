#ifndef JX11_REGISTER_H
#define JX11_REGISTER_H

#include <stdint.h>

/*
 * Shared awake-state ABI with jx\HotRef / DesktopWindowRegister.
 *
 * Bags remember; registers react.
 *
 * register: one byte identifying the canonical WindowBag within the awake
 * register bank.
 * packed:   high byte = slot, low byte = shadow.
 */
#define JX11_MAX_REGISTERS 256u
#define JX11_MAX_SLOT      255u
#define JX11_MAX_SHADOW    255u

typedef uint8_t jx11_register_t;
typedef uint16_t jx11_hotref_t;

typedef struct {
    jx11_register_t reg;
    jx11_hotref_t ref;
} jx11_window_ref;

static inline jx11_hotref_t jx11_hotref_pack(uint8_t slot, uint8_t shadow) {
    return (jx11_hotref_t)(((uint16_t)slot << 8) | (uint16_t)shadow);
}

static inline uint8_t jx11_hotref_slot(jx11_hotref_t ref) {
    return (uint8_t)((ref >> 8) & 0xffu);
}

static inline uint8_t jx11_hotref_shadow(jx11_hotref_t ref) {
    return (uint8_t)(ref & 0xffu);
}

static inline jx11_window_ref jx11_window_ref_make(uint8_t reg, uint8_t slot, uint8_t shadow) {
    jx11_window_ref out;
    out.reg = reg;
    out.ref = jx11_hotref_pack(slot, shadow);
    return out;
}

#endif
