#include "jx11-window-hot.h"

jx11_window_hot jx11_window_hot_make(jx11_register_t reg, uint8_t slot) {
    jx11_window_hot hot;
    hot.state = jx11_window_ref_make(reg, slot, JX11_SHADOW_STATE);
    hot.taskbar = jx11_window_ref_make(reg, slot, JX11_SHADOW_TASKBAR);
    hot.title = jx11_window_ref_make(reg, slot, JX11_SHADOW_TITLE);
    hot.focus = jx11_window_ref_make(reg, slot, JX11_SHADOW_FOCUS);
    hot.geometry = jx11_window_ref_make(reg, slot, JX11_SHADOW_GEOMETRY);
    return hot;
}

jx11_window_ref jx11_window_hot_ref(const jx11_window_hot *hot, uint8_t shadow) {
    if (!hot) return jx11_window_ref_make(0, 0, 0);
    switch (shadow) {
        case JX11_SHADOW_STATE: return hot->state;
        case JX11_SHADOW_TASKBAR: return hot->taskbar;
        case JX11_SHADOW_TITLE: return hot->title;
        case JX11_SHADOW_FOCUS: return hot->focus;
        case JX11_SHADOW_GEOMETRY: return hot->geometry;
        default: return jx11_window_ref_make(hot->state.reg, jx11_hotref_slot(hot->state.ref), shadow);
    }
}
