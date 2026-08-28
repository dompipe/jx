#include "../host/common/jx-idle-bus.h"
#include <assert.h>
#include <stdio.h>
#include <string.h>

typedef struct {
    unsigned calls;
    uint64_t last_epoch;
    uint64_t last_ms;
} probe;

static int on_system_tick(uint64_t epoch, uint64_t monotonic_ms, void *context) {
    probe *p = (probe *)context;
    ++p->calls;
    p->last_epoch = epoch;
    p->last_ms = monotonic_ms;
    return 0;
}

int main(void) {
    jx_idle_bus bus;
    jx_idle_bus_init(&bus);
    assert(bus.version == JX_IDLE_BUS_VERSION);
    assert(JX_IDLE_BUS_PERIOD_MS == 250u);

    uint8_t code[JX_IDLE_CALL_BYTES] = {0};
    assert(jx_idle_bus_encode(code) == 0);
    const uint8_t expected[JX_IDLE_CALL_BYTES] = {0x7f, 0x00, 0x01};
    assert(memcmp(code, expected, sizeof expected) == 0);
    assert(jx_idle_bus_is_tick(code, sizeof code));
    assert(!jx_idle_bus_is_tick(code, 2u));

    probe system = {0};
    assert(jx_idle_bus_add_system(&bus, on_system_tick, &system) == 0);
    assert(jx_idle_bus_add_system(&bus, on_system_tick, &system) == -2);

    for (uint32_t id = 1u; id <= 100u; ++id)
        assert(jx_idle_bus_add_program(&bus, id) == 0);
    assert(bus.program_count == 100u);

    assert(jx_idle_bus_maybe_tick(&bus, 1000u) == 1);
    assert(system.calls == 1u);
    assert(system.last_epoch == 1u && system.last_ms == 1000u);

    uint64_t epoch = 0u;
    assert(jx_idle_bus_take_permission(&bus, 1u, &epoch) == 1);
    assert(epoch == 1u);
    assert(jx_idle_bus_take_permission(&bus, 1u, &epoch) == 0);
    assert(jx_idle_bus_take_permission(&bus, 100u, &epoch) == 1);
    assert(epoch == 1u);

    assert(jx_idle_bus_maybe_tick(&bus, 1249u) == 0);
    assert(system.calls == 1u);

    /* Program 2 sleeps through three 250ms pulses. Permission coalesces to
     * the newest epoch instead of building three queued wakeups. */
    assert(jx_idle_bus_maybe_tick(&bus, 1250u) == 1);
    assert(jx_idle_bus_maybe_tick(&bus, 1500u) == 1);
    assert(jx_idle_bus_maybe_tick(&bus, 1750u) == 1);
    assert(system.calls == 4u);
    assert(jx_idle_bus_take_permission(&bus, 2u, &epoch) == 1);
    assert(epoch == 4u);
    assert(jx_idle_bus_take_permission(&bus, 2u, &epoch) == 0);

    assert(jx_idle_bus_remove_program(&bus, 50u) == 0);
    assert(bus.program_count == 99u);
    assert(jx_idle_bus_take_permission(&bus, 50u, &epoch) == -2);

    assert(jx_idle_bus_remove_system(&bus, on_system_tick, &system) == 0);
    assert(jx_idle_bus_tick(&bus, 2000u) == 0);
    assert(system.calls == 4u);

    puts("jx-idle-bus: one 3-byte pulse broadcasts permission every 250ms");
    return 0;
}
