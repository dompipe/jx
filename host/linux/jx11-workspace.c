#include "jx11-workspace.h"

void jx11_workspace_state_init(jx11_workspace_state *state, uint32_t count, uint32_t current) {
    if (!state) return;
    if (count == 0u) count = 1u;
    if (count > JX11_MAX_WORKSPACES) count = JX11_MAX_WORKSPACES;
    state->count = count;
    state->current = current < count ? current : 0u;
}

uint32_t jx11_workspace_normalize(const jx11_workspace_state *state, uint32_t workspace) {
    if (!state) return 0u;
    if (workspace == JX11_WORKSPACE_STICKY) return workspace;
    return workspace < state->count ? workspace : state->current;
}

int jx11_workspace_visible(const jx11_workspace_state *state, const jx11_workspace_window *window) {
    if (!state || !window || !window->in_use) return 0;
    return window->workspace == JX11_WORKSPACE_STICKY || window->workspace == state->current;
}

void jx11_workspace_assign(const jx11_workspace_state *state, jx11_workspace_window *window, uint32_t workspace) {
    if (!state || !window) return;
    window->workspace = jx11_workspace_normalize(state, workspace);
}

int jx11_workspace_switch(jx11_workspace_state *state, uint32_t next) {
    if (!state || next >= state->count || next == state->current) return 0;
    state->current = next;
    return 1;
}

void jx11_workspace_mark_hidden(jx11_workspace_window *window) {
    if (!window || !window->in_use) return;
    window->hidden_by_workspace = 1u;
    window->mapped = 0u;
}

void jx11_workspace_mark_shown(jx11_workspace_window *window) {
    if (!window || !window->in_use) return;
    window->hidden_by_workspace = 0u;
    window->mapped = 1u;
}

int jx11_workspace_consume_wm_unmap(jx11_workspace_window *window) {
    if (!window || !window->in_use || !window->hidden_by_workspace) return 0;
    window->hidden_by_workspace = 0u;
    window->mapped = 0u;
    return 1;
}
