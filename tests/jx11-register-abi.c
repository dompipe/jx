#include "../host/linux/jx11-register.h"
#include <stdio.h>
#include <string.h>

int main(void) {
    jx11_register_t reg = 255u;
    jx11_register_reset();
    if (jx11_register_count() != 0u) return 1;
    if (jx11_register_intern("desktop-windows", &reg) != 0) return 2;
    if (reg != 0u) return 3;
    if (jx11_register_count() != 1u) return 4;
    if (!jx11_register_name(reg) || strcmp(jx11_register_name(reg), "desktop-windows") != 0) return 5;

    jx11_window_ref r = jx11_window_ref_make(reg, 17u, 3u);
    if (r.reg != 0u) return 6;
    if (r.ref != 0x1103u) return 7;
    if (jx11_hotref_slot(r.ref) != 17u) return 8;
    if (jx11_hotref_shadow(r.ref) != 3u) return 9;
    if (jx11_hotref_pack(255u, 255u) != 0xffffu) return 10;

    jx11_register_reset();
    if (jx11_register_count() != 0u) return 11;
    puts("jx11-register-abi: ok");
    return 0;
}
