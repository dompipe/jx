#include "../host/linux/jx11-workspace.h"
#include <assert.h>
#include <stdio.h>

int main(void) {
    jx11_workspace_state state;
    jx11_workspace_window a = {0}, b = {0}, sticky = {0};

    jx11_workspace_state_init(&state, 4u, 0u);
    assert(state.count == 4u && state.current == 0u);

    a.in_use = 1u; a.mapped = 1u;
    b.in_use = 1u; b.mapped = 1u;
    sticky.in_use = 1u; sticky.mapped = 1u;
    jx11_workspace_assign(&state, &a, 0u);
    jx11_workspace_assign(&state, &b, 1u);
    jx11_workspace_assign(&state, &sticky, JX11_WORKSPACE_STICKY);

    assert(jx11_workspace_visible(&state, &a));
    assert(!jx11_workspace_visible(&state, &b));
    assert(jx11_workspace_visible(&state, &sticky));

    assert(jx11_workspace_switch(&state, 1u));
    assert(!jx11_workspace_visible(&state, &a));
    assert(jx11_workspace_visible(&state, &b));
    assert(jx11_workspace_visible(&state, &sticky));

    jx11_workspace_mark_hidden(&a);
    assert(a.hidden_by_workspace == 1u && a.mapped == 0u);
    assert(jx11_workspace_consume_wm_unmap(&a) == 1);
    assert(a.hidden_by_workspace == 0u && a.mapped == 0u);
    assert(jx11_workspace_consume_wm_unmap(&a) == 0);

    jx11_workspace_mark_shown(&a);
    assert(a.mapped == 1u && a.hidden_by_workspace == 0u);

    assert(!jx11_workspace_switch(&state, 4u));
    assert(state.current == 1u);
    assert(jx11_workspace_normalize(&state, 99u) == 1u);

    puts("PASS jx11 workspace visibility/unmap semantics");
    return 0;
}
