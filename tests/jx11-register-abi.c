#include "../host/linux/jx11-register.h"
#include <stdio.h>

int main(void) {
    jx11_window_ref r = jx11_window_ref_make(0, 17, 3);
    if (r.reg != 0) return 1;
    if (r.ref != 0x1103u) return 2;
    if (jx11_hotref_slot(r.ref) != 17u) return 3;
    if (jx11_hotref_shadow(r.ref) != 3u) return 4;
    if (jx11_hotref_pack(255u, 255u) != 0xffffu) return 5;
    puts("jx11-register-abi: ok");
    return 0;
}
