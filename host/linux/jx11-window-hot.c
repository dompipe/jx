#include "jx11-window-hot.h"
#include <string.h>

static jx11_window_reaction reaction_make(
    jx11_window_ref first,
    jx11_window_ref second,
    uint8_t mask,
    uint8_t count
) {
    jx11_window_reaction reaction;
    reaction.first = first;
    reaction.second = second;
    reaction.mask = mask;
    reaction.count = count;
    return reaction;
}

jx11_window_hot jx11_window_hot_make(jx11_register_t reg, uint8_t slot) {
    jx11_window_hot hot;
    memset(&hot, 0, sizeof hot);
    hot.state = jx11_window_ref_make(reg, slot, JX11_SHADOW_STATE);
    hot.taskbar = jx11_window_ref_make(reg, slot, JX11_SHADOW_TASKBAR);
    hot.title = jx11_window_ref_make(reg, slot, JX11_SHADOW_TITLE);
    hot.focus = jx11_window_ref_make(reg, slot, JX11_SHADOW_FOCUS);
    hot.geometry = jx11_window_ref_make(reg, slot, JX11_SHADOW_GEOMETRY);

    hot.reaction[JX11_EVENT_STATE_OPEN] = reaction_make(
        hot.state, hot.taskbar,
        (uint8_t)((1u << JX11_SHADOW_STATE) | (1u << JX11_SHADOW_TASKBAR)), 2u);
    hot.reaction[JX11_EVENT_TITLE] = reaction_make(
        hot.title, hot.taskbar,
        (uint8_t)((1u << JX11_SHADOW_TITLE) | (1u << JX11_SHADOW_TASKBAR)), 2u);
    hot.reaction[JX11_EVENT_FOCUS] = reaction_make(
        hot.focus, hot.taskbar,
        (uint8_t)((1u << JX11_SHADOW_FOCUS) | (1u << JX11_SHADOW_TASKBAR)), 2u);
    hot.reaction[JX11_EVENT_GEOMETRY] = reaction_make(
        hot.geometry, jx11_window_ref_make(reg, slot, 0u),
        (uint8_t)(1u << JX11_SHADOW_GEOMETRY), 1u);
    hot.reaction[JX11_EVENT_STATE_CLOSE] = hot.reaction[JX11_EVENT_STATE_OPEN];
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
