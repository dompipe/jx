#ifndef JX11_WINDOW_HOT_H
#define JX11_WINDOW_HOT_H

#include "jx11-register.h"
#include "jx11-shadow.h"
#include <stdint.h>

typedef struct {
    jx11_window_ref state;
    jx11_window_ref taskbar;
    jx11_window_ref title;
    jx11_window_ref focus;
    jx11_window_ref geometry;
} jx11_window_hot;

jx11_window_hot jx11_window_hot_make(jx11_register_t reg, uint8_t slot);
jx11_window_ref jx11_window_hot_ref(const jx11_window_hot *hot, uint8_t shadow);

#endif
