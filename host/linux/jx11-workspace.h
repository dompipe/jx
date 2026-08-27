#ifndef JX11_WORKSPACE_H
#define JX11_WORKSPACE_H

#include <stdint.h>

#define JX11_MAX_WORKSPACES 16u
#define JX11_WORKSPACE_STICKY 0xffffffffu

typedef struct {
    uint32_t workspace;
    uint8_t in_use;
    uint8_t mapped;
    uint8_t hidden_by_workspace;
} jx11_workspace_window;

typedef struct {
    uint32_t count;
    uint32_t current;
} jx11_workspace_state;

void jx11_workspace_state_init(jx11_workspace_state *state, uint32_t count, uint32_t current);
uint32_t jx11_workspace_normalize(const jx11_workspace_state *state, uint32_t workspace);
int jx11_workspace_visible(const jx11_workspace_state *state, const jx11_workspace_window *window);
void jx11_workspace_assign(const jx11_workspace_state *state, jx11_workspace_window *window, uint32_t workspace);
int jx11_workspace_switch(jx11_workspace_state *state, uint32_t next);
void jx11_workspace_mark_hidden(jx11_workspace_window *window);
void jx11_workspace_mark_shown(jx11_workspace_window *window);
int jx11_workspace_consume_wm_unmap(jx11_workspace_window *window);

#endif
