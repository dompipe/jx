#include "../host/linux/jx11-window-hot.h"
#include <stdio.h>

int main(void) {
    jx11_window_hot hot = jx11_window_hot_make(0, 17);
    if (hot.state.ref != 0x1100u) return 1;
    if (hot.taskbar.ref != 0x1101u) return 2;
    if (hot.title.ref != 0x1102u) return 3;
    if (hot.focus.ref != 0x1103u) return 4;
    if (hot.geometry.ref != 0x1104u) return 5;
    if (!jx11_shadow_mask_has(jx11_shadow_mask_for_event(JX11_EVENT_TITLE), JX11_SHADOW_TITLE)) return 6;
    if (!jx11_shadow_mask_has(jx11_shadow_mask_for_event(JX11_EVENT_TITLE), JX11_SHADOW_TASKBAR)) return 7;
    if (jx11_shadow_mask_has(jx11_shadow_mask_for_event(JX11_EVENT_GEOMETRY), JX11_SHADOW_TASKBAR)) return 8;
    puts("jx11-shadow-abi: ok");
    return 0;
}
