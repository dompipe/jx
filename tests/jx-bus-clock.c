#include "../host/common/jx-bus-clock.h"
#include <assert.h>
#include <stdio.h>

int main(void) {
    jx_bus_clock clock;
    jx_bus_clock_init(&clock);

    assert(jx_bus_clock_interval(&clock) == 250u);
    assert(jx_bus_clock_gear_get(&clock) == JX_BUS_CLOCK_NORMAL);

    /* A single active epoch does not overreact. */
    assert(jx_bus_clock_observe(&clock, 1u, 1u) == 250u);
    assert(jx_bus_clock_gear_get(&clock) == JX_BUS_CLOCK_NORMAL);

    /* Four sustained active epochs promote to BUSY. */
    for (unsigned i = 0; i < 3u; ++i)
        jx_bus_clock_observe(&clock, UINT64_C(0x5), 3u);
    assert(jx_bus_clock_gear_get(&clock) == JX_BUS_CLOCK_BUSY);
    assert(jx_bus_clock_interval(&clock) == 125u);

    /* Eight sustained active epochs promote to BURST. */
    for (unsigned i = 0; i < 4u; ++i)
        jx_bus_clock_observe(&clock, UINT64_C(0x15), 8u);
    assert(jx_bus_clock_gear_get(&clock) == JX_BUS_CLOCK_BURST);
    assert(jx_bus_clock_interval(&clock) == 62u);

    /* Quiet epochs decay one gear first, then settle at the 250ms baseline. */
    assert(jx_bus_clock_observe(&clock, 0u, 0u) == 125u);
    assert(jx_bus_clock_gear_get(&clock) == JX_BUS_CLOCK_BUSY);
    assert(jx_bus_clock_observe(&clock, 0u, 0u) == 250u);
    assert(jx_bus_clock_gear_get(&clock) == JX_BUS_CLOCK_NORMAL);
    assert(jx_bus_clock_observe(&clock, 0u, 0u) == 250u);
    assert(jx_bus_clock_gear_get(&clock) == JX_BUS_CLOCK_QUIET);

    /* Bus mask alone is not work: no DATA means stay quiet. */
    assert(jx_bus_clock_observe(&clock, UINT64_C(0xffff), 0u) == 250u);
    assert(jx_bus_clock_gear_get(&clock) == JX_BUS_CLOCK_QUIET);

    puts("jx-bus-clock: multiplex activity gears 250->125->62ms and decays safely");
    return 0;
}
