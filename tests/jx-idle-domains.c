#include "../host/common/jx-idle-domains.h"
#include <assert.h>
#include <stdio.h>

int main(void) {
    jx_idle_domains domains;
    jx_idle_domains_init(&domains);

    jx_idle_bitmap *core = jx_idle_domains_core(&domains);
    jx_idle_bitmap *window = jx_idle_domains_window(&domains);
    assert(core && window && core != window);

    assert(jx_idle_bitmap_begin(core, 1u, 8u) == 0);
    assert(jx_idle_bitmap_begin(window, 1u, 64u) == 0);

    /* Window-domain activity must not alter the core completion barrier. */
    assert(jx_idle_bitmap_reply(window, 1u, 47u, 1u) == 2);
    assert(!jx_idle_bitmap_complete(window));
    assert(!jx_idle_bitmap_complete(core));

    for (uint32_t i = 0; i < 8u; ++i)
        assert(jx_idle_bitmap_reply(core, 1u, i, (uint8_t)(i == 3u)) >= 0);

    assert(jx_idle_bitmap_complete(core));
    assert(jx_idle_bitmap_data_count(core) == 1u);
    assert(!jx_idle_bitmap_complete(window));

    puts("jx-idle-domains: core and window/background bitstrings isolated ok");
    return 0;
}
