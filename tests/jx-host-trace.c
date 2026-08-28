#include "../host/common/jx-host-trace.h"
#include <assert.h>
#include <stdio.h>

static void scenario(jx_host_trace *t) {
    assert(jx_host_trace_emit(t, JX_TRACE_PROGRAM_START, 1u, 100u, 0u) == 0);
    assert(jx_host_trace_emit(t, JX_TRACE_INPUT, 1u, 12u, 1u) == 0);
    assert(jx_host_trace_emit(t, JX_TRACE_CHANNEL_IN, 1u, 7u, 55u) == 0);
    assert(jx_host_trace_emit(t, JX_TRACE_BAG_REVISION, 1u, 3u, 42u) == 0);
    assert(jx_host_trace_emit(t, JX_TRACE_RENDER_INVALIDATE, 1u, 9u, 1u) == 0);
    assert(jx_host_trace_emit(t, JX_TRACE_POWER_STATE, 2u, 0u, 1u) == 0);
    assert(jx_host_trace_emit(t, JX_TRACE_GENERATION, 2u, 0u, 2u) == 0);
}

int main(void) {
    jx_host_trace x11, win32;
    jx_host_trace_init(&x11, JX_HOST_LINUX_X11);
    jx_host_trace_init(&win32, JX_HOST_WINDOWS_WIN32);
    scenario(&x11);
    scenario(&win32);

    jx_host_trace_compare_result r;
    assert(jx_host_trace_compare(&x11, &win32, &r) == 0);
    assert(r.equal == 1);
    assert(r.matched == x11.count);

    win32.records[3].value = 43u;
    assert(jx_host_trace_compare(&x11, &win32, &r) == 0);
    assert(r.equal == 0);
    assert(r.mismatch_index == 3u);

    puts("jx-host-trace: Linux X11 and Win32 semantics compare deterministically");
    return 0;
}
